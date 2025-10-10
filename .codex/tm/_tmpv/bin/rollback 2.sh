#!/usr/bin/env bash
# Impact Shop Fast Rollback
# Quick rollback to previous release
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
CURRENT_LINK="$APP_DIR/current"
SSH_OPTS="-o StrictHostKeyChecking=yes"

TARGET="${1:-}"

echo "🔄 IMPACT SHOP ROLLBACK"
echo "======================="
echo "🎯 Server: $HOST"
echo "📅 $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# ---- TARGET MEGHATÁROZÁS ----
if [ -z "$TARGET" ]; then
    echo "❌ Target release nincs megadva!"
    echo ""
    echo "Használat: bin/rollback.sh <release-timestamp>"
    echo ""
    echo "📋 Elérhető releases (legújabb elől):"
    ssh $SSH_OPTS "$HOST" "ls -1t '$RELEASES_DIR' 2>/dev/null | head -10" || {
        echo "❌ Nem érem el a releases könyvtárat"
        exit 1
    }
    echo ""
    echo "💡 Példa: bin/rollback.sh 20251002-143522"
    echo "💡 Auto-rollback előzőre: bin/rollback.sh auto"
    exit 1
fi

# Auto rollback az előző release-re
if [ "$TARGET" = "auto" ]; then
    echo "🤖 Auto rollback mode..."
    TARGET=$(ssh $SSH_OPTS "$HOST" "ls -1t '$RELEASES_DIR' | sed -n '2p'")
    if [ -z "$TARGET" ]; then
        echo "❌ Nincs előző release!"
        exit 1
    fi
    echo "📍 Auto-selected target: $TARGET"
fi

# ---- VALIDÁCIÓ ----
echo "🔍 Validating target release..."

# Release létezik?
if ! ssh $SSH_OPTS "$HOST" "test -d '$RELEASES_DIR/$TARGET'"; then
    echo "❌ Release nem található: $RELEASES_DIR/$TARGET"
    echo ""
    echo "📋 Elérhető releases:"
    ssh $SSH_OPTS "$HOST" "ls -1t '$RELEASES_DIR'"
    exit 1
fi

# Jelenlegi release
CURRENT_RELEASE=$(ssh $SSH_OPTS "$HOST" "readlink '$CURRENT_LINK' 2>/dev/null" | xargs basename || echo "unknown")
echo "📍 Current release: $CURRENT_RELEASE"
echo "🎯 Target release: $TARGET"

if [ "$CURRENT_RELEASE" = "$TARGET" ]; then
    echo "⚠️ A target release már aktív!"
    echo "✅ Nincs mit rollback-elni"
    exit 0
fi

# ---- ROLLBACK VÉGREHAJTÁS ----
echo ""
echo "⚡ Executing rollback..."
echo "🔄 $CURRENT_RELEASE -> $TARGET"

START_TIME=$(date +%s)

ssh $SSH_OPTS "$HOST" "
    set -e
    
    cd '$RELEASES_DIR/$TARGET'
    
    # Maintenance mode (ha elérhető)
    if command -v wp >/dev/null 2>&1 && [ -f wp-config.php ]; then
        echo '🔧 Activating maintenance mode...'
        wp maintenance-mode activate || echo '⚠️ Maintenance activation failed'
    fi
    
    # Atomikus switch
    echo '⚡ Switching symlink...'
    ln -sfn '$RELEASES_DIR/$TARGET' '$CURRENT_LINK'
    
    cd '$CURRENT_LINK'
    
    # Cache flush
    if command -v wp >/dev/null 2>&1; then
        echo '🧹 Flushing cache...'
        wp cache flush || echo '⚠️ Cache flush failed'
    fi
    
    # PHP-FPM reload  
    if command -v sudo >/dev/null 2>&1; then
        echo '⚡ Reloading PHP-FPM...'
        sudo systemctl reload php-fpm || sudo systemctl reload php8.2-fpm || echo '⚠️ PHP-FPM reload failed'
    fi
    
    # Maintenance mode off
    if command -v wp >/dev/null 2>&1 && [ -f wp-config.php ]; then
        echo '✅ Deactivating maintenance mode...'
        wp maintenance-mode deactivate || echo '⚠️ Maintenance deactivation failed'
    fi
    
    echo '✅ Rollback completed'
"

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

# ---- SMOKE TEST ----
echo "🧪 Post-rollback smoke test..."
if ssh $SSH_OPTS "$HOST" "curl -fsS -I http://localhost/ | head -n 1 | grep -q '200 OK'"; then
    echo "✅ Smoke test passed"
else
    echo "⚠️ Smoke test failed - manual check recommended"
fi

# ---- SUCCESS REPORT ----
echo ""
echo "🎉 ROLLBACK SIKERES!"
echo "==================="
echo "⏱️ Duration: ${DURATION}s"
echo "🔄 Rolled back: $CURRENT_RELEASE -> $TARGET"
echo "🔗 Active: $CURRENT_LINK -> $RELEASES_DIR/$TARGET"
echo ""
echo "🧪 Test URLs:"
echo "   http://impactshop.hu/"
echo "   http://impactshop.hu/?ims=1"
echo ""
echo "🔮 Forward rollback (ha szükséges):"
echo "   bin/rollback.sh $CURRENT_RELEASE"
echo ""
echo "📊 Available releases:"
ssh $SSH_OPTS "$HOST" "ls -1t '$RELEASES_DIR' | head -5"
