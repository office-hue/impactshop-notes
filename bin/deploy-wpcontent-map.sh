#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
HASH_PATH="${ROOT_DIR}/docs/impactshop-guard-hashes.json"

if [[ $# -gt 1 ]]; then
  echo "Usage: $0 [--staging|--production|--env=FILE|ENV_FILE]" >&2
  exit 2
fi

case "${1:-}" in
  "" ) ENV_FILE=".deploy.staging.env" ;;
  --staging|-s ) ENV_FILE=".deploy.staging.env" ;;
  --production|-p ) ENV_FILE=".deploy.production.env" ;;
  --env=* ) ENV_FILE="${1#--env=}" ;;
  -* )
    echo "❌ Ismeretlen opció: $1" >&2
    echo "Használat: $0 [--staging|--production|--env=FILE|ENV_FILE]" >&2
    exit 2
    ;;
  * ) ENV_FILE="$1" ;;
esac

echo "🚚 WP-CONTENT DEPLOY (MAPPING SYSTEM)"
[ -f "$ENV_FILE" ] || { echo "❌ ${ENV_FILE} hiányzik"; exit 1; }
# shellcheck disable=SC1090
source "$ENV_FILE"

IS_STAGING=0
if [[ "${REMOTE_WP_PATH:-}" == */app-staging* ]] || [[ "$ENV_FILE" == *staging* ]]; then
  IS_STAGING=1
fi

if [[ $IS_STAGING -eq 1 ]]; then
  if [ -x "$SCRIPT_DIR/impactctl-guard-staging.sh" ]; then
    "$SCRIPT_DIR/impactctl-guard-staging.sh"
  else
    echo "⚠️  Guard script nem található ($SCRIPT_DIR/impactctl-guard-staging.sh)" >&2
  fi
fi

run_hatas_korok_post_deploy_smoke() {
  local smoke_script="${ROOT_DIR}/scripts/hatas-korok-post-deploy-smoke.sh"
  local base_url="${PREFLIGHT_BASE_URL:-}"

  [[ -x "$smoke_script" ]] || return 0
  [[ -n "$base_url" ]] || return 0
  [[ $IS_STAGING -eq 0 ]] || return 0

  echo "🧪 Hatás Körök post-deploy smoke…"
  if "$smoke_script" "$base_url"; then
    echo "✅ Hatás Körök post-deploy smoke OK"
  else
    echo "❌ Hatás Körök post-deploy smoke FAILED" >&2
    exit 1
  fi
}

verify_remote_bastion_manifest() {
  local remote_app_root="${1:-}"
  [[ -n "$remote_app_root" ]] || return 0
  [[ -f "$HASH_PATH" ]] || return 0

  local local_hash remote_hash
  local_hash="$(python3 - "$HASH_PATH" <<'PY'
import hashlib
import sys
print(hashlib.sha256(open(sys.argv[1], "rb").read()).hexdigest())
PY
)"

  remote_hash="$(ssh -o BatchMode=yes "$SSH_HOST" "python3 - <<'PY'
import hashlib
path = '${remote_app_root}/.bastion/protected-hashes.json'
try:
    print(hashlib.sha256(open(path, 'rb').read()).hexdigest())
except FileNotFoundError:
    print('')
PY" < /dev/null)"

  if [[ -z "$remote_hash" ]]; then
    echo "❌ Remote bastion manifest hiányzik: ${SSH_HOST}:${remote_app_root}/.bastion/protected-hashes.json" >&2
    exit 1
  fi

  if [[ "$local_hash" != "$remote_hash" ]]; then
    echo "❌ Remote bastion manifest checksum mismatch." >&2
    echo "   local : $local_hash" >&2
    echo "   remote: $remote_hash" >&2
    exit 1
  fi
}

if [[ "${DRY_RUN:-0}" == "1" ]]; then
  echo "🛡️ DRY-RUN MODE ENABLED — rsync nem ír a távoli szerverre."
  RSYNC_OPTS="${RSYNC_OPTS:-} -n"
fi

ENV_PATH="$ENV_FILE"
if [[ "$ENV_PATH" != /* ]]; then
  ENV_PATH="$(pwd)/$ENV_PATH"
fi

if [[ -z "${SKIP_PREFLIGHT:-}" ]]; then
  PREFLIGHT_SCRIPT="$SCRIPT_DIR/preflight-check.sh"
  if [[ -x "$PREFLIGHT_SCRIPT" ]]; then
    if [[ -n "${PREFLIGHT_BASE_URL:-}" ]]; then
      echo "🧪 Preflight futtatása…"
      if ! "$PREFLIGHT_SCRIPT" "$ENV_PATH"; then
        echo "❌ Preflight hiba – deploy megszakítva"
        exit 1
      fi
    else
      echo "⚠️ Preflight kihagyva (PREFLIGHT_BASE_URL nincs megadva)"
    fi
  else
    echo "⚠️ Preflight kihagyva (bin/preflight-check.sh hiányzik vagy nem futtatható)"
  fi
else
  echo "⏭️ Preflight kihagyva (SKIP_PREFLIGHT=1)"
fi

echo "🎯 Cél: $SSH_HOST:$REMOTE_WP_CONTENT"
ssh -o BatchMode=yes "$SSH_HOST" "[ -d '$REMOTE_WP_CONTENT' ] || mkdir -p '$REMOTE_WP_CONTENT'/{plugins,mu-plugins,themes,uploads}" < /dev/null
verify_remote_bastion_manifest "$(dirname "${REMOTE_WP_CONTENT}")"

# Szelídített rsync opciók (régi verziókhoz is)
RSYNC_OPTS_SAFE="${RSYNC_OPTS:-}"
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

run_hatas_korok_post_deploy_smoke

echo "🎉 Done."
