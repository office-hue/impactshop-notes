#!/usr/bin/env bash
# Robust Staging QA Test Suite — cPanel compatible
set -euo pipefail

NO_BLOCK=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-block)
      NO_BLOCK=1
      ;;
    -h|--help)
      cat <<USAGE
Usage: $0 [--no-block]

Runs QA checks against the staging environment. With --no-block the script
prints the summary but always exits 0 (useful for non-blocking CI capture).
USAGE
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      exit 2
      ;;
  esac
  shift
done

echo "🧪 STAGING QA SUITE (cPanel compatible)"
echo "========================================"
echo "📅 $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

STAGING_URL="${STAGING_URL:-https://sharity.hu/impactshop-staging}"
IMPACT_TOTAL_EP="${IMPACT_TOTAL_EP:-total}"
DEPLOY_HOST="${DEPLOY_HOST:-${SSH_HOST:-}}"
DEPLOY_PATH_RAW="${DEPLOY_PATH:-${STAGING_PATH:-}}"
DEPLOY_PATH="$DEPLOY_PATH_RAW"
[ -n "$DEPLOY_PATH" ] || DEPLOY_PATH="${REMOTE_WP_PATH:-}"

PASSED=0
TOTAL=0
LOG_FILE="staging-qa-$(date +%Y%m%d-%H%M%S).log"
touch "$LOG_FILE"

ssh_with_timeout() {
  local secs="${1:-10}"; shift
  local host="$1"; shift
  if command -v timeout >/dev/null 2>&1; then
    timeout "$secs" ssh -o ConnectTimeout="$secs" "$host" "$@"
  elif command -v gtimeout >/dev/null 2>&1; then
    gtimeout "$secs" ssh -o ConnectTimeout="$secs" "$host" "$@"
  else
    ssh -o ConnectTimeout="$secs" "$host" "$@"
  fi
}

detect_wp_cli() {
  local cand
  local wp_paths=(
    "/usr/local/bin/wp"
    "/usr/bin/wp"
    "wp"
    "/opt/cpanel/composer/bin/wp"
    "$HOME/bin/wp"
  )
  for cand in "${wp_paths[@]}"; do
    if ssh_with_timeout 5 "$DEPLOY_HOST" "command -v $cand >/dev/null 2>&1"; then
      printf '%s' "$cand"
      return 0
    fi
  done
  if [ -n "${APP_PATH:-}" ] && ssh_with_timeout 5 "$DEPLOY_HOST" "[ -f '$APP_PATH/wp-cli.phar' ]"; then
    printf 'php %s' "$APP_PATH/wp-cli.phar"
    return 0
  fi
  if ssh_with_timeout 5 "$DEPLOY_HOST" "[ -f '$DEPLOY_PATH/wp-cli.phar' ]"; then
    printf 'php %s' "$DEPLOY_PATH/wp-cli.phar"
    return 0
  fi
  if ssh_with_timeout 5 "$DEPLOY_HOST" "[ -f '$HOME/wp-cli.phar' ]"; then
    printf 'php %s' "\$HOME/wp-cli.phar"
    return 0
  fi
  printf ''
}

run_test() {
  local name="$1"
  local command="$2"
  local expected="${3:-}"
  local mode="${4:-output}"

  TOTAL=$((TOTAL + 1))
  echo "🧪 Test: $name"

  case "$mode" in
    http)
      local expect_re="${expected:-^(200|30[12])$}"
      local status
      status=$((eval "$command" || true) 2>/dev/null | awk 'NR==1{print $2}')
      if [[ "${status:-000}" =~ $expect_re ]]; then
        echo "   ✅ PASSED (HTTP ${status:-000})"
        PASSED=$((PASSED + 1))
      else
        echo "   ❌ FAILED (HTTP ${status:-000})"
        {
          echo "[HTTP FAIL] $name"
          echo "Command: $command"
          echo "Status: ${status:-000}"
          echo "Expected: $expect_re"
        } >>"$LOG_FILE"
      fi
      ;;
    ssh)
      if ssh_with_timeout 30 "$DEPLOY_HOST" "$command" >/dev/null 2>&1; then
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
      if (eval "$command" || true) 2>/dev/null | grep -Eq "$expected"; then
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

