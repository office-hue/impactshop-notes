#!/usr/bin/env bash
# ImpactShop REST preflight checker with latency/content guards
set -euo pipefail

ENV_FILE="${1:-.deploy.staging.env}"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "❌ Preflight config missing: $ENV_FILE" >&2
  exit 1
fi

if [[ "$ENV_FILE" != /* ]]; then
  ENV_FILE="$(pwd)/$ENV_FILE"
fi

# shellcheck disable=SC1090
source "$ENV_FILE"

BASE_URL="${PREFLIGHT_BASE_URL:-}"
if [[ -z "$BASE_URL" ]]; then
  echo "⚠️  Preflight skipped: PREFLIGHT_BASE_URL not set in $ENV_FILE" >&2
  exit 0
fi

# Thresholds and retry policy
LAT_WARN="${PREFLIGHT_LATENCY_WARN:-0}"
LAT_FAIL="${PREFLIGHT_LATENCY_FAIL:-0}"
LAT_WARN_MS=$(awk -v t="$LAT_WARN" 'BEGIN{printf "%.0f", t*1000}')
LAT_FAIL_MS=$(awk -v t="$LAT_FAIL" 'BEGIN{printf "%.0f", t*1000}')
MAX_RETRY="${PREFLIGHT_RETRY:-0}"
[[ "$MAX_RETRY" =~ ^[0-9]+$ ]] || MAX_RETRY=0
MIN_BYTES_WARN="${PREFLIGHT_MIN_BYTES:-0}"
[[ "$MIN_BYTES_WARN" =~ ^[0-9]+$ ]] || MIN_BYTES_WARN=0
MIN_BYTES_FAIL="${PREFLIGHT_MIN_BYTES_FAIL:-0}"
[[ "$MIN_BYTES_FAIL" =~ ^[0-9]+$ ]] || MIN_BYTES_FAIL=0
EXPECT_REDIRECT="${PREFLIGHT_EXPECT_REDIRECT:-0}"

TOTALS_KEYS_DEFAULT="${PREFLIGHT_TOTALS_KEYS:-}"
TOTALS_NUMERIC_KEYS="${PREFLIGHT_TOTALS_NUMERIC_KEYS:-}"
GENERIC_KEYS="${PREFLIGHT_KEYS_GENERIC:-}"

# Collect endpoints
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
WARN_COUNT=0
TOTAL_COUNT=0

trim() { sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//'; }

endpoint_name() {
  local path="$1"
  case "$path" in
    *"impact/v1/totals"* ) echo "totals" ;;
    *"impact/v1/ticker"* ) echo "ticker" ;;
    *"impact/v1/activity"* ) echo "activity" ;;
    *"impact/v1/leaderboard"* )
      if [[ "$path" == *"tab=ngo"* ]]; then
        echo "leaderboard(ngo)"
      elif [[ "$path" == *"tab=shop"* ]]; then
        echo "leaderboard(shop)"
      else
        echo "leaderboard"
      fi
      ;;
    * ) echo "generic" ;;
  esac
}

required_keys_for() {
  local name="$1"
  case "$name" in
    totals) echo "${PREFLIGHT_KEYS_TOTALS:-$TOTALS_KEYS_DEFAULT}" ;;
    ticker) echo "${PREFLIGHT_KEYS_TICKER:-}" ;;
    activity) echo "${PREFLIGHT_KEYS_ACTIVITY:-}" ;;
    leaderboard* ) echo "${PREFLIGHT_KEYS_LEADERBOARD:-}" ;;
    * ) echo "${GENERIC_KEYS}" ;;
  esac
}

numeric_keys_for() {
  local name="$1"
  case "$name" in
    totals) echo "${TOTALS_NUMERIC_KEYS}" ;;
    ticker) echo "${PREFLIGHT_NUMERIC_KEYS_TICKER:-}" ;;
    * ) echo "" ;;
  esac
}

min_rows_for() {
  local name="$1"
  case "$name" in
    leaderboard*) echo "${PREFLIGHT_MIN_ROWS_LEADERBOARD:-0}" ;;
    * ) echo "0" ;;
  esac
}

is_retryable() {
  local code="$1"
  [[ "$code" == 429 ]] && return 0
  [[ "$code" =~ ^5 ]] && return 0
  return 1
}

printf '🧪 Preflight: %s (timeout %ss)\n' "$BASE_URL" "$TIMEOUT"

for endpoint in "${endpoints[@]}"; do
  endpoint="$(printf '%s' "$endpoint" | trim)"
  [[ -z "$endpoint" ]] && continue
  TOTAL_COUNT=$((TOTAL_COUNT + 1))

  if [[ "$endpoint" != http* ]]; then
    endpoint="${endpoint#/}"
    url="${BASE_URL%/}/${endpoint}"
  else
    url="$endpoint"
  fi

  name="$(endpoint_name "$endpoint")"
  attempt=0
  success=0

  while [[ $attempt -le $MAX_RETRY ]]; do
    tmp="$(mktemp)"
    printf '  → (%s) %s ' "$name" "$url"

    if ! result=$(curl -sS -L --max-redirs 5 --http1.1 -o "$tmp" -w '%{http_code}|%{time_total}|%{size_download}|%{url_effective}' \
                   --max-time "$TIMEOUT" \
                   -H "User-Agent: ${USER_AGENT}" \
                   -H 'Accept: application/json, */*' \
                   "$url" 2>&1); then
      echo "❌ curl error"
      echo "    ↳ ${result}" | sed 's/^/    /'
      rm -f "$tmp"
      if [[ $attempt -lt $MAX_RETRY ]]; then
        attempt=$((attempt + 1))
        echo "    ↻ retry ${attempt}/${MAX_RETRY}"
        sleep 1
        continue
      else
        FAIL_COUNT=$((FAIL_COUNT + 1))
        break
      fi
    fi

    IFS='|' read -r status latency bytes effective_url <<< "$result"
    bytes=${bytes%.*}
    [[ -z "$bytes" ]] && bytes=0

    body="$(<"$tmp")"
    rm -f "$tmp"

    if [[ "$status" != "200" ]]; then
      echo "❌ HTTP $status (${latency}s)"
      [[ -n "$body" ]] && printf '    ↳ %s\n' "$(printf '%s' "$body" | tr '\r\n' ' ' | cut -c1-200)"
      if is_retryable "$status" && [[ $attempt -lt $MAX_RETRY ]]; then
        attempt=$((attempt + 1))
        echo "    ↻ retry ${attempt}/${MAX_RETRY}"
        sleep 1
        continue
      else
        FAIL_COUNT=$((FAIL_COUNT + 1))
        break
      fi
    fi

    ms=$(awk -v t="$latency" 'BEGIN{printf "%.0f", t*1000}')
    warn_latency=0
    fail_latency=0
    if (( LAT_FAIL_MS > 0 && ms > LAT_FAIL_MS )); then
      fail_latency=1
    elif (( LAT_WARN_MS > 0 && ms > LAT_WARN_MS )); then
      warn_latency=1
    fi

    if (( MIN_BYTES_FAIL > 0 && bytes < MIN_BYTES_FAIL )); then
      echo "❌ small response (${bytes}B < ${MIN_BYTES_FAIL}B)"
      FAIL_COUNT=$((FAIL_COUNT + 1))
      break
    fi
    if (( MIN_BYTES_WARN > 0 && bytes < MIN_BYTES_WARN )); then
      echo "⚠️  small response (${bytes}B < ${MIN_BYTES_WARN}B)"
      WARN_COUNT=$((WARN_COUNT + 1))
    fi

    if ! jq -e . >/dev/null 2>&1 <<< "$body"; then
      echo "❌ invalid JSON"
      FAIL_COUNT=$((FAIL_COUNT + 1))
      break
    fi

    keys_required="$(required_keys_for "$name")"
    if [[ -n "$keys_required" ]]; then
      missing=()
      for key in $keys_required; do
        key="$(printf '%s' "$key" | trim)"
        [[ -z "$key" ]] && continue
        if ! jq -e --arg key "$key" 'paths as $p | select($p[-1] == $key) | true' <<< "$body" >/dev/null 2>&1; then
          missing+=("$key")
        fi
      done
      if [[ ${#missing[@]} -gt 0 ]]; then
        echo "❌ missing keys: ${missing[*]}"
        FAIL_COUNT=$((FAIL_COUNT + 1))
        break
      fi
    fi

    numeric_keys="$(numeric_keys_for "$name")"
    if [[ -n "$numeric_keys" ]]; then
      for key in $numeric_keys; do
        key="$(printf '%s' "$key" | trim)"
        [[ -z "$key" ]] && continue
        if ! jq -e --arg key "$key" 'paths as $p | select($p[-1] == $key) | getpath($p) | type == "number"' <<< "$body" >/dev/null 2>&1; then
          echo "⚠️  key $key not numeric"
          WARN_COUNT=$((WARN_COUNT + 1))
        fi
      done
    fi

    min_rows="$(min_rows_for "$name")"
    if [[ "$min_rows" =~ ^[0-9]+$ ]] && (( min_rows > 0 )); then
      rows=$(jq -r 'if type=="array" then length elif has("rows") and (.rows|type=="array") then (.rows|length) else 0 end' <<< "$body" 2>/dev/null || echo 0)
      if (( rows < min_rows )); then
        echo "⚠️  few rows (${rows} < ${min_rows})"
        WARN_COUNT=$((WARN_COUNT + 1))
      fi
    fi

    if (( fail_latency )); then
      echo "❌ slow response (${ms}ms > ${LAT_FAIL_MS}ms)"
      FAIL_COUNT=$((FAIL_COUNT + 1))
      break
    fi
    if (( warn_latency )); then
      echo "⚠️  slow response (${ms}ms > ${LAT_WARN_MS}ms)"
      WARN_COUNT=$((WARN_COUNT + 1))
    else
      echo "✅ ${ms}ms"
    fi

    success=1
    break
  done

  if [[ $success -eq 0 ]]; then
    continue
  fi

done

if [[ "$EXPECT_REDIRECT" == "1" ]]; then
  home_result=$(curl -sS -I -o /dev/null -w '%{http_code}|%{time_total}' "$BASE_URL" 2>/dev/null || echo "000|0")
  IFS='|' read -r home_code home_latency <<< "$home_result"
  if [[ "$home_code" == "301" || "$home_code" == "302" ]]; then
    echo "ℹ️  Homepage redirect OK (HTTP $home_code)"
  elif [[ "$home_code" == "200" ]]; then
    echo "ℹ️  Homepage responded 200"
  else
    echo "⚠️  Homepage HEAD unexpected status: $home_code"
    WARN_COUNT=$((WARN_COUNT + 1))
  fi
fi

if [[ $FAIL_COUNT -gt 0 ]]; then
  printf '❌ Preflight failed (%d endpoints)\n' "$FAIL_COUNT" >&2
  exit 1
fi

if [[ $WARN_COUNT -gt 0 ]]; then
  printf '⚠️  Preflight completed with warnings (%d)\n' "$WARN_COUNT"
else
  printf '✅ Preflight OK (%d endpoint%s)\n' "$TOTAL_COUNT" "$([[ $TOTAL_COUNT -eq 1 ]] && echo '' || echo 's')"
fi
exit 0
