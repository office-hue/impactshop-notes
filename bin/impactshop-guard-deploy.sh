#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_PATH="${ROOT_DIR}/docs/impactshop-guard-config.json"
HASH_PATH="${ROOT_DIR}/docs/impactshop-guard-hashes.json"
CONFIG_CHECKSUM_PATH="${ROOT_DIR}/docs/impactshop-guard-config.sha256"
PROTECTED_MODEL_PATH="${ROOT_DIR}/docs/impactshop-protected-files.json"
PROTECTED_TOUCH_SCRIPT="${ROOT_DIR}/scripts/check-protected-file-touch.sh"
SNAPSHOT_DIR_DEFAULT="${ROOT_DIR}/.codex/guard-snapshots"
AUDIT_DIR="${ROOT_DIR}/.codex/guard-events"
EMERGENCY_LOG="${AUDIT_DIR}/emergency-override.jsonl"
LOCK_PATH="${AUDIT_DIR}/.guard.lock"

mkdir -p "$AUDIT_DIR"

EMERGENCY_OVERRIDE=0
EMERGENCY_REASON=""
AUTO_COMMIT=""
REQUIRE_REASON=""
LOCK_MODE="${IMPACTSHOP_GUARD_LOCK_MODE:-none}"
SAFE_MODE="${IMPACTSHOP_GUARD_SAFE_MODE:-0}"
NON_INTERACTIVE=0
AUTO_APPROVE=0

ARGS=()
for arg in "$@"; do
  case "$arg" in
    --emergency-override)
      EMERGENCY_OVERRIDE=1
      ;;
    --reason=*)
      EMERGENCY_REASON="${arg#--reason=}"
      ;;
    --non-interactive)
      NON_INTERACTIVE=1
      ;;
    --auto-approve)
      AUTO_APPROVE=1
      ;;
    *)
      ARGS+=("$arg")
      ;;
  esac
done

if [[ -z "${IMPACT_ENV:-}" ]]; then
  for arg in "${ARGS[@]:-}"; do
    case "$arg" in
      --staging|-s)
        export IMPACT_ENV="staging"
        break
        ;;
      --production|-p)
        export IMPACT_ENV="production"
        break
        ;;
    esac
  done
fi

acquire_guard_lock() {
  local now_ts lock_age pid_file pid
  if mkdir "$LOCK_PATH" 2>/dev/null; then
    printf '%s\n' "$$" >"${LOCK_PATH}/pid"
    date -u +"%Y-%m-%dT%H:%M:%SZ" >"${LOCK_PATH}/started_at"
    return 0
  fi

  pid_file="${LOCK_PATH}/pid"
  if [[ -f "$pid_file" ]]; then
    pid="$(cat "$pid_file" 2>/dev/null || true)"
    if [[ -n "$pid" && "$pid" =~ ^[0-9]+$ ]]; then
      if kill -0 "$pid" 2>/dev/null; then
        echo "❌ Guard lock aktív (PID: $pid). Várj, vagy töröld a lockot, ha biztosan stale." >&2
        exit 1
      fi
    fi
  fi

  lock_age="$(LOCK_PATH="$LOCK_PATH" python3 - <<'PY'
import os
import sys
from datetime import datetime, timezone
lock_path = os.environ["LOCK_PATH"]
try:
    mtime = os.path.getmtime(lock_path)
except FileNotFoundError:
    print("0")
    sys.exit(0)
age = int(datetime.now(timezone.utc).timestamp() - mtime)
print(str(age))
PY
)"

  if [[ "$lock_age" -gt 120 ]]; then
    rm -rf "$LOCK_PATH"
    mkdir "$LOCK_PATH"
    printf '%s\n' "$$" >"${LOCK_PATH}/pid"
    date -u +"%Y-%m-%dT%H:%M:%SZ" >"${LOCK_PATH}/started_at"
    echo "⚠️  Stale guard lock törölve (age=${lock_age}s, pid inaktív)."
    return 0
  fi

  echo "❌ Guard lock aktív (stale gyanú: age=${lock_age}s). Próbáld újra ~2 perc múlva." >&2
  exit 1
}

