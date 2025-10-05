#!/usr/bin/env bash
set -euo pipefail
echo "🚚 WP-CONTENT DEPLOY (MAPPING SYSTEM)"
[ -f .deploy.staging.env ] || { echo "❌ .deploy.staging.env hiányzik"; exit 1; }
source .deploy.staging.env

echo "🎯 Cél: $SSH_HOST:$REMOTE_WP_CONTENT"
ssh -o BatchMode=yes "$SSH_HOST" "[ -d '$REMOTE_WP_CONTENT' ] || mkdir -p '$REMOTE_WP_CONTENT'/{plugins,mu-plugins,themes,uploads}" < /dev/null

# Szelídített rsync opciók (régi verziókhoz is)
RSYNC_OPTS_SAFE="$RSYNC_OPTS"
RSYNC_OPTS_SAFE="${RSYNC_OPTS_SAFE//--info=progress2/}"
RSYNC_OPTS_SAFE="${RSYNC_OPTS_SAFE//  / }"

REMOTE_RSYNC_VER=$(ssh -o BatchMode=yes "$SSH_HOST" "rsync --version 2>/dev/null | head -1 || echo 'rsync unknown'" < /dev/null)
echo "ℹ️  Remote rsync: $REMOTE_RSYNC_VER"
echo "ℹ️  RSYNC_OPTS_SAFE: $RSYNC_OPTS_SAFE"
echo

sync_count=0; skip_count=0
while IFS= read -r LINE; do
  LINE_TRIM="$(echo "$LINE" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
  [[ -z "$LINE_TRIM" || "$LINE_TRIM" =~ ^# ]] && continue

  SRC="$(echo "$LINE_TRIM" | awk -F'->' '{print $1}' | sed 's/[[:space:]]*$//')"
  DST="$(echo "$LINE_TRIM" | awk -F'->' '{print $2}' | sed 's/^[[:space:]]*//')"

  if [ ! -e "$SRC" ]; then
    echo "⏭️ SKIP: $SRC (nincs a helyi gépen)"
    ((skip_count++)); continue
  fi

  remote_dir="$REMOTE_WP_CONTENT/$DST"
  ssh -o BatchMode=yes "$SSH_HOST" "mkdir -p '$remote_dir'" < /dev/null

  echo "📦 SYNC: $SRC → $DST"
  if ! rsync $RSYNC_OPTS_SAFE "$SRC"/ "$SSH_HOST:$remote_dir/" < /dev/null; then
    echo "   ❌ rsync hiba ezen a mappingon (tovább lépek)"
    ((skip_count++))
  else
    echo "   ✅ Success"
    ((sync_count++))
  fi
  echo
done <<< "$MAPPINGS"

echo "📊 SUMMARY"
echo "✅ Synced : $sync_count"
echo "⏭️ Skipped: $skip_count"

echo "🧹 WP maintenance…"
ssh -o BatchMode=yes "$SSH_HOST" "wp --path='$REMOTE_WP_PATH' cache flush 2>/dev/null || true; \
                                   wp --path='$REMOTE_WP_PATH' cron event run --due-now 2>/dev/null || true; \
                                   wp --path='$REMOTE_WP_PATH' rewrite flush --hard 2>/dev/null || true" < /dev/null
echo "🎉 Done."
