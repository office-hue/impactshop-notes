#!/usr/bin/env bash
set -euo pipefail
SOCK="$HOME/.ssh/impactshop-agent.sock"
KEY="$HOME/.ssh/id_ed25519"

# agent indul, ha nem fut
if ! ssh-add -l >/dev/null 2>&1; then
  eval "$(ssh-agent -a "$SOCK" -s)" >/dev/null
  ssh-add --apple-use-keychain "$KEY" 2>/dev/null || ssh-add "$KEY"
fi

export SSH_AUTH_SOCK="$SOCK"
cd /Users/bujdosoarnold/Documents/GitHub/impactshop-notes
exec codex