release_guard_lock() {
  rm -rf "$LOCK_PATH" >/dev/null 2>&1 || true
}

acquire_guard_lock

if [[ ! -f "$CONFIG_PATH" ]]; then
  echo "❌ Guard konfiguráció hiányzik: $CONFIG_PATH" >&2
  exit 1
fi

if [[ ! -f "$HASH_PATH" ]]; then
  echo "❌ Guard hash manifest hiányzik: $HASH_PATH" >&2
  echo "   Futtasd: bin/impactshop-guard-init.sh" >&2
  exit 1
fi

if [[ ! -f "$CONFIG_CHECKSUM_PATH" ]]; then
  echo "❌ Guard config checksum hiányzik: $CONFIG_CHECKSUM_PATH" >&2
  exit 1
fi

python3 - "$CONFIG_PATH" "$CONFIG_CHECKSUM_PATH" <<'PY' >/tmp/impactshop_guard_cfg.json
import json
import hashlib
import sys
cfg_path, checksum_path = sys.argv[1], sys.argv[2]
raw = open(cfg_path, "rb").read()
cfg = json.loads(raw.decode("utf-8"))

checksum_line = open(checksum_path, "r", encoding="utf-8").read().strip()
expected = checksum_line.split()[0] if checksum_line else ""
actual = hashlib.sha256(raw).hexdigest()
if not expected or expected != actual:
    print("CONFIG_CHECKSUM_MISMATCH")
    sys.exit(2)

repo = cfg.get("repo", {})
missing = []
for key in ("root", "remote", "branch"):
    if not repo.get(key):
        missing.append(f"repo.{key}")
if missing:
    print("CONFIG_SCHEMA_ERROR|" + ",".join(missing))
    sys.exit(3)

protected = cfg.get("protected_files", [])
if not isinstance(protected, list) or not protected:
    print("CONFIG_SCHEMA_ERROR|protected_files")
    sys.exit(3)
for item in protected:
    if isinstance(item, dict):
        if not item.get("path"):
            print("CONFIG_SCHEMA_ERROR|protected_files.path")
            sys.exit(3)
    elif isinstance(item, str):
        if not item.strip():
            print("CONFIG_SCHEMA_ERROR|protected_files.path")
            sys.exit(3)
    else:
        print("CONFIG_SCHEMA_ERROR|protected_files.type")
        sys.exit(3)

print(json.dumps(cfg))
PY

cfg_status="$(cat /tmp/impactshop_guard_cfg.json)"
if [[ "$cfg_status" == "CONFIG_CHECKSUM_MISMATCH" ]]; then
  echo "❌ Guard config checksum mismatch. Ellenőrizd: $CONFIG_PATH vs $CONFIG_CHECKSUM_PATH" >&2
  exit 1
fi
if [[ "$cfg_status" == CONFIG_SCHEMA_ERROR* ]]; then
  echo "❌ Guard config séma hiba: ${cfg_status#CONFIG_SCHEMA_ERROR|}" >&2
  exit 1
fi

CONFIG_JSON="$cfg_status"
SNAPSHOT_DIR="$(python3 - <<'PY'
import json
import os
cfg = json.loads(open("/tmp/impactshop_guard_cfg.json","r").read())
print(cfg.get("snapshot", {}).get("storage_path", ""))
PY
)"

"${ROOT_DIR}/bin/impactshop-guard-preflight.sh"

if [[ -f "$PROTECTED_MODEL_PATH" && -x "$PROTECTED_TOUCH_SCRIPT" ]]; then
  echo "🧭 Protected-touch guard ellenőrzés…"
  "$PROTECTED_TOUCH_SCRIPT" --mode local
fi

AUTO_COMMIT="$(python3 - <<'PY'
import json
cfg = json.loads(open("/tmp/impactshop_guard_cfg.json","r").read())
print("1" if cfg.get("approval", {}).get("auto_commit_hash") else "0")
PY
)"
REQUIRE_REASON="$(python3 - <<'PY'
import json
cfg = json.loads(open("/tmp/impactshop_guard_cfg.json","r").read())
print("1" if cfg.get("approval", {}).get("require_reason") else "0")
PY
)"

