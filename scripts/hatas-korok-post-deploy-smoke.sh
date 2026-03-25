#!/usr/bin/env bash
set -euo pipefail

HOST="${1:-https://app.sharity.hu}"
BASE_URL="${HOST%/}"
ROUTE_URL="${BASE_URL}/hatas-korok"
AUTH_URL="${BASE_URL}/wp-json/impact/v1/auth/status"
CIRCLES_URL="${BASE_URL}/wp-json/impact/v1/circles?page=1"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

HDR_ROUTE="$TMP_DIR/route-headers.txt"
BODY_ROUTE="$TMP_DIR/route-body.html"
BODY_AUTH="$TMP_DIR/auth.json"
BODY_CIRCLES="$TMP_DIR/circles.json"

fail() {
  echo "[FAIL] $*" >&2
  exit 1
}

pass() {
  echo "[PASS] $*"
}

info() {
  echo "[INFO] $*"
}

check_http_200() {
  local url="$1"
  local out="$2"
  curl -fsS -D "$out" -o /dev/null "$url"
  local status
  status="$(awk 'toupper($1) ~ /^HTTP/ { code=$2 } END { print code }' "$out")"
  [[ "$status" == "200" ]] || fail "Expected HTTP 200 from $url, got ${status:-unknown}"
}

info "Route header check: $ROUTE_URL"
check_http_200 "$ROUTE_URL" "$HDR_ROUTE"
pass "Route returns HTTP 200"

info "Route HTML shell check"
curl -fsS "$ROUTE_URL" -o "$BODY_ROUTE"
node - <<'NODE' "$BODY_ROUTE"
const fs = require('fs');
const html = fs.readFileSync(process.argv[2], 'utf8');
const checks = {
  title: /Hatás Körök — Impact Community/.test(html),
  contentRoot: /id="ic-content"/.test(html),
  bootstrap: /window\.ImpactCommunity/.test(html),
  cta: /Csatlakozz NGO vagy települési közösségekhez/.test(html),
};
const failed = Object.entries(checks).filter(([, ok]) => !ok).map(([key]) => key);
if (failed.length) {
  console.error(JSON.stringify({ ok: false, failed }, null, 2));
  process.exit(1);
}
console.log(JSON.stringify({ ok: true, checks }, null, 2));
NODE
pass "Route HTML shell contains expected bootstrap markers"

info "Auth status API check: $AUTH_URL"
curl -fsS "$AUTH_URL" -o "$BODY_AUTH"
node - <<'NODE' "$BODY_AUTH"
const fs = require('fs');
const payload = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
if (typeof payload.authenticated !== 'boolean') {
  throw new Error('auth payload missing boolean authenticated');
}
if (typeof payload.nonce !== 'string' || payload.nonce.length < 8) {
  throw new Error('auth payload missing nonce');
}
console.log(JSON.stringify({
  ok: true,
  authenticated: payload.authenticated,
  nonceLength: payload.nonce.length,
}, null, 2));
NODE
pass "Auth status API returned expected shape"

info "Circles API check: $CIRCLES_URL"
curl -fsS "$CIRCLES_URL" -o "$BODY_CIRCLES"
node - <<'NODE' "$BODY_CIRCLES"
const fs = require('fs');
const payload = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
if (!Array.isArray(payload.circles) || payload.circles.length === 0) {
  throw new Error('circles payload missing non-empty circles array');
}
if (typeof payload.total !== 'number' || payload.total < payload.circles.length) {
  throw new Error('circles payload has invalid total');
}
const first = payload.circles[0] || {};
if (typeof first.name !== 'string' || first.name.length === 0) {
  throw new Error('first circle missing name');
}
console.log(JSON.stringify({
  ok: true,
  total: payload.total,
  page: payload.page,
  per_page: payload.per_page,
  first_circle: first.name,
  returned: payload.circles.length,
}, null, 2));
NODE
pass "Circles API returned expected payload"

echo
echo "Hatás Körök post-deploy smoke: OK"
