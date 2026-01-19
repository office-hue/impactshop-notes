#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
cd "$REPO_ROOT"

LAST_RUN_FILE=".codex/logs/impactall-last-run.json"
MAX_AGE=${HOTFIX_IMPACTALL_MAX_AGE:-900}

if [[ ! -f "$LAST_RUN_FILE" ]]; then
  echo "❌ impactall-last-run json hiányzik (${LAST_RUN_FILE}). Futtasd: source .codex/.env.local && ~/bin/impactall"
  exit 1
fi

INFO=$(python3 -c 'import json,sys,time; data=json.load(open(sys.argv[1])); now=time.time(); age=now-data.get("epoch",0); status=data.get("status","unknown"); ts=data.get("timestamp","n/a");
print(f"{int(age)} {status} {ts}")' "$LAST_RUN_FILE") || {
  echo "❌ Nem sikerült beolvasni ${LAST_RUN_FILE}-t"
  exit 1
}

AGE=$(echo "$INFO" | awk '{print $1}')
STATUS=$(echo "$INFO" | awk '{print $2}')
TS=$(echo "$INFO" | cut -d' ' -f3-)

if (( AGE > MAX_AGE )); then
  echo "❌ impactall utolsó futása régebbi mint $MAX_AGE másodperc (${AGE}s). Futtasd újra."
  exit 1
fi

if [[ "$STATUS" != "pass" ]]; then
  echo "❌ impactall utolsó futása nem lett PASS (státusz: $STATUS, időpont: $TS)."
  exit 1
fi

echo "✅ impactall utolsó futása friss (${AGE}s) és PASS (${TS})."
