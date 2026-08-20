#!/usr/bin/env python3
"""Fail-closed exact-file release transaction for the ImpactShop VPS."""

from __future__ import annotations

import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import secrets
import shutil
import stat
import subprocess
import sys
from datetime import datetime, timezone
from typing import Any


SCHEMA_VERSION = 1
SAFE_RELEASE_ID = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]{7,96}$")
SAFE_RELATIVE = re.compile(r"^[A-Za-z0-9._/-]+$")
SAFE_SHA256 = re.compile(r"^[a-f0-9]{64}$")
ALLOWED_PHASES = {
    "prepared",
    "deployed",
    "rolled_back",
    "failed_recovered",
    "failed_unrecovered",
}


class ReleaseError(RuntimeError):
    pass


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def emit(payload: dict[str, Any]) -> None:
    print(json.dumps(payload, ensure_ascii=True, sort_keys=True, separators=(",", ":")))


def require_sha256(value: str, label: str) -> str:
    if not SAFE_SHA256.fullmatch(value):
        raise ReleaseError(f"invalid_{label}")
    return value


def require_release_id(value: str) -> str:
    if not SAFE_RELEASE_ID.fullmatch(value):
        raise ReleaseError("invalid_release_id")
    return value


def require_relative(value: str) -> PurePosixPath:
    if not value or not SAFE_RELATIVE.fullmatch(value):
        raise ReleaseError("invalid_target_relative")
    rel = PurePosixPath(value)
    if rel.is_absolute() or any(part in {"", ".", ".."} for part in rel.parts):
        raise ReleaseError("invalid_target_relative")
    if len(rel.parts) < 3 or rel.parts[0] != "wp-content":
        raise ReleaseError("target_outside_wp_content")
    return rel


def validate_root(root_raw: str) -> Path:
    root_input = Path(root_raw)
    if not root_input.is_absolute() or root_input.is_symlink():
        raise ReleaseError("unsafe_root")
    try:
        root = root_input.resolve(strict=True)
    except OSError as exc:
        raise ReleaseError("missing_root") from exc
    if not root.is_dir():
        raise ReleaseError("missing_root")
    return root


def ensure_real_directory(path: Path, *, create: bool = False, mode: int = 0o700) -> None:
    if os.path.lexists(path):
        info = path.lstat()
        if stat.S_ISLNK(info.st_mode) or not stat.S_ISDIR(info.st_mode):
            raise ReleaseError(f"unsafe_directory:{path.name}")
    elif create:
        path.mkdir(mode=mode)
    else:
        raise ReleaseError(f"missing_directory:{path.name}")


def require_private_directory(path: Path) -> None:
    ensure_real_directory(path)
    if stat.S_IMODE(path.lstat().st_mode) != 0o700:
        raise ReleaseError(f"unsafe_directory_mode:{path.name}")


def resolve_target(root: Path, target_relative: str) -> tuple[PurePosixPath, Path]:
    rel = require_relative(target_relative)
    cursor = root
    for part in rel.parts[:-1]:
        cursor = cursor / part
        ensure_real_directory(cursor)
    target = root.joinpath(*rel.parts)
    if target.parent.resolve(strict=True) != cursor.resolve(strict=True):
        raise ReleaseError("target_parent_escape")
    return rel, target


def sha256_path(path: Path) -> str:
    digest = hashlib.sha256()
    nofollow = getattr(os, "O_NOFOLLOW", 0)
    descriptor = os.open(path, os.O_RDONLY | nofollow)
    try:
        info = os.fstat(descriptor)
        if not stat.S_ISREG(info.st_mode):
            raise ReleaseError(f"not_regular:{path.name}")
        with os.fdopen(descriptor, "rb", closefd=False) as source:
            for chunk in iter(lambda: source.read(1024 * 1024), b""):
                digest.update(chunk)
    finally:
        os.close(descriptor)
    return digest.hexdigest()


def regular_state(path: Path) -> dict[str, Any]:
    if not os.path.lexists(path):
        return {"state": "absent", "sha256": None, "mode": None}
    info = path.lstat()
    if stat.S_ISLNK(info.st_mode) or not stat.S_ISREG(info.st_mode):
        raise ReleaseError("unsafe_target_type")
    return {
        "state": "present",
        "sha256": sha256_path(path),
        "mode": stat.S_IMODE(info.st_mode),
    }


def fsync_directory(path: Path) -> None:
    descriptor = os.open(path, os.O_RDONLY)
    try:
        os.fsync(descriptor)
    finally:
        os.close(descriptor)


