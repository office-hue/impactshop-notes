#!/usr/bin/env bash
# Post-deployment verification checklist
set -euo pipefail

printf '📈 POST-DEPLOYMENT CHECKLIST\n'
printf '=============================\n'
printf '📅 %s\n\n' "$(date '+%Y-%m-%d %H:%M:%S')"

source ~/.production_env 2>/dev/null || {
    printf '❌ Production environment not loaded.\n'
    exit 1
}

PROD_URL="${STAGING_URL/staging.//}"
BASE_URL="$(printf '%s' "$PROD_URL" | awk -F/ '{print $1"//"$3}')"
printf '🎯 Production URL: %s\n' "$PROD_URL"
printf '🌍 Base host: %s\n\n' "$BASE_URL"

passed=0
total=5

# 1. Homepage & canary
printf '1️⃣ Homepage & Canary\n'
printf -- '--------------------\n'
home=$(curl -sI "$PROD_URL/" | awk 'NR==1{print $2}' || echo 000)
canary=$(curl -sI "$PROD_URL/?ims=1" | awk 'NR==1{print $2}' || echo 000)
printf '   🏠 %s\n   🍪 %s\n' "$home" "$canary"
if [[ "$home" =~ ^(200|30[12])$ ]] && [[ "$canary" =~ ^(200|30[12])$ ]]; then
    printf '   ✅ PASSED\n'
    passed=$((passed+1))
else
    printf '   ❌ FAILED\n'
fi
printf '\n'

# 2. Redirects
printf '2️⃣ Redirect system (/go, /go-deal)\n'
printf -- '----------------------------------\n'
go=$(curl -sI "$BASE_URL/go?u=https://example.com" | awk 'NR==1{print $2}' || echo 000)
go_deal=$(curl -sI "$BASE_URL/go-deal?u=https://example.com" | awk 'NR==1{print $2}' || echo 000)
printf '   /go: %s\n   /go-deal: %s\n' "$go" "$go_deal"
if [[ "$go" =~ ^30[12]$ ]] && [[ "$go_deal" =~ ^30[12]$ ]]; then
    printf '   ✅ PASSED\n'
    passed=$((passed+1))
else
    printf '   ❌ FAILED\n'
fi
printf '\n'

# 3. WordPress admin
printf '3️⃣ WordPress admin accessibility\n'
printf -- '--------------------------------\n'
admin=$(curl -sI "$BASE_URL/wp-admin/" | awk 'NR==1{print $2}' || echo 000)
printf '   /wp-admin/: %s\n' "$admin"
if [[ "$admin" =~ ^(200|30[12])$ ]]; then
    printf '   ✅ PASSED\n'
    passed=$((passed+1))
else
    printf '   ❌ FAILED\n'
fi
printf '\n'

# 4. Error logs
printf '4️⃣ Error log scan\n'
printf -- '-----------------\n'
if ssh "$DEPLOY_HOST" "tail -n 80 /home/sharityh/app/error_log 2>/dev/null || tail -n 80 /home/sharityh/app/wp-content/debug.log 2>/dev/null" | grep -E '(critical|fatal|PHP Fatal|Parse error)' >/tmp/error-scan.$$.log 2>/dev/null; then
    printf '   ❌ Critical errors found (see /tmp/error-scan.$$.log)\n'
else
    printf '   ✅ PASSED (no critical errors)\n'
    passed=$((passed+1))
fi
printf '\n'

# 5. Response time
printf '5️⃣ Response time (TTFB proxy)\n'
printf -- '-----------------------------\n'
rt=$(curl -o /dev/null -s -w '%{time_starttransfer}' "$PROD_URL/" || echo 9)
rt_ms=$(awk -v t="$rt" 'BEGIN { printf "%.0f", t*1000 }')
printf '   TTFB: %sms\n' "$rt_ms"
if [ "$rt_ms" -lt 2000 ]; then
    printf '   ✅ PASSED\n'
    passed=$((passed+1))
else
    printf '   ❌ FAILED (above 2000ms)\n'
fi
printf '\n'

printf '📊 Summary: %d/%d checks passed\n' "$passed" "$total"
if [ "$passed" -eq "$total" ]; then
    printf '🎉 Production deployment healthy.\n'
else
    printf '⚠️ Review failing items above.\n'
fi
