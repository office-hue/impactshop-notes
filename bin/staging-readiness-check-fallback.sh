#!/usr/bin/env bash
set -euo pipefail
echo "🧪 IMPACTSHOP STAGING READINESS CHECK (FALLBACK)"
echo "=============================================="
STAGING_URL="${STAGING_URL:-https://app.sharity.hu/impactshop-staging}"
echo "📅 $(date '+%Y-%m-%d %H:%M:%S')"
echo "🌍 STAGING_URL: $STAGING_URL"; echo
test_ep(){ n="$1"; ep="$2";
  url="$STAGING_URL$ep"; s=$(curl -s -o /dev/null -w "%{http_code}" "$url" || echo 000);
  if [[ "$s" =~ ^(200|30[12])$ ]]; then echo "🔎 $n 🟢 $s $url"; return 0; fi
  alt="${ep#/wp-json}"; alt_url="$STAGING_URL/index.php?rest_route=$alt"; s2=$(curl -s -o /dev/null -w "%{http_code}" "$alt_url" || echo 000);
  if [[ "$s2" =~ ^(200|30[12])$ ]]; then echo "🔎 $n 🟡 $s2 $alt_url (alt)"; return 0; fi
  echo "🔎 $n 🟥 $s $url  | alt 🟥 $s2 $alt_url"; return 1; }
echo "🔍 Testing REST endpoints..."
ok=0; fail=0
for p in "/wp-json/impact/v1/ticker" \
         "/wp-json/impact/v1/leaderboard?tab=ngo" \
         "/wp-json/impact/v1/leaderboard?tab=shop" \
         "/wp-json/impact/v1/activity" \
         "/wp-json/impact/v1/total"; do
  test_ep "$p" "$p" && ((ok++)) || ((fail++))
done
echo; echo "✅ ok=$ok | ❌ fail=$fail"
