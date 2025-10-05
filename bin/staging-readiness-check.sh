#!/bin/bash
set -e

echo "🧪 IMPACTSHOP STAGING READINESS CHECK"
echo "====================================="
echo "📅 $(date '+%Y-%m-%d %H:%M:%S')"
echo "🌍 STAGING_URL: ${STAGING_URL:-https://sharity.hu/impactshop-staging}"
echo

# --- REST endpointok ellenőrzése ---
declare -a endpoints=(
  "/wp-json/impact/v1/ticker"
  "/wp-json/impact/v1/leaderboard?tab=ngo"
  "/wp-json/impact/v1/leaderboard?tab=shop"
  "/wp-json/impact/v1/activity"
  "/wp-json/impact/v1/total"
)

for ep in "${endpoints[@]}"; do
  printf "🔎 %-50s " "$ep"
  code=$(curl -s -I -L -o /dev/null -w "%{http_code} %{url_effective}" "${STAGING_URL}${ep}")
  if [ "$code" = "200" ]; then
    echo "✅ ${code}"
  else
    echo "🟥 ${code}"
  fi
done

# --- MU pluginek (szerver oldalon) ---
echo
echo "🔍 MU plugin lista (rövid):"
if command -v wp >/dev/null 2>&1; then
  wp mu-plugin list --fields=name,status | sed -n '1,120p' || true
else
  echo "ℹ️  Helyben nincs wp-cli — ez normális (a szerveren fut)."
fi

# --- Rövidkód próbarender (távoli környezetben használd a deploy után) ---
echo
echo "ℹ️  Rövidkód-render tesztet a szerveren 'wp eval' parancsokkal végezd, ha kell."
echo
echo "✅ Readiness script lefutott."
