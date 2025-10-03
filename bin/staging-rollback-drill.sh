#!/usr/bin/env bash
# Platform-független Staging Rollback Drill
set -euo pipefail

echo "🔄 ENHANCED STAGING ROLLBACK DRILL"
echo "=================================="
echo "📅 $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

if [ -z "${DEPLOY_HOST:-}" ] || [ -z "${DEPLOY_PATH:-}" ]; then
    echo "❌ Environment variables not set!"
    echo "💡 Run: source ~/.staging_env"
    exit 1
fi

echo "🎯 Target: $DEPLOY_HOST:$DEPLOY_PATH"

# Determine WP-CLI path
find_wp_cli() {
    local candidates=("/usr/local/bin/wp" "/usr/bin/wp" "wp")
    for candidate in "${candidates[@]}"; do
        if ssh "$DEPLOY_HOST" "command -v $candidate >/dev/null 2>&1" 2>/dev/null; then
            printf '%s' "$candidate"
            return 0
        fi
    done
    printf ''
}

WP_CLI=$(find_wp_cli)

# Identify current release
echo "📍 Current deployment status..."
CURRENT=$(ssh "$DEPLOY_HOST" "readlink '$DEPLOY_PATH/current' 2>/dev/null | xargs basename" 2>/dev/null || true)
if [ -z "$CURRENT" ]; then
    echo "❌ Unable to determine current release"
    exit 1
fi
echo "   Current release: $CURRENT"

# List releases
RELEASES=$(ssh "$DEPLOY_HOST" "ls -1t '$DEPLOY_PATH/releases'" 2>/dev/null || true)
if [ -z "$RELEASES" ]; then
    echo "❌ No releases found"
    exit 1
fi

echo "📋 Available releases:"
printf '%s
' "$RELEASES" | head -5 | nl

PREVIOUS=$(printf '%s
' "$RELEASES" | sed -n '2p')
if [ -z "$PREVIOUS" ]; then
    echo "❌ No previous release for rollback drill"
    exit 1
fi
echo "🎯 Previous release: $PREVIOUS"

echo "🧪 Pre-rollback smoke test..."
if curl -sIL "https://staging.impactshop.hu/" | awk 'NR==1{print $2}' | grep -Eq '^(200|30[12])$'; then
    echo "   ✅ Pre-rollback smoke test PASSED"
else
    echo "   ❌ Pre-rollback smoke test FAILED"
fi

start_ts=$(date +%s)

echo ""
echo "⚡ EXECUTING ROLLBACK: $CURRENT → $PREVIOUS"
if ! ./bin/rollback.sh "$PREVIOUS"; then
    echo "❌ Rollback execution failed"
    exit 1
fi

echo "🧪 Post-rollback verification..."
HTTP_OK=false
WP_OK=true
SYMLINK_OK=false

if curl -sIL "https://staging.impactshop.hu/" | awk 'NR==1{print $2}' | grep -Eq '^(200|30[12])$'; then
    echo "   ✅ HTTP smoke test PASSED"
    HTTP_OK=true
else
    echo "   ❌ HTTP smoke test FAILED"
fi

if [ -n "$WP_CLI" ]; then
    if ssh "$DEPLOY_HOST" "cd '$DEPLOY_PATH/current' && $WP_CLI core is-installed" >/dev/null 2>&1; then
        echo "   ✅ WordPress functionality PASSED"
    else
        echo "   ❌ WordPress functionality FAILED"
        WP_OK=false
    fi
fi

ROLLED_BACK=$(ssh "$DEPLOY_HOST" "readlink '$DEPLOY_PATH/current' | xargs basename" 2>/dev/null || true)
if [ "$ROLLED_BACK" = "$PREVIOUS" ]; then
    echo "   ✅ Symlink points to previous release"
    SYMLINK_OK=true
else
    echo "   ❌ Symlink mismatch (expected $PREVIOUS, got ${ROLLED_BACK:-unknown})"
fi

echo ""
echo "🔄 RESTORING ORIGINAL RELEASE: $PREVIOUS → $CURRENT"
if ! ./bin/rollback.sh "$CURRENT"; then
    echo "❌ Roll-forward failed – manual intervention required"
    exit 1
fi

echo "🧪 Final verification..."
FINAL_HTTP_OK=false
FINAL_WP_OK=true

if curl -sIL "https://staging.impactshop.hu/" | awk 'NR==1{print $2}' | grep -Eq '^(200|30[12])$'; then
    echo "   ✅ Final HTTP test PASSED"
    FINAL_HTTP_OK=true
else
    echo "   ❌ Final HTTP test FAILED"
fi

if [ -n "$WP_CLI" ]; then
    if ssh "$DEPLOY_HOST" "cd '$DEPLOY_PATH/current' && $WP_CLI core is-installed" >/dev/null 2>&1; then
        echo "   ✅ Final WordPress test PASSED"
    else
        echo "   ❌ Final WordPress test FAILED"
        FINAL_WP_OK=false
    fi
fi

end_ts=$(date +%s)
duration=$((end_ts - start_ts))

ALL_OK=true
for flag in "$HTTP_OK" "$WP_OK" "$SYMLINK_OK" "$FINAL_HTTP_OK" "$FINAL_WP_OK"; do
    if [ "$flag" != "true" ]; then
        ALL_OK=false
        break
    fi
done

echo ""
echo "🎯 ROLLBACK DRILL SUMMARY"
echo "========================="
echo "⏱️ Duration: ${duration}s"
if [ "$ALL_OK" = true ]; then
    echo "✅ Rollback drill successful"
    exit 0
else
    echo "❌ Rollback drill failed – review the output above"
    exit 1
fi
