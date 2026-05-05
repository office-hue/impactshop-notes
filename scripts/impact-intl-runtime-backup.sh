#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_BASE="${ROOT_DIR}/.codex/backups/impact-intl-runtime"
DRY_RUN=0
INCLUDE_COMMUNITY=0
ACK_INCLUDE_COMMUNITY=0
BACKUP_ID="$(date -u +%Y%m%dT%H%M%SZ)"

CORE_FILES=(
  "wp-content/mu-plugins/impactshop-intl-runtime.php"
  "wp-content/mu-plugins/impactshop-offerwall-intl-overlay.php"
  "wp-content/mu-plugins/impactshop-offerwall-intl-overlay.js"
  "wp-content/mu-plugins/impactshop-offerwall.php"
  "wp-content/mu-plugins/impactshop-offerwall.js"
  "wp-content/mu-plugins/impactshop-ads-watch-intl-overlay.php"
  "wp-content/mu-plugins/impactshop-ads-watch-intl-overlay.js"
  "wp-content/mu-plugins/impactshop-ads-watch.php"
  "wp-content/mu-plugins/impactshop-ads-watch.js"
  "wp-content/mu-plugins/impactshop-ngo-guides.php"
  "wp-content/mu-plugins/impactshop-ngo-guides/hatas-korok.html"
  "wp-content/mu-plugins/impactshop-ngo-guides/hatas-korok-en.html"
)

COMMUNITY_FILES=(
  "wp-content/mu-plugins/impact-community.php"
  "wp-content/mu-plugins/impact-community-app.php"
)

usage() {
  cat <<'EOF'
Usage: scripts/impact-intl-runtime-backup.sh [options]

Options:
  --backup-id ID                Backup identifier (default: UTC timestamp)
  --dry-run                     Print planned actions only
  --include-community           Include impact-community files in backup
  --ack-include-community       Required safety acknowledgement with --include-community
  -h, --help                    Show help

Note:
  Community files are excluded by default to prevent accidental rollback lanes
  from restoring unrelated admin/community backend state.
EOF
}

validate_backup_id() {
  local value="$1"
  [[ "$value" =~ ^[A-Za-z0-9._-]+$ ]]
}

is_core_path() {
  local relative_path="$1"
  local path
  for path in "${CORE_FILES[@]}"; do
    [[ "$relative_path" == "$path" ]] && return 0
  done
  return 1
}

is_community_path() {
  local relative_path="$1"
  local path
  for path in "${COMMUNITY_FILES[@]}"; do
    [[ "$relative_path" == "$path" ]] && return 0
  done
  return 1
}

is_allowed_relative_path() {
  local relative_path="$1"
  if is_core_path "$relative_path"; then
    return 0
  fi
  if [[ "$INCLUDE_COMMUNITY" -eq 1 ]] && is_community_path "$relative_path"; then
    return 0
  fi
  return 1
}

hash_file() {
  local file_path="$1"
  shasum -a 256 "$file_path" | awk '{print $1}'
}

mkdir_cmd() {
  local path="$1"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "[dry-run] mkdir -p $path"
  else
    mkdir -p "$path"
  fi
}

copy_cmd() {
  local source="$1"
  local destination="$2"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "[dry-run] cp $source $destination"
  else
    cp "$source" "$destination"
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    --include-community)
      INCLUDE_COMMUNITY=1
      shift
      ;;
    --ack-include-community)
      ACK_INCLUDE_COMMUNITY=1
      shift
      ;;
    --backup-id)
      BACKUP_ID="${2:?missing backup id}"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if ! validate_backup_id "$BACKUP_ID"; then
  echo "Invalid backup id: ${BACKUP_ID}" >&2
  exit 1
fi

if [[ "$INCLUDE_COMMUNITY" -eq 1 && "$ACK_INCLUDE_COMMUNITY" -ne 1 ]]; then
  echo "Refusing community backup without explicit acknowledgement." >&2
  echo "Use: --include-community --ack-include-community" >&2
  exit 1
fi

TARGET_DIR="${BACKUP_BASE}/${BACKUP_ID}"
MANIFEST_PATH="${TARGET_DIR}/manifest.txt"

FILES=("${CORE_FILES[@]}")
if [[ "$INCLUDE_COMMUNITY" -eq 1 ]]; then
  FILES+=("${COMMUNITY_FILES[@]}")
fi

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "[dry-run] backup id: ${BACKUP_ID}"
  echo "[dry-run] target dir: ${TARGET_DIR}"
fi

mkdir_cmd "$TARGET_DIR"

if [[ "$DRY_RUN" -eq 0 ]]; then
  {
    echo "BACKUP_ID=${BACKUP_ID}"
    echo "CREATED_AT=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "INCLUDE_COMMUNITY=${INCLUDE_COMMUNITY}"
    echo "FILES"
  } > "$MANIFEST_PATH"
fi

for relative_path in "${FILES[@]}"; do
  if ! is_allowed_relative_path "$relative_path"; then
    echo "skip disallowed path: ${relative_path}" >&2
    continue
  fi

  source_path="${ROOT_DIR}/${relative_path}"
  if [[ ! -f "$source_path" ]]; then
    echo "skip missing: ${relative_path}"
    continue
  fi

  target_path="${TARGET_DIR}/${relative_path}"
  mkdir_cmd "$(dirname "$target_path")"
  copy_cmd "$source_path" "$target_path"

  if [[ "$DRY_RUN" -eq 0 ]]; then
    echo -e "FILE\t$(hash_file "$source_path")\t$relative_path" >> "$MANIFEST_PATH"
  fi
done

echo "backup ready: ${TARGET_DIR}"