#!/usr/bin/env bash
set -euo pipefail
# Native content/product audit; no engine, capsule, selector or model route.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
exec python3 -B "$SCRIPT_DIR/workspace-hooks.py" audit "$@"
