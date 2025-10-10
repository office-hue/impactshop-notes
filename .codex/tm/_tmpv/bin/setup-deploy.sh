#!/usr/bin/env bash
# Impact Shop Deploy Setup - első beállítás
set -euo pipefail

HOST="${1:-}"
if [ -z "$HOST" ]; then
    echo "❌ Használat: bin/setup-deploy.sh user@server"
    echo "💡 Példa: bin/setup-deploy.sh deploy@impactshop.hu"
    exit 1
fi

APP_DIR="${DEPLOY_PATH:-/var/www/impactshop.hu}"

echo "🏗️ IMPACT SHOP DEPLOY SETUP"
echo "============================="
echo "🎯 Target server: $HOST"
echo "📁 App directory: $APP_DIR"
echo "📅 $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# SSH kapcsolat ellenőrzés
echo "🔍 SSH connection test..."
if ! ssh -o ConnectTimeout=10 "$HOST" "echo '✅ SSH connection OK'"; then
    echo "❌ SSH connection failed!"
    echo "💡 Ellenőrizd:"
    echo "   - SSH kulcs beállítva?"
    echo "   - Szerver elérhető?"
    echo "   - User létezik és sudo joga van?"
    exit 1
fi

# Szerver oldali könyvtár struktúra létrehozása
echo "📁 Creating directory structure..."
ssh "$HOST" "
    set -e
    
    # Alapkönyvtárak
    sudo mkdir -p '$APP_DIR'/{releases,shared}
    sudo mkdir -p '$APP_DIR/shared/wp-content/uploads'
    
    # Jogosultságok beállítása
    sudo chown -R \$USER:www-data '$APP_DIR'
    sudo chmod -R 775 '$APP_DIR/shared/wp-content/uploads'
    
    echo '📊 Directory structure:'
    ls -la '$APP_DIR'
    
    echo '📊 Permissions:'
    ls -la '$APP_DIR/shared/wp-content/'
"

# Script jogosultságok helyi gépén
echo "🔧 Setting local script permissions..."
chmod +x bin/setup-deploy.sh
chmod +x bin/deploy.sh 2>/dev/null || echo "⚠️ bin/deploy.sh not found yet"
chmod +x bin/rollback.sh 2>/dev/null || echo "⚠️ bin/rollback.sh not found yet"

# Környezeti változók javaslat
echo ""
echo "✅ SETUP SIKERES!"
echo "=================="
echo "📝 Következő lépések:"
echo ""
echo "1️⃣ Környezeti változók beállítása:"
echo "   export DEPLOY_HOST='$HOST'"
echo "   export DEPLOY_PATH='$APP_DIR'"
echo ""
echo "2️⃣ Webszerver konfiguráció:"
echo "   Nginx/Apache root: $APP_DIR/current"
echo ""
echo "3️⃣ Első deploy:"
echo "   bin/deploy.sh"
echo ""
echo "4️⃣ Rollback teszt:"
echo "   bin/rollback.sh <timestamp>"
