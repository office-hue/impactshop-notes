#!/usr/bin/env bash
# Emergency brake utilities for production issues
set -euo pipefail

printf '🚨 EMERGENCY BRAKE SYSTEM\n'
printf '============================\n'

source ~/.production_env 2>/dev/null || {
    printf '❌ No production environment configured.\n'
    exit 1
}

if [ -z "${DEPLOY_HOST:-}" ] || [ -z "${DEPLOY_PATH:-}" ]; then
    printf '❌ DEPLOY_HOST or DEPLOY_PATH missing.\n'
    exit 1
fi

action="${1:-help}"

case "$action" in
    kill)
        printf '🔴 Activating SAFE MODE on production...\n'
        ssh "$DEPLOY_HOST" "cd '$DEPLOY_PATH/current' && \
            if grep -q 'IMPACT_SAFE_MODE' wp-config.php; then \
                perl -pi -e 's/define\(\'IMPACT_SAFE_MODE\',[^)]*\);/define(\'IMPACT_SAFE_MODE\', true);/;' wp-config.php; \
            else \
                echo "define('IMPACT_SAFE_MODE', true);" >> wp-config.php; \
            fi && wp cache flush >/dev/null 2>&1 && echo '✅ SAFE MODE ACTIVE'" || printf '⚠️ Failed to toggle safe mode.\n'
        ;;
    rollback)
        printf '🔄 Rolling back to previous release...\n'
        prev=$(ssh "$DEPLOY_HOST" "ls -1t '$DEPLOY_PATH/releases' | sed -n '2p'")
        if [ -z "$prev" ]; then
            printf '❌ No previous release found.\n'
            exit 1
        fi
        bin/rollback.sh "$prev"
        ;;
    flags)
        printf '🛡️ Disabling Impact modules via WP options...\n'
        ssh "$DEPLOY_HOST" "cd '$DEPLOY_PATH/current' && \
            wp option update impact_disable_link_guard 1 >/dev/null 2>&1 && \
            wp option update impact_disable_slug_normalizer 1 >/dev/null 2>&1 && \
            wp option update impact_aff_preserve_off 1 >/dev/null 2>&1 && \
            wp cache flush >/dev/null 2>&1 && \
            echo '✅ All Impact modules disabled'" || printf '⚠️ Failed to update options.\n'
        ;;
    status)
        PROD_URL="${STAGING_URL/staging.//}"
        printf '🌐 HTTP status: '
        curl -sI "$PROD_URL/" | head -1 || printf 'Unavailable\n'
        printf '🔐 Admin status: '
        curl -sI "$PROD_URL/wp-admin/" | head -1 || printf 'Unavailable\n'
        printf '🍪 Canary status: '
        curl -sI "$PROD_URL/?ims=1" | head -1 || printf 'Unavailable\n'
        printf '\n📋 Recent log lines:\n'
        ssh "$DEPLOY_HOST" "sudo tail -n 10 /var/log/nginx/error.log 2>/dev/null || sudo tail -n 10 /var/log/apache2/error.log 2>/dev/null" || true
        ;;
    *)
        printf 'Usage:\n'
        printf '  bin/emergency-brake.sh kill      # Force SAFE MODE on production\n'
        printf '  bin/emergency-brake.sh rollback  # Roll back to previous release\n'
        printf '  bin/emergency-brake.sh flags     # Disable Impact modules via options\n'
        printf '  bin/emergency-brake.sh status    # Quick production status snapshot\n'
        ;;
esac
