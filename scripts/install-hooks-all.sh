#!/usr/bin/env bash
set -euo pipefail

# Team policy wrapper for consistent bootstrap command across repos.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec "${SCRIPT_DIR}/install-hooks.sh" "$@"
