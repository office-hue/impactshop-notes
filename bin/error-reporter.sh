#!/usr/bin/env bash
# Enhanced error reporter for staging QA failures
set -euo pipefail

echo "🚨 STAGING QA ERROR REPORTER"
echo "============================"
echo "📅 $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

QA_LOG=$(ls -t staging-qa-*.log 2>/dev/null | head -1 || true)
if [ -z "${QA_LOG:-}" ]; then
  echo "❌ No staging QA log found"
  echo "💡 Run: bin/staging-qa-suite.sh"
  exit 1
fi

echo "📋 Analyzing: $QA_LOG"
if command -v stat >/dev/null 2>&1; then
  (stat -f "%Sm" "$QA_LOG" 2>/dev/null || stat -c "%y" "$QA_LOG" 2>/dev/null) | sed 's/^/📅 Created: /'
fi
echo ""

if grep -q "FAIL" "$QA_LOG"; then
  echo "🔍 FAILED TESTS DETECTED"
  echo "========================"
  HTTP_FAILS=$(grep -c "\[HTTP FAIL\]" "$QA_LOG" 2>/dev/null || echo 0)
  SSH_FAILS=$(grep -c "\[SSH FAIL\]" "$QA_LOG" 2>/dev/null || echo 0)
  OUT_FAILS=$(grep -c "\[OUTPUT FAIL\]" "$QA_LOG" 2>/dev/null || echo 0)
  echo "   HTTP:  $HTTP_FAILS"
  echo "   SSH:   $SSH_FAILS"
  echo "   OUT:   $OUT_FAILS"
  echo ""

  echo "📎 COPY-PASTE REPORT:"
  echo "---------------------"
  grep "\[.*FAIL\]" "$QA_LOG" | head -3 | while IFS= read -r line; do
    echo ""
    echo "FAILED TEST: ${line#*] }"
    case "$line" in
      "[HTTP FAIL]"*)
        code=$(grep -A3 -F "$line" "$QA_LOG" | grep -m1 "Status:" | awk '{print $2}')
        exp=$(grep -A3 -F "$line" "$QA_LOG" | grep -m1 "Expected:" | cut -d' ' -f2-)
        echo "HTTP CODE: ${code:-unknown}"
        echo "EXPECTED : ${exp:-200|30x}"
        ;;
    esac
    echo "LOG EXCERPT:"
    grep -A2 -F "$line" "$QA_LOG" | sed 's/^/   /'
  done
else
  echo "✅ No failures detected"
  if grep -q "ALL TESTS PASSED" "$QA_LOG"; then
    echo "🎉 Status: ALL TESTS PASSED"
  elif grep -q "MOSTLY PASSED" "$QA_LOG"; then
    echo "🟡 Status: MOSTLY PASSED - REVIEW NEEDED"
  fi
fi

echo ""
echo "📝 Log file: $QA_LOG"
