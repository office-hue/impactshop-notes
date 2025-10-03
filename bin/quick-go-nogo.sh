#!/usr/bin/env bash
# 30-second go/no-go triage
set -euo pipefail

printf '🚦 QUICK GO/NO-GO CHECK\n'
printf '========================\n'
printf '📅 %s\n\n' "$(date '+%Y-%m-%d %H:%M:%S')"

source ~/.production_env 2>/dev/null || source ~/.staging_env 2>/dev/null || {
    printf '❌ No environment configured.\n'
    exit 1
}

TARGET_URL="${STAGING_URL/staging.//}"
printf '🎯 Target URL: %s\n' "$TARGET_URL"

passes=0
total=6

check() {
    local label="$1"
    local command="$2"
    local pattern="$3"
    printf '🔍 %s: ' "$label"
    if eval "$command" 2>/dev/null | grep -Eq "$pattern"; then
        printf '✅ GO\n'
        passes=$((passes+1))
    else
        printf '❌ NO-GO\n'
    fi
}

check 'Homepage' "curl -sI '$TARGET_URL/' | awk 'NR==1{print \$2}'" '^(200|30[12])$'
check 'Canary' "curl -sI '$TARGET_URL/?ims=1' | awk 'NR==1{print \$2}'" '^(200|30[12])$'
check 'Redirect' "curl -sI '$TARGET_URL/go?u=https://example.com' | awk 'NR==1{print \$2}'" '^30[12]$'

if command -v ssh >/dev/null && [ -n "${DEPLOY_HOST:-}" ]; then
    check 'Symlinks' "ssh '$DEPLOY_HOST' 'test -L $DEPLOY_PATH/current && test -L $DEPLOY_PATH/current/wp-content/uploads && echo OK'" 'OK'
    check 'WordPress' "ssh '$DEPLOY_HOST' 'cd $DEPLOY_PATH/current && wp core is-installed && echo OK'" 'OK'
    check 'Safety Flags' "ssh '$DEPLOY_HOST' 'cd $DEPLOY_PATH/current && wp option get impact_disable_link_guard'" '^0$'
else
    printf '🔍 Symlinks: ⏭️ SKIPPED (no SSH)\n'
    printf '🔍 WordPress: ⏭️ SKIPPED (no SSH)\n'
    printf '🔍 Safety Flags: ⏭️ SKIPPED (no SSH)\n'
    passes=$((passes+3))
fi

printf '\n📊 Result: %d/%d GO checks\n' "$passes" "$total"

if [ "$passes" -eq "$total" ]; then
    printf '🟢 GO!\n'
    exit 0
elif [ "$passes" -ge $((total*4/5)) ]; then
    printf '🟡 MOSTLY GO — review warnings.\n'
    exit 1
fi

printf '🔴 NO-GO — address failing checks.\n'
exit 2
