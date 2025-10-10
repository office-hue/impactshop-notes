#!/usr/bin/env bash
# Automated diagnostics for staging environment
set -euo pipefail

printf '🔍 STAGING AUTO-DIAGNOSTIC\n'
printf '==========================\n'

if [ -z "${DEPLOY_HOST:-}" ] || [ -z "${DEPLOY_PATH:-}" ] || [ -z "${STAGING_URL:-}" ]; then
    printf '❌ Environment variables not set!\n'
    printf '💡 Run: source ~/.staging_env\n'
    exit 1
fi

diagnose_connectivity() {
    printf '🔗 Testing SSH connectivity...\n'
    if ssh -o ConnectTimeout=10 "$DEPLOY_HOST" "echo 'Connection OK'" >/dev/null 2>&1; then
        printf '   ✅ SSH: Connected\n'
        return 0
    fi
    printf '   ❌ SSH: Unreachable\n'
    return 1
}

diagnose_tools() {
    printf '🔧 Checking remote tools...\n'
    ssh "$DEPLOY_HOST" "
        check() { command -v \"$1\" >/dev/null 2>&1 && printf '✅' || printf '❌'; }
        printf '   php: '; check php; printf '\n'
        printf '   curl: '; check curl; printf '\n'
        printf '   wp: '; check wp; printf '\n'
        printf '   systemctl: '; check systemctl; printf '\n'
    "
}

diagnose_structure() {
    printf '📁 Checking deployment structure...\n'
    ssh "$DEPLOY_HOST" "
        printf '   Deploy path: '
        if [ -d '$DEPLOY_PATH' ]; then echo '✅'; else echo '❌'; fi
        printf '   Current link: '
        if [ -L '$DEPLOY_PATH/current' ]; then echo '✅'; else echo '❌'; fi
        printf '   Releases dir: '
        if [ -d '$DEPLOY_PATH/releases' ]; then echo '✅'; else echo '❌'; fi
        printf '   Uploads link: '
        if [ -L '$DEPLOY_PATH/current/wp-content/uploads' ]; then echo '✅'; else echo '❌'; fi
    "
}

diagnose_http() {
    printf '🌐 Testing HTTP endpoints...\n'
    status_root=$(curl -sI "$STAGING_URL/" | awk 'NR==1{print $2}')
    status_canary=$(curl -sI "$STAGING_URL/?ims=1" | awk 'NR==1{print $2}')
    printf '   Homepage: %s\n' "${status_root:-N/A}"
    printf '   Canary: %s\n' "${status_canary:-N/A}"
}

CONNECTIVITY_OK=false
TOOLS_OK=false
STRUCTURE_OK=false
HTTP_OK=false

diagnose_connectivity && CONNECTIVITY_OK=true
if [ "$CONNECTIVITY_OK" = true ]; then
    diagnose_tools && TOOLS_OK=true
    diagnose_structure && STRUCTURE_OK=true
fi
diagnose_http && HTTP_OK=true

printf '\n📊 DIAGNOSTIC SUMMARY\n'
printf '====================\n'
printf '   SSH: %s\n' "$([ "$CONNECTIVITY_OK" = true ] && echo '✅' || echo '❌')"
printf '   Tools: %s\n' "$([ "$TOOLS_OK" = true ] && echo '✅' || echo '❌')"
printf '   Structure: %s\n' "$([ "$STRUCTURE_OK" = true ] && echo '✅' || echo '❌')"
printf '   HTTP: %s\n' "$([ "$HTTP_OK" = true ] && echo '✅' || echo '❌')"

if [ "${1:-}" = "--fix" ] && [ "$CONNECTIVITY_OK" = true ]; then
    printf '\n🛠️ Running quick fixes...\n'
    bin/staging-quick-fix.sh all
fi

if [ "$CONNECTIVITY_OK" = false ]; then
    printf '\n🔴 Critical: fix SSH connectivity before proceeding.\n'
    exit 1
fi

if [ "$TOOLS_OK" = false ] || [ "$STRUCTURE_OK" = false ] || [ "$HTTP_OK" = false ]; then
    printf '\n🟡 Issues detected. Run: bin/staging-quick-fix.sh all\n'
    exit 1
fi

printf '\n🟢 All baseline checks passed. Ready for QA suite.\n'
