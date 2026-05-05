#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_BASE="${ROOT_DIR}/.codex/backups/impact-intl-runtime"
DRY_RUN=0
INCLUDE_COMMUNITY=0
ACK_INCLUDE_COMMUNITY=0
BACKUP_ID=""

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
Usage: scripts/impact-intl-runtime-rollback.sh --backup-id ID [options]

Options:
  --backup-id ID                Backup identifier to restore from (required)
  --dry-run                     Print planned restore actions only
  --include-community           Restore impact-community files too
  --ack-include-community       Required safety acknowledgement with --include-community
  -h, --help                    Show help

Note:
  Community files are skipped by default even if they are present in the manifest.
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

restore_cmd() {
  local source="$1"
  local destination="$2"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "[dry-run] cp $source $destination"
  else
    mkdir -p "$(dirname "$destination")"
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

if [[ -z "$BACKUP_ID" ]]; then
  echo "--backup-id is required" >&2
  usage >&2
  exit 1
fi

if ! validate_backup_id "$BACKUP_ID"; then
  echo "Invalid backup id: ${BACKUP_ID}" >&2
  exit 1
fi

if [[ "$INCLUDE_COMMUNITY" -eq 1 && "$ACK_INCLUDE_COMMUNITY" -ne 1 ]]; then
  echo "Refusing community rollback without explicit acknowledgement." >&2
  echo "Use: --include-community --ack-include-community" >&2
  exit 1
fi

TARGET_DIR="${BACKUP_BASE}/${BACKUP_ID}"
MANIFEST_PATH="${TARGET_DIR}/manifest.txt"

if [[ ! -f "$MANIFEST_PATH" ]]; then
  echo "Manifest not found: ${MANIFEST_PATH}" >&2
  exit 1
fi

in_files=0
while IFS= read -r line; do
  if [[ "$line" == "FILES" ]]; then
    in_files=1
    continue
  fi

  if [[ "$in_files" -eq 0 || -z "$line" ]]; then
    continue
  fi

  if [[ "$line" != FILE$'\t'* ]]; then
    continue
  fi

  IFS=$'\t' read -r kind expected_hash relative_path <<< "$line"
  if [[ "$kind" != "FILE" || -z "$relative_path" || -z "$expected_hash" ]]; then
    echo "skip malformed manifest entry" >&2
    continue
  fi

  if is_community_path "$relative_path" && [[ "$INCLUDE_COMMUNITY" -ne 1 ]]; then
    echo "skip community path (opt-in required): ${relative_path}" >&2
    continue
  fi

  if ! is_allowed_relative_path "$relative_path"; then
    echo "skip disallowed manifest path: ${relative_path}" >&2
    continue
  fi

  source_path="${TARGET_DIR}/${relative_path}"
  destination_path="${ROOT_DIR}/${relative_path}"

  if [[ ! -f "$source_path" ]]; then
    echo "skip missing backup file: ${relative_path}" >&2
    continue
  fi

  actual_hash="$(hash_file "$source_path")"
  if [[ "$actual_hash" != "$expected_hash" ]]; then
    echo "skip hash mismatch: ${relative_path}" >&2
    continue
  fi

  restore_cmd "$source_path" "$destination_path"
done < "$MANIFEST_PATH"

echo "rollback applied from: ${TARGET_DIR}"