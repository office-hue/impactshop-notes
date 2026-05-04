#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

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

run_impact_community_guard() {
  local guard_script="${ROOT_DIR}/scripts/guarded-remote-write.sh"
  local local_file="${ROOT_DIR}/wp-content/mu-plugins/impact-community.php"
  local remote_file="${REMOTE_WP_CONTENT}/mu-plugins/impact-community.php"

  if [[ "${IMPACTSHOP_SKIP_COMMUNITY_GUARD:-0}" == "1" ]]; then
    echo "⚠️ impact-community guard kihagyva (IMPACTSHOP_SKIP_COMMUNITY_GUARD=1)"
    return 0
  fi

  if [[ ! -x "$guard_script" ]]; then
    echo "❌ impact-community guard script hiányzik vagy nem futtatható: $guard_script" >&2
    exit 1
  fi

  if [[ ! -f "$local_file" ]]; then
    echo "❌ impact-community guard: hiányzik a lokális fájl: $local_file" >&2
    exit 1
  fi

  echo "🛡️ impact-community regresszió guard ellenőrzés…"
  "$guard_script" --local "$local_file" --remote "$remote_file" --dry-run
}

verify_remote_bastion_manifest() {
  local remote_root="$1"
  local check_result=""

  check_result="$(ssh -o BatchMode=yes "$SSH_HOST" "python3 - <<'PY'
import json
from pathlib import Path

root = Path('${remote_root}')
if not root.exists() or not root.is_dir():
    print('missing_root')
    raise SystemExit(0)

if not (root / 'wp-config.php').exists():
    print('missing_wp_config')
    raise SystemExit(0)

if not (root / 'wp-content').exists():
    print('missing_wp_content')
    raise SystemExit(0)

candidates = [
    root / '.codex' / 'bastion-manifest.json',
    root / '.bastion-manifest.json',
    root / 'wp-content' / '.bastion-manifest.json',
]

for cand in candidates:
    if cand.exists():
        try:
            json.loads(cand.read_text(encoding='utf-8'))
        except Exception:
            print(f'invalid_manifest:{cand}')
            raise SystemExit(0)
        print(f'ok_manifest:{cand}')
        raise SystemExit(0)

print('ok_no_manifest')
PY" < /dev/null)"

  case "$check_result" in
    ok_manifest:*)
      echo "✅ Bastion manifest ellenőrzés OK: ${check_result#ok_manifest:}"
      ;;
    ok_no_manifest)
      echo "⚠️ Bastion manifest nem található a remote root alatt (folytatás engedélyezett)."
      ;;
    missing_root)
      echo "❌ Bastion ellenőrzés: hiányzó remote root: $remote_root" >&2
      exit 1
      ;;
    missing_wp_config)
      echo "❌ Bastion ellenőrzés: wp-config.php hiányzik a remote root alatt: $remote_root" >&2
      exit 1
      ;;
    missing_wp_content)
      echo "❌ Bastion ellenőrzés: wp-content hiányzik a remote root alatt: $remote_root" >&2
      exit 1
      ;;
    invalid_manifest:*)
      echo "❌ Bastion manifest hibás JSON: ${check_result#invalid_manifest:}" >&2
      exit 1
      ;;
    *)
      echo "❌ Bastion ellenőrzés ismeretlen válasz: $check_result" >&2
      exit 1
      ;;
  esac
}

verify_production_origin_alignment() {
  [[ $IS_STAGING -eq 0 ]] || return 0
  [[ "${REMOTE_WP_PATH:-}" == "/home/sharityh/app" ]] || return 0

  local remote_check=""
  remote_check="$(ssh -o BatchMode=yes "$SSH_HOST" "python3 - <<'PY'
from pathlib import Path
public_index = Path('/home/sharityh/public_html/index.php')
app_index = Path('/home/sharityh/app/index.php')
if not public_index.exists() or not app_index.exists():
    print('missing')
    raise SystemExit(0)
txt = public_index.read_text(encoding='utf-8', errors='ignore')
print('ok' if '../app/wp-blog-header.php' in txt else 'mismatch')
PY" < /dev/null)"

  case "$remote_check" in
    ok)
      echo "✅ Production origin alignment OK: public_html entrypoint -> /home/sharityh/app"
      ;;
    missing)
      echo "❌ Production origin alignment check failed: missing public_html/app index.php" >&2
      exit 1
      ;;
    *)
      echo "❌ Production origin alignment mismatch: public_html wrapper does not point to /home/sharityh/app" >&2
      exit 1
      ;;
  esac
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
verify_production_origin_alignment
run_impact_community_guard

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
