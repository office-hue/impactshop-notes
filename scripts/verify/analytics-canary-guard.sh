#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${ANALYTICS_WP_REST_BASE:-}" && -f ".staging_env" ]]; then
  set -a
  source .staging_env
  set +a
fi

if [[ -z "${ANALYTICS_WP_REST_BASE:-}" || -z "${ANALYTICS_WP_API_SECRET:-}" ]]; then
  echo "Missing ANALYTICS_WP_REST_BASE or ANALYTICS_WP_API_SECRET" >&2
  exit 2
fi

FROM_DATE="${FROM_DATE:-$(date -u -v-1d +%Y-%m-%d 2>/dev/null || date -u -d '1 day ago' +%Y-%m-%d)}"
TO_DATE="${TO_DATE:-$(date -u +%Y-%m-%d)}"
BASE="${ANALYTICS_WP_REST_BASE%/}"

ts="$(date +%s)"
payload="${ts}|${FROM_DATE}|${TO_DATE}"
sig="$(printf '%s' "$payload" | openssl dgst -sha256 -hmac "${ANALYTICS_WP_API_SECRET}" | tr -d '[:space:]')"

summary_url="${BASE}/analytics/summary?from=${FROM_DATE}&to=${TO_DATE}&impact_ts=${ts}&impact_sig=${sig}"
flags_payload="${ts}|flags"
flags_sig="$(printf '%s' "$flags_payload" | openssl dgst -sha256 -hmac "${ANALYTICS_WP_API_SECRET}" | tr -d '[:space:]')"
flags_url="${BASE}/analytics/flags?impact_ts=${ts}&impact_sig=${flags_sig}"

call_endpoint_or_skip_if_route_missing() {
  local url="$1"
  local header_sig="$2"
  local body_file
  local code

  body_file="$(mktemp)"
  code="$(curl -sS -o "$body_file" -w '%{http_code}' -H "X-Impact-Ts: ${ts}" -H "X-Impact-Signature: ${header_sig}" "$url" || true)"

  if [[ "$code" == "200" ]]; then
    rm -f "$body_file"
    return 0
  fi

  if [[ "$code" == "404" ]] && grep -q '"code":"rest_no_route"' "$body_file"; then
    echo "analytics-canary-guard SKIP: endpoint not registered (${url})"
    rm -f "$body_file"
    return 20
  fi

  echo "analytics-canary-guard FAIL: endpoint call failed (${url}) http=${code}" >&2
  head -c 300 "$body_file" >&2 || true
  echo >&2
  rm -f "$body_file"
  return 3
}

summary_rc=0
flags_rc=0
call_endpoint_or_skip_if_route_missing "${summary_url}" "${sig}" || summary_rc=$?
call_endpoint_or_skip_if_route_missing "${flags_url}" "${flags_sig}" || flags_rc=$?

if [[ "$summary_rc" -eq 20 && "$flags_rc" -eq 20 ]]; then
  echo "analytics-canary-guard SKIP: analytics routes are missing on this target"
  exit 0
fi

if [[ "$summary_rc" -ne 0 && "$summary_rc" -ne 20 ]]; then
  exit "$summary_rc"
fi

if [[ "$flags_rc" -ne 0 && "$flags_rc" -ne 20 ]]; then
  exit "$flags_rc"
fi

if [[ -n "${ANALYTICS_AI_AGENT_URL:-}" ]]; then
  health_url="${ANALYTICS_AI_AGENT_URL%/}/api/v1/analytics/health"
  if [[ -n "${ANALYTICS_AI_AGENT_KEY:-}" ]]; then
    curl -fsS -H "Authorization: Bearer ${ANALYTICS_AI_AGENT_KEY}" "${health_url}" >/dev/null
  else
    curl -fsS "${health_url}" >/dev/null
  fi
fi

echo "analytics-canary-guard OK (${FROM_DATE}..${TO_DATE})"
