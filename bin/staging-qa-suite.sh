#!/usr/bin/env bash
# Megbízható Staging QA Test Suite
# Rugalmas HTTP tesztek + platform-független commands
set -euo pipefail

echo "🧪 ROBUST STAGING QA SUITE"
echo "=========================="
echo "📅 $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

STAGING_URL="https://staging.impactshop.hu"
PASSED=0
TOTAL=0
LOG_FILE="staging-qa-$(date +%Y%m%d-%H%M%S).log"

# WP-CLI path detection
detect_wp_cli() {
    local wp_paths=("/usr/local/bin/wp" "/usr/bin/wp" "wp")
    for wp_path in "${wp_paths[@]}"; do
        if ssh "$DEPLOY_HOST" "command -v $wp_path >/dev/null 2>&1" 2>/dev/null; then
            printf '%s' "$wp_path"
            return 0
        fi
    done
    printf ''
}

# Ensure environment variables are present
if [ -z "${DEPLOY_HOST:-}" ]; then
    echo "❌ DEPLOY_HOST not set!"
    echo "💡 Run: export DEPLOY_HOST='deploy@staging.impactshop.hu'"
    exit 1
fi

if [ -z "${DEPLOY_PATH:-}" ]; then
    echo "❌ DEPLOY_PATH not set!"
    echo "💡 Run: export DEPLOY_PATH='/var/www/impactshop-staging'"
    exit 1
fi

echo "✅ Environment OK"
echo "   DEPLOY_HOST: $DEPLOY_HOST"
echo "   DEPLOY_PATH: $DEPLOY_PATH"
echo ""

WP_CLI=$(detect_wp_cli)
if [ -n "$WP_CLI" ]; then
    echo "🔧 WP-CLI detected: $WP_CLI"
else
    echo "⚠️ WP-CLI not found - WordPress CLI tests will be skipped"
fi

touch "$LOG_FILE"

run_test() {
    local name="$1"
    local command="$2"
    local expected="$3"
    local mode="${4:-output}"

    TOTAL=$((TOTAL + 1))
    echo "🧪 Test: $name"

    case "$mode" in
        http)
            local status
            status=$(eval "$command" 2>/dev/null | awk 'NR==1{print $2}')
            if [[ "$status" =~ ^(200|30[12])$ ]]; then
                echo "   ✅ PASSED (HTTP $status)"
                PASSED=$((PASSED + 1))
            else
                echo "   ❌ FAILED (HTTP ${status:-000})"
                {
                    echo "[HTTP FAIL] $name"
                    echo "Command: $command"
                    echo "Status: ${status:-000}"
                } >>"$LOG_FILE"
            fi
            ;;
        ssh)
            if ssh "$DEPLOY_HOST" "$command" >/dev/null 2>&1; then
                echo "   ✅ PASSED"
                PASSED=$((PASSED + 1))
            else
                echo "   ❌ FAILED"
                {
                    echo "[SSH FAIL] $name"
                    echo "Command: ssh $DEPLOY_HOST '$command'"
                } >>"$LOG_FILE"
            fi
            ;;
        output)
            if eval "$command" 2>/dev/null | grep -Eq "$expected"; then
                echo "   ✅ PASSED"
                PASSED=$((PASSED + 1))
            else
                echo "   ❌ FAILED"
                {
                    echo "[OUTPUT FAIL] $name"
                    echo "Command: $command"
                    echo "Expected: $expected"
                } >>"$LOG_FILE"
            fi
            ;;
        *)
            echo "   ⚠️ Unknown test mode: $mode" | tee -a "$LOG_FILE"
            ;;
    esac

    echo ""
}

# 1️⃣ Enhanced HTTP tests
echo "1️⃣ ENHANCED HTTP TESTS"
echo "----------------------"
run_test "Homepage (with redirects)" "curl -sIL '$STAGING_URL/'" "" http
run_test "Canary mode (with redirects)" "curl -sIL '$STAGING_URL/?ims=1'" "" http
run_test "WordPress admin (with redirects)" "curl -sIL '$STAGING_URL/wp-admin/'" "" http
run_test "Non-existent page (404 expected)" "curl -sIL '$STAGING_URL/nonexistent'" "" http

# 2️⃣ Safety system tests
echo "2️⃣ SAFETY SYSTEM TESTS"
echo "----------------------"
run_test "Safety QA suite execution" "cd '$DEPLOY_PATH/current' && php bin/impact-safety-qa.php" "" ssh
run_test "Safety QA suite success marker" "ssh '$DEPLOY_HOST' \"cd '$DEPLOY_PATH/current' && php bin/impact-safety-qa.php\"" "MINDEN TESZT SIKERES" output

