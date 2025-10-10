#!/usr/bin/env bash
set -euo pipefail
log(){ printf "%b\n" "$*"; }
have(){ command -v "$1" >/dev/null 2>&1; }
json_kv(){ printf '"%s":"%s"' "$1" "$(printf '%s' "$2" | sed 's/"/\\"/g')"; }

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$ROOT"; mkdir -p .codex

# Env betöltés
[ -f .staging_env ] && source .staging_env || true
[ -f .deploy.production.env ] && source .deploy.production.env || true
[ -f .production_env ] && source .production_env || true

SSH_HOST="${SSH_HOST:-}"
STAGING_URL="${STAGING_URL:-https://sharity.hu/impactshop-staging}"
STAGING_PATH="${STAGING_PATH:-/home/sharityh/app-staging}"
PROD_URL="${DEPLOY_URL:-https://app.sharity.hu/impactshop}"
REMOTE_WP_PATH="${REMOTE_WP_PATH:-/home/sharityh/app}"

log "🔌 ImpactCTL Connect – ImpactShop"
log "📁 Project: $ROOT"
log "🌐 Staging URL: $STAGING_URL"
log "🌐 Prod URL:    $PROD_URL"

for bin in jq curl rsync ssh; do have "$bin" || { echo "❌ $bin hiányzik"; exit 1; }; done
have wp || log "ℹ️  wp (WP-CLI) nincs lokálisan – nem gond."

# SSH próba
if [ -n "$SSH_HOST" ]; then
  ssh -o BatchMode=yes -o ConnectTimeout=6 "$SSH_HOST" 'echo ok' 2>/dev/null | grep -q '^ok$' \
    && log "✅ SSH ok" || log "⚠️ SSH nem elérhető BatchMode-ban (nem blokkoló)."
else
  log "⚠️  SSH_HOST nincs beállítva – távoli próba kihagyva."
fi

# Codex context frissítés: impactctl refresh → codex-refresh fallback
REFRESH_OK=0
[ -x ./impactctl ] && ./impactctl refresh >/dev/null 2>&1 && REFRESH_OK=1 || true
[ $REFRESH_OK -eq 1 ] || { [ -x ./bin/codex-refresh.sh ] && ./bin/codex-refresh.sh >/dev/null 2>&1 || true; }
[ -f ./.codex/context-latest.json ] && log "✅ Context: .codex/context-latest.json" || log "⚠️  Nincs context-latest.json"

# REST quick check – IPv4; totals és total is próbálva
probe(){ local url="$1" label="$2"; code=$(curl -4 -m 8 -s -o /tmp/impact_probe.json -w "%{http_code}" "$url" || true); printf "  • %s → %s\n" "$label" "$code"; }
log "🧪 REST quick checks:"
probe "$PROD_URL" "Front (prod)"
probe "https://app.sharity.hu/wp-json/impact/v1/ticker" "ticker"
probe "https://app.sharity.hu/wp-json/impact/v1/leaderboard?tab=ngo" "leaderboard"
probe "https://app.sharity.hu/wp-json/impact/v1/activity" "activity"
code_totals=$(curl -4 -m 8 -s -o /tmp/impact_probe.json -w "%{http_code}" "https://app.sharity.hu/wp-json/impact/v1/" || true)
if [ "$code_totals" = "000" ] || [ "$code_totals" = "404" ]; then
  code_total=$(curl -4 -m 8 -s -o /tmp/impact_probe.json -w "%{http_code}" "https://app.sharity.hu/wp-json/impact/v1/total" || true)
  printf "  • totals→%s | total→%s\n" "$code_totals" "$code_total"
else
  printf "  • totals → %s\n" "$code_totals"
fi

# Marker + restore prompt
TS="$(date +%Y-%m-%dT%H:%M:%S%z)"
printf '{%s,%s,%s,%s,%s}\n' \
  "$(json_kv ts "$TS")" \
  "$(json_kv staging_url "$STAGING_URL")" \
  "$(json_kv prod_url "$PROD_URL")" \
  "$(json_kv ssh_host "${SSH_HOST:-}")" \
  "$(json_kv remote_wp_path "$REMOTE_WP_PATH")" > .codex/connection.json

cat > .codex/context-restore.txt <<'TXT'
Ez az ImpactShop projekt folytatása.
Töltsd be a feltöltött `.codex/context-latest.json` fájlt a memóriába,
és állítsd vissza a teljes projektállapotot (Codex, deploy pipeline, gate, staging/prod, snippetek).
Innen folytatjuk a Sora → `impactctl media-import` integráció tervezését.
TXT

log "📝 Állapot: .codex/connection.json"
log "✅ CONNECTED (MVP)."
log "   – Snapshot: .codex/context-latest.json (ha elérhető)"
log "   – Restore-prompt: .codex/context-restore.txt"
