#!/usr/bin/env bash
# Production Go-Live - Complete Deployment Pipeline
set -euo pipefail

printf '🚀 PRODUCTION GO-LIVE PIPELINE\n'
printf '==============================\n'
printf '📅 %s\n\n' "$(date '+%Y-%m-%d %H:%M:%S')"

printf '⚠️ PRODUCTION DEPLOYMENT WARNING\n'
printf '================================\n'
printf '🔴 This script will deploy to the LIVE production server.\n'
printf '🔴 Ensure all staging validation steps passed (QA, rollback drill, certification).\n'
printf '🔴 A full backup will be created before deploying.\n\n'

read -rp '🚨 Continue with production deployment? (yes/no): ' confirm
if [ "$confirm" != "yes" ]; then
    printf '❌ Production deployment cancelled.\n'
    exit 0
fi

if [ -z "${STAGING_URL:-}" ]; then
    printf '❌ STAGING_URL not set. Run staging validation first.\n'
    exit 1
fi

START_TS=$(date +%s)
STEP_TS=$START_TS

step_duration() {
    local now shell_dur
    now=$(date +%s)
    shell_dur=$((now - STEP_TS))
    printf '   ⏱️ Step duration: %ss\n' "$shell_dur"
    STEP_TS=$now
}

printf '0️⃣ FINAL STAGING VERIFICATION\n'
printf '-----------------------------\n'

if [ ! -f "bin/staging-auto-diagnose.sh" ] || [ ! -f "bin/staging-qa-suite.sh" ] || [ ! -f "bin/staging-rollback-drill.sh" ]; then
    printf '❌ Staging validation scripts missing.\n'
    exit 1
fi

source ~/.staging_env 2>/dev/null || {
    printf '❌ No staging environment found.\n'
    exit 1
}

if ! bin/staging-auto-diagnose.sh >/dev/null 2>&1; then
    printf '❌ Staging diagnostics failed. Run: bin/staging-auto-diagnose.sh --fix\n'
    exit 1
fi

if ! bin/staging-qa-suite.sh >/dev/null 2>&1; then
    printf '❌ Staging QA suite failed. Resolve before production deploy.\n'
    exit 1
fi

if ! bin/staging-rollback-drill.sh >/dev/null 2>&1; then
    printf '❌ Staging rollback drill failed. Fix emergency path before production.\n'
    exit 1
fi

printf '✅ Staging environment green.\n'
step_duration
printf '\n'

printf '1️⃣ PRODUCTION ENVIRONMENT PREP\n'
printf '-------------------------------\n'

if [ ! -f "bin/prepare-production.sh" ]; then
    printf '❌ bin/prepare-production.sh missing.\n'
    exit 1
fi

bin/prepare-production.sh
source ~/.production_env 2>/dev/null || {
    printf '❌ Production environment not configured.\n'
    exit 1
}

printf '🎯 Production target: %s (%s)\n' "${DEPLOY_HOST:-unset}" "${DEPLOY_PATH:-unset}"

if [ -z "${DEPLOY_HOST:-}" ] || [ -z "${DEPLOY_PATH:-}" ]; then
    printf '❌ DEPLOY_HOST or DEPLOY_PATH missing.\n'
    exit 1
fi

printf '🔗 Testing SSH connectivity...\n'
if ! ssh -o ConnectTimeout=10 "$DEPLOY_HOST" "echo SSH OK" >/dev/null 2>&1; then
    printf '❌ Unable to reach production host %s.\n' "$DEPLOY_HOST"
    exit 1
fi
printf '✅ SSH connectivity confirmed.\n'
step_duration
printf '\n'

printf '2️⃣ FULL BACKUP BEFORE DEPLOY\n'
printf '-----------------------------\n'

if [ ! -x "bin/impact-backup.sh" ]; then
    printf '❌ bin/impact-backup.sh missing or not executable.\n'
    exit 1
fi

if ! ./bin/impact-backup.sh --all; then
    printf '❌ Backup failed. Deployment aborted.\n'
    exit 1
fi
step_duration
printf '\n'

