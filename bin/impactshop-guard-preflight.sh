#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_PATH="${IMPACTSHOP_GUARD_CONFIG:-${ROOT_DIR}/docs/impactshop-guard-config.json}"

if [[ ! -f "$CONFIG_PATH" ]]; then
  echo "❌ Guard preflight: config nem található: $CONFIG_PATH" >&2
  exit 1
fi

python3 - "$ROOT_DIR" "$CONFIG_PATH" <<'PY'
import json
import os
import subprocess
import sys

root = os.path.realpath(sys.argv[1])
config_path = sys.argv[2]

with open(config_path, "r", encoding="utf-8") as fh:
    cfg = json.load(fh)

repo = cfg.get("repo", {})
repo_root = os.path.realpath(repo.get("root", ""))
repo_remote = repo.get("remote", "")
repo_branch = repo.get("branch", "")

if not repo_root or not repo_remote or not repo_branch:
    print("❌ Guard preflight: hiányzó repo meta (root/remote/branch).")
    sys.exit(1)

def git_output(*args: str) -> str:
    try:
        return subprocess.check_output(["git", "-C", root, *args], text=True).strip()
    except subprocess.CalledProcessError:
        return ""

current_remote = git_output("remote", "get-url", "origin")
current_branch = git_output("rev-parse", "--abbrev-ref", "HEAD")
common_dir_raw = git_output("rev-parse", "--git-common-dir")
current_common_dir = os.path.realpath(os.path.join(root, common_dir_raw)) if common_dir_raw else ""
expected_common_dir = os.path.realpath(os.path.join(repo_root, ".git"))

same_repo_root = root == repo_root
same_common_dir = current_common_dir == expected_common_dir if current_common_dir else False

if not (same_repo_root or same_common_dir):
    print(
        f"❌ Guard preflight: repo root mismatch. current={root} expected={repo_root} "
        f"current_common={current_common_dir or '<unknown>'} expected_common={expected_common_dir}"
    )
    sys.exit(1)

if current_remote != repo_remote:
    print(f"❌ Guard preflight: remote mismatch. current={current_remote} expected={repo_remote}")
    sys.exit(1)
if current_branch != repo_branch:
    print(f"❌ Guard preflight: branch mismatch. current={current_branch} expected={repo_branch}")
    sys.exit(1)

protected = cfg.get("protected_files", [])
missing = []
missing_wrong_repo = []
invalid = []
for item in protected:
    path = item.get("path") if isinstance(item, dict) else str(item)
    path = (path or "").strip()
    if not path:
        continue
    abs_path = os.path.realpath(os.path.join(root, path))
    if not abs_path.startswith(root + os.sep):
        invalid.append(path)
        continue
    owner_repo = item.get("owner_repo") if isinstance(item, dict) else ""
    owner_root = item.get("owner_root") if isinstance(item, dict) else ""
    owner_branch = item.get("owner_branch") if isinstance(item, dict) else ""
    owner_root = os.path.realpath(owner_root) if owner_root else ""
    owner_mismatch = False
    if owner_repo and owner_repo != repo_remote:
        owner_mismatch = True
    if owner_root and owner_root != repo_root:
        owner_mismatch = True
    if owner_branch and owner_branch != repo_branch:
        owner_mismatch = True
    if not os.path.isfile(abs_path):
        if owner_mismatch:
            missing_wrong_repo.append((path, owner_repo, owner_root, owner_branch))
        else:
            missing.append(path)

if invalid:
    print("❌ Guard preflight: protected file path a repo-n kívül:")
    for p in invalid:
        print(f"  - {p}")
    sys.exit(1)

if missing_wrong_repo:
    print("❌ Guard preflight: védett fájl másik repo tulajdonban:")
    for path, owner_repo, owner_root, owner_branch in missing_wrong_repo:
        print(f"  - {path} (owner_repo={owner_repo} owner_root={owner_root} owner_branch={owner_branch})")
    sys.exit(1)

if missing:
    print("❌ Guard preflight: hiányzó védett fájl(ok):")
    for p in missing:
        print(f"  - {p}")
    sys.exit(1)

print("✅ Guard preflight OK")
PY
