#!/usr/bin/env bash
set -euo pipefail

if [ "${IMPACT_ENV:-}" != "staging" ]; then
  echo "Guard: staging only"
  exit 1
fi

branch="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)"
if [ "$branch" = "HEAD" ] || [ -z "$branch" ]; then
  echo "ℹ️  Branch: detached HEAD"
elif [ "$branch" != "main" ] && [ "$branch" != "master" ]; then
  echo "ℹ️  Branch: $branch"
fi

exit 0
