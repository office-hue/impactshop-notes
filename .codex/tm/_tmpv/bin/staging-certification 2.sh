#!/usr/bin/env bash
# Final Staging Certification before Production
set -euo pipefail

printf '🏆 STAGING CERTIFICATION GATEWAY\n'
printf '=================================\n'
printf '📅 %s\n\n' "$(date '+%Y-%m-%d %H:%M:%S')"

if [ -z "${DEPLOY_HOST:-}" ] || [ -z "${DEPLOY_PATH:-}" ] || [ -z "${STAGING_URL:-}" ]; then
    printf '❌ Environment variables not set!\n'
    printf '💡 Run: source ~/.staging_env\n'
    exit 1
fi

printf '🎯 Certification target:\n'
printf '   Host: %s\n' "$DEPLOY_HOST"
printf '   Path: %s\n' "$DEPLOY_PATH"
printf '   URL: %s\n\n' "$STAGING_URL"

declare -A CRITERIA
CRITERIA[qa_suite]='QA Suite Results'
CRITERIA[http_health]='HTTP Health Check'
CRITERIA[canary_mode]='Canary Mode Function'
CRITERIA[safety_system]='Safety System Status'
CRITERIA[file_structure]='File Structure Integrity'
CRITERIA[rollback_ready]='Rollback Readiness'
CRITERIA[performance]='Performance Baseline'

