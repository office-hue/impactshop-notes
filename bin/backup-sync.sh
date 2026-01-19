#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO_ROOT=$(cd "${SCRIPT_DIR}/.." && pwd)
cd "$REPO_ROOT"

TARGET="${BACKUP_SYNC_TARGET:-}"
if [[ -z "$TARGET" ]]; then
  echo "❌ BACKUP_SYNC_TARGET nincs beállítva (pl. rsync cél vagy smb megosztás)."
  exit 1
fi

if [[ ! -d ".backups" ]]; then
  echo "❌ .backups könyvtár nem található a repóban."
  exit 1
fi

echo "📤 Syncing git bundle backups to ${TARGET}"
rsync -av --include 'impactshop-git-*.bundle' --include 'git-status-*.txt' --include 'working-tree-*.patch' --exclude '*' .backups/ "$TARGET" || {
  echo "❌ rsync sikertelen."
  exit 1
}

echo "✅ Backup bundle szinkron befejezve."