printf '3️⃣ DEPLOY TO PRODUCTION\n'
printf '-----------------------\n'

if ! bin/deploy.sh; then
    printf '\n🚨 DEPLOYMENT FAILED! Initiate rollback with bin/rollback.sh auto\n'
    exit 1
fi
step_duration
printf '\n'

printf '4️⃣ PRODUCTION SMOKE TESTS\n'
printf '-------------------------\n'

PROD_URL="${STAGING_URL/staging.//}"
printf '🌐 Testing live URL: %s\n' "$PROD_URL"

homepage_status=$(curl -sI "$PROD_URL/" | awk 'NR==1{print $2}' || echo 000)
canary_status=$(curl -sI "$PROD_URL/?ims=1" | awk 'NR==1{print $2}' || echo 000)
admin_status=$(curl -sI "$PROD_URL/wp-admin/" | awk 'NR==1{print $2}' || echo 000)
go_status=$(curl -sI "$PROD_URL/go?u=https://example.com" | awk 'NR==1{print $2}' || echo 000)

printf '   🏠 Homepage: %s\n' "$homepage_status"
printf '   🍪 Canary: %s\n' "$canary_status"
printf '   🔐 Admin: %s\n' "$admin_status"
printf '   🔗 /go redirect: %s\n' "$go_status"

step_duration
printf '\n'

printf '5️⃣ FINAL GO/NO-GO\n'
printf '------------------\n'

cat > bin/production-go-nogo-check.sh <<'GOLOGO'
#!/usr/bin/env bash
set -euo pipefail
source ~/.production_env
PROD_URL="${STAGING_URL/staging.//}"
checks=0
printf '🚦 PRODUCTION GO/NO-GO CHECK\n'
printf '================================\n'

if curl -sI "$PROD_URL/" | awk 'NR==1{print $2}' | grep -Eq '^(200|30[12])$'; then
    printf '✅ HTTP Health\n'; checks=$((checks+1))
else
    printf '❌ HTTP Health\n'
fi

if curl -sI "$PROD_URL/?ims=1" | awk 'NR==1{print $2}' | grep -Eq '^(200|30[12])$'; then
    printf '✅ Canary Mode\n'; checks=$((checks+1))
else
    printf '❌ Canary Mode\n'
fi

if ssh "$DEPLOY_HOST" "cd $DEPLOY_PATH/current && wp core is-installed" >/dev/null 2>&1; then
    printf '✅ WordPress Core\n'; checks=$((checks+1))
else
    printf '❌ WordPress Core\n'
fi

if ssh "$DEPLOY_HOST" "test -L $DEPLOY_PATH/current && test -L $DEPLOY_PATH/current/wp-content/uploads"; then
    printf '✅ Symlink Structure\n'; checks=$((checks+1))
else
    printf '❌ Symlink Structure\n'
fi

if ssh "$DEPLOY_HOST" "cd $DEPLOY_PATH/current && php -r 'include \"wp-content/mu-plugins/impact-safety-loader.php\"; echo class_exists(\"Impact_Safety\") ? \"OK\" : \"FAIL\";'" | grep -q OK; then
    printf '✅ Safety Loader\n'; checks=$((checks+1))
else
    printf '❌ Safety Loader\n'
fi

printf '\n📊 Score: %d/5\n' "$checks"
[ "$checks" -eq 5 ]
GOLOGO

chmod +x bin/production-go-nogo-check.sh

if bin/production-go-nogo-check.sh; then
    printf '🟢 GO FOR PRODUCTION CONFIRMED\n'
else
    printf '🔴 FINAL CHECK FAILED — consider rollback\n'
fi

TOTAL_DURATION=$(( $(date +%s) - START_TS ))
printf '\n🎉 PRODUCTION DEPLOYMENT COMPLETE\n'
printf '================================\n'
printf '⏱️ Total duration: %ss\n' "$TOTAL_DURATION"
printf '🌐 Live URL: %s\n' "$PROD_URL"
printf '🔄 Rollback command: bin/rollback.sh auto\n'
printf '🧪 Consider running: bin/post-deploy-checklist.sh\n'