if [[ -n "${IMPACTSHOP_GUARD_AUTO_COMMIT:-}" ]]; then
  AUTO_COMMIT="${IMPACTSHOP_GUARD_AUTO_COMMIT}"
fi
if [[ -n "${IMPACTSHOP_GUARD_REQUIRE_REASON:-}" ]]; then
  REQUIRE_REASON="${IMPACTSHOP_GUARD_REQUIRE_REASON}"
fi
if [[ -n "${IMPACTSHOP_GUARD_SAFE_MODE:-}" ]]; then
  SAFE_MODE="${IMPACTSHOP_GUARD_SAFE_MODE}"
fi

EMERGENCY_ACTION="$(python3 - <<'PY'
import json
cfg = json.loads(open("/tmp/impactshop_guard_cfg.json","r").read())
print(cfg.get("emergency_override", {}).get("action", "block"))
PY
)"

with_lock_mode() {
  local mode="$1"
  python3 - "$CONFIG_PATH" "$mode" <<'PY'
import json
import os
import sys
import stat

cfg_path = sys.argv[1]
mode = sys.argv[2]
root = os.path.abspath(os.path.join(os.path.dirname(cfg_path), ".."))
with open(cfg_path, "r", encoding="utf-8") as fh:
    cfg = json.load(fh)

files = [p for p in cfg.get("protected_files", []) if p]
changed = False
if mode == "chmod_unlock":
    for entry in files:
        rel = entry.get("path") if isinstance(entry, dict) else entry
        if not rel:
            continue
        path = os.path.join(root, rel)
        if os.path.isfile(path):
            st = os.stat(path)
            if not (st.st_mode & stat.S_IWUSR):
                os.chmod(path, 0o640)
                changed = True
elif mode == "chmod_lock":
    for entry in files:
        rel = entry.get("path") if isinstance(entry, dict) else entry
        if not rel:
            continue
        path = os.path.join(root, rel)
        if os.path.isfile(path):
            st = os.stat(path)
            if (st.st_mode & stat.S_IWUSR):
                os.chmod(path, 0o440)
                changed = True
print("1" if changed else "0")
PY
}

if [[ "$LOCK_MODE" == "chmod" ]]; then
  lock_changed="$(with_lock_mode "chmod_unlock")"
  if [[ "$lock_changed" == "1" ]]; then
    echo "🔓 Guard lock: chmod unlock aktív"
  else
    echo "🔓 Guard lock: nincs változtatás"
  fi
fi

cleanup_lock() {
  if [[ "$LOCK_MODE" == "chmod" ]]; then
    with_lock_mode "chmod_lock" >/dev/null
  fi
  release_guard_lock
}
trap cleanup_lock EXIT

if [[ -z "$SNAPSHOT_DIR" ]]; then
  SNAPSHOT_DIR="$SNAPSHOT_DIR_DEFAULT"
fi

mkdir -p "$SNAPSHOT_DIR"

timestamp="$(date -u +%Y%m%d-%H%M%S)"
snapshot_path="${SNAPSHOT_DIR}/deploy-${timestamp}"
mkdir -p "$snapshot_path"

python3 - "$CONFIG_PATH" "$HASH_PATH" "$snapshot_path" <<'PY'
import json
import os
import shutil
import sys
import hashlib
from datetime import datetime, timezone

cfg_path = sys.argv[1]
hash_path = sys.argv[2]
snap_dir = sys.argv[3]
root = os.path.abspath(os.path.join(os.path.dirname(cfg_path), ".."))
with open(cfg_path, "r", encoding="utf-8") as fh:
    cfg = json.load(fh)

protected = cfg.get("protected_files", [])
hashes = {}
for entry in protected:
    rel = entry.get("path") if isinstance(entry, dict) else entry
    rel = (rel or "").strip()
    if not rel:
        continue
    src = os.path.join(root, rel)
    if os.path.isfile(src):
        dst = os.path.join(snap_dir, rel)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copy2(src, dst)
        with open(src, "rb") as fh:
            hashes[rel] = hashlib.sha256(fh.read()).hexdigest()

meta = {
    "timestamp": datetime.now(timezone.utc).isoformat(),
    "repo": cfg.get("repo", {}),
    "protected_files": [entry.get("path") if isinstance(entry, dict) else entry for entry in protected],
    "hashes": hashes,
}
with open(os.path.join(snap_dir, "snapshot.meta.json"), "w", encoding="utf-8") as fh:
    json.dump(meta, fh, indent=2, ensure_ascii=False)

if os.path.isfile(hash_path):
    dst = os.path.join(snap_dir, os.path.basename(hash_path))
    shutil.copy2(hash_path, dst)
    checksum_path = os.path.join(os.path.dirname(hash_path), "impactshop-guard-hashes.sha256")
    if os.path.isfile(checksum_path):
        shutil.copy2(checksum_path, os.path.join(snap_dir, os.path.basename(checksum_path)))
    config_checksum = os.path.join(os.path.dirname(hash_path), "impactshop-guard-config.sha256")
    if os.path.isfile(config_checksum):
        shutil.copy2(config_checksum, os.path.join(snap_dir, os.path.basename(config_checksum)))
PY

echo "📦 Guard snapshot: $snapshot_path"
export IMPACTSHOP_DEPLOY_GUARD_SNAPSHOT_PATH="$snapshot_path"

if [[ "$SAFE_MODE" == "1" && "$EMERGENCY_OVERRIDE" == "1" ]]; then
  echo "❌ Safe-mode aktív: emergency override tiltva (IMPACTSHOP_GUARD_SAFE_MODE=1)" >&2
  exit 1
fi

if [[ "$EMERGENCY_OVERRIDE" == "1" ]]; then
  if [[ -z "$EMERGENCY_REASON" ]]; then
    echo "❌ Emergency override requires --reason=\"...\"" >&2
    exit 1
  fi
  emergency_out="$(python3 - "$CONFIG_PATH" "$EMERGENCY_LOG" "$EMERGENCY_REASON" <<'PY'
import json
import os
import sys
from datetime import datetime, timedelta, timezone

cfg_path, log_path, reason = sys.argv[1], sys.argv[2], sys.argv[3]
with open(cfg_path, "r", encoding="utf-8") as fh:
    cfg = json.load(fh)

override = cfg.get("emergency_override", {})
rate_days = int(override.get("rate_limit_days", 7))
max_count = int(override.get("max_count", 1))
now = datetime.now(timezone.utc)
window_start = now - timedelta(days=rate_days)

events = []
if os.path.isfile(log_path):
    with open(log_path, "r", encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if not line:
                continue
            try:
                events.append(json.loads(line))
            except json.JSONDecodeError:
                continue

recent = [e for e in events if e.get("timestamp") and datetime.fromisoformat(e["timestamp"]) >= window_start]
if len(recent) >= max_count:
    print(f"BLOCK|Emergency override limit exceeded ({len(recent)}/{max_count} in {rate_days}d)")
    sys.exit(0)

entry = {
    "timestamp": now.isoformat(),
    "action": "emergency_override",
    "reason": reason,
    "window_days": rate_days,
    "max_count": max_count,
    "git": os.popen("git -C " + os.path.abspath(os.path.join(os.path.dirname(cfg_path), "..")) + " rev-parse HEAD").read().strip(),
    "whoami": os.popen("whoami").read().strip(),
    "env": os.environ.get("IMPACTSHOP_ENV", "")
}
os.makedirs(os.path.dirname(log_path), exist_ok=True)
with open(log_path, "a", encoding="utf-8") as fh:
    fh.write(json.dumps(entry, ensure_ascii=False) + "\n")
print("OK|Emergency override recorded")
PY
)"
  emergency_status="${emergency_out%%|*}"
  if [[ "$emergency_status" == "BLOCK" ]]; then
    if [[ "$EMERGENCY_ACTION" == "warn_confirm" ]]; then
      echo "⚠️  Emergency override limit exceeded."
      echo "Részletek: ${emergency_out#*|}"
      echo "Ha folytatni akarod, írd be pontosan: I accept the risk"
      read -r confirm
      if [[ "$confirm" != "I accept the risk" ]]; then
        echo "❌ Deploy megszakítva (emergency limit)." >&2
        exit 1
      fi
    else
      echo "❌ Emergency override limit exceeded. Deploy blocked." >&2
      exit 1
    fi
  fi
  echo "🚨 Emergency override aktív: $EMERGENCY_REASON"
  export IMPACTSHOP_PROTECTED_DEPLOY="${IMPACTSHOP_PROTECTED_DEPLOY:-1}"
  export IMPACTSHOP_POST_DEPLOY_UI_SMOKE="${IMPACTSHOP_POST_DEPLOY_UI_SMOKE:-1}"
  export IMPACTSHOP_POST_DEPLOY_ELEMENTOR_AUDIT="${IMPACTSHOP_POST_DEPLOY_ELEMENTOR_AUDIT:-1}"
  "${ROOT_DIR}/bin/deploy-wpcontent-map.sh" "${ARGS[@]}"
  deploy_status=$?
  goto_finalize=1
else
  goto_finalize=0
fi

python3 - "$CONFIG_PATH" "$HASH_PATH" <<'PY' >/tmp/impactshop_guard_check.txt
import json
import hashlib
import os
import sys

cfg_path, hash_path = sys.argv[1], sys.argv[2]
root = os.path.abspath(os.path.join(os.path.dirname(cfg_path), ".."))

with open(cfg_path, "r", encoding="utf-8") as fh:
    cfg = json.load(fh)
with open(hash_path, "r", encoding="utf-8") as fh:
    manifest = json.load(fh)

repo = cfg.get("repo", {})
repo_root = os.path.realpath(repo.get("root", root))
repo_remote = repo.get("remote", "")
repo_branch = repo.get("branch", "")

protected = cfg.get("protected_files", [])
hashes = manifest.get("hashes", {})

missing = []
missing_wrong_repo = []
invalid_paths = []
changed = []
for entry in protected:
    rel = entry.get("path") if isinstance(entry, dict) else entry
    rel = (rel or "").strip()
    if not rel:
        continue
    abs_path = os.path.realpath(os.path.join(root, rel))
    if not abs_path.startswith(os.path.realpath(root) + os.sep):
        invalid_paths.append(rel)
        continue
    owner_repo = entry.get("owner_repo") if isinstance(entry, dict) else ""
    owner_root = entry.get("owner_root") if isinstance(entry, dict) else ""
    owner_branch = entry.get("owner_branch") if isinstance(entry, dict) else ""
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
            missing_wrong_repo.append({
                "path": rel,
                "owner_repo": owner_repo,
                "owner_root": owner_root,
                "owner_branch": owner_branch,
            })
        else:
            missing.append(rel)
        continue
    with open(abs_path, "rb") as fh:
        digest = hashlib.sha256(fh.read()).hexdigest()
    known = hashes.get(rel)
    if not known or digest != known:
        changed.append(rel)

out = {
    "missing": missing,
    "missing_wrong_repo": missing_wrong_repo,
    "invalid_paths": invalid_paths,
    "changed": changed,
}
print(json.dumps(out, ensure_ascii=False))
PY

guard_state="$(cat /tmp/impactshop_guard_check.txt)"
missing_count=$(
GUARD_STATE="$guard_state" python3 - <<'PY'
import json
import os
data = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
print(len(data.get("missing", [])))
PY
)
wrong_repo_count=$(
GUARD_STATE="$guard_state" python3 - <<'PY'
import json
import os
data = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
print(len(data.get("missing_wrong_repo", [])))
PY
)
invalid_count=$(
GUARD_STATE="$guard_state" python3 - <<'PY'
import json
import os
data = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
print(len(data.get("invalid_paths", [])))
PY
)
changed_count=$(
GUARD_STATE="$guard_state" python3 - <<'PY'
import json
import os
data = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
print(len(data.get("changed", [])))
PY
)
auto_approve_blocked_json="$(
CONFIG_JSON="$CONFIG_JSON" GUARD_STATE="$guard_state" python3 - <<'PY'
import fnmatch
import json
import os

