#!/usr/bin/env bash
set -euo pipefail

PROFILE="staging"
TIMEOUT_SSH=10
TIMEOUT_WPCLI=30

# timeout bin discovery (supports macOS coreutils `gtimeout`)
TIMEOUT_BIN=""
if command -v timeout >/dev/null 2>&1; then
  TIMEOUT_BIN="timeout"
elif command -v gtimeout >/dev/null 2>&1; then
  TIMEOUT_BIN="gtimeout"
fi

run_with_timeout() {
  local seconds="$1"
  shift
  if [[ -n "$TIMEOUT_BIN" ]]; then
    "$TIMEOUT_BIN" "$seconds" "$@"
  else
    "$@"
  fi
}

# Opcionális argok
while [[ $# -gt 0 ]]; do
  case "$1" in
    --profile=*) PROFILE="${1#*=}"; shift ;;
    --timeout-ssh=*) TIMEOUT_SSH="${1#*=}"; shift ;;
    --timeout-wpcli=*) TIMEOUT_WPCLI="${1#*=}"; shift ;;
    *) shift ;;
  esac
done

# Kötelező env változók
REQUIRED=(SSH_HOST SSH_USER WP_PATH)
MISSING=()
for k in "${REQUIRED[@]}"; do
  v="$(printenv "$k" || true)"
  [[ -z "${v:-}" ]] && MISSING+=("$k")
done

echo "=== Deploy Profile Validation ==="
echo "Profile: $PROFILE"
echo "SSH timeout: ${TIMEOUT_SSH}s"
echo "WP-CLI timeout: ${TIMEOUT_WPCLI}s"

if [[ ${#MISSING[@]} -gt 0 ]]; then
  echo "Missing fields: ${MISSING[*]}"
  echo "Exit: 2"
  exit 2
fi

# SSH reachability (read-only) — locale zaj elkerülésére LANG=C
if ! run_with_timeout "${TIMEOUT_SSH}" ssh -o BatchMode=yes -o ConnectTimeout="${TIMEOUT_SSH}" \
  "${SSH_USER}@${SSH_HOST}" 'LANG=C LC_ALL=C echo connected' >/dev/null 2>&1; then
  echo "❌ SSH reachability: FAIL"
  echo "Exit: 1"
  exit 1
else
  echo "✅ SSH reachability: PASS"
fi

# WP-CLI availability (read-only) — szintén LANG=C
if run_with_timeout "${TIMEOUT_WPCLI}" ssh -o BatchMode=yes "${SSH_USER}@${SSH_HOST}" \
  "LANG=C LC_ALL=C command -v wp >/dev/null && wp --path='${WP_PATH}' --version" >/dev/null 2>&1; then
  echo "✅ WP-CLI availability: PASS"
  echo "Exit: 0"
  exit 0
else
  echo "⚠️ WP-CLI availability: FAIL"
  echo "Exit: 2"
  exit 2
fi