PASSED_CRITERIA=0
TOTAL_CRITERIA=${#CRITERIA[@]}
CERT_LOG="staging-certification-$(date '+%Y%m%d-%H%M%S').log"

printf '📋 CERTIFICATION CRITERIA (%d checks):\n' "$TOTAL_CRITERIA"
for key in "${!CRITERIA[@]}"; do
    printf '   • %s\n' "${CRITERIA[$key]}"
fi
printf '\n'

certify_criterion() {
    local key="$1"
    local name="${CRITERIA[$key]}"
    local command="$2"
    local pattern="$3"

    printf '🧪 Testing: %s\n' "$name"
    if eval "$command" 2>/dev/null | grep -Eq "$pattern"; then
        printf '   ✅ CERTIFIED: %s\n' "$name"
        PASSED_CRITERIA=$((PASSED_CRITERIA + 1))
        printf 'PASS: %s\n' "$name" >>"$CERT_LOG"
        return 0
    fi

    printf '   ❌ FAILED: %s\n' "$name"
    printf 'FAIL: %s | %s\n' "$name" "$command" >>"$CERT_LOG"
    return 1
}

printf '🔍 Pre-certification SSH check...\n'
if ! ssh -o ConnectTimeout=10 "$DEPLOY_HOST" "echo 'SSH OK'" >/dev/null 2>&1; then
    printf '❌ SSH connectivity failed!\n'
    printf '💡 Check SSH keys, network, and user access.\n'
    exit 1
fi
printf '✅ SSH connectivity verified\n\n'

printf '1️⃣ QA SUITE RESULTS\n'
printf '-------------------\n'
if ls staging-qa-*.log >/dev/null 2>&1; then
    LATEST_QA_LOG=$(ls -t staging-qa-*.log | head -1)
    printf '📋 Checking QA log: %s\n' "$LATEST_QA_LOG"
    if grep -q 'ALL TESTS PASSED' "$LATEST_QA_LOG"; then
        printf '   ✅ CERTIFIED: QA Suite Results\n'
        PASSED_CRITERIA=$((PASSED_CRITERIA + 1))
        printf 'PASS: QA Suite - ALL TESTS PASSED\n' >>"$CERT_LOG"
    else
        printf '   ❌ FAILED: QA Suite Results\n'
        printf 'FAIL: QA Suite - Not all tests passed\n' >>"$CERT_LOG"
        grep '❌ FAILED' "$LATEST_QA_LOG" | head -3 | sed 's/^/      /'
    fi
else
    printf '   ❌ FAILED: No QA log found\n'
    printf 'FAIL: QA Suite - No log file found\n' >>"$CERT_LOG"
fi
printf '\n'

printf '2️⃣ HTTP HEALTH CHECK\n'
printf '--------------------\n'
certify_criterion "http_health" "curl -sI '$STAGING_URL/' | awk 'NR==1{print \$2}'" '^(200|30[12])$'
printf '\n'

printf '3️⃣ CANARY MODE FUNCTION\n'
printf '-----------------------\n'
certify_criterion "canary_mode" "curl -sI '$STAGING_URL/?ims=1' | awk 'NR==1{print \$2}'" '^(200|30[12])$'
printf '\n'

printf '4️⃣ SAFETY SYSTEM STATUS\n'
printf '-----------------------\n'
if ssh "$DEPLOY_HOST" "cd '$DEPLOY_PATH/current' && php -r 'include \"wp-content/mu-plugins/impact-safety-loader.php\"; echo class_exists(\"Impact_Safety\") ? \"LOADED\" : \"MISSING\";'" 2>/dev/null | grep -q 'LOADED'; then
    printf '   ✅ CERTIFIED: Safety System Status\n'
    PASSED_CRITERIA=$((PASSED_CRITERIA + 1))
    printf 'PASS: Safety System - Impact_Safety loaded\n' >>"$CERT_LOG"
else
    printf '   ❌ FAILED: Safety System Status\n'
    printf 'FAIL: Safety System - Impact_Safety missing\n' >>"$CERT_LOG"
fi
printf '\n'

printf '5️⃣ FILE STRUCTURE INTEGRITY\n'
printf '---------------------------\n'
certify_criterion "file_structure" "ssh '$DEPLOY_HOST' 'test -L $DEPLOY_PATH/current && test -L $DEPLOY_PATH/current/wp-content/uploads && echo OK'" 'OK'
printf '\n'

printf '6️⃣ ROLLBACK READINESS\n'
printf '---------------------\n'
certify_criterion "rollback_ready" "ssh '$DEPLOY_HOST' 'ls -1t $DEPLOY_PATH/releases | wc -l'" '[2-9]'
printf '\n'

printf '7️⃣ PERFORMANCE BASELINE\n'
printf '-----------------------\n'
if timeout 5 curl -s "$STAGING_URL/" >/dev/null 2>&1; then
    printf '   ✅ CERTIFIED: Performance Baseline\n'
    PASSED_CRITERIA=$((PASSED_CRITERIA + 1))
    printf 'PASS: Performance - Response < 5s\n' >>"$CERT_LOG"
else
    printf '   ❌ FAILED: Performance Baseline\n'
    printf 'FAIL: Performance - Response timeout\n' >>"$CERT_LOG"
fi
printf '\n'

printf '🎯 FINAL CERTIFICATION VERDICT\n'
printf '==============================\n'
printf '📊 Criteria passed: %d/%d\n' "$PASSED_CRITERIA" "$TOTAL_CRITERIA"
if command -v bc >/dev/null 2>&1; then
    printf '📈 Success rate: %s%%\n' "$(echo "scale=1; $PASSED_CRITERIA*100/$TOTAL_CRITERIA" | bc)"
fi
printf '📝 Certification log: %s\n' "$CERT_LOG"

if [ "$PASSED_CRITERIA" -eq "$TOTAL_CRITERIA" ]; then
    printf '\n🏆 STAGING FULLY CERTIFIED! 🎉\n'
    printf '===============================\n'
    printf '✅ All certification criteria met\n'
    printf '🚀 APPROVED FOR PRODUCTION DEPLOYMENT\n\n'
    printf '📋 Next steps:\n'
    printf '   1. bin/prepare-production.sh\n'
    printf '   2. source ~/.production_env\n'
    printf '   3. bin/deploy.sh\n\n'
    exit 0
fi

if [ "$PASSED_CRITERIA" -gt $((TOTAL_CRITERIA * 4 / 5)) ]; then
    printf '\n🟡 STAGING CONDITIONALLY CERTIFIED\n'
    printf '==================================\n'
    printf '⚠️ Minor issues detected\n'
    printf '🔧 Consider running: bin/staging-quick-fix.sh all\n'
    exit 1
fi

printf '\n❌ STAGING CERTIFICATION FAILED\n'
printf '===============================\n'
printf '🚨 Critical issues prevent production deployment\n'
printf '🛠️ Fix issues then re-run certification\n'
printf '📋 Review log: %s\n' "$CERT_LOG"
exit 2
