#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[[ -n "${REPO_ROOT}" ]] || exit 1

command git -C "${REPO_ROOT}" push "$@"

POST_PUSH="${REPO_ROOT}/scripts/post-push-pr-suggest.sh"
if [[ -x "${POST_PUSH}" ]]; then
  "${POST_PUSH}" || true
fi
