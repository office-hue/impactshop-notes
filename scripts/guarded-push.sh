#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[[ -n "${REPO_ROOT}" ]] || exit 1

LANE_GUARD="${REPO_ROOT}/scripts/check-commit-lane.sh"
PROTECTED_GUARD="${REPO_ROOT}/scripts/check-protected-file-touch.sh"
SAFE_AUDIT="${REPO_ROOT}/scripts/safe-repo-audit.sh"

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
    for wt in "$search"/.worktrees/ai-agent*; do
      if [[ -d "$wt" && -f "$wt/scripts/dev-memory.ts" ]]; then
        echo "$wt"
        return 0
      fi
    done
    parent="$(cd "$search/.." && pwd)"
    [[ "$parent" == "$search" ]] && break
    search="$parent"
  done
  return 1
}

if [[ -x "${LANE_GUARD}" ]]; then
  "${LANE_GUARD}" --mode push
fi

if [[ -x "${PROTECTED_GUARD}" ]]; then
  "${PROTECTED_GUARD}" --mode push
fi

if [[ -x "${SAFE_AUDIT}" ]]; then
  "${SAFE_AUDIT}" --repo "${REPO_ROOT}" --strict --mode push
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
