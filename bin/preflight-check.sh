#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-.deploy.staging.env}"
if [[ ! -f "${ENV_FILE}" ]]; then
  echo "❌ Preflight config missing: ${ENV_FILE}" >&2
  exit 1
fi

# Resolve absolute path so sourced file works regardless of cwd
if [[ "${ENV_FILE}" != /* ]]; then
  ENV_FILE="$(pwd)/${ENV_FILE}"
fi

# shellcheck disable=SC1090
source "${ENV_FILE}"

BASE_URL="${PREFLIGHT_BASE_URL:-}"
if [[ -z "${BASE_URL}" ]]; then
  echo "⚠️  Preflight skipped: PREFLIGHT_BASE_URL not set in ${ENV_FILE}" >&2
  exit 0
fi

# Collect endpoints either from array or space-separated string
endpoints=()
if declare -p PREFLIGHT_ENDPOINTS 2>/dev/null | grep -q 'declare \-a'; then
  endpoints+=("${PREFLIGHT_ENDPOINTS[@]}")
elif [[ -n "${PREFLIGHT_ENDPOINTS:-}" ]]; then
  # shellcheck disable=SC2206
  endpoints=(${PREFLIGHT_ENDPOINTS})
else
  endpoints=(
    "/?rest_route=/impact/v1/totals"
    "/?rest_route=/impact/v1/ticker"
    "/?rest_route=/impact/v1/leaderboard"
    "/?rest_route=/impact/v1/report"
  )
fi

if [[ ${#endpoints[@]} -eq 0 ]]; then
  echo "⚠️  Preflight skipped: no endpoints configured" >&2
  exit 0
fi

TIMEOUT="${PREFLIGHT_TIMEOUT:-10}"
USER_AGENT="ImpactShopPreflight/1.0"
FAIL_COUNT=0
TOTAL_COUNT=0

printf '🧪 Preflight: %s (timeout %ss)\n' "${BASE_URL}" "${TIMEOUT}"

for endpoint in "${endpoints[@]}"; do
  endpoint="${endpoint//\"/}"  # strip accidental quotes
  endpoint="$(echo "${endpoint}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
  [[ -z "${endpoint}" ]] && continue
  TOTAL_COUNT=$((TOTAL_COUNT + 1))

  if [[ "${endpoint}" != http* ]]; then
    endpoint="${endpoint#/}"
    url="${BASE_URL%/}/${endpoint}"
  else
    url="${endpoint}"
  fi

  tmp="$(mktemp)"

  printf '  → GET %s ' "${url}"

  # Use curl to capture HTTP status and total time
  if ! result=$(curl -sS -L --max-redirs 5 -o "${tmp}" -w '%{http_code} %{time_total}' \
                 --max-time "${TIMEOUT}" \
                 -H "User-Agent: ${USER_AGENT}" \
                 -H 'Accept: application/json, */*' \
                 "${url}" 2>&1); then
    echo "❌ curl error"
    echo "    ↳ ${result}" | sed 's/^/    /'
    FAIL_COUNT=$((FAIL_COUNT + 1))
    rm -f "${tmp}"
    continue
  fi

  status="${result%% *}"
  latency="${result##* }"
  ms=$(awk -v t="${latency}" 'BEGIN{printf "%.0f", t*1000}')

  body_preview="$(<"${tmp}")"
  body_preview="${body_preview//$'\r'/ }"
  body_preview="${body_preview//$'\n'/ }"
  for _ in 1 2 3 4 5; do
    body_preview="${body_preview//  / }"
  done
  body_preview=$(printf '%.220s' "${body_preview}")
  rm -f "${tmp}"

  if [[ "${status}" == "200" ]]; then
    printf '✅ (%sms)\n' "${ms}"
  else
    printf '❌ (status %s, %sms)\n' "${status}" "${ms}"
    [[ -n "${body_preview}" ]] && printf '    ↳ %s\n' "${body_preview}"
    FAIL_COUNT=$((FAIL_COUNT + 1))
  fi

done

if [[ ${TOTAL_COUNT} -eq 0 ]]; then
  echo "⚠️  Preflight finished: no endpoints processed" >&2
  exit 0
fi

if [[ ${FAIL_COUNT} -gt 0 ]]; then
  printf '❌ Preflight failed (%d/%d endpoints)\n' "${FAIL_COUNT}" "${TOTAL_COUNT}" >&2
  exit 1
fi

printf '✅ Preflight OK (%d endpoint%s)\n' "${TOTAL_COUNT}" "$([[ ${TOTAL_COUNT} -eq 1 ]] && echo '' || echo 's')"
exit 0
