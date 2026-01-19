#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$HOME/Documents/GitHub/impactshop-notes"
LOG_FILE="$REPO_DIR/tmp/impact-fragment-prewarm.log"
CMD="cd $REPO_DIR && ./scripts/impact-fragment-prewarm.sh both >> $LOG_FILE 2>&1"
CRON_TAG="# impact-fragment-prewarm"
CRON_LINE="5 * * * * $CMD $CRON_TAG"

tmp=$(mktemp)
trap 'rm -f "$tmp"' EXIT
crontab -l 2>/dev/null | grep -v "$CRON_TAG" > "$tmp" || true
echo "$CRON_LINE" >> "$tmp"
crontab "$tmp"

echo "Fragment prewarm cron entry installed: $CRON_LINE"
