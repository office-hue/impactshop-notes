#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Running analytics canary guard..."
"${SCRIPT_DIR}/analytics-canary-guard.sh"

echo "Running analytics consent guard..."
"${SCRIPT_DIR}/analytics-consent-guard.sh"

echo "Running ads-watch pseudo canary..."
"${SCRIPT_DIR}/ads-watch-pseudo-canary.sh"

echo "Analytics suite OK"
