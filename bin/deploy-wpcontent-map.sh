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

if [[ "$ENV_FILE" != /* ]]; then
  ENV_FILE="$ROOT_DIR/$ENV_FILE"
fi
cd "$ROOT_DIR"

echo "🚚 WP-CONTENT DEPLOY (MAPPING SYSTEM)"
[ -f "$ENV_FILE" ] || { echo "❌ ${ENV_FILE} hiányzik"; exit 1; }
# shellcheck disable=SC1090
source "$ENV_FILE"

trim_value() {
  local value="${1:-}"
  value="${value#"${value%%[![:space:]]*}"}"
  value="${value%"${value##*[![:space:]]}"}"
  printf '%s' "$value"
}

require_safe_relative_path() {
  local path="${1:-}"
  local label="${2:-relative path}"

  if [[ -z "$path" ]] || [[ "$path" == /* ]] || [[ ! "$path" =~ ^[A-Za-z0-9._/-]+$ ]] || \
     [[ "/$path/" == *"/../"* ]] || [[ "/$path/" == *"/./"* ]] || [[ "$path" == *"//"* ]]; then
    echo "❌ Nem biztonságos ${label}: $path" >&2
    exit 1
  fi
}

case "${DEPLOY_ENVIRONMENT:-}" in
  staging)
    IS_STAGING=1
    IS_PRODUCTION=0
    ;;
  production)
    IS_STAGING=0
    IS_PRODUCTION=1
    ;;
  *)
    echo "❌ DEPLOY_ENVIRONMENT csak staging vagy production lehet." >&2
    exit 1
    ;;
esac

if [[ $IS_STAGING -eq 1 && "${REMOTE_WP_PATH:-}" != */app-staging* ]]; then
  echo "❌ Ellentmondó staging profil: REMOTE_WP_PATH=${REMOTE_WP_PATH:-}" >&2
  exit 1
fi
if [[ $IS_PRODUCTION -eq 1 && "${REMOTE_WP_PATH:-}" == */app-staging* ]]; then
  echo "❌ Ellentmondó production profil: REMOTE_WP_PATH=${REMOTE_WP_PATH:-}" >&2
  exit 1
fi

