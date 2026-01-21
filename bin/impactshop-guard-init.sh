#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_PATH="${ROOT_DIR}/docs/impactshop-guard-config.json"
OUTPUT_PATH="${ROOT_DIR}/docs/impactshop-guard-hashes.json"
CHECKSUM_PATH="${ROOT_DIR}/docs/impactshop-guard-hashes.sha256"

if [[ ! -f "$CONFIG_PATH" ]]; then
  echo "❌ Hiányzik a guard konfiguráció: $CONFIG_PATH" >&2
  exit 1
fi

python3 - "$CONFIG_PATH" "$OUTPUT_PATH" "$CHECKSUM_PATH" "$ROOT_DIR" <<'PY'
import json
import hashlib
import os
import sys
from datetime import datetime, timezone

config_path = sys.argv[1]
output_path = sys.argv[2]
checksum_path = sys.argv[3]
root_dir = os.path.abspath(sys.argv[4])

with open(config_path, "r", encoding="utf-8") as fh:
    config = json.load(fh)

protected = config.get("protected_files", [])
hashes = {}
missing = []

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
    hashes[rel] = digest

payload = {
    "version": config.get("version", 1),
    "generated_at": datetime.now(timezone.utc).isoformat(),
    "root": root_dir,
    "hashes": hashes,
    "missing_files": missing,
}

os.makedirs(os.path.dirname(output_path), exist_ok=True)
with open(output_path, "w", encoding="utf-8") as fh:
    json.dump(payload, fh, indent=2, ensure_ascii=False)

checksum = hashlib.sha256(open(output_path, "rb").read()).hexdigest()
with open(checksum_path, "w", encoding="utf-8") as fh:
    fh.write(f"{checksum}  {os.path.basename(output_path)}\n")

print(f"✅ Guard hash manifest létrehozva: {output_path}")
print(f"✅ Guard hash checksum: {checksum_path}")
if missing:
    print("⚠️ Hiányzó védett fájlok:")
    for rel in missing:
        print(f"  - {rel}")
PY