select_total_endpoint() {
  local base="$STAGING_URL/wp-json/impact/v1"
  local primary="$IMPACT_TOTAL_EP"
  local fallback="total"
  if [[ "$primary" == "total" ]]; then
    fallback="totals"
  fi

  local status_primary
  status_primary=$(curl -s -m 10 -o /dev/null -w "%{http_code}" "$base/$primary" 2>/dev/null || echo "000")

  TOTAL_ENDPOINT_PRIMARY="$primary"
  TOTAL_ENDPOINT="$base/$primary"
  TOTAL_ENDPOINT_STATUS="$status_primary"
  TOTAL_ENDPOINT_USED="$primary"
  TOTAL_ENDPOINT_FALLBACK=0

  if [[ "$status_primary" =~ ^(404|000)$ ]]; then
    local status_fallback
    status_fallback=$(curl -s -m 10 -o /dev/null -w "%{http_code}" "$base/$fallback" 2>/dev/null || echo "000")
    if [[ "$status_fallback" =~ ^2[0-9][0-9]$ ]]; then
      TOTAL_ENDPOINT="$base/$fallback"
      TOTAL_ENDPOINT_STATUS="$status_fallback"
      TOTAL_ENDPOINT_USED="$fallback"
      TOTAL_ENDPOINT_FALLBACK=1
    fi
  fi
}

if [ -z "${DEPLOY_HOST:-}" ]; then
  echo "❌ DEPLOY_HOST not set!"; echo "💡 source .staging_env"; exit 1; fi
if [ -z "${DEPLOY_PATH:-}" ] && [ -z "${STAGING_PATH:-}" ] && [ -z "${REMOTE_WP_PATH:-}" ]; then
  echo "❌ DEPLOY_PATH not set!"; echo "💡 source .staging_env"; exit 1; fi
if [ -z "${STAGING_URL:-}" ]; then
  echo "❌ STAGING_URL not set!"; echo "💡 source .staging_env"; exit 1; fi

select_total_endpoint

if [[ "$TOTAL_ENDPOINT_STATUS" =~ ^(404|000)$ ]]; then
  echo "⚠️  Impact totals endpoint nem elérhető (primary: $IMPACT_TOTAL_EP)" | tee -a "$LOG_FILE"
fi

echo "🔍 Testing SSH connectivity..."
if ! ssh_with_timeout 10 "$DEPLOY_HOST" "echo 'SSH OK'" >/dev/null 2>&1; then
  echo "❌ SSH connection failed! Enable SSH in cPanel."; exit 1; fi
echo "✅ SSH connectivity verified"

echo ""
if ssh_with_timeout 5 "$DEPLOY_HOST" "test -L '$DEPLOY_PATH/current'"; then
  APP_PATH="$DEPLOY_PATH/current"
  echo "🏗️ Deployment style: symlink (current -> releases)"
else
  APP_PATH="$DEPLOY_PATH"
  echo "🏗️ Deployment style: flat"
fi

echo "🏠 Environment:"
echo "   Host : $DEPLOY_HOST"
echo "   Path : $DEPLOY_PATH"
echo "   App  : $APP_PATH"
echo "   URL  : $STAGING_URL"
echo "   Total endpoint: $TOTAL_ENDPOINT (HTTP $TOTAL_ENDPOINT_STATUS)"
if [[ $TOTAL_ENDPOINT_FALLBACK -eq 1 ]]; then
  echo "   ⚠️  Fallback endpoint használva ($TOTAL_ENDPOINT_PRIMARY -> $TOTAL_ENDPOINT_USED)" | tee -a "$LOG_FILE"
fi
echo ""

WP_CLI=$(detect_wp_cli)
if [ -n "$WP_CLI" ]; then
  echo "🔧 WP-CLI: $WP_CLI"
else
  echo "⚠️ WP-CLI not detected"
fi
echo ""

echo "🏥 QUICK SANITY"
echo "================"
curl -sI "$STAGING_URL" | head -1 | sed 's/^/   /'
if curl -sI "$STAGING_URL/wp-json/impact/v1/ticker" >/dev/null 2>&1; then
  echo "   ✅ Impact REST (ticker) reachable"
else
  echo "   ⚠️ Impact REST ticker unreachable"
fi
if [[ "$TOTAL_ENDPOINT_STATUS" =~ ^2[0-9][0-9]$ ]]; then
  echo "   ✅ Impact totals reachable ($TOTAL_ENDPOINT_USED)"
else
  echo "   ⚠️ Impact totals unavailable (HTTP $TOTAL_ENDPOINT_STATUS)"
fi
echo ""

