#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USG'
impactctl-core-task – Core Console API helper

Usage:
  impactctl-core-task --workspace=<id> --title="Feladat" [--description="..."] [--job-type=TYPE] [--job-params=JSON]

Környezeti változók:
  AI_AGENT_API_URL  - API base URL (default: http://localhost:4000)
  AI_AGENT_API_KEY  - szükséges API kulcs
USG
}

API_URL="${AI_AGENT_API_URL:-http://localhost:4000}"
API_KEY="${AI_AGENT_API_KEY:-}"
WORKSPACE=""
TITLE=""
DESCRIPTION=""
JOB_TYPE=""
JOB_PARAMS=""

for arg in "$@"; do
  case "$arg" in
    --workspace=*) WORKSPACE="${arg#*=}" ;;
    --title=*) TITLE="${arg#*=}" ;;
    --description=*) DESCRIPTION="${arg#*=}" ;;
    --job-type=*) JOB_TYPE="${arg#*=}" ;;
    --job-params=*) JOB_PARAMS="${arg#*=}" ;;
    --help) usage; exit 0 ;;
    *) echo "Ismeretlen paraméter: $arg"; usage; exit 1 ;;
  esac
  shift || true
end

if [[ -z "$WORKSPACE" || -z "$TITLE" ]]; then
  echo "--workspace és --title kötelező" >&2
  usage
  exit 1
fi

if [[ -z "$API_KEY" ]]; then
  echo "AI_AGENT_API_KEY hiányzik" >&2
  exit 1
fi

payload="{\"workspaceId\": \"$WORKSPACE\", \"title\": \"$TITLE\""
if [[ -n "$DESCRIPTION" ]]; then
  payload+=" , \"description\": \"$DESCRIPTION\""
fi
if [[ -n "$JOB_TYPE" ]]; then
  payload+=" , \"jobType\": \"$JOB_TYPE\""
fi
if [[ -n "$JOB_PARAMS" ]]; then
  payload+=" , \"jobParams\": $JOB_PARAMS"
fi
payload+=" }"

curl -sSf -X POST \
  -H "Content-Type: application/json" \
  -H "x-api-key: $API_KEY" \
  -d "$payload" \
  "$API_URL/core/tasks"
