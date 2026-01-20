#!/usr/bin/env bash
# Impact Shop Atomic Deploy
# Symlink-based WordPress deployment with rollback support
set -euo pipefail

# ---- KONFIG ----
HOST="${DEPLOY_HOST:-}"
APP_DIR="${DEPLOY_PATH:-/var/www/impactshop.hu}"

if [ -z "$HOST" ]; then
    echo "❌ DEPLOY_HOST környezeti változó nincs beállítva!"
    echo "💡 export DEPLOY_HOST='user@server'"
    exit 1
fi

RELEASES_DIR="$APP_DIR/releases"
SHARED_DIR="$APP_DIR/shared"
CURRENT_LINK="$APP_DIR/current"
KEEP_RELEASES=5
SSH_OPTS="-o StrictHostKeyChecking=yes"

# Rsync kizárások
EXCLUDES=(
    "--exclude=.git/"
    "--exclude=.github/"
    "--exclude=.backups/"
    "--exclude=bin/"
    "--exclude=bin/image/"
    "--exclude=node_modules/"
    "--exclude=vendor/"
    "--exclude=wp-content/uploads/"
    "--exclude=*.mp4"
    "--exclude=*.log"
    "--exclude=.DS_Store"
    "--exclude=*.tmp"
)

STAMP="$(date +%Y%m%d-%H%M%S)"
RELEASE_DIR="$RELEASES_DIR/$STAMP"

echo "🚀 IMPACT SHOP DEPLOY"
echo "======================"
echo "📅 Timestamp: $STAMP"
echo "🎯 Server: $HOST"
echo "📁 Release: $RELEASE_DIR"
echo "🔗 Current: $CURRENT_LINK"
echo ""

# ---- PRE-DEPLOY ELLENŐRZÉSEK ----
echo "🛡️ Pre-deploy checks..."

# Git állapot
if ! git diff --quiet; then
    echo "❌ Uncommitted changes found!"
    git status --porcelain
    echo "💡 Commit first: git add . && git commit -m 'deploy prep'"
    exit 1
fi

# Backup (opcionális)
if [ -x "bin/impact-backup.sh" ]; then
    echo "📦 Creating pre-deploy backup..."
    ./bin/impact-backup.sh --git-only || echo "⚠️ Backup failed, continuing..."
fi

# Git tag
git fetch --tags >/dev/null 2>&1 || true
TAG="deploy-$STAMP"
git tag -a "$TAG" -m "Deploy $STAMP" && echo "🏷️ Git tag: $TAG"

echo "✅ Pre-deploy checks passed"

# ---- RELEASE KÖNYVTÁR LÉTREHOZÁSA ----
echo "📁 Creating release directory..."
ssh $SSH_OPTS "$HOST" "
    mkdir -p '$RELEASE_DIR'
    mkdir -p '$SHARED_DIR/wp-content/uploads'
"

# ---- KÓDPUSH ----
echo "⬆️ Uploading files (excluding uploads)..."
rsync -avz --delete "${EXCLUDES[@]}" \
    --progress \
    "./" "$HOST:$RELEASE_DIR/"

echo "✅ Files uploaded successfully"

# ---- SHARED KÖNYVTÁRAK BEKÖTÉSE ----
echo "🔗 Linking shared directories..."
ssh $SSH_OPTS "$HOST" "
    # Uploads symlink
    ln -sfn '$SHARED_DIR/wp-content/uploads' '$RELEASE_DIR/wp-content/uploads'
    
    # Ellenőrzés
    test -L '$RELEASE_DIR/wp-content/uploads' && echo '✅ Uploads symlink OK'
"

# ---- MAINTENANCE MODE ----
echo "🔧 Activating maintenance mode..."
ssh $SSH_OPTS "$HOST" "
    cd '$RELEASE_DIR'
    
    # WordPress checks
    if [ -f wp-config.php ] && command -v wp >/dev/null 2>&1; then
        wp maintenance-mode activate || echo '⚠️ Maintenance activation failed'
        
        # Safety options (konzervatív start)
        wp option update impact_disable_link_guard 0 --quiet || true
        wp option update impact_aff_preserve_off 0 --quiet || true
        wp option update impact_disable_slug_normalizer 0 --quiet || true
        
        echo '✅ WordPress maintenance & safety options set'
    else
        echo '⚠️ WordPress not available or WP-CLI missing'
    fi
