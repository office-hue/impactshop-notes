#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[[ -n "${REPO_ROOT}" ]] || exit 1

SAFE_AUDIT_SCRIPT="${REPO_ROOT}/scripts/safe-repo-audit.sh"
PROTECTED_TOUCH_SCRIPT="${REPO_ROOT}/scripts/check-protected-file-touch.sh"
COMMIT_LANE_SCRIPT="${REPO_ROOT}/scripts/check-commit-lane.sh"

AI_AGENT_REPO=""
resolve_ai_agent_repo() {
  local repo_root="$1"
  local search="$repo_root"
  local parent=""
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    if [[ "$(basename "$search")" == "ai-agent" && -f "$search/scripts/dev-memory.ts" ]]; then
      echo "$search"
      return 0
    fi
    if [[ -d "$search/ai-agent" && -f "$search/ai-agent/scripts/dev-memory.ts" ]]; then
      echo "$search/ai-agent"
      return 0
    fi
    parent="$(cd "$search/.." && pwd)"
    [[ "$parent" == "$search" ]] && break
    search="$parent"
  done
  return 1
}

if [[ -x "${SAFE_AUDIT_SCRIPT}" ]]; then
  "${SAFE_AUDIT_SCRIPT}" --repo "${REPO_ROOT}" --strict --mode push
fi

if [[ -x "${PROTECTED_TOUCH_SCRIPT}" ]]; then
  "${PROTECTED_TOUCH_SCRIPT}" --mode push
fi

if [[ -x "${COMMIT_LANE_SCRIPT}" ]]; then
  "${COMMIT_LANE_SCRIPT}" --mode push
fi

AI_AGENT_REPO="$(resolve_ai_agent_repo "${REPO_ROOT}" 2>/dev/null || true)"
if [[ -n "${AI_AGENT_REPO}" ]] && command -v npm >/dev/null 2>&1; then
  npm --prefix "${AI_AGENT_REPO}" run -s memory:gate -- --repo "${REPO_ROOT}"
fi

command git -C "${REPO_ROOT}" push "$@"

POST_PUSH="${REPO_ROOT}/scripts/post-push-pr-suggest.sh"
if [[ -x "${POST_PUSH}" ]]; then
  "${POST_PUSH}" || true
fi
