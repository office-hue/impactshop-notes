#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$ROOT"

DOC_RUNBOOK="docs/coupon-harvester.md"
if [[ ! -f "$DOC_RUNBOOK" ]]; then
  echo "❌ $DOC_RUNBOOK nem található – hiányzik a runbook." >&2
  exit 1
fi

CONFIG_FILE=".codex/cron/coupon-harvester-config.json"
rewrite_config() {
  mkdir -p "$(dirname "$CONFIG_FILE")"
  cat <<'JSON' > "$CONFIG_FILE"
{
  "whitelist": [
    {"slug": "decathlon", "domain": "decathlon.hu"},
    {"slug": "notino", "domain": "notino.hu"},
    {"slug": "emag", "domain": "emag.hu"}
  ],
  "out_dir": "tmp/coupon-harvester",
  "gmail_fixture_dir": "fixtures/coupon-harvester/gmail",
  "html_sources": [
    {"slug": "notino", "type": "file", "path": "fixtures/coupon-harvester/html/sample-notino.html", "title": "Notino promó"}
  ]
}
JSON
}

if [[ ! -f "$CONFIG_FILE" ]]; then
  rewrite_config
else
  NEEDS_UPDATE=$(python3 - "$CONFIG_FILE" <<'PY'
import json,sys
cfg=json.load(open(sys.argv[1]))
required=["gmail_fixture_dir","html_sources","out_dir"]
print("yes" if any(k not in cfg for k in required) else "no")
PY
  )
  if [[ "$NEEDS_UPDATE" == "yes" ]]; then
    rewrite_config
  fi
fi

DRY_RUN="${DRY_RUN:-1}"
OUT_DIR="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1]))["out_dir"])' "$CONFIG_FILE")"
JSON_OUT_DEFAULT="../ai-agent/tmp/ingest/gmail.json"
JSON_OUT="${JSON_OUT:-$JSON_OUT_DEFAULT}"
mkdir -p "$OUT_DIR" .codex/logs "$(dirname "$JSON_OUT")"
LOG_FILE=".codex/logs/coupon-harvester-smoke.log"
PIPELINE="scripts/coupon_harvester_pipeline.py"

if [[ ! -x "$PIPELINE" ]]; then
  echo "❌ Pipeline script hiányzik: $PIPELINE" >&2
  exit 1
fi

PIPELINE_ARGS=("--config" "$CONFIG_FILE" "--out-dir" "$OUT_DIR" "--log-text" "$LOG_FILE" "--json-out" "$JSON_OUT")
if [[ "$DRY_RUN" == "1" ]]; then
  PIPELINE_ARGS+=("--dry-run")
fi

SUMMARY="$(python3 "$PIPELINE" "${PIPELINE_ARGS[@]}")"
CSV_FILE="$(python3 -c "import json,sys;print(json.loads(sys.argv[1]).get('csv_path',''))" "$SUMMARY")"
SHOPS_FILE="$(python3 -c "import json,sys;print(json.loads(sys.argv[1]).get('shops_path',''))" "$SUMMARY")"
COUPON_COUNT="$(python3 -c "import json,sys;print(json.loads(sys.argv[1]).get('coupon_count',0))" "$SUMMARY")"

echo "Runbook: $DOC_RUNBOOK"
echo "Config : $CONFIG_FILE"
echo "CSV    : $CSV_FILE"
echo "Shops  : $SHOPS_FILE"
echo "Log    : $LOG_FILE"
echo "Kuponok: $COUPON_COUNT"
