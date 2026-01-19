#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$HOME/Documents/GitHub/impactshop-notes"
LOG_FILE="$REPO_DIR/.codex/logs/ai-agent.cron.log"
CRON_WRAPPER="$REPO_DIR/.codex/cron/ai-agent-guard-cron.sh"
CRON_TAG="# ai-agent-guard"
CRON_LINE="*/15 * * * * $CRON_WRAPPER $CRON_TAG"

mkdir -p "$REPO_DIR/.codex/logs" "$REPO_DIR/.codex/cron"

cat > "$CRON_WRAPPER" <<EOF
#!/usr/bin/env bash
set -euo pipefail

LOG_FILE="$LOG_FILE"
exec >> "\$LOG_FILE" 2>&1

SOCK="\$(launchctl getenv SSH_AUTH_SOCK 2>/dev/null || true)"
if [[ -n "\$SOCK" ]]; then
  export SSH_AUTH_SOCK="\$SOCK"
else
  echo "\$(date -Iseconds) WARN ai-agent-guard: SSH_AUTH_SOCK is empty in cron environment" >&2
fi

cd "$REPO_DIR"
./.codex/guards/ai-agent-guard.sh
EOF

chmod +x "$CRON_WRAPPER"

tmp=$(mktemp)
trap 'rm -f "$tmp"' EXIT
crontab -l 2>/dev/null | grep -v "$CRON_TAG" > "$tmp" || true
echo "$CRON_LINE" >> "$tmp"
crontab "$tmp"

echo "AI agent guard cron entry installed: $CRON_LINE"
