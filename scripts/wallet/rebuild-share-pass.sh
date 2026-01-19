#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: scripts/wallet/rebuild-share-pass.sh <slug>

Rebuilds a static ImpactShop share Wallet pass from the base template
(`impactshop-share-card-base-bator.pkpass`), a slughoz tartozó REST
válasz alapján, majd előállítja a `wallet-pass-downloads/impactshop-share-card-<slug>-<ts>.pkpass`
csomagot és frissíti a canonical fájlt is. A szkript NEM deployol,
csak lokálisan gyártja le a PKPASS-t.
USAGE
}

[[ ${1:-} ]] || { usage >&2; exit 1; }
SLUG="$1"
SYSTEM_MESSAGE="${2:-}"
shift || true

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMPLATE_PATH="${IMPACTSHOP_SHARE_PASS_TEMPLATE:-$ROOT_DIR/../wallet-pass-downloads/impactshop-share-card-template.pkpass}"
DEST_DIR="$ROOT_DIR/../wallet-pass-downloads"
CERT_DIR="$ROOT_DIR/../wallet-pass-downloads/tmp_rebuild"
WWDR="$CERT_DIR/AppleWWDRCAG4.pem"
CERT="$CERT_DIR/cert.pem"
KEY="$CERT_DIR/key.pem"

for tool in curl jq unzip zip openssl python3; do
  command -v "$tool" >/dev/null 2>&1 || { echo "Missing required tool: $tool" >&2; exit 1; }
done

[[ -f "$TEMPLATE_PATH" ]] || { echo "Template pkpass not found: $TEMPLATE_PATH" >&2; exit 1; }
[[ -f "$WWDR" && -f "$CERT" && -f "$KEY" ]] || { echo "Missing signing cert/key in $CERT_DIR" >&2; exit 1; }

API_URL="https://app.sharity.hu/wp-json/impact/v1/ngo-card/${SLUG}"
API_JSON=$(curl -sSf "$API_URL") || { echo "API fetch failed for $SLUG" >&2; exit 1; }

TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT
cp "$TEMPLATE_PATH" "$TMP_DIR/base.pkpass"
unzip -q "$TMP_DIR/base.pkpass" -d "$TMP_DIR/unpack"

PASS_PATH="$TMP_DIR/unpack/pass.json" API_JSON="$API_JSON" SLUG="$SLUG" SYSTEM_MESSAGE="$SYSTEM_MESSAGE" python3 scripts/wallet/rebuild_share_pass.py

python3 - "$TMP_DIR" <<'PY'
import hashlib, json, os, sys
from pathlib import Path
base = Path(sys.argv[1]) / 'unpack'
files = ['icon.png','icon@2x.png','icon@3x.png','logo.png','logo@2x.png','logo@3x.png','pass.json']
manifest = {}
for name in files:
    path = base / name
    if not path.exists():
        continue
    with path.open('rb') as f:
        manifest[name] = hashlib.sha1(f.read()).hexdigest()
if 'pass.json' not in manifest:
    print('Missing pass.json after rebuild', file=sys.stderr)
    sys.exit(1)
with (base / 'manifest.json').open('w') as f:
    json.dump(manifest, f, separators=(',', ':'))
PY

openssl smime -binary -sign -certfile "$WWDR" -signer "$CERT" -inkey "$KEY" -in "$TMP_DIR/unpack/manifest.json" -out "$TMP_DIR/unpack/signature" -outform DER -noattr >/dev/null 2>&1

OUT_BASENAME="impactshop-share-card-${SLUG}"
TS=$(date -u +%Y%m%dT%H%M%S)
ZIP_PATH="$TMP_DIR/${OUT_BASENAME}.pkpass"
( cd "$TMP_DIR/unpack" && zip -qr "$ZIP_PATH" . )

mkdir -p "$DEST_DIR"
cp "$ZIP_PATH" "$DEST_DIR/${OUT_BASENAME}-${TS}.pkpass"
cp "$ZIP_PATH" "$DEST_DIR/${OUT_BASENAME}.pkpass"

trap - EXIT
rm -rf "$TMP_DIR"

echo "Generated $DEST_DIR/${OUT_BASENAME}-${TS}.pkpass"
echo "Set canonical: $DEST_DIR/${OUT_BASENAME}.pkpass"
echo "Next: deploy via HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/${OUT_BASENAME}.pkpass"