def copy_regular(source: Path, destination: Path, mode: int) -> None:
    if os.path.lexists(destination):
        raise ReleaseError(f"destination_exists:{destination.name}")
    nofollow = getattr(os, "O_NOFOLLOW", 0)
    source_fd = os.open(source, os.O_RDONLY | nofollow)
    try:
        source_info = os.fstat(source_fd)
        if not stat.S_ISREG(source_info.st_mode):
            raise ReleaseError(f"not_regular:{source.name}")
        destination_fd = os.open(
            destination,
            os.O_WRONLY | os.O_CREAT | os.O_EXCL | nofollow,
            mode,
        )
        try:
            with os.fdopen(source_fd, "rb", closefd=False) as src, os.fdopen(
                destination_fd, "wb", closefd=False
            ) as dst:
                shutil.copyfileobj(src, dst, length=1024 * 1024)
                dst.flush()
                os.fchmod(destination_fd, mode)
                os.fsync(destination_fd)
        finally:
            os.close(destination_fd)
    finally:
        os.close(source_fd)


def atomic_copy(source: Path, target: Path, mode: int) -> None:
    temporary = target.parent / f".{target.name}.release-{secrets.token_hex(8)}.tmp"
    try:
        copy_regular(source, temporary, mode)
        os.replace(temporary, target)
        fsync_directory(target.parent)
    finally:
        if os.path.lexists(temporary):
            temporary.unlink()


def atomic_json(path: Path, payload: dict[str, Any]) -> None:
    temporary = path.parent / f".{path.name}.{secrets.token_hex(8)}.tmp"
    encoded = (json.dumps(payload, ensure_ascii=True, sort_keys=True, indent=2) + "\n").encode()
    nofollow = getattr(os, "O_NOFOLLOW", 0)
    descriptor = os.open(
        temporary,
        os.O_WRONLY | os.O_CREAT | os.O_EXCL | nofollow,
        0o600,
    )
    try:
        with os.fdopen(descriptor, "wb", closefd=False) as destination:
            destination.write(encoded)
            destination.flush()
            os.fchmod(descriptor, 0o600)
            os.fsync(descriptor)
    finally:
        os.close(descriptor)
    os.replace(temporary, path)
    fsync_directory(path.parent)


def release_paths(root: Path, release_id: str, *, create_parent: bool) -> tuple[Path, Path, Path, Path]:
    bastion = root / ".bastion"
    require_private_directory(bastion)
    releases = bastion / "exact-file-releases"
    ensure_real_directory(releases, create=create_parent, mode=0o700)
    require_private_directory(releases)
    release_dir = releases / require_release_id(release_id)
    return release_dir, release_dir / "manifest.json", release_dir / "backup.bin", release_dir / "payload.bin"


class LockedRoot:
    def __init__(self, root: Path) -> None:
        self.root = root
        self.descriptor: int | None = None

    def __enter__(self) -> "LockedRoot":
        bastion = self.root / ".bastion"
        ensure_real_directory(bastion)
        lock_path = bastion / "exact-file-release.lock"
        nofollow = getattr(os, "O_NOFOLLOW", 0)
        self.descriptor = os.open(lock_path, os.O_RDWR | os.O_CREAT | nofollow, 0o600)
        info = os.fstat(self.descriptor)
        if not stat.S_ISREG(info.st_mode):
            raise ReleaseError("unsafe_lock")
        os.fchmod(self.descriptor, 0o600)
        fcntl.flock(self.descriptor, fcntl.LOCK_EX)
        return self

    def __exit__(self, exc_type: Any, exc: Any, traceback: Any) -> None:
        if self.descriptor is not None:
            fcntl.flock(self.descriptor, fcntl.LOCK_UN)
            os.close(self.descriptor)
            self.descriptor = None


def validate_manifest(raw: Any, release_id: str) -> dict[str, Any]:
    if not isinstance(raw, dict) or raw.get("schemaVersion") != SCHEMA_VERSION:
        raise ReleaseError("invalid_manifest_schema")
    if raw.get("releaseId") != release_id:
        raise ReleaseError("manifest_release_mismatch")
    require_relative(str(raw.get("targetRelative", "")))
    require_sha256(str(raw.get("intendedSha256", "")), "manifest_intended_sha256")
    original_state = raw.get("originalState")
    if original_state not in {"absent", "present"}:
        raise ReleaseError("invalid_manifest_original_state")
    if original_state == "present":
        require_sha256(str(raw.get("originalSha256", "")), "manifest_original_sha256")
        original_mode = raw.get("originalMode")
        if not isinstance(original_mode, int) or not 0 <= original_mode <= 0o7777:
            raise ReleaseError("invalid_manifest_original_mode")
    elif raw.get("originalSha256") is not None or raw.get("originalMode") is not None:
        raise ReleaseError("invalid_absent_original_metadata")
    if raw.get("phase") not in ALLOWED_PHASES:
        raise ReleaseError("invalid_manifest_phase")
    return raw


