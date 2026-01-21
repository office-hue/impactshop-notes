#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
GUARD_NAME="impactshop-bastya-guard"
LOG_DIR="${ROOT}/.codex/logs"
CONFIG_PATH="${ROOT}/docs/impactshop-guard-config.json"
HASH_PATH="${ROOT}/docs/impactshop-guard-hashes.json"
CHECKSUM_PATH="${ROOT}/docs/impactshop-guard-hashes.sha256"

source "${ROOT}/.codex/scripts/lib/guard-common.sh"

guard_heartbeat_ping "$GUARD_NAME"

if [[ ! -f "$CONFIG_PATH" ]]; then
  guard_result "$GUARD_NAME" "FAIL" "Guard config hiányzik: ${CONFIG_PATH}"
  exit 1
fi

if [[ ! -f "$HASH_PATH" ]]; then
  guard_result "$GUARD_NAME" "WARN" "Guard hash manifest hiányzik: ${HASH_PATH} (futtasd: bin/impactshop-guard-init.sh)"
  exit 0
fi

if [[ -f "$CHECKSUM_PATH" ]]; then
  expected="$(awk '{print $1}' "$CHECKSUM_PATH" | tr -d '\r\n')"
  actual="$(python3 - <<'PY'
import hashlib
print(hashlib.sha256(open("docs/impactshop-guard-hashes.json","rb").read()).hexdigest())
PY
)"
  if [[ -n "$expected" && "$expected" != "$actual" ]]; then
    guard_result "$GUARD_NAME" "FAIL" "Guard hash checksum eltérés: ${CHECKSUM_PATH}"
    exit 1
  fi
else
  guard_result "$GUARD_NAME" "WARN" "Guard checksum hiányzik: ${CHECKSUM_PATH}"
fi

guard_output="$(python3 - "$CONFIG_PATH" "$HASH_PATH" <<'PY'
import json
import hashlib
import os
import sys

config_path = sys.argv[1]
hash_path = sys.argv[2]
root_dir = os.path.abspath(os.path.join(os.path.dirname(config_path), ".."))

with open(config_path, "r", encoding="utf-8") as fh:
    config = json.load(fh)
with open(hash_path, "r", encoding="utf-8") as fh:
    manifest = json.load(fh)

protected = config.get("protected_files", [])
hashes = manifest.get("hashes", {})

missing = []
changed = []

for rel in protected:
    rel = rel.strip()
    if not rel:
        continue
    abs_path = os.path.join(root_dir, rel)
    if not os.path.isfile(abs_path):
        missing.append(rel)
        continue
    with open(abs_path, "rb") as fh:
        data = fh.read()
    digest = hashlib.sha256(data).hexdigest()
    known = hashes.get(rel)
    if not known:
        changed.append(f"{rel} (no hash)")
    elif digest != known:
        changed.append(rel)

status = "OK"
message = "Guard hash rendben"
if missing or changed:
    status = "FAIL"
    parts = []
    if missing:
        parts.append(f"Hiányzó fájlok: {', '.join(missing)}")
    if changed:
        parts.append(f"Módosult fájlok: {', '.join(changed)}")
    message = " | ".join(parts)

print(status)
print(message)
PY
)"

status="$(printf '%s\n' "$guard_output" | sed -n '1p')"
message="$(printf '%s\n' "$guard_output" | sed -n '2p')"

if [[ -z "$status" ]]; then
  guard_result "$GUARD_NAME" "WARN" "Bástya guard nem adott státuszt"
  exit 0
fi

guard_result "$GUARD_NAME" "$status" "$message"
if [[ "$status" == "FAIL" ]]; then
  exit 1
fi
