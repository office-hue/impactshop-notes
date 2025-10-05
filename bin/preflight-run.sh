#!/usr/bin/env bash
# Wrap preflight-check.sh, capture output, log summary, and update latency chart
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
INPUT="${1:-staging}"
LABEL=""
ENV_FILE=""

case "$INPUT" in
  ""|staging|stage|stg)
    LABEL="staging"
    ENV_FILE="$ROOT/.deploy.staging.env"
    ;;
  production|prod)
    LABEL="production"
    ENV_FILE="$ROOT/.deploy.production.env"
    ;;
  --env=*)
    LABEL="$(basename "${INPUT#--env=}")"
    ENV_FILE="${INPUT#--env=}"
    ;;
  *)
    LABEL="$(basename "$INPUT")"
    ENV_FILE="$INPUT"
    ;;
 esac

[[ -f "$ENV_FILE" ]] || { echo "❌ Missing env file: $ENV_FILE" >&2; exit 2; }

# shellcheck disable=SC1090
source "$ENV_FILE"

STAMP="$(date +%Y%m%d-%H%M%S)"
OUT_DIR="$ROOT/.codex"
OUT_FILE="$OUT_DIR/_preflight_${LABEL}_${STAMP}.out"
LOG_FILE="$OUT_DIR/deploy-log.txt"
CHART_SCRIPT="$ROOT/bin/deploy-latency-chart.py"

mkdir -p "$OUT_DIR"

if [[ ! -x "$ROOT/bin/preflight-check.sh" ]]; then
  echo "❌ Missing script: bin/preflight-check.sh" >&2
  exit 2
fi

set +e
"$ROOT/bin/preflight-check.sh" "$ENV_FILE" | tee "$OUT_FILE"
RC=${PIPESTATUS[0]}
set -e

read warns oks rest_fail maxlat <<< "$(python3 - <<'PY' "$OUT_FILE"
import sys, re
path = sys.argv[1]
warns = oks = rest_fail = 0
latmax = 0.0
try:
    with open(path) as fh:
        for line in fh:
            if '⚠️' in line or 'WARN' in line:
                warns += 1
            if '✅' in line:
                oks += 1
            if '❌ REST' in line or ('❌' in line and 'HTTP' in line):
                rest_fail += 1
            for match in re.findall(r'([0-9]+\.[0-9]+)s', line):
                latmax = max(latmax, float(match))
            for match in re.findall(r'([0-9]+)ms', line):
                latmax = max(latmax, float(match) / 1000.0)
except FileNotFoundError:
    pass
print(warns, oks, rest_fail, f"{latmax:.3f}")
PY
)"

status="PASS"
if [[ $RC -ne 0 ]]; then
  status="FAIL"
elif [[ ${warns:-0} -gt 0 ]]; then
  status="PASS_WITH_WARN"
fi

if [[ ! -s "$LOG_FILE" ]]; then
  echo "timestamp,env,status,warns,max_latency_s,endpoints_ok,rest_fail_count,exit_code" > "$LOG_FILE"
fi
printf '%s,%s,%s,%s,%s,%s,%s,%s
' \
  "$(date '+%Y-%m-%d %H:%M:%S')" "$LABEL" "$status" "$warns" "$maxlat" "$oks" "$rest_fail" "$RC" >> "$LOG_FILE"

echo "────────────────────────────────────────"
echo "🧾 Summary → $status | WARN=$warns | MAX_LAT=${maxlat}s | OK_ENDPOINTS=$oks | REST_FAILS=$rest_fail"
echo "🗂  Raw: $OUT_FILE"
echo "📈 Log: $LOG_FILE"

if [[ -x "$CHART_SCRIPT" ]]; then
  WARN_THR="${PREFLIGHT_LATENCY_WARN:-}"
  FAIL_THR="${PREFLIGHT_LATENCY_FAIL:-}"
  CMD=("$CHART_SCRIPT" --env "$LABEL" --last 200 --out "$OUT_DIR/deploy-latency.png" --ma 10)
  [[ -n "$WARN_THR" && "$WARN_THR" != "0" ]] && CMD+=(--warn "$WARN_THR")
  [[ -n "$FAIL_THR" && "$FAIL_THR" != "0" ]] && CMD+=(--fail "$FAIL_THR")
  if ! "${CMD[@]}"; then
    echo "⚠️  Latency chart generation failed" >&2
  fi
else
  echo "ℹ️  Latency chart skipped (missing $CHART_SCRIPT)"
fi

exit "$RC"
