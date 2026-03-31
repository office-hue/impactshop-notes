#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[[ -n "${REPO_ROOT}" ]] || exit 1

LANE_GUARD="${REPO_ROOT}/scripts/check-commit-lane.sh"
PROTECTED_GUARD="${REPO_ROOT}/scripts/check-protected-file-touch.sh"

if [[ -x "${LANE_GUARD}" ]]; then
  "${LANE_GUARD}" --mode push
fi

if [[ -x "${PROTECTED_GUARD}" ]]; then
  "${PROTECTED_GUARD}" --mode push
fi

command git -C "${REPO_ROOT}" push "$@"

POST_PUSH="${REPO_ROOT}/scripts/post-push-pr-suggest.sh"
if [[ -x "${POST_PUSH}" ]]; then
  "${POST_PUSH}" || true
fi
