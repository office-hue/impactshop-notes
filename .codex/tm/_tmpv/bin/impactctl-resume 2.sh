#!/usr/bin/env bash
set -euo pipefail

say(){ printf "%b\n" "$*\n"; }
ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$ROOT"

say "🚀 ImpactShop – Resume (SSH-agent + Codex)"
say "📁 Project: $ROOT"

# === 0) Kényelmi függvények (parancsok is maradnak) ===
impactcd()      { cd "$ROOT"; pwd; }
impactenv()     { [ -f "$HOME/.staging_env" ] && . "$HOME/.staging_env" || true; [ -f "$HOME/.production_env" ] && . "$HOME/.production_env" || true; echo "✔ ENV betöltve"; }
impactconnect() { [ -x "$ROOT/bin/impactctl-connect.sh" ] && "$ROOT/bin/impactctl-connect.sh" || echo "⚠️  Nincs impactctl-connect.sh"; }
impactrefresh() { [ -x "$ROOT/impactctl" ] && "$ROOT/impactctl" refresh || [ -x "$ROOT/bin/codex-refresh.sh" ] && "$ROOT/bin/codex-refresh.sh" || echo "⚠️  Nincs Codex refresher"; }

# === 1) SSH-agent + kulcs betöltés (perzisztens socket) ===
SOCK="$HOME/.ssh/impactshop-agent.sock"
KEY="${HOME}/.ssh/id_ed25519"
mkdir -p "$HOME/.ssh"
if ! ssh-add -l >/dev/null 2>&1; then
  eval "$(ssh-agent -a "$SOCK" -s)" >/dev/null
  if [[ "$(uname -s)" == "Darwin" ]]; then
    ssh-add --apple-use-keychain "$KEY" 2>/dev/null || ssh-add "$KEY"
  else
    ssh-add "$KEY"
  fi
else
  # ha fut az agent, állítsuk a saját socketre
  eval "$(ssh-agent -a "$SOCK" -s)" >/dev/null || true
fi
export SSH_AUTH_SOCK="$SOCK"
ssh-add -l >/dev/null || { say "❌ ssh-agent nem lát kulcsot: $KEY"; exit 1; }
say "🔑 ssh-agent kész: $SSH_AUTH_SOCK"

# === 2) ~/.ssh/config javítás + host-blokk biztosítása ===
CONF="$HOME/.ssh/config"
touch "$CONF"; chmod 600 "$CONF"
# cseréljük az elgépelést (yesHost → HostName)
perl -0777 -pe 's/^\s*yesHost\s+/  HostName /gmi' -i "$CONF"
# ha nincs cp40 blokk, adjuk hozzá; ha van, fűzzük ki a minimális mezőket
if ! grep -qE '^Host\s+cp40\.ezit\.hu\s*$' "$CONF"; then
  cat >>"$CONF" <<EOF
Host cp40.ezit.hu
  HostName cp40.ezit.hu
  User sharityh
  IdentityFile ~/.ssh/id_ed25519
  AddKeysToAgent yes
  UseKeychain yes
  IdentitiesOnly yes
  StrictHostKeyChecking accept-new
EOF
fi

# === 3) ENV betöltés + DEPLOY_HOST autó-kitöltés ===
impactenv >/dev/null
# ha SSH_HOST formátum: user@host → host
if [ -z "${DEPLOY_HOST:-}" ] && [ -n "${SSH_HOST:-}" ]; then
  export DEPLOY_HOST="${SSH_HOST#*@}"
  say "ℹ️  DEPLOY_HOST beállítva SSH_HOST alapján: $DEPLOY_HOST"
fi

# === 4) Gyors SSH sanity (BatchMode: no prompt) ===
if [ -n "${SSH_HOST:-}" ]; then
  if ssh -o BatchMode=yes -o ConnectTimeout=6 "$SSH_HOST" 'echo OK' 2>/dev/null | grep -q '^OK$'; then
    say "✅ SSH kapcsolat működik: $SSH_HOST"
  else
    say "⚠️  SSH BatchMode nem sikerült ($SSH_HOST). Ellenőrizd a kulcsot/authorized_keys-t."
  fi
else
  say "⚠️  SSH_HOST nincs az env-ben – kihagyom az SSH sanity-t."
fi

# === 5) Context ellenőrzés/frissítés ===
if [ ! -f .codex/context-latest.json ]; then
  say "🧠 Nincs .codex/context-latest.json – frissítek…"
  impactrefresh || true
fi
[ -f .codex/context-latest.json ] && say "✅ Context ready: .codex/context-latest.json" || say "⚠️ Context nem készült – később pótolható."

# === 6) Codex indítása örökölt agenttel + gyors parancsok ===
if ! command -v codex >/dev/null 2>&1; then
  say "❌ 'codex' nincs telepítve. Telepítsd: brew install codex"
  exit 1
fi

say "🟢 Codex indítása… (kilépés: Ctrl+C)"
codex <<'INSTRUCTIONS'
cd /Users/bujdosoarnold/Documents/GitHub/impactshop-notes
/status
# Ha kell a teljes projektállapot:
Load .codex/context-latest.json and restore full project state.
INSTRUCTIONS
