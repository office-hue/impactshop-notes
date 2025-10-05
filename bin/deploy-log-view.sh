#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG="$ROOT/.codex/deploy-log.txt"
N="${1:-20}"
[[ -f "$LOG" ]] || { echo "No log found: $LOG" >&2; exit 2; }
{
  head -n1 "$LOG"
  tail -n "$N" "$LOG"
} | column -s, -t
