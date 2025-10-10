#!/usr/bin/env bash
# sharity.hu cPanel environment setup (portable)
set -euo pipefail

echo "🏠 SHARITY.HU CPANEL DEPLOYMENT SETUP"
echo "====================================="
echo "📋 This will create ~/.staging_env and ~/.production_env"
echo ""

read -p "🏠 cPanel SSH hostname: " CPANEL_HOST
read -p "👤 cPanel username   : " CPANEL_USER

DEFAULT_HOME="/home/$CPANEL_USER"
echo "📁 Home dir default: $DEFAULT_HOME"
read -p "📁 Home directory (Enter for default): " HOME_DIR
HOME_DIR=${HOME_DIR:-$DEFAULT_HOME}

echo ""
echo "🎯 Summary"
echo "----------"
echo "Host : $CPANEL_HOST"
echo "User : $CPANEL_USER"
echo "Home : $HOME_DIR"
echo "Staging URL   : https://sharity.hu/impactshop-staging"
echo "Production URL: https://sharity.hu/impactshop"
echo "Staging path  : $HOME_DIR/public_html/impactshop-staging"
echo "Production path: $HOME_DIR/public_html/impactshop"
echo ""
read -p "✅ Confirm? (yes/no): " ok
[ "$ok" = "yes" ] || { echo "❌ Cancelled"; exit 1; }

cat > "$HOME/.staging_env" <<ENV
# Sharity.hu Staging
export DEPLOY_HOST="$CPANEL_USER@$CPANEL_HOST"
export DEPLOY_PATH="$HOME_DIR/public_html/impactshop-staging"
export STAGING_URL="https://sharity.hu/impactshop-staging"
export CPANEL_MODE="true"
export CPANEL_USER="$CPANEL_USER"
export CPANEL_HOST="$CPANEL_HOST"
export HOME_DIR="$HOME_DIR"
ENV

cat > "$HOME/.production_env" <<ENV
# Sharity.hu Production
export DEPLOY_HOST="$CPANEL_USER@$CPANEL_HOST"
export DEPLOY_PATH="$HOME_DIR/public_html/impactshop"
export STAGING_URL="https://sharity.hu/impactshop"
export CPANEL_MODE="true"
export CPANEL_USER="$CPANEL_USER"
export CPANEL_HOST="$CPANEL_HOST"
export HOME_DIR="$HOME_DIR"
ENV

chmod 600 "$HOME/.staging_env" "$HOME/.production_env"
echo "✅ Wrote: ~/.staging_env, ~/.production_env"
echo ""

echo "🧪 SSH CONNECTIVITY TEST"
SSH_OK=false
if command -v timeout >/dev/null 2>&1; then
  timeout 15 ssh -o ConnectTimeout=10 -o BatchMode=yes "$CPANEL_USER@$CPANEL_HOST" "echo ok" 2>/dev/null && SSH_OK=true
elif command -v gtimeout >/dev/null 2>&1; then
  gtimeout 15 ssh -o ConnectTimeout=10 -o BatchMode=yes "$CPANEL_USER@$CPANEL_HOST" "echo ok" 2>/dev/null && SSH_OK=true
else
  ssh -o ConnectTimeout=10 -o BatchMode=yes "$CPANEL_USER@$CPANEL_HOST" "echo ok" 2>/dev/null && SSH_OK=true
fi

if $SSH_OK; then
  echo "✅ SSH OK"
  source "$HOME/.staging_env"
  echo ""
  echo "📁 Remote checks:"
  ssh "$DEPLOY_HOST" "
    echo 'Home: $HOME_DIR'
    test -d $HOME_DIR && echo '  ✅ Home exists' || echo '  ❌ Home missing'
    test -d $HOME_DIR/public_html && echo '  ✅ public_html exists' || echo '  ❌ public_html missing'
    php -v | head -1 2>/dev/null || echo '  ⚠️ PHP not available'
  " 2>/dev/null || true
  echo ""
  echo "🎯 Setup complete."
else
  echo "❌ SSH failed. Enable SSH in cPanel, import & authorize key, then retry."
fi

echo ""
echo "➡ Next:"
echo "   source ~/.staging_env"
echo "   bin/staging-qa-suite.sh"
