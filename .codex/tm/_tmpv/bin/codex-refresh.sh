#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$ROOT"
mkdir -p .codex

# Env beolvasás (nem kötelező mind)
[ -f .staging_env ] && source .staging_env || true
[ -f .deploy.production.env ] && source .deploy.production.env || true
[ -f .production_env ] && source .production_env || true

TS="$(date +%Y%m%d-%H%M%S)"
STAGING_URL="${STAGING_URL:-https://sharity.hu/impactshop-staging}"
PROD_URL="${DEPLOY_URL:-https://app.sharity.hu/impactshop}"
SSH_HOST="${SSH_HOST:-}"
REMOTE_WP_PATH="${REMOTE_WP_PATH:-/home/sharityh/app}"

# Gyűjtsünk meta-információt (deploy log utolsó 50 sor, ha van)
DEPLOY_LOG=".codex/deploy-log.txt"
TAIL_LOG="$( [ -f "$DEPLOY_LOG" ] && tail -n 50 "$DEPLOY_LOG" | sed 's/"/\\"/g' || echo "" )"

# Állítsunk elő egy KONZISZTENS JSON-t (context-latest.json)
CONTEXT_LATEST=".codex/context-latest.json"
cat > "$CONTEXT_LATEST" <<JSON
{
  "ts": "$TS",
  "project_root": "$ROOT",
  "staging_url": "$STAGING_URL",
  "prod_url": "$PROD_URL",
  "ssh_host": "$SSH_HOST",
  "remote_wp_path": "$REMOTE_WP_PATH",
  "notes": "ImpactShop Codex context seed – staging/prod pipeline, env & meta for ChatGPT seeding.",
  "deploy_log_tail": "$(printf '%s' "$TAIL_LOG")"
}
JSON

# Készítsünk egy időszeletes másolatot is
cp -f "$CONTEXT_LATEST" ".codex/context-$TS.json"

# Kompat üzenetek a régi kimenetekhez
echo "🧠 Sharity ImpactShop – Codex Refresh"
echo "────────────────────────────────────────"
echo "✅ Codex snapshot created → $ROOT/.codex/context-$TS.json"
echo "✅ Codex latest context   → $ROOT/$CONTEXT_LATEST"
# Opcionálisan egy meta-fálj
cat > .codex/context-meta.json <<META
{"ts":"$TS","staging_url":"$STAGING_URL","prod_url":"$PROD_URL"}
META