"

# ---- HEALTH CHECK ----
echo "🩺 Health check..."
ssh $SSH_OPTS "$HOST" "
    cd '$RELEASE_DIR'
    
    # Basic file structure
    test -f index.php || { echo '❌ Missing index.php'; exit 1; }
    test -d wp-content || { echo '❌ Missing wp-content'; exit 1; }
    test -d wp-content/mu-plugins || { echo '❌ Missing mu-plugins'; exit 1; }
    
    # WordPress health (if available)
    if command -v wp >/dev/null 2>&1 && [ -f wp-config.php ]; then
        wp core is-installed || { echo '❌ WordPress not properly installed'; exit 1; }
        echo '✅ WordPress health check passed'
    else
        echo '⚠️ WordPress health check skipped'
    fi
    
    echo '✅ Health check passed'
"

# ---- ATOMIKUS SWITCH ----
echo "⚡ Atomic deployment switch..."
ssh $SSH_OPTS "$HOST" "
    # Atomikus symlink váltás
    ln -sfn '$RELEASE_DIR' '$CURRENT_LINK'
    
    echo '✅ Symlink updated:'
    ls -la '$CURRENT_LINK'
"

# ---- POST-DEPLOY OPTIMALIZÁCIÓ ----
echo "🧹 Post-deploy optimization..."
ssh $SSH_OPTS "$HOST" "
    cd '$CURRENT_LINK'
    
    # Cache flush
    if command -v wp >/dev/null 2>&1; then
        wp cache flush || echo '⚠️ Cache flush failed'
    fi
    
    # PHP-FPM reload
    if command -v sudo >/dev/null 2>&1; then
        sudo systemctl reload php-fpm || sudo systemctl reload php8.2-fpm || echo '⚠️ PHP-FPM reload failed'
    fi
    
    echo '✅ Post-deploy optimization completed'
"

# ---- SMOKE TEST ----
echo "🧪 Smoke test..."
SMOKE_FAILED=false

# Basic HTTP test
if ! ssh $SSH_OPTS "$HOST" "curl -fsS -I http://localhost/ | head -n 1"; then
    echo "⚠️ Local HTTP test failed"
    SMOKE_FAILED=true
fi

# External test (if domain configured)
if ! curl -fsS -I https://impactshop.hu/ >/dev/null 2>&1; then
    echo "⚠️ External HTTPS test failed (normal if domain not configured yet)"
fi

if [ "$SMOKE_FAILED" = true ]; then
    echo "❌ SMOKE TEST FAILED!"
    echo "🔄 Rollback command: bin/rollback.sh $STAMP"
    echo "📋 Available releases:"
    ssh $SSH_OPTS "$HOST" "ls -1t '$RELEASES_DIR' | head -3"
    exit 1
fi

echo "✅ Smoke test passed"

# ---- FINALIZÁLÁS ----
echo "🏁 Finalizing deployment..."
ssh $SSH_OPTS "$HOST" "
    cd '$CURRENT_LINK'
    
    # Maintenance mode off
    if command -v wp >/dev/null 2>&1; then
        wp maintenance-mode deactivate || echo '⚠️ Maintenance deactivation failed'
    fi
    
    # Old releases cleanup
    cd '$RELEASES_DIR'
    echo 'Cleaning old releases (keeping $KEEP_RELEASES)...'
    ls -1tr | head -n -$KEEP_RELEASES | xargs -r rm -rf
    
    echo 'Current releases:'
    ls -1t | head -$KEEP_RELEASES
"

# ---- SUCCESS REPORT ----
echo ""
echo "🎉 DEPLOY SIKERES!"
echo "=================="
echo "📦 Release: $STAMP"
echo "🏷️ Git tag: $TAG"
echo "🔗 Active: $CURRENT_LINK -> $RELEASE_DIR"
echo ""
echo "🧪 Test URLs:"
echo "   http://impactshop.hu/"
echo "   http://impactshop.hu/?ims=1 (canary mode)"
echo ""
echo "🔄 Rollback (ha szükséges):"
echo "   bin/rollback.sh $STAMP"
echo ""
echo "📊 Következő deploy:"
echo "   bin/deploy.sh"
