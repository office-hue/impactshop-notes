#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "⚠️  bin/deploy.sh deprecated."
echo "    A támogatott deploy entrypoint: bin/impactshop-guard-deploy.sh"
echo "    Ez a wrapper most automatikusan delegál a guardolt deploy-ra."
echo

exec "${SCRIPT_DIR}/impactshop-guard-deploy.sh" "$@"
