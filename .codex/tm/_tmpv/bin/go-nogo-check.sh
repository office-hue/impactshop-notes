#!/usr/bin/env bash
# Quick go/no-go decision helper for production deployment
set -euo pipefail

printf '🚦 GO/NO-GO DECISION MATRIX\n'
printf '==========================\n'
printf '📅 %s\n\n' "$(date '+%Y-%m-%d %H:%M:%S')"

if [ -z "${DEPLOY_HOST:-}" ] || [ -z "${DEPLOY_PATH:-}" ] || [ -z "${STAGING_URL:-}" ]; then
    printf '❌ Environment variables not set!\n'
    printf '💡 Run: source ~/.staging_env\n'
    exit 1
fi

GO_CRITERIA=0
TOTAL_CRITERIA=6

check() {
    local label="$1"
    local command="$2"
    local pattern="$3"

    printf '🔍 %s: ' "$label"
    if eval "$command" 2>/dev/null | grep -Eq "$pattern"; then
        printf '✅ GO\n'
        GO_CRITERIA=$((GO_CRITERIA + 1))
    else
        printf '❌ NO-GO\n'
    fi
}

if ls staging-qa-*.log >/dev/null 2>&1 && grep -q 'ALL TESTS PASSED' "$(ls -t staging-qa-*.log | head -1)"; then
    printf '🔍 QA Suite: ✅ GO\n'
    GO_CRITERIA=$((GO_CRITERIA + 1))
else
    printf '🔍 QA Suite: ❌ NO-GO\n'
fi

check 'Rollback Capability' "ssh '$DEPLOY_HOST' 'ls -1t $DEPLOY_PATH/releases | wc -l'" '[2-9]'
check 'Safety Flags' "ssh '$DEPLOY_HOST' 'cd $DEPLOY_PATH/current && wp option get impact_disable_link_guard'" '0'
check 'HTTP Health' "curl -sI '$STAGING_URL/' | awk 'NR==1{print \$2}'" '^(200|30[12])$'
check 'Canary Function' "curl -sI '$STAGING_URL/?ims=1' | awk 'NR==1{print \$2}'" '^(200|30[12])$'
check 'Symlink Structure' "ssh '$DEPLOY_HOST' 'test -L $DEPLOY_PATH/current && test -L $DEPLOY_PATH/current/wp-content/uploads && echo OK'" 'OK'

printf '\n📊 GO criteria met: %d/%d\n' "$GO_CRITERIA" "$TOTAL_CRITERIA"

if [ "$GO_CRITERIA" -eq "$TOTAL_CRITERIA" ]; then
    printf '\n🟢 PRODUCTION GO-LIVE APPROVED!\n'
    exit 0
fi

if [ "$GO_CRITERIA" -gt $((TOTAL_CRITERIA * 4 / 5)) ]; then
    printf '\n🟡 CONDITIONAL GO — review remaining issues.\n'
    exit 1
fi

printf '\n🔴 NO-GO — resolve issues before production.\n'
exit 2
