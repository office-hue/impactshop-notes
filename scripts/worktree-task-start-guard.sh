#!/usr/bin/env bash
set -euo pipefail
# Retired DEV compatibility report; no marker, repin, shared-state or controller write.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
if command -v python3 >/dev/null 2>&1 && [[ -f "$SCRIPT_DIR/workspace-guidance.py" && ! -L "$SCRIPT_DIR/workspace-guidance.py" ]]; then
  exec python3 -B "$SCRIPT_DIR/workspace-guidance.py" "$@"
fi
echo '{"status":"retired","decision":"advisory","authorityGranted":false,"writes":false,"sourceChecks":"not-evaluated","reason":"optional_guidance_unavailable"}'
