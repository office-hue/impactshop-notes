#!/usr/bin/env bash
# ImpactShop GO/WARN/NO-GO validator for staging|production (read-only)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STRICT=0
ENV_ARG=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --strict) STRICT=1 ;;
    --env) shift; ENV_ARG="${1:-$ENV_ARG}" ;;
    --env=*) ENV_ARG="${1#--env=}" ;;
    --*) echo "Ismeretlen opció: $1" >&2; exit 2 ;;
    *)
      if [[ -z "$ENV_ARG" ]]; then
        ENV_ARG="$1"
      else
        echo "Ismeretlen extra paraméter: $1" >&2
        exit 2
      fi
      ;;
  esac
  shift
done

[[ -n "$ENV_ARG" ]] || ENV_ARG="staging"

resolve_env() {
  local key="${ENV_ARG}"
  case "$key" in
    ""|staging|stage|stg)
      ENV_LABEL="staging"
      ENV_FILE="$ROOT/.deploy.staging.env"
      ;;
    production|prod)
      ENV_LABEL="production"
      ENV_FILE="$ROOT/.deploy.production.env"
      ;;
    --env=*)
      ENV_LABEL="$(basename "${key#--env=}")"
      ENV_FILE="${key#--env=}"
      ;;
    *)
      if [[ -f "$key" ]]; then
        ENV_LABEL="$(basename "$key")"
        ENV_FILE="$key"
      elif [[ -f "$ROOT/$key" ]]; then
        ENV_LABEL="$(basename "$key")"
        ENV_FILE="$ROOT/$key"
      else
        echo "❌ Ismeretlen környezet vagy fájl: $key" >&2
        exit 2
      fi
      ;;
  esac
  [[ -f "$ENV_FILE" ]] || { echo "❌ Környezeti fájl hiányzik: $ENV_FILE" >&2; exit 2; }
}

resolve_env

OUT_DIR="$ROOT/.codex"
LOG_FILE="$OUT_DIR/deploy-log.txt"
STAMP="$(date +%Y%m%d-%H%M%S)"
TMP_OUT="$OUT_DIR/_go_no_go_${ENV_LABEL}_${STAMP}.out"
mkdir -p "$OUT_DIR"

RUNNER="$ROOT/bin/preflight-run.sh"
CHECKER="$ROOT/bin/preflight-check.sh"
STATUS="GO"
WARN_COUNT=0
REST_FAIL=0
EXIT_CODE=0
MAX_LAT="0"

UPPER_ENV="$(printf '%s' "$ENV_LABEL" | tr '[:lower:]' '[:upper:]')"
echo "🚀 ImpactShop ${UPPER_ENV} GO/NO-GO VALIDATOR"
echo "🕒 $(date '+%Y-%m-%d %H:%M:%S')"
echo "───────────────────────────────────────────────"

run_preflight_runner() {
  set +e
  "$RUNNER" "$ENV_LABEL" | tee "$TMP_OUT"
  EXIT_CODE=${PIPESTATUS[0]}
  set -e
}

run_preflight_checker() {
  set +e
  "$CHECKER" "$ENV_FILE" | tee "$TMP_OUT"
  EXIT_CODE=${PIPESTATUS[0]}
  set -e
}

extract_metrics_from_log() {
  python3 - <<'PY' "$LOG_FILE" "$ENV_LABEL"
import csv, sys
path, env = sys.argv[1:3]
try:
    rows = list(csv.DictReader(open(path)))
except FileNotFoundError:
    print("")
    raise SystemExit
rows = [row for row in rows if row.get('env') == env]
if not rows:
    print("")
else:
    row = rows[-1]
    print(row.get('status',''), row.get('warns','0'), row.get('rest_fail_count','0'), row.get('max_latency_s','0'), row.get('exit_code','0'))
PY
}

append_log_entry() {
  local status="$1" warns="$2" maxlat="$3" oks="$4" restfail="$5" exitcode="$6"
  if [[ ! -s "$LOG_FILE" ]]; then
    echo "timestamp,env,status,warns,max_latency_s,endpoints_ok,rest_fail_count,exit_code" > "$LOG_FILE"
  fi
  printf '%s,%s,%s,%s,%s,%s,%s,%s\n' \
    "$(date '+%Y-%m-%d %H:%M:%S')" "$ENV_LABEL" "$status" "$warns" "$maxlat" "$oks" "$restfail" "$exitcode" >> "$LOG_FILE"
}

parse_stdout_metrics() {
  python3 - <<'PY' "$TMP_OUT"
import re, sys
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
                latmax = max(latmax, float(match)/1000.0)
except FileNotFoundError:
    pass
print(warns, oks, rest_fail, f"{latmax:.3f}")
PY
}

if [[ -x "$RUNNER" ]]; then
  run_preflight_runner
elif [[ -x "$CHECKER" ]]; then
  run_preflight_checker
else
  echo "❌ preflight-run.sh / preflight-check.sh nem elérhető" >&2
  exit 2
fi

status_line="$(extract_metrics_from_log || true)"
if [[ -n "$status_line" ]]; then
  read STATUS WARN_COUNT REST_FAIL MAX_LAT EXIT_CODE <<< "$status_line"
else
  read WARN_COUNT OK_COUNT REST_FAIL MAX_LAT <<< "$(parse_stdout_metrics)"
  STATUS="GO"
  EXIT_CODE=$EXIT_CODE  # already set from preflight
  if [[ $EXIT_CODE -ne 0 || $REST_FAIL -gt 0 ]]; then
    STATUS="NO-GO"
  elif [[ ${WARN_COUNT:-0} -gt 0 ]]; then
    STATUS="PASS_WITH_WARN"
  fi
  append_log_entry "$STATUS" "$WARN_COUNT" "$MAX_LAT" "${OK_COUNT:-0}" "$REST_FAIL" "$EXIT_CODE"
fi

case "$STATUS" in
  FAIL|NO-GO)
    OUT_STATUS="NO-GO"
    CODE=2
    ;;
  PASS_WITH_WARN)
    OUT_STATUS="WARN"
    CODE=1
    ;;
  *)
    OUT_STATUS="GO"
    CODE=0
    ;;
 esac

echo "───────────────────────────────────────────────"
echo "📊 Result:  $OUT_STATUS (env=$ENV_LABEL)"
echo "📉 Max latency: ${MAX_LAT}s"
echo "🧾 WARN count: ${WARN_COUNT} | REST fails: ${REST_FAIL}"
echo "🗂  Raw: $TMP_OUT"
echo "📈 Log: $LOG_FILE"

echo "───────────────────────────────────────────────"
if [[ "$STRICT" == "1" && "$CODE" -eq 1 ]]; then
  exit 1
fi
exit "$CODE"