def read_manifest(path: Path, release_id: str) -> dict[str, Any]:
    if not os.path.lexists(path):
        raise ReleaseError("missing_manifest")
    info = path.lstat()
    if stat.S_ISLNK(info.st_mode) or not stat.S_ISREG(info.st_mode):
        raise ReleaseError("unsafe_manifest")
    if stat.S_IMODE(info.st_mode) & 0o077:
        raise ReleaseError("manifest_permissions_too_open")
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise ReleaseError("invalid_manifest_json") from exc
    return validate_manifest(raw, release_id)


def require_state_matches(current: dict[str, Any], expected_state: str, expected_sha: str | None) -> None:
    if current["state"] != expected_state:
        raise ReleaseError("compare_and_swap_state_mismatch")
    if expected_state == "present" and current["sha256"] != expected_sha:
        raise ReleaseError("compare_and_swap_hash_mismatch")


def verify_php(path: Path, *, required: bool | None = None) -> None:
    if required is None:
        required = path.suffix.lower() == ".php"
    if not required:
        return
    result = subprocess.run(
        ["php", "-l", str(path)],
        stdin=subprocess.DEVNULL,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        raise ReleaseError("php_lint_failed")


def prepare(args: argparse.Namespace) -> dict[str, Any]:
    root = validate_root(args.root)
    intended = require_sha256(args.intended_sha, "intended_sha256")
    expected = args.expected_before
    if expected != "absent":
        require_sha256(expected, "expected_before")
    rel, target = resolve_target(root, args.target_relative)
    with LockedRoot(root):
        release_dir, manifest_path, backup_path, _ = release_paths(root, args.release_id, create_parent=True)
        if os.path.lexists(release_dir):
            raise ReleaseError("release_id_exists")
        current = regular_state(target)
        if expected == "absent":
            require_state_matches(current, "absent", None)
        else:
            require_state_matches(current, "present", expected)
        release_dir.mkdir(mode=0o700)
        try:
            if current["state"] == "present":
                copy_regular(target, backup_path, 0o600)
                if sha256_path(backup_path) != current["sha256"]:
                    raise ReleaseError("backup_hash_mismatch")
            manifest = {
                "schemaVersion": SCHEMA_VERSION,
                "releaseId": args.release_id,
                "targetRelative": rel.as_posix(),
                "originalState": current["state"],
                "originalSha256": current["sha256"],
                "originalMode": current["mode"],
                "intendedSha256": intended,
                "phase": "prepared",
                "preparedAt": utc_now(),
            }
            atomic_json(manifest_path, manifest)
        except Exception:
            shutil.rmtree(release_dir, ignore_errors=True)
            raise
    return {
        "ok": True,
        "action": "prepare",
        "releaseId": args.release_id,
        "targetRelative": rel.as_posix(),
        "originalState": current["state"],
        "originalSha256": current["sha256"],
        "intendedSha256": intended,
        "phase": "prepared",
    }


def restore_original(target: Path, backup: Path, manifest: dict[str, Any]) -> None:
    if manifest["originalState"] == "absent":
        if os.path.lexists(target):
            target.unlink()
            fsync_directory(target.parent)
        return
    if not os.path.lexists(backup) or backup.is_symlink():
        raise ReleaseError("missing_or_unsafe_backup")
    if sha256_path(backup) != manifest["originalSha256"]:
        raise ReleaseError("backup_hash_mismatch")
    atomic_copy(backup, target, int(manifest["originalMode"]))
    restored = regular_state(target)
    require_state_matches(restored, "present", manifest["originalSha256"])
    if restored["mode"] != manifest["originalMode"]:
        raise ReleaseError("restored_mode_mismatch")


def apply_release(args: argparse.Namespace) -> dict[str, Any]:
    root = validate_root(args.root)
    with LockedRoot(root):
        release_dir, manifest_path, backup_path, payload_path = release_paths(root, args.release_id, create_parent=False)
        require_private_directory(release_dir)
        manifest = read_manifest(manifest_path, args.release_id)
        if manifest["phase"] != "prepared":
            raise ReleaseError("release_not_prepared")
        _, target = resolve_target(root, manifest["targetRelative"])
        payload_state = regular_state(payload_path)
        require_state_matches(payload_state, "present", manifest["intendedSha256"])
        verify_php(payload_path, required=manifest["targetRelative"].lower().endswith(".php"))
        current = regular_state(target)
        require_state_matches(current, manifest["originalState"], manifest["originalSha256"])
        atomic_copy(payload_path, target, 0o444)
        try:
            deployed = regular_state(target)
            require_state_matches(deployed, "present", manifest["intendedSha256"])
            if deployed["mode"] != 0o444:
                raise ReleaseError("deployed_mode_mismatch")
            verify_php(target)
        except Exception as exc:
            live = regular_state(target)
            if live["state"] == "present" and live["sha256"] == manifest["intendedSha256"]:
                try:
                    restore_original(target, backup_path, manifest)
                    manifest["phase"] = "failed_recovered"
                    manifest["failure"] = str(exc)
                    manifest["failedAt"] = utc_now()
                    atomic_json(manifest_path, manifest)
                except Exception as recovery_exc:
                    manifest["phase"] = "failed_unrecovered"
                    manifest["failure"] = str(exc)
                    manifest["recoveryFailure"] = str(recovery_exc)
                    manifest["failedAt"] = utc_now()
                    atomic_json(manifest_path, manifest)
            else:
                manifest["phase"] = "failed_unrecovered"
                manifest["failure"] = str(exc)
                manifest["failedAt"] = utc_now()
                atomic_json(manifest_path, manifest)
            raise
        manifest["phase"] = "deployed"
        manifest["deployedAt"] = utc_now()
        manifest["deployedSha256"] = deployed["sha256"]
        manifest["deployedMode"] = deployed["mode"]
        atomic_json(manifest_path, manifest)
    return {
        "ok": True,
        "action": "apply",
        "releaseId": args.release_id,
        "targetRelative": manifest["targetRelative"],
        "phase": "deployed",
        "sha256": deployed["sha256"],
        "mode": format(deployed["mode"], "04o"),
    }


def rollback(args: argparse.Namespace) -> dict[str, Any]:
    root = validate_root(args.root)
    expected_deployed = require_sha256(args.expected_deployed_sha, "expected_deployed_sha256")
    with LockedRoot(root):
        release_dir, manifest_path, backup_path, _ = release_paths(root, args.release_id, create_parent=False)
        require_private_directory(release_dir)
        manifest = read_manifest(manifest_path, args.release_id)
        if manifest["phase"] != "deployed":
            raise ReleaseError("release_not_deployed")
        if manifest["intendedSha256"] != expected_deployed:
            raise ReleaseError("expected_deployed_sha_mismatch")
        _, target = resolve_target(root, manifest["targetRelative"])
        current = regular_state(target)
        require_state_matches(current, "present", expected_deployed)
        restore_original(target, backup_path, manifest)
        final_state = regular_state(target)
        require_state_matches(final_state, manifest["originalState"], manifest["originalSha256"])
        manifest["phase"] = "rolled_back"
        manifest["rolledBackAt"] = utc_now()
        atomic_json(manifest_path, manifest)
    return {
        "ok": True,
        "action": "rollback",
        "releaseId": args.release_id,
        "targetRelative": manifest["targetRelative"],
        "phase": "rolled_back",
        "state": final_state["state"],
        "sha256": final_state["sha256"],
        "mode": format(final_state["mode"], "04o") if final_state["mode"] is not None else None,
    }


def inspect(args: argparse.Namespace) -> dict[str, Any]:
    root = validate_root(args.root)
    with LockedRoot(root):
        release_dir, manifest_path, _, _ = release_paths(root, args.release_id, create_parent=False)
        require_private_directory(release_dir)
        manifest = read_manifest(manifest_path, args.release_id)
        _, target = resolve_target(root, manifest["targetRelative"])
        current = regular_state(target)
    return {
        "ok": True,
        "action": "inspect",
        "releaseId": args.release_id,
        "targetRelative": manifest["targetRelative"],
        "phase": manifest["phase"],
        "intendedSha256": manifest["intendedSha256"],
        "originalState": manifest["originalState"],
        "currentState": current["state"],
        "currentSha256": current["sha256"],
        "currentMode": format(current["mode"], "04o") if current["mode"] is not None else None,
    }


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(add_help=True)
    parser.add_argument("action", choices=("prepare", "apply", "inspect", "rollback"))
    parser.add_argument("--root", required=True)
    parser.add_argument("--release-id", required=True)
    parser.add_argument("--target-relative")
    parser.add_argument("--expected-before")
    parser.add_argument("--intended-sha")
    parser.add_argument("--expected-deployed-sha")
    return parser


def require_action_arguments(args: argparse.Namespace) -> None:
    if args.action == "prepare":
        if not args.target_relative or not args.expected_before or not args.intended_sha:
            raise ReleaseError("missing_prepare_argument")
    elif args.action == "rollback":
        if not args.expected_deployed_sha:
            raise ReleaseError("missing_expected_deployed_sha")


def main() -> int:
    try:
        args = build_parser().parse_args()
        require_release_id(args.release_id)
        require_action_arguments(args)
        if args.action == "prepare":
            result = prepare(args)
        elif args.action == "apply":
            result = apply_release(args)
        elif args.action == "rollback":
            result = rollback(args)
        else:
            result = inspect(args)
        emit(result)
        return 0
    except ReleaseError as exc:
        emit({"ok": False, "error": str(exc)})
        return 1
    except Exception as exc:
        emit({"ok": False, "error": f"internal_error:{type(exc).__name__}"})
        return 1


if __name__ == "__main__":
    sys.exit(main())
