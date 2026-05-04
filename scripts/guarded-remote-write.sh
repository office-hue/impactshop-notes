#!/usr/bin/env bash
set -euo pipefail

# Guarded remote write helper.
# Purpose: prevent accidental overwrite of production-critical files with stale/local-old copies.

HOST="${GUARDED_REMOTE_HOST:-s59}"
REMOTE_USER="${GUARDED_REMOTE_USER:-sharityh}"
LOCAL_FILE=""
REMOTE_FILE=""
DRY_RUN=0
ALLOW_SHRINK=0
FORCE=0

usage() {
  cat <<'EOF'
Usage:
  scripts/guarded-remote-write.sh \
    --local <path> \
    --remote <absolute-remote-path> \
    [--host <ssh-host>] \
    [--remote-user <user>] \
    [--dry-run] \
    [--allow-shrink] \
    [--force]

Examples:
  scripts/guarded-remote-write.sh \
    --local wp-content/mu-plugins/impact-community.php \
    --remote /home/sharityh/app/wp-content/mu-plugins/impact-community.php

Notes:
  - impact-community.php esetén extra guard fut (required symbols + anti-shrink check).
  - Minden futás backupot készít: <remote>.bak-YYYYmmdd-HHMMSS
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --local)
      LOCAL_FILE="${2:-}"
      shift 2
      ;;
    --remote)
      REMOTE_FILE="${2:-}"
      shift 2
      ;;
    --host)
      HOST="${2:-}"
      shift 2
      ;;
    --remote-user)
      REMOTE_USER="${2:-}"
      shift 2
      ;;
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    --allow-shrink)
      ALLOW_SHRINK=1
      shift
      ;;
    --force)
      FORCE=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 2
      ;;
  esac
done

if [[ -z "$LOCAL_FILE" || -z "$REMOTE_FILE" ]]; then
  echo "ERROR: --local and --remote are required." >&2
  usage
  exit 2
fi

if [[ ! -f "$LOCAL_FILE" ]]; then
  echo "ERROR: local file not found: $LOCAL_FILE" >&2
  exit 2
fi

if [[ "$REMOTE_FILE" != /* ]]; then
  echo "ERROR: --remote must be absolute path." >&2
  exit 2
fi

if [[ "$LOCAL_FILE" == *.php ]]; then
  php -l "$LOCAL_FILE" >/dev/null
fi

BASENAME="$(basename "$REMOTE_FILE")"
SSH_TARGET="${REMOTE_USER}@${HOST}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
REMOTE_BACKUP="${REMOTE_FILE}.bak-${TIMESTAMP}"
LOCAL_LINES="$(wc -l < "$LOCAL_FILE" | tr -d ' ')"

REMOTE_TMP="/tmp/${BASENAME}.guarded-${TIMESTAMP}"

if [[ "$BASENAME" == "impact-community.php" ]]; then
  required_symbols=(
    "ic_ngo_cegjelzo_client"
    "ic_rest_ngo_admin_company_search"
    "register_rest_route(\$ns, '/ngo/admin/company-search'"
  )

  for sym in "${required_symbols[@]}"; do
    if ! grep -Fq "$sym" "$LOCAL_FILE"; then
      echo "ERROR: local impact-community.php missing required symbol: $sym" >&2
      echo "Refusing deploy to prevent cégjelző regression." >&2
      exit 1
    fi
  done

  REMOTE_LINES="$(ssh "$SSH_TARGET" "if [ -f '$REMOTE_FILE' ]; then wc -l < '$REMOTE_FILE'; else echo 0; fi" | tr -d ' ')"
  if [[ -z "$REMOTE_LINES" ]]; then
    REMOTE_LINES=0
  fi

  if [[ "$REMOTE_LINES" -gt 0 && "$ALLOW_SHRINK" -ne 1 ]]; then
    # Hard block if local is >10% shorter than remote. This catches stale/cutoff file deploys.
    threshold=$(( REMOTE_LINES * 90 / 100 ))
    if [[ "$LOCAL_LINES" -lt "$threshold" ]]; then
      echo "ERROR: anti-shrink guard triggered for impact-community.php" >&2
      echo "  local lines : $LOCAL_LINES" >&2
      echo "  remote lines: $REMOTE_LINES" >&2
      echo "  threshold   : $threshold (90% of remote)" >&2
      echo "Use --allow-shrink only with explicit review." >&2
      exit 1
    fi
  fi
fi

echo "Guard summary:"
echo "  host        : $HOST"
echo "  remote_user : $REMOTE_USER"
echo "  local_file  : $LOCAL_FILE"
echo "  remote_file : $REMOTE_FILE"
echo "  local_lines : $LOCAL_LINES"
echo "  dry_run     : $DRY_RUN"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "DRY RUN: would create backup: $REMOTE_BACKUP"
  echo "DRY RUN: would upload to temp: $REMOTE_TMP"
  echo "DRY RUN: would move temp -> $REMOTE_FILE and lock permissions"
  exit 0
fi

ssh "$SSH_TARGET" "if [ ! -f '$REMOTE_FILE' ]; then echo 'ERROR: remote file missing: $REMOTE_FILE' >&2; exit 1; fi"

ssh "$SSH_TARGET" "cp '$REMOTE_FILE' '$REMOTE_BACKUP'"

echo "Backup created: $REMOTE_BACKUP"

scp "$LOCAL_FILE" "${SSH_TARGET}:${REMOTE_TMP}"

if [[ "$BASENAME" == *.php ]]; then
  ssh "$SSH_TARGET" "php -l '$REMOTE_TMP' >/dev/null"
fi

# Keep deploy atomic: upload to tmp, validate, then move into place.
if [[ "$FORCE" -eq 1 ]]; then
  ssh "$SSH_TARGET" "chmod 644 '$REMOTE_FILE' || true; mv '$REMOTE_TMP' '$REMOTE_FILE'; chmod 444 '$REMOTE_FILE' || true"
else
  ssh "$SSH_TARGET" "chmod 644 '$REMOTE_FILE'; mv '$REMOTE_TMP' '$REMOTE_FILE'; chmod 444 '$REMOTE_FILE'"
fi

if [[ "$BASENAME" == *.php ]]; then
  ssh "$SSH_TARGET" "php -l '$REMOTE_FILE' >/dev/null"
fi

echo "Deploy successful: $REMOTE_FILE"
echo "Backup path      : $REMOTE_BACKUP"
