#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_PATH="${ROOT_DIR}/docs/impactshop-guard-config.json"
HASH_PATH="${ROOT_DIR}/docs/impactshop-guard-hashes.json"
SNAPSHOT_DIR_DEFAULT="${ROOT_DIR}/.codex/guard-snapshots"

usage() {
  echo "Használat: $0 [--list] [snapshot-id]"
  echo "  --list: listázza a snapshotokat"
  echo "  snapshot-id: pl. deploy-20260121-143000"
}

if [[ ! -f "$CONFIG_PATH" ]]; then
  echo "❌ Guard konfiguráció hiányzik: $CONFIG_PATH" >&2
  exit 1
fi

SNAPSHOT_DIR="$(python3 - <<'PY'
import json
cfg = json.loads(open("docs/impactshop-guard-config.json","r").read())
print(cfg.get("snapshot", {}).get("storage_path", ""))
PY
)"

if [[ -z "$SNAPSHOT_DIR" ]]; then
  SNAPSHOT_DIR="$SNAPSHOT_DIR_DEFAULT"
fi

if [[ "${1:-}" == "--list" ]]; then
  ls -1 "$SNAPSHOT_DIR" 2>/dev/null || true
  exit 0
fi

SNAP_ID="${1:-}"
if [[ -z "$SNAP_ID" ]]; then
  usage
  exit 1
fi

SNAPSHOT_PATH="${SNAPSHOT_DIR}/${SNAP_ID}"
if [[ ! -d "$SNAPSHOT_PATH" ]]; then
  echo "❌ Snapshot nem található: $SNAPSHOT_PATH" >&2
  exit 1
fi

python3 - "$CONFIG_PATH" "$HASH_PATH" "$SNAPSHOT_PATH" <<'PY'
import json
import os
import shutil
import sys

cfg_path, hash_path, snap_path = sys.argv[1], sys.argv[2], sys.argv[3]
root = os.path.abspath(os.path.join(os.path.dirname(cfg_path), ".."))
with open(cfg_path, "r", encoding="utf-8") as fh:
    cfg = json.load(fh)

files = [p.strip() for p in cfg.get("protected_files", []) if p.strip()]
restored = 0
for rel in files:
    src = os.path.join(snap_path, rel)
    dst = os.path.join(root, rel)
    if os.path.isfile(src):
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copy2(src, dst)
        restored += 1

hash_snap = os.path.join(snap_path, os.path.basename(hash_path))
if os.path.isfile(hash_snap):
    shutil.copy2(hash_snap, hash_path)
checksum_snap = os.path.join(snap_path, "impactshop-guard-hashes.sha256")
if os.path.isfile(checksum_snap):
    shutil.copy2(checksum_snap, os.path.join(os.path.dirname(hash_path), "impactshop-guard-hashes.sha256"))

print(f"✅ Visszaállított fájlok: {restored}")
print(f"✅ Hash manifest visszaállítva, ha elérhető: {os.path.basename(hash_path)}")
PY
