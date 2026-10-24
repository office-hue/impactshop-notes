#!/usr/bin/env bash
set -euo pipefail
# Read-only observations. Optional hook/helper absence is advisory, never a PASS.
git rev-parse --show-toplevel
git branch --show-current
git rev-parse HEAD
git status --short
echo 'Hook/controller installation is optional. This inventory is not source-check or publication evidence.'
