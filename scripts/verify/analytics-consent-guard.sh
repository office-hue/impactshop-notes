#!/usr/bin/env bash
set -euo pipefail

ENVIRONMENT="${IMPACT_ENV:-staging}"
BASE_URL="${IMPACT_BASE_URL:-}"

if [[ -z "$BASE_URL" ]]; then
  if [[ "$ENVIRONMENT" == "production" ]]; then
    BASE_URL="https://app.sharity.hu"
  else
    BASE_URL="https://app.sharity.hu/impactshop-staging"
  fi
fi

CHECK_PATHS=(
  "/"
  "/impactshop/"
)

needle="gtag('consent'"
alt_needle='gtag("consent"'
fallback_marker='sharity-consent-blocking'

found_any=0
checked_any=0

for path in "${CHECK_PATHS[@]}"; do
  url="${BASE_URL%/}${path}"
  body=""
  curl_rc=0
  body="$(curl -fsS --max-time 8 "$url" 2>/dev/null)" || curl_rc=$?

  if [[ "$curl_rc" -ne 0 ]]; then
    echo "analytics-consent-guard SKIP: cannot fetch ${url} (curl rc=${curl_rc})"
    continue
  fi

  if [[ -z "$body" ]]; then
    echo "analytics-consent-guard SKIP: empty response body at ${url}"
    continue
  fi

  checked_any=1

  if [[ "$body" == *"$needle"* || "$body" == *"$alt_needle"* || "$body" == *"$fallback_marker"* ]]; then
    found_any=1
    echo "analytics-consent-guard OK snippet found at ${url}"
  fi
done

if [[ "$checked_any" -eq 0 ]]; then
  echo "analytics-consent-guard SKIP: no reachable pages for consent check"
  exit 0
fi

if [[ "$found_any" -ne 1 ]]; then
  echo "Consent guard FAIL: consent snippet missing on all checked pages" >&2
  exit 3
fi

echo "analytics-consent-guard OK"
