#!/usr/bin/env bash
# Codex Refresh — újraindexeli a projektet és ment egy snapshotot
# (hogy a Codex / Copilot Chat mindig naprakész kontextussal induljon)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SNAPDIR="$ROOT/.codex"
SNAP="$SNAPDIR/context-snapshot.txt"
META="$SNAPDIR/context-meta.json"

mkdir -p "$SNAPDIR"

timestamp() { date +"%Y-%m-%d %H:%M:%S"; }

echo "🧠 Sharity ImpactShop – Codex Refresh"
echo "────────────────────────────────────────"
echo "📅 $(timestamp)" > "$SNAP"

# 1️⃣ Fő komponensek összefoglalása
echo "# Project: Sharity ImpactShop" >> "$SNAP"
echo "# Components: WordPress + Elementor, Dognet Publisher API, Impact Bridge Local REST, mu-plugins, deploy pipeline" >> "$SNAP"
echo "# Key scripts: impactctl, bin/deploy-wpcontent-map.sh, bin/preflight-check.sh, bin/post-deploy-activate.sh" >> "$SNAP"
echo "# Environments: .deploy.staging.env, .deploy.production.env" >> "$SNAP"
echo >> "$SNAP"

# 2️⃣ Fontos fájlok első pár sora
FILES=(
  "$ROOT/impactctl"
  "$ROOT/bin/deploy-wpcontent-map.sh"
  "$ROOT/bin/preflight-check.sh"
  "$ROOT/bin/post-deploy-activate.sh"
  "$ROOT/wp-content/mu-plugins"/*.php
  "$ROOT/.deploy.staging.env"
  "$ROOT/.deploy.production.env"
)

for f in "${FILES[@]}"; do
  [[ -f "$f" ]] || continue
  echo "### ${f#$ROOT/}" >> "$SNAP"
  head -n 20 "$f" >> "$SNAP"
  echo >> "$SNAP"
done

# 3️⃣ Rövid repo térkép
echo "### Repository tree" >> "$SNAP"
if [[ -d "$ROOT/wp-content" ]]; then
  (cd "$ROOT" && find wp-content -maxdepth 3 -type f | sort) >> "$SNAP"
fi

# 4️⃣ Meta JSON a Copilot Chathez
cat > "$META" <<JSON
{
  "project": "Sharity ImpactShop",
  "description": "WordPress + Elementor affiliate platform integrating Dognet Publisher API through local REST endpoints (Impact Bridge Local) with mu-plugins for donation tracking and deploy automation.",
  "components": [
    "impactctl",
    "bin/deploy-wpcontent-map.sh",
    "bin/preflight-check.sh",
    "bin/post-deploy-activate.sh",
    "wp-content/mu-plugins"
  ],
  "last_snapshot": "$(timestamp)"
}
JSON

echo "✅ Codex snapshot created → $SNAP"
echo "🪣 Meta exported → $META"
