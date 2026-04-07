#!/usr/bin/env bash
set -euo pipefail

MODE="human"
if [[ "${1:-}" == "--porcelain" ]]; then
  MODE="porcelain"
fi

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "${REPO_ROOT}" ]]; then
  echo "[workflow-state] hiba: nem git repóban futsz." >&2
  exit 1
fi

COMMON_GIT_DIR="$(git -C "${REPO_ROOT}" rev-parse --git-common-dir 2>/dev/null || true)"
if [[ -n "${COMMON_GIT_DIR}" && "${COMMON_GIT_DIR}" != /* ]]; then
  COMMON_GIT_DIR="${REPO_ROOT}/${COMMON_GIT_DIR}"
fi
if [[ -n "${COMMON_GIT_DIR}" ]]; then
  COMMON_REPO_ROOT="$(cd "$(dirname "${COMMON_GIT_DIR}")" && pwd)"
else
  COMMON_REPO_ROOT="${REPO_ROOT}"
fi

REPO_NAME="$(basename "${COMMON_REPO_ROOT}")"
BRANCH="$(git -C "${REPO_ROOT}" branch --show-current 2>/dev/null || echo detached)"
HEAD_SHA="$(git -C "${REPO_ROOT}" rev-parse --short HEAD 2>/dev/null || echo unknown)"

count_lines() {
  local value
  value="$1"
  if [[ -z "${value}" ]]; then
    echo 0
  else
    printf '%s\n' "${value}" | sed '/^$/d' | wc -l | tr -d ' '
  fi
}

STAGED_FILES="$(git -C "${REPO_ROOT}" diff --cached --name-only)"
UNSTAGED_FILES="$(git -C "${REPO_ROOT}" diff --name-only)"
UNTRACKED_FILES="$(git -C "${REPO_ROOT}" ls-files --others --exclude-standard)"

STAGED_COUNT="$(count_lines "${STAGED_FILES}")"
UNSTAGED_COUNT="$(count_lines "${UNSTAGED_FILES}")"
UNTRACKED_COUNT="$(count_lines "${UNTRACKED_FILES}")"
DIRTY_COUNT=$((STAGED_COUNT + UNSTAGED_COUNT + UNTRACKED_COUNT))

UPSTREAM=""
if UPSTREAM="$(git -C "${REPO_ROOT}" rev-parse --abbrev-ref --symbolic-full-name @{upstream} 2>/dev/null)"; then
  :
else
  UPSTREAM=""
fi

BEHIND_COUNT=0
AHEAD_COUNT=0
if [[ -n "${UPSTREAM}" ]]; then
  COUNTS="$(git -C "${REPO_ROOT}" rev-list --left-right --count "${UPSTREAM}...HEAD" 2>/dev/null || echo "0 0")"
  BEHIND_COUNT="$(printf '%s' "${COUNTS}" | awk '{print $1}')"
  AHEAD_COUNT="$(printf '%s' "${COUNTS}" | awk '{print $2}')"
fi

PR_STATE="none"
PR_URL=""
PR_NUMBER=""
PR_DRAFT="false"
PR_MERGE_STATE=""
if command -v gh >/dev/null 2>&1; then
  PR_JSON="$(gh pr view --json number,state,isDraft,url,mergeStateStatus 2>/dev/null || true)"
  if [[ -n "${PR_JSON}" ]] && command -v jq >/dev/null 2>&1; then
    PR_STATE="$(printf '%s' "${PR_JSON}" | jq -r '.state // "none"' | tr '[:upper:]' '[:lower:]')"
    PR_URL="$(printf '%s' "${PR_JSON}" | jq -r '.url // ""')"
    PR_NUMBER="$(printf '%s' "${PR_JSON}" | jq -r '.number // ""')"
    PR_DRAFT="$(printf '%s' "${PR_JSON}" | jq -r '.isDraft // false')"
    PR_MERGE_STATE="$(printf '%s' "${PR_JSON}" | jq -r '.mergeStateStatus // ""')"
  fi
fi

NEXT_ACTION=""
REASON=""
DETAIL=""

is_protected_branch() {
  [[ "${BRANCH}" == "main" || "${BRANCH}" == "master" ]]
}

is_manual_deploy_repo() {
  [[ "${REPO_NAME}" == "impactshop-notes" ]]
}

if is_protected_branch; then
  if (( DIRTY_COUNT > 0 )); then
    NEXT_ACTION="start_feature_branch"
    REASON="protected_branch_dirty"
    DETAIL="A worktree dirty a védett ${BRANCH} ágon. Előbb feature/worktree branch kell."
  elif is_manual_deploy_repo; then
    NEXT_ACTION="deploy_decision_required"
    REASON="main_branch_manual_deploy"
    DETAIL="A merge-elt főág kézi deploy-döntést igényel."
  else
    NEXT_ACTION="monitor_auto_deploy"
    REASON="main_branch_auto_deploy"
    DETAIL="A merge-elt főág auto-deployt indít; most monitorozás kell."
  fi
elif [[ "${PR_STATE}" == "merged" ]]; then
  NEXT_ACTION="new_branch_needed"
  REASON="branch_pr_already_merged"
  DETAIL="Ennek a branchnek a PR-je már merge-elve van; új munka új branchre menjen."
elif (( DIRTY_COUNT > 0 )); then
  NEXT_ACTION="commit"
  REASON="worktree_dirty"
  DETAIL="Van helyi módosítás; a következő logikus lépés a commit."
elif [[ -z "${UPSTREAM}" ]]; then
  NEXT_ACTION="push"
  REASON="no_upstream"
  DETAIL="A branchnek nincs upstreamje; push kell, hogy PR-alap legyen."
elif (( BEHIND_COUNT > 0 && AHEAD_COUNT == 0 )); then
  NEXT_ACTION="pull_or_rebase"
  REASON="branch_behind_upstream"
  DETAIL="A branch le van maradva az upstreamhez képest; előbb sync/rebase kell."
elif (( AHEAD_COUNT > 0 )); then
  NEXT_ACTION="push"
  REASON="ahead_of_upstream"
  DETAIL="Van nem pusholt commit; a következő logikus lépés a push."
elif [[ "${PR_STATE}" == "none" ]]; then
  NEXT_ACTION="open_pr"
  REASON="branch_pushed_no_pr"
  DETAIL="A branch pusholva van, de nincs hozzá nyitott PR."
elif [[ "${PR_STATE}" == "open" && "${PR_DRAFT}" == "true" ]]; then
  NEXT_ACTION="ready_for_review"
  REASON="draft_pr_open"
  DETAIL="Draft PR nyitva van; ha kész, review-ra kell tenni."
elif [[ "${PR_STATE}" == "open" ]]; then
  if [[ "${PR_MERGE_STATE}" == "CLEAN" || "${PR_MERGE_STATE}" == "HAS_HOOKS" || "${PR_MERGE_STATE}" == "UNKNOWN" ]]; then
    NEXT_ACTION="merge_ready"
    REASON="pr_open_clean_branch"
    DETAIL="A PR nyitva van és a branch tiszta; valószínűleg merge/review a következő lépés."
  else
    NEXT_ACTION="pr_open"
    REASON="pr_open_not_merge_ready"
    DETAIL="A PR nyitva van, de még nem tűnik merge-readynek."
  fi
else
  NEXT_ACTION="review_state"
  REASON="fallback"
  DETAIL="Állapotellenőrzés szükséges."
fi

PR_COMMAND="gh pr create --fill"
if [[ -f "${REPO_ROOT}/package.json" ]] && command -v jq >/dev/null 2>&1; then
  if jq -e '.scripts["pr:create-with-memory"]' "${REPO_ROOT}/package.json" >/dev/null 2>&1; then
    PR_COMMAND="npm run pr:create-with-memory -- --fill"
  fi
elif [[ -x "${REPO_ROOT}/scripts/pr-create-with-memory.sh" ]]; then
  PR_COMMAND="bash scripts/pr-create-with-memory.sh --fill"
fi

if [[ "${MODE}" == "porcelain" ]]; then
  cat <<EOF
repo=${REPO_NAME}
branch=${BRANCH}
head=${HEAD_SHA}
staged=${STAGED_COUNT}
unstaged=${UNSTAGED_COUNT}
untracked=${UNTRACKED_COUNT}
dirty=${DIRTY_COUNT}
upstream=${UPSTREAM}
ahead=${AHEAD_COUNT}
behind=${BEHIND_COUNT}
pr_state=${PR_STATE}
pr_url=${PR_URL}
pr_number=${PR_NUMBER}
pr_draft=${PR_DRAFT}
pr_merge_state=${PR_MERGE_STATE}
next_action=${NEXT_ACTION}
reason=${REASON}
detail=${DETAIL}
pr_command=${PR_COMMAND}
EOF
  exit 0
fi

echo "[workflow-state] repo: ${REPO_NAME}"
echo "[workflow-state] branch: ${BRANCH} @ ${HEAD_SHA}"
echo "[workflow-state] dirty: staged=${STAGED_COUNT} unstaged=${UNSTAGED_COUNT} untracked=${UNTRACKED_COUNT}"
if [[ -n "${UPSTREAM}" ]]; then
  echo "[workflow-state] upstream: ${UPSTREAM} (ahead=${AHEAD_COUNT}, behind=${BEHIND_COUNT})"
else
  echo "[workflow-state] upstream: nincs"
fi
if [[ "${PR_STATE}" != "none" ]]; then
  echo "[workflow-state] PR: state=${PR_STATE} draft=${PR_DRAFT} merge=${PR_MERGE_STATE:-n/a} ${PR_URL}"
else
  echo "[workflow-state] PR: nincs nyitott PR a branchhez"
fi
echo "[workflow-state] következő lépés: ${NEXT_ACTION}"
echo "[workflow-state] miért: ${DETAIL}"
case "${NEXT_ACTION}" in
  commit)
    echo "[workflow-state] javaslat: commitáld a jelenlegi logikai egységet."
    ;;
  push)
    echo "[workflow-state] javaslat: pushold a branch-et. Használhatod: git wpush"
    ;;
  open_pr)
    echo "[workflow-state] javaslat: nyiss PR-t. Javasolt parancs: ${PR_COMMAND}"
    ;;
  ready_for_review)
    echo "[workflow-state] javaslat: vedd le draftból / kérj review-t."
    ;;
  merge_ready)
    echo "[workflow-state] javaslat: review után merge jöhet."
    ;;
  deploy_decision_required)
    echo "[workflow-state] javaslat: staging/production deploy döntés kell."
    ;;
  monitor_auto_deploy)
    echo "[workflow-state] javaslat: ellenőrizd a Railway/Vercel auto-deploy állapotot."
    ;;
  start_feature_branch)
    echo "[workflow-state] javaslat: előbb menj feature/worktree branchre."
    ;;
  new_branch_needed)
    echo "[workflow-state] javaslat: új munkához új branch kell; ez a branch már merge-elve van."
    ;;
  pull_or_rebase)
    echo "[workflow-state] javaslat: előbb sync/rebase az upstreamről."
    ;;
esac