MAPPING_SRCS=()
MAPPING_DSTS=()
while IFS= read -r mapping_line; do
  mapping_line="$(trim_value "$mapping_line")"
  [[ -z "$mapping_line" || "$mapping_line" == \#* ]] && continue

  if [[ "$mapping_line" != *"->"* ]] || [[ "${mapping_line#*->}" == *"->"* ]]; then
    echo "❌ Hibás mapping sor (pontosan egy -> szükséges): $mapping_line" >&2
    exit 1
  fi

  mapping_src="$(trim_value "${mapping_line%%->*}")"
  mapping_dst="$(trim_value "${mapping_line#*->}")"
  require_safe_relative_path "$mapping_src" "mapping source"
  require_safe_relative_path "$mapping_dst" "mapping destination"

  if [[ -e "$mapping_src" ]]; then
    if [[ -L "$mapping_src" ]]; then
      echo "❌ Symlinkelt mapping source tiltott: $mapping_src" >&2
      exit 1
    fi
    if [[ -d "$mapping_src" ]]; then
      mapping_physical="$(cd "$mapping_src" && pwd -P)"
    elif [[ -f "$mapping_src" ]]; then
      mapping_physical="$(cd "$(dirname "$mapping_src")" && pwd -P)/$(basename "$mapping_src")"
    else
      echo "❌ A mapping source csak normál fájl vagy könyvtár lehet: $mapping_src" >&2
      exit 1
    fi
    if [[ "$mapping_physical" != "$ROOT_DIR" && "$mapping_physical" != "$ROOT_DIR/"* ]]; then
      echo "❌ A mapping source fizikailag a repo gyökerén kívülre mutat: $mapping_src" >&2
      exit 1
    fi
  fi

  for mapping_index in "${!MAPPING_SRCS[@]}"; do
    if [[ "${MAPPING_SRCS[$mapping_index]}" == "$mapping_src" ]]; then
      echo "❌ Duplikált mapping source: $mapping_src" >&2
      exit 1
    fi
    if [[ "${MAPPING_DSTS[$mapping_index]}" == "$mapping_dst" ]]; then
      echo "❌ Duplikált mapping destination: $mapping_dst" >&2
      exit 1
    fi
  done

  MAPPING_SRCS+=("$mapping_src")
  MAPPING_DSTS+=("$mapping_dst")
done <<< "${MAPPINGS:-}"

if [[ ${#MAPPING_SRCS[@]} -eq 0 ]]; then
  echo "❌ A deploy profil nem tartalmaz érvényes mappinget." >&2
  exit 1
fi

DRY_RUN_MODE=0
if [[ "${DRY_RUN:-0}" == "1" ]]; then
  DRY_RUN_MODE=1
  echo "🛡️ DRY-RUN MODE ENABLED — rsync nem ír a távoli szerverre."
  RSYNC_OPTS="${RSYNC_OPTS:-} -n --itemize-changes"
fi

SCOPED_DEPLOY=0
SCOPED_SRC=""
SCOPED_DST=""
if [[ -n "${IMPACTSHOP_DEPLOY_FILE:-}" ]]; then
  SCOPED_DEPLOY=1
  SCOPED_SRC="$(trim_value "$IMPACTSHOP_DEPLOY_FILE")"
  require_safe_relative_path "$SCOPED_SRC" "IMPACTSHOP_DEPLOY_FILE"

  if [[ ! -f "$SCOPED_SRC" ]] || [[ -L "$SCOPED_SRC" ]]; then
    echo "❌ Az exact deploy scope csak létező, normál, nem symlinkelt fájl lehet: $SCOPED_SRC" >&2
    exit 1
  fi
  scoped_physical="$(cd "$(dirname "$SCOPED_SRC")" && pwd -P)/$(basename "$SCOPED_SRC")"
  if [[ "$scoped_physical" != "$ROOT_DIR/"* ]]; then
    echo "❌ Az exact deploy scope fizikailag a repo gyökerén kívülre mutat: $SCOPED_SRC" >&2
    exit 1
  fi

  scoped_match_count=0
  for mapping_index in "${!MAPPING_SRCS[@]}"; do
    mapping_src="${MAPPING_SRCS[$mapping_index]}"
    mapping_dst="${MAPPING_DSTS[$mapping_index]}"
    scoped_suffix=""

    if [[ -f "$mapping_src" && "$SCOPED_SRC" == "$mapping_src" ]]; then
      scoped_suffix=""
    elif [[ -d "$mapping_src" && "$SCOPED_SRC" == "$mapping_src/"* ]]; then
      scoped_suffix="${SCOPED_SRC#${mapping_src}/}"
    else
      continue
    fi

    ((scoped_match_count += 1))
    if [[ -n "$scoped_suffix" ]]; then
      SCOPED_DST="$mapping_dst/$scoped_suffix"
    else
      SCOPED_DST="$mapping_dst"
    fi
  done

  if [[ $scoped_match_count -ne 1 ]]; then
    echo "❌ Az exact deploy scope-nak pontosan egy mappinghez kell tartoznia (találat: $scoped_match_count): $SCOPED_SRC" >&2
    exit 1
  fi
  require_safe_relative_path "$SCOPED_DST" "exact mapping destination"
fi

if [[ $IS_PRODUCTION -eq 1 && $DRY_RUN_MODE -eq 0 ]]; then
  if [[ $SCOPED_DEPLOY -eq 0 ]]; then
    echo "❌ Valós production deploy csak IMPACTSHOP_DEPLOY_FILE exact scope-pal készíthető elő." >&2
  else
    echo "❌ Valós exact-file production írás még tiltott: remote backup/CAS/rollback admission hiányzik." >&2
  fi
  exit 1
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
if [[ $DRY_RUN_MODE -eq 1 || $SCOPED_DEPLOY -eq 1 ]]; then
  if ! ssh -o BatchMode=yes "$SSH_HOST" "test -d '$REMOTE_WP_CONTENT'" < /dev/null; then
    echo "❌ A remote wp-content cél nem létezik: $REMOTE_WP_CONTENT" >&2
    exit 1
  fi
  echo "🔎 Remote cél létezik; könyvtárlétrehozás kihagyva."
else
  ssh -o BatchMode=yes "$SSH_HOST" "[ -d '$REMOTE_WP_CONTENT' ] || mkdir -p '$REMOTE_WP_CONTENT'/{plugins,mu-plugins,themes,uploads}" < /dev/null
fi
verify_remote_bastion_manifest "$(dirname "${REMOTE_WP_CONTENT}")"
verify_production_origin_alignment

# Szelídített rsync opciók (régi verziókhoz is)
RSYNC_OPTS_SAFE="${RSYNC_OPTS:-}"
RSYNC_OPTS_SAFE="${RSYNC_OPTS_SAFE//--info=progress2/}"
RSYNC_OPTS_SAFE="${RSYNC_OPTS_SAFE//  / }"
if [[ $SCOPED_DEPLOY -eq 1 ]]; then
  scoped_rsync_opts=""
  for rsync_token in $RSYNC_OPTS_SAFE; do
    case "$rsync_token" in
      --delete|--delete-*)
        continue
        ;;
    esac
    scoped_rsync_opts="$scoped_rsync_opts $rsync_token"
  done
  RSYNC_OPTS_SAFE="$(trim_value "$scoped_rsync_opts") --checksum"
fi

REMOTE_RSYNC_VER=$(ssh -o BatchMode=yes "$SSH_HOST" "rsync --version 2>/dev/null | head -1 || echo 'rsync unknown'" < /dev/null)
echo "ℹ️  Remote rsync: $REMOTE_RSYNC_VER"
echo "ℹ️  RSYNC_OPTS_SAFE: $RSYNC_OPTS_SAFE"
echo

sync_count=0; skip_count=0
if [[ $SCOPED_DEPLOY -eq 1 ]]; then
  remote_file="$REMOTE_WP_CONTENT/$SCOPED_DST"
  remote_parent="$(dirname "$remote_file")"
  require_safe_remote_path "$remote_file" "exact remote file"
  require_safe_remote_path "$remote_parent" "exact remote parent"

  if ! ssh -o BatchMode=yes "$SSH_HOST" "test -d '$remote_parent'" < /dev/null; then
    echo "❌ Hiányzó exact remote szülőkönyvtár, létrehozás tiltva: $remote_parent" >&2
    exit 1
  fi

  echo "🎯 EXACT FILE: $SCOPED_SRC → $SCOPED_DST"
  if ! rsync $RSYNC_OPTS_SAFE "$SCOPED_SRC" "$SSH_HOST:$remote_file" < /dev/null; then
    echo "   ❌ Exact-file rsync hiba; deploy megszakítva." >&2
    exit 1
  fi
  echo "   ✅ Success"
  ((sync_count += 1))
  echo
else
  for mapping_index in "${!MAPPING_SRCS[@]}"; do
    SRC="${MAPPING_SRCS[$mapping_index]}"
    DST="${MAPPING_DSTS[$mapping_index]}"

    if [ ! -e "$SRC" ]; then
      echo "⏭️ SKIP: $SRC (nincs a helyi gépen)"
      ((skip_count += 1)); continue
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
      ((skip_count += 1))
    else
      echo "   ✅ Success"
      ((sync_count += 1))
    fi
    echo
  done
fi

echo "📊 SUMMARY"
echo "✅ Synced : $sync_count"
echo "⏭️ Skipped: $skip_count"

if [[ $DRY_RUN_MODE -eq 1 ]]; then
  echo "🔎 DRY-RUN: WP maintenance és post-deploy smoke kihagyva."
elif [[ $SCOPED_DEPLOY -eq 1 ]]; then
  echo "🔎 EXACT FILE: általános WP maintenance és post-deploy smoke kihagyva."
else
  echo "🧹 WP maintenance…"
  ssh -o BatchMode=yes "$SSH_HOST" "wp --path='$REMOTE_WP_PATH' cache flush 2>/dev/null || true; \
                                     wp --path='$REMOTE_WP_PATH' cron event run --due-now 2>/dev/null || true; \
                                     wp --path='$REMOTE_WP_PATH' rewrite flush --hard 2>/dev/null || true" < /dev/null

  run_hatas_korok_post_deploy_smoke
fi

echo "🎉 Done."