echo "1️⃣ HTTP TESTS"
echo "--------------"
run_test "Homepage"               "curl -sIL '$STAGING_URL/'"                               ""      http
run_test "Canary mode"            "curl -sIL '$STAGING_URL/?ims=1'"                         ""      http
run_test "WP admin"               "curl -sIL '$STAGING_URL/wp-admin/'"                      ""      http
run_test "Non-existent (404 exp.)" "curl -sIL '$STAGING_URL/nonexistent-$(date +%s)'"       "^404$" http
run_test "Impact totals"          "curl -sIL '$TOTAL_ENDPOINT'"                             "^200$" http

echo "2️⃣ REDIRECT TESTS"
echo "----------------"
run_test "/go valid u"            "curl -sIL '$STAGING_URL/go?u=https://example.com'"       ""      http
run_test "/go invalid u"          "curl -sIL '$STAGING_URL/go?u=invalid'"                   ""      http
run_test "/go-deal valid u"       "curl -sIL '$STAGING_URL/go-deal?u=https://example.com'"  ""      http
run_test "/go-deal invalid u"     "curl -sIL '$STAGING_URL/go-deal?u=invalid'"              ""      http
if [ -n "${SHOP_SLUG:-}" ]; then
  run_test "/go/<slug> + d1"      "curl -sIL '$STAGING_URL/go/'"$SHOP_SLUG"'?d1=test-ngo'" ""      http
  run_test "/go-deal/<slug>"      "curl -sIL \"$STAGING_URL/go-deal/$SHOP_SLUG?d1=test-ngo&u=$(printf %s 'https://example.com' | base64)\"" "" http
else
  echo "   ⏭️ SHOP_SLUG not set — slug tests skipped"
fi
echo ""

echo "3️⃣ WORDPRESS TESTS"
echo "-------------------"
if [ -n "$WP_CLI" ]; then
  run_test "WP core installed"    "cd '$APP_PATH' && $WP_CLI core is-installed"                   ""     ssh
  run_test "WP version"           "cd '$APP_PATH' && $WP_CLI core version"                        "^[0-9]" ssh
  run_test "Impact_Safety exists" "cd '$APP_PATH' && $WP_CLI eval 'echo class_exists(\"Impact_Safety\") ? \"true\" : \"false\";'" "true" output
  run_test "link_guard flag"      "cd '$APP_PATH' && $WP_CLI option get impact_disable_link_guard" "^0$" ssh
  run_test "MU plugin count"      "cd '$APP_PATH' && $WP_CLI plugin list --must-use --format=count" "^[0-9]+$" ssh
else
  echo "   ⏭️ WP-CLI not available"
fi
echo ""

echo "4️⃣ FILESYSTEM / DEPLOYMENT"
echo "---------------------------"
run_test "wp-config readable"     "test -r '$APP_PATH/wp-config.php'"                         "" ssh
run_test "No .env"                 "! test -f '$APP_PATH/.env'"                               "" ssh
run_test "No .git"                 "! test -d '$APP_PATH/.git'"                               "" ssh
run_test "Releases populated"     "test -d '$DEPLOY_PATH/releases'"                           "" ssh
echo ""

echo "5️⃣ SERVICES"
echo "------------"
run_test "PHP available"          "php -v | head -1" "PHP [0-9]" ssh
echo ""

echo "🎯 RESULT"
echo "=========="
echo "📊 Passed: $PASSED / $TOTAL"
if [ "$TOTAL" -gt 0 ]; then
  success=$(awk "BEGIN { printf \"%.1f\", ($PASSED/$TOTAL)*100 }")
else
  success="0.0"
fi
echo "📈 Success rate: ${success}%"
echo "📝 Log: $LOG_FILE"

exit_code=0
if [ "$PASSED" -eq "$TOTAL" ]; then
  echo "ALL TESTS PASSED" | tee -a "$LOG_FILE"
  exit_code=0
elif [ "$PASSED" -ge $((TOTAL * 8 / 10)) ]; then
  echo "MOSTLY PASSED - REVIEW NEEDED" | tee -a "$LOG_FILE"
  exit_code=1
else
  echo "CRITICAL FAILURES - INVESTIGATION REQUIRED" | tee -a "$LOG_FILE"
  exit_code=2
fi

if [ "$NO_BLOCK" -eq 1 ]; then
  echo "🟢 NO-BLOCK MODE: returning exit 0 despite failures" | tee -a "$LOG_FILE"
  exit 0
fi

exit "$exit_code"
