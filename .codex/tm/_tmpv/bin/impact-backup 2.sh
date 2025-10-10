#!/usr/bin/env bash
# Impact Backup Script - Teljes mentési rendszer
# Használat: bin/impact-backup.sh [--git-only|--fs-only|--critical-only|--all]

set -euo pipefail
IFS=$'\n\t'

# Automatikus repo-gyökér detektálás
REPO_ROOT=$(git rev-parse --show-toplevel)
cd "$REPO_ROOT"

# Időbélyeg és mentési mappa
TS=$(date '+%Y%m%d-%H%M%S')
BACKUP_DIR="${REPO_ROOT}/.backups"
mkdir -p "$BACKUP_DIR"

# Argumentum parsing
MODE="${1:---all}"

echo "🛡️ Impact Backup System - $(date)"
echo "📁 Repository: $REPO_ROOT"
echo "⏰ Timestamp: $TS"
echo "🎯 Mode: $MODE"
echo "=" $(printf '=%.0s' {1..50})

# Git mentés funkció
backup_git() {
    echo "📦 Git Backup Process..."
    
    # Változások ellenőrzése
    if ! git diff-index --quiet HEAD --; then
        echo "  📝 Uncommitted changes found - committing..."
        git add -A
        git commit -m "🛡️ PRE-SAFETY backup ${TS}"
    else
        echo "  ✅ No uncommitted changes"
    fi
    
    # Tag létrehozása
    TAG_NAME="backup-pre-safety-${TS}"
    git tag -a "$TAG_NAME" -m "Pre-safety backup created at ${TS}"
    echo "  🏷️ Created tag: $TAG_NAME"
    
    # Push if remote exists
    if git remote get-url origin >/dev/null 2>&1; then
        echo "  ⬆️ Pushing to origin..."
        git push origin HEAD || echo "  ⚠️ Push failed, continuing..."
        git push --tags || echo "  ⚠️ Tag push failed, continuing..."
    else
        echo "  ⚠️ No origin remote found, skipping push"
    fi
    
    echo "  ✅ Git backup completed"
}

# Fájlrendszer mentés funkció
backup_filesystem() {
    echo "📁 Filesystem Backup Process..."
    
    TAR_FILE="${BACKUP_DIR}/impactshop-${TS}.tar.gz"
    
    echo "  📦 Creating tar archive: $(basename "$TAR_FILE")"
    tar -czf "$TAR_FILE" \
        --exclude='.git' \
        --exclude='.backups' \
        --exclude='node_modules' \
        --exclude='*.log' \
        .
    
    TAR_SIZE=$(du -h "$TAR_FILE" | cut -f1)
    echo "  📊 Archive size: $TAR_SIZE"
    echo "  ✅ Filesystem backup completed"
}

# Kritikus fájlok mentés funkció
backup_critical() {
    echo "🔧 Critical Files Backup Process..."
    
    CRITICAL_DIR="${BACKUP_DIR}/critical-${TS}"
    mkdir -p "$CRITICAL_DIR"
    
    # MU-plugins mentés
    if [ -d "wp-content/mu-plugins" ]; then
        echo "  📋 Backing up MU-plugins..."
        cp -r wp-content/mu-plugins "$CRITICAL_DIR/"
    fi
    
    # wp-config.php mentés (ha létezik)
    if [ -f "wp-config.php" ]; then
        echo "  ⚙️ Backing up wp-config.php..."
        cp wp-config.php "$CRITICAL_DIR/"
    fi
    
    # Módosítandó fájlok listája
    FILES_LIST="${BACKUP_DIR}/files-to-modify-${TS}.txt"
    cat > "$FILES_LIST" <<'LIST'
wp-content/mu-plugins/impact-api-url-fix.php
wp-content/mu-plugins/impact-diag.php
wp-content/mu-plugins/impact-combat-pack.php
wp-content/mu-plugins/sharity-impact-compat.php
wp-content/plugins/impact-mini-shortcodes/impact-mini-shortcodes.php
wp-content/plugins/impact-short codes-legacy/impact-short codes-legacy.php
wp-content/plugins/sharity-impact-mini/sharity-impact-mini.php
wp-content/mu-plugins/impactshop-boot.php
wp-content/mu-plugins/sharity-default-d1-helper.php
wp-content/mu-plugins/impact-arukereso-deeplink-fix.php
wp-content/mu-plugins/sharity-impact-banners-deals.php
LIST
    
    echo "  📄 Created modification list: $(basename "$FILES_LIST")"
    echo "  ✅ Critical files backup completed"
}

# Rollback DRY-RUN funkció
show_rollback() {
    echo "🔄 Rollback Commands (DRY-RUN)..."
    
    # Legutóbbi backup tag
    LATEST_TAG=$(git tag -l "backup-pre-safety-*" | sort -V | tail -1 || echo "")
    if [ -n "$LATEST_TAG" ]; then
        echo "  📋 Latest backup tag: $LATEST_TAG"
        echo "  🔧 Git rollback command:"
        echo "     git checkout $LATEST_TAG"
    else
        echo "  ⚠️ No backup tags found"
    fi
    
    # Fájlrendszer rollback
    LATEST_TAR=$(ls -1t "${BACKUP_DIR}"/impactshop-*.tar.gz 2>/dev/null | head -1 || echo "")
    if [ -n "$LATEST_TAR" ]; then
        echo "  🔧 Filesystem rollback commands:"
        echo "     cd $REPO_ROOT"
        echo "     rm -rf * .*"
        echo "     tar -xzf $LATEST_TAR"
    else
        echo "  ⚠️ No filesystem backups found"
    fi
    
    # SAFE MODE emergency
    echo "  🚨 Emergency SAFE MODE (if wp-config.php exists):"
    echo "     echo \"define('IMPACT_SAFE_MODE', true);\" >> wp-config.php"
}

# Fő végrehajtási logika
case "$MODE" in
    --git-only)
        backup_git
        ;;
    --fs-only)
        backup_filesystem
        ;;
    --critical-only)
        backup_critical
        ;;
    --all|*)
        backup_git
        backup_filesystem
        backup_critical
        show_rollback
        ;;
esac

# Összegzés
echo ""
echo "🎯 BACKUP SUMMARY"
echo "=" $(printf '=%.0s' {1..30})

if [[ "$MODE" == "--all" || "$MODE" == "--git-only" ]]; then
    CURRENT_TAG=$(git describe --tags --exact-match HEAD 2>/dev/null || echo "No tag")
    echo "📦 Git tag: $CURRENT_TAG"
fi

if [[ "$MODE" == "--all" || "$MODE" == "--fs-only" ]]; then
    TAR_FILE="${BACKUP_DIR}/impactshop-${TS}.tar.gz"
    if [ -f "$TAR_FILE" ]; then
        echo "📁 Tar file: $(basename "$TAR_FILE") ($(du -h "$TAR_FILE" | cut -f1))"
    fi
fi

if [[ "$MODE" == "--all" || "$MODE" == "--critical-only" ]]; then
    CRITICAL_DIR="${BACKUP_DIR}/critical-${TS}"
    if [ -d "$CRITICAL_DIR" ]; then
        echo "🔧 Critical dir: $(basename "$CRITICAL_DIR")"
    fi
fi

echo "✅ Backup process completed successfully!"
echo "📂 All backups stored in: .backups/"