cfg = json.loads(os.environ.get("CONFIG_JSON", "{}") or "{}")
state = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
allowed = cfg.get("approval", {}).get("auto_approve_allowed_globs", [])
changed = state.get("changed", [])
blocked = []
for rel in changed:
    if not any(fnmatch.fnmatch(rel, pattern) for pattern in allowed):
        blocked.append(rel)
print(json.dumps({"blocked": blocked}, ensure_ascii=False))
PY
)"
auto_approve_blocked_count="$(
AUTO_APPROVE_BLOCKED_JSON="$auto_approve_blocked_json" python3 - <<'PY'
import json
import os
data = json.loads(os.environ.get("AUTO_APPROVE_BLOCKED_JSON", "{}") or "{}")
print(len(data.get("blocked", [])))
PY
)"

if [[ "$invalid_count" -gt 0 ]]; then
  echo "❌ Guard: védett fájl a repo-n kívülre mutat." >&2
  GUARD_STATE="$guard_state" python3 - <<'PY'
import json
import os
data = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
for rel in data.get("invalid_paths", []):
    print(f"  - {rel}")
PY
  exit 1
fi

if [[ "$wrong_repo_count" -gt 0 ]]; then
  echo "❌ Guard: védett fájl másik repo tulajdonban van. Deploy megszakítva." >&2
  GUARD_STATE="$guard_state" python3 - <<'PY'
import json
import os
data = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
for item in data.get("missing_wrong_repo", []):
    path = item.get("path")
    owner_repo = item.get("owner_repo")
    owner_root = item.get("owner_root")
    owner_branch = item.get("owner_branch")
    print(f"  - {path} (owner_repo={owner_repo} owner_root={owner_root} owner_branch={owner_branch})")
PY
  exit 1
fi

if [[ "$missing_count" -gt 0 ]]; then
  echo "❌ Guard: hiányzó védett fájl(ok). Deploy megszakítva." >&2
  GUARD_STATE="$guard_state" python3 - <<'PY'
import json
import os
data = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
for rel in data.get("missing", []):
    print(f"  - {rel}")
PY
  exit 1
fi

if [[ "$changed_count" -gt 0 ]]; then
  echo "🛡️ Védett fájl változás észlelve:"
  GUARD_STATE="$guard_state" python3 - <<'PY'
import json
import os
data = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
for rel in data.get("changed", []):
    print(f"  - {rel}")
PY

  if [[ "$NON_INTERACTIVE" == "1" ]]; then
    if [[ "$AUTO_APPROVE" == "1" ]]; then
      if [[ "$auto_approve_blocked_count" -gt 0 ]]; then
        echo "❌ Non-interactive auto-approve tiltva: protected runtime/shell drift észlelve." >&2
        AUTO_APPROVE_BLOCKED_JSON="$auto_approve_blocked_json" python3 - <<'PY' >&2
import json
import os
data = json.loads(os.environ.get("AUTO_APPROVE_BLOCKED_JSON", "{}") or "{}")
for rel in data.get("blocked", []):
    print(f"  - {rel}")
PY
        echo "   Ezekhez kézi jóváhagyás kell, auto-approve nem használható." >&2
        exit 1
      fi
      echo "⚠️  Non-interactive: auto-approve aktív."
    else
      echo "❌ Non-interactive módban nincs auto-approve → deploy megszakítva."
      exit 1
    fi
  fi

  while true; do
    echo "Mit tegyek? [approve] Jóváhagyás / [diff] Diff / [no] Megszakítás"
    if [[ "$NON_INTERACTIVE" == "1" && "$AUTO_APPROVE" == "1" ]]; then
      choice="approve"
    else
      read -r choice
    fi
    choice_norm=$(printf '%s' "$choice" | tr '[:upper:]' '[:lower:]')
    if [[ "$choice_norm" == "diff" ]]; then
      GUARD_STATE="$guard_state" python3 - "$ROOT_DIR" "$SNAPSHOT_DIR" <<'PY'
import json
import os
import sys
import tempfile
import subprocess

root = sys.argv[1]
snap_root = sys.argv[2]
data = json.loads(os.environ.get("GUARD_STATE", "{}") or "{}")
for rel in data.get("changed", []):
    try:
        old = subprocess.check_output(["git", "show", f"HEAD:{rel}"], cwd=root)
    except subprocess.CalledProcessError:
        latest_snap = ""
        if os.path.isdir(snap_root):
            entries = sorted([d for d in os.listdir(snap_root) if d.startswith("deploy-")])
            if entries:
                latest_snap = os.path.join(snap_root, entries[-1], rel)
        if latest_snap and os.path.isfile(latest_snap):
            subprocess.call(["git", "diff", "--no-index", latest_snap, os.path.join(root, rel)])
        else:
            print(f"⚠️ Nincs git history és nincs snapshot: {rel}")
        continue
    with tempfile.NamedTemporaryFile(delete=False) as tmp:
        tmp.write(old)
        tmp_path = tmp.name
    subprocess.call(["git", "diff", "--no-index", tmp_path, os.path.join(root, rel)])
PY
      continue
    fi
    if [[ "$choice_norm" == "approve" ]]; then
      break
    fi
    if [[ "$choice_norm" == "no" || -z "$choice_norm" ]]; then
      echo "❌ Deploy megszakítva (guard reject)."
      exit 1
    fi
    echo "❓ Ismeretlen válasz."
  done

  if [[ "$REQUIRE_REASON" == "1" ]]; then
    echo "Indok (kötelező):"
    if [[ "$NON_INTERACTIVE" == "1" && "$AUTO_APPROVE" == "1" ]]; then
      reason="${IMPACTSHOP_GUARD_APPROVE_REASON:-auto-approve}"
    else
      read -r reason
    fi
    if [[ -z "$reason" ]]; then
      echo "❌ Indok nélkül nincs jóváhagyás."
      exit 1
    fi
  else
    reason="(nincs megadva)"
  fi

  python3 - "$CONFIG_PATH" "$HASH_PATH" "$reason" "$guard_state" <<'PY'
import json
import hashlib
import os
import sys
from datetime import datetime, timezone

cfg_path, hash_path, reason, guard_state = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
root = os.path.abspath(os.path.join(os.path.dirname(cfg_path), ".."))

with open(cfg_path, "r", encoding="utf-8") as fh:
    cfg = json.load(fh)
with open(hash_path, "r", encoding="utf-8") as fh:
    manifest = json.load(fh)

hashes = manifest.get("hashes", {})
data = json.loads(guard_state)
changed = set(data.get("changed", []))

for rel in changed:
    rel = rel.strip()
    if not rel:
        continue
    abs_path = os.path.join(root, rel)
    if os.path.isfile(abs_path):
        with open(abs_path, "rb") as fh:
            hashes[rel] = hashlib.sha256(fh.read()).hexdigest()

manifest["hashes"] = hashes
manifest["generated_at"] = datetime.now(timezone.utc).isoformat()
with open(hash_path, "w", encoding="utf-8") as fh:
    json.dump(manifest, fh, indent=2, ensure_ascii=False)

os.makedirs(os.path.join(root, ".codex", "guard-events"), exist_ok=True)
event_path = os.path.join(root, ".codex", "guard-events", f"approval-{datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')}.jsonl")
event = {
    "timestamp": datetime.now(timezone.utc).isoformat(),
    "action": "deploy_approved",
    "reason": reason,
    "files": sorted(changed),
    "git": os.popen("git rev-parse HEAD").read().strip(),
    "whoami": os.popen("whoami").read().strip(),
    "env": os.environ.get("IMPACTSHOP_ENV", "")
}
with open(event_path, "a", encoding="utf-8") as fh:
    fh.write(json.dumps(event, ensure_ascii=False) + "\n")
