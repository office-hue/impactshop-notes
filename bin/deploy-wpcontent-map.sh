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

require_safe_remote_path() {
  local remote_path="${1:-}"
  local label="${2:-remote path}"

  if [[ ! "$remote_path" =~ ^/[A-Za-z0-9._/-]+$ ]] || [[ "$remote_path" == *".."* ]]; then
    echo "❌ Nem biztonságos ${label}: $remote_path" >&2
    exit 1
  fi
}

verify_remote_bastion_manifest() {
  local remote_root="${1:-}"
  local check_result=""

  require_safe_remote_path "$remote_root" "remote root"

  if ! check_result="$(ssh -o BatchMode=yes "$SSH_HOST" "python3 - '$remote_root'" <<'PY'
import json
import re
import sys
from pathlib import Path

root = Path(sys.argv[1])
if not root.exists() or not root.is_dir():
    print("missing_root")
    raise SystemExit(0)
if not (root / "wp-config.php").is_file():
    print("missing_wp_config")
    raise SystemExit(0)
if not (root / "wp-content").is_dir():
    print("missing_wp_content")
    raise SystemExit(0)

candidates = [
    root / ".bastion" / "protected-hashes.json",
    root / ".codex" / "bastion-manifest.json",
    root / ".bastion-manifest.json",
    root / "wp-content" / ".bastion-manifest.json",
]
manifest = next((candidate for candidate in candidates if candidate.exists()), None)
if manifest is None:
    print("missing_manifest")
    raise SystemExit(0)
if manifest.is_symlink() or not manifest.is_file():
    print(f"unsafe_manifest:{manifest}")
    raise SystemExit(0)
if manifest.stat().st_size > 4 * 1024 * 1024:
    print(f"oversized_manifest:{manifest}")
    raise SystemExit(0)

try:
    data = json.loads(manifest.read_text(encoding="utf-8"))
except Exception:
    print(f"invalid_manifest:{manifest}")
    raise SystemExit(0)

hashes = data.get("hashes") if isinstance(data, dict) else None
protected = data.get("protected_files") if isinstance(data, dict) else None
if not isinstance(hashes, dict) or not hashes or not isinstance(protected, list) or not protected:
    print(f"invalid_schema:{manifest}")
    raise SystemExit(0)

digest_pattern = re.compile(r"^[a-f0-9]{64}$")
for rel, digest in hashes.items():
    if not isinstance(rel, str) or not rel or Path(rel).is_absolute() or ".." in Path(rel).parts:
        print(f"unsafe_entry:{manifest}")
        raise SystemExit(0)
    if not isinstance(digest, str) or not digest_pattern.fullmatch(digest):
        print(f"invalid_hash:{manifest}")
        raise SystemExit(0)

print(f"ok_manifest:{manifest}:{len(hashes)}")
PY
)"; then
    echo "❌ Bastion manifest ellenőrzés: SSH/Python hiba." >&2
    exit 1
  fi

  case "$check_result" in
    ok_manifest:*)
      echo "✅ Bastion manifest ellenőrzés OK: ${check_result#ok_manifest:}"
      ;;
    missing_root)
      echo "❌ Bastion ellenőrzés: hiányzó remote root: $remote_root" >&2
      exit 1
      ;;
    missing_wp_config)
      echo "❌ Bastion ellenőrzés: wp-config.php hiányzik: $remote_root" >&2
      exit 1
      ;;
    missing_wp_content)
      echo "❌ Bastion ellenőrzés: wp-content hiányzik: $remote_root" >&2
      exit 1
      ;;
    missing_manifest)
      echo "❌ Bastion manifest hiányzik: $remote_root" >&2
      exit 1
      ;;
    unsafe_manifest:*|oversized_manifest:*|invalid_manifest:*|invalid_schema:*|unsafe_entry:*|invalid_hash:*)
      echo "❌ Bastion manifest elutasítva: $check_result" >&2
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

DRY_RUN_MODE=0
if [[ "${DRY_RUN:-0}" == "1" ]]; then
  DRY_RUN_MODE=1
  echo "🛡️ DRY-RUN MODE ENABLED — rsync nem ír a távoli szerverre."
  RSYNC_OPTS="${RSYNC_OPTS:-} -n --itemize-changes"
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
require_safe_remote_path "$REMOTE_WP_CONTENT" "remote wp-content"
if [[ $DRY_RUN_MODE -eq 1 ]]; then
  if ! ssh -o BatchMode=yes "$SSH_HOST" "test -d '$REMOTE_WP_CONTENT'" < /dev/null; then
    echo "❌ DRY-RUN: a remote wp-content cél nem létezik: $REMOTE_WP_CONTENT" >&2
    exit 1
  fi
  echo "🔎 DRY-RUN: remote cél létezik; könyvtárlétrehozás kihagyva."
else
  ssh -o BatchMode=yes "$SSH_HOST" "[ -d '$REMOTE_WP_CONTENT' ] || mkdir -p '$REMOTE_WP_CONTENT'/{plugins,mu-plugins,themes,uploads}" < /dev/null
fi
verify_remote_bastion_manifest "$(dirname "${REMOTE_WP_CONTENT}")"
verify_production_origin_alignment

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
  require_safe_remote_path "$remote_dir" "mapping destination"
  if [[ $DRY_RUN_MODE -eq 1 ]]; then
    if ! ssh -o BatchMode=yes "$SSH_HOST" "test -d '$remote_dir'" < /dev/null; then
      echo "❌ DRY-RUN: hiányzó mapping cél, létrehozás tiltva: $remote_dir" >&2
      exit 1
    fi
  else
    ssh -o BatchMode=yes "$SSH_HOST" "mkdir -p '$remote_dir'" < /dev/null
  fi

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

if [[ $DRY_RUN_MODE -eq 1 ]]; then
  echo "🔎 DRY-RUN: WP maintenance és post-deploy smoke kihagyva."
else
  echo "🧹 WP maintenance…"
  ssh -o BatchMode=yes "$SSH_HOST" "wp --path='$REMOTE_WP_PATH' cache flush 2>/dev/null || true; \
                                     wp --path='$REMOTE_WP_PATH' cron event run --due-now 2>/dev/null || true; \
                                     wp --path='$REMOTE_WP_PATH' rewrite flush --hard 2>/dev/null || true" < /dev/null

  run_hatas_korok_post_deploy_smoke
fi

echo "🎉 Done."