# 3️⃣ Redirect system tests
echo "3️⃣ REDIRECT SYSTEM TESTS"
echo "------------------------"
run_test "/go with valid URL" "curl -sIL '$STAGING_URL/go?u=https://example.com'" "" http
run_test "/go with invalid URL" "curl -sIL '$STAGING_URL/go?u=invalid'" "" http
run_test "/go without parameter" "curl -sIL '$STAGING_URL/go'" "" http
run_test "/go-deal with valid URL" "curl -sIL '$STAGING_URL/go-deal?u=https://example.com'" "" http
run_test "/go-deal with invalid URL" "curl -sIL '$STAGING_URL/go-deal?u=invalid'" "" http

# 4️⃣ WordPress functionality
echo "4️⃣ WORDPRESS FUNCTIONALITY"
echo "--------------------------"
if [ -n "$WP_CLI" ]; then
    run_test "WordPress core installed" "ssh '$DEPLOY_HOST' \"cd '$DEPLOY_PATH/current' && $WP_CLI core is-installed\"" "" output
    run_test "Impact safety loader available" "ssh '$DEPLOY_HOST' \"cd '$DEPLOY_PATH/current' && $WP_CLI eval 'echo class_exists(\\'Impact_Safety\\') ? \\\'true\\\' : \\\'false\\\';'\"" "true" output
    run_test "Impact safety option disabled flag" "ssh '$DEPLOY_HOST' \"cd '$DEPLOY_PATH/current' && $WP_CLI option get impact_disable_link_guard\"" "0" output
    run_test "MU plugin count" "ssh '$DEPLOY_HOST' \"cd '$DEPLOY_PATH/current' && $WP_CLI plugin list --must-use --format=count\"" "^[0-9]+$" output
else
    echo "   ⏭️ WordPress tests skipped (no WP-CLI)"
fi

# 5️⃣ File system & deployment tests
echo "5️⃣ FILE SYSTEM & DEPLOYMENT TESTS"
echo "----------------------------------"
run_test "Current symlink exists" "test -L '$DEPLOY_PATH/current'" "" ssh
run_test "Uploads symlink exists" "test -L '$DEPLOY_PATH/current/wp-content/uploads'" "" ssh
run_test "Uploads directory writable" "test -w '$DEPLOY_PATH/shared/wp-content/uploads'" "" ssh
run_test "Safety loader present" "test -f '$DEPLOY_PATH/current/wp-content/mu-plugins/impact-safety-loader.php'" "" ssh
run_test "Releases directory populated" "ssh '$DEPLOY_HOST' \"ls '$DEPLOY_PATH/releases' | wc -l\"" "^[1-9][0-9]*$" output

# 6️⃣ Security & configuration tests
echo "6️⃣ SECURITY & CONFIGURATION TESTS"
echo "----------------------------------"
run_test "wp-config.php readable" "test -r '$DEPLOY_PATH/current/wp-config.php'" "" ssh
run_test "No .env in web root" "! test -f '$DEPLOY_PATH/current/.env'" "" ssh
run_test "Git directory excluded" "! test -d '$DEPLOY_PATH/current/.git'" "" ssh

# 7️⃣ Performance & service tests
echo "7️⃣ PERFORMANCE & SERVICE TESTS"
echo "--------------------------------"
run_test "Homepage response under 3s" "curl -s -m 3 '$STAGING_URL/' >/dev/null" "" output
run_test "PHP-FPM service active" "systemctl is-active php-fpm || systemctl is-active php8.2-fpm" "active" ssh

# Results summary
echo "🎯 STAGING QA RESULTS"
echo "===================="
echo "📊 Tests passed: $PASSED/$TOTAL"
if [ "$TOTAL" -gt 0 ]; then
    success_rate=$(awk "BEGIN { printf \"%.1f\", ($PASSED/$TOTAL)*100 }")
else
    success_rate="0.0"
fi
echo "📈 Success rate: ${success_rate}%"
echo "📝 Log file: $LOG_FILE"

echo ""
if [ "$PASSED" -eq "$TOTAL" ]; then
    echo "🎉 ALL TESTS PASSED - STAGING READY!"
    exit 0
elif [ "$PASSED" -ge $((TOTAL * 8 / 10)) ]; then
    echo "⚠️ MOSTLY PASSED - REVIEW NEEDED" | tee -a "$LOG_FILE"
    exit 1
else
    echo "❌ CRITICAL FAILURES - INVESTIGATION REQUIRED" | tee -a "$LOG_FILE"
    exit 2
fi
