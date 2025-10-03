#!/usr/bin/env bash
# Quick fixes for staging environment issues
set -euo pipefail

printf '🔧 STAGING QUICK FIX TOOLKIT\n'
printf '=============================\n'

if [ -z "${DEPLOY_HOST:-}" ] || [ -z "${DEPLOY_PATH:-}" ] || [ -z "${STAGING_URL:-}" ]; then
    printf '❌ Environment variables not set!\n'
printf '💡 Run: source ~/.staging_env\n'
    exit 1
fi

source_env_msg=false

fix_http() {
    printf '🌐 Fixing HTTP issues...\n'
    ssh "$DEPLOY_HOST" "sudo systemctl reload php-fpm 2>/dev/null || sudo systemctl reload php8.2-fpm 2>/dev/null || sudo systemctl reload php8.1-fpm 2>/dev/null || echo '⚠️ PHP-FPM reload failed'"
    ssh "$DEPLOY_HOST" "sudo systemctl reload nginx 2>/dev/null || sudo systemctl reload apache2 2>/dev/null || echo '⚠️ Web server reload failed'"
    printf '✅ HTTP services reloaded\n'
}

fix_safety() {
    printf '🛡️ Resetting safety module flags...\n'
    ssh "$DEPLOY_HOST" "cd '$DEPLOY_PATH/current' && \
        wp option update impact_disable_link_guard 0 >/dev/null 2>&1 && \
        wp option update impact_disable_slug_normalizer 0 >/dev/null 2>&1 && \
        wp option update impact_aff_preserve_off 0 >/dev/null 2>&1 && \
        wp option delete impact_disable_qa_test >/dev/null 2>&1 && \
        wp option delete impact_circuit_breaker_open >/dev/null 2>&1 && \
        wp cache flush >/dev/null 2>&1 && \
        echo '✅ Safety flags reset'" || printf '⚠️ Safety reset encountered issues\n'
}

fix_symlinks() {
    printf '📁 Verifying symlink structure...\n'
    ssh "$DEPLOY_HOST" "cd '$DEPLOY_PATH' && \
        latest=\$(ls -1t releases | head -1) && \
        [ -n \"\$latest\" ] && ln -sfn releases/\$latest current && \
        mkdir -p shared/wp-content/uploads && \
        ln -sfn \"\$(pwd)/shared/wp-content/uploads\" current/wp-content/uploads && \
        sudo chown -R \$USER:www-data shared/wp-content/uploads 2>/dev/null || true && \
        sudo chmod -R 775 shared/wp-content/uploads 2>/dev/null || true && \
        echo '✅ Symlinks refreshed'"
}

show_log_tail() {
    ssh "$DEPLOY_HOST" "sudo tail -n 20 /var/log/nginx/error.log 2>/dev/null || sudo tail -n 20 /var/log/apache2/error.log 2>/dev/null || echo 'No web server error log.'"
}

action="${1:-help}"
case "$action" in
    http)
        fix_http
        ;;
    safety)
        fix_safety
        ;;
    symlinks)
        fix_symlinks
        ;;
    logs)
        show_log_tail
        ;;
    smoke)
        printf '🧪 Quick smoke test...\n'
        curl -sI "$STAGING_URL/" | head -3
        curl -sI "$STAGING_URL/?ims=1" | head -3
        ;;
    all)
        fix_http
        fix_safety
        fix_symlinks
        printf '🧪 Quick smoke test...\n'
        curl -sI "$STAGING_URL/" | head -3
        ;;
    help|*)
        printf 'Optional commands:\n'
        printf '  bin/staging-quick-fix.sh http     # Reload services\n'
        printf '  bin/staging-quick-fix.sh safety   # Reset safety flags\n'
        printf '  bin/staging-quick-fix.sh symlinks # Refresh symlinks\n'
        printf '  bin/staging-quick-fix.sh logs     # Tail server logs\n'
        printf '  bin/staging-quick-fix.sh smoke    # Quick curl smoke test\n'
        printf '  bin/staging-quick-fix.sh all      # Run all fixes\n'
        ;;
esac
