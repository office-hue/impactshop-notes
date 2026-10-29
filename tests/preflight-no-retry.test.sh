#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir "$TMP/bin"
cat > "$TMP/profile.env" <<'ENV'
PREFLIGHT_BASE_URL='https://preflight.invalid'
PREFLIGHT_RETRY=2
PREFLIGHT_ENDPOINTS=('/check')
ENV
cat > "$TMP/bin/curl" <<'MOCK'
#!/usr/bin/env bash
printf 'call\n' >> "$PREFLIGHT_TEST_CALLS"
while [[ $# -gt 0 ]]; do
  if [[ "$1" == '-o' ]]; then printf '{"error":"rate_limited"}' > "$2"; shift; fi
  shift
done
printf '429|0.001|24|https://preflight.invalid/check'
MOCK
# Keep the default-retry behavior check instantaneous and offline.
printf '#!/usr/bin/env bash\nexit 0\n' > "$TMP/bin/sleep"
chmod +x "$TMP/bin/curl" "$TMP/bin/sleep"
export PREFLIGHT_TEST_CALLS="$TMP/calls"
export PATH="$TMP/bin:$PATH"
if PREFLIGHT_NO_RETRY=1 bash "$ROOT/bin/preflight-check.sh" "$TMP/profile.env" > "$TMP/no-retry.log" 2>&1; then
  echo '429 must fail preflight' >&2; exit 1
fi
[[ "$(wc -l < "$TMP/calls" | tr -d ' ')" == 1 ]]
! grep -q '↻ retry' "$TMP/no-retry.log"
: > "$TMP/calls"
if PREFLIGHT_NO_RETRY=0 bash "$ROOT/bin/preflight-check.sh" "$TMP/profile.env" > "$TMP/default.log" 2>&1; then
  echo '429 must fail default preflight' >&2; exit 1
fi
[[ "$(wc -l < "$TMP/calls" | tr -d ' ')" == 3 ]]
grep -q 'retry 2/2' "$TMP/default.log"
echo 'preflight invocation no-retry and profile defaults: PASS'