print(f"✅ Hash frissítve: {hash_path}")
print(f"🧾 Audit log: {event_path}")
PY

  python3 - "$HASH_PATH" <<'PY'
import hashlib
import os
import sys
hash_path = sys.argv[1]
checksum_path = os.path.join(os.path.dirname(hash_path), "impactshop-guard-hashes.sha256")
checksum = hashlib.sha256(open(hash_path, "rb").read()).hexdigest()
with open(checksum_path, "w", encoding="utf-8") as fh:
    fh.write(f"{checksum}  {hash_path}\n")
print(f"✅ Checksum frissítve: {checksum_path}")
PY

  if [[ "$AUTO_COMMIT" == "1" ]]; then
    git add "$HASH_PATH" || true
    git commit -m "guard: approve hash update" || true
  fi
fi

if [[ "${goto_finalize:-0}" -eq 0 ]]; then
  if [[ "$changed_count" -gt 0 ]]; then
    export IMPACTSHOP_PROTECTED_DEPLOY="${IMPACTSHOP_PROTECTED_DEPLOY:-1}"
    export IMPACTSHOP_POST_DEPLOY_UI_SMOKE="${IMPACTSHOP_POST_DEPLOY_UI_SMOKE:-1}"
    export IMPACTSHOP_POST_DEPLOY_ELEMENTOR_AUDIT="${IMPACTSHOP_POST_DEPLOY_ELEMENTOR_AUDIT:-1}"
  else
    export IMPACTSHOP_PROTECTED_DEPLOY="${IMPACTSHOP_PROTECTED_DEPLOY:-0}"
  fi
  "${ROOT_DIR}/bin/deploy-wpcontent-map.sh" ${ARGS+"${ARGS[@]}"}
  deploy_status=$?
fi

python3 - "$CONFIG_PATH" "$SNAPSHOT_DIR" <<'PY'
import json
import os
import shutil
import sys
from datetime import datetime, timedelta, timezone

cfg_path, snap_dir = sys.argv[1], sys.argv[2]
if not os.path.isdir(snap_dir):
    sys.exit(0)
with open(cfg_path, "r", encoding="utf-8") as fh:
    cfg = json.load(fh)
retention_days = int(cfg.get("snapshot", {}).get("retention_days", 30))
max_snaps = int(cfg.get("snapshot", {}).get("max_snapshots", 50))
now = datetime.now(timezone.utc)
cutoff = now - timedelta(days=retention_days)

entries = []
for name in os.listdir(snap_dir):
    path = os.path.join(snap_dir, name)
    if not os.path.isdir(path):
        continue
    mtime = datetime.fromtimestamp(os.path.getmtime(path), tz=timezone.utc)
    entries.append((name, path, mtime))

for name, path, mtime in entries:
    if mtime < cutoff:
        shutil.rmtree(path, ignore_errors=True)

entries = [(n, p, datetime.fromtimestamp(os.path.getmtime(p), tz=timezone.utc)) for n, p, _ in entries if os.path.isdir(p)]
entries.sort(key=lambda x: x[2])
while len(entries) > max_snaps:
    name, path, _ = entries.pop(0)
    shutil.rmtree(path, ignore_errors=True)
PY

latest_snap="$(ls -1 "$SNAPSHOT_DIR" 2>/dev/null | tail -n 1 || true)"
if [[ -n "$latest_snap" ]]; then
  rollback_script="${ROOT_DIR}/bin/impactshop-guard-rollback.sh"
  if [[ -x "$rollback_script" ]]; then
    echo "🧰 Gyors visszaállítás: bin/impactshop-guard-rollback.sh ${latest_snap}"
  else
    echo "📦 Lokális source snapshot elkészült: ${latest_snap}"
    echo "🛑 Remote runtime rollback nem elérhető; valós production írás továbbra is tiltott."
  fi
fi

exit "${deploy_status:-0}"
