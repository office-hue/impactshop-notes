#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "${REPO_ROOT}" ]]; then
  echo "[install-hooks] hiba: nem git repóban futsz." >&2
  exit 1
fi

ORIGIN_URL="$(git -C "$REPO_ROOT" remote get-url origin 2>/dev/null || true)"

case "$ORIGIN_URL" in
  *impactshop-notes.git)
    ORIGIN_ENV_VAR="IMPACTSHOP_NOTES_EXPECTED_ORIGIN"
    DEFAULT_ORIGIN="https://github.com/office-hue/impactshop-notes.git"
    ORIGIN_MODE="required"
    ;;
  *ai-agent.git)
    ORIGIN_ENV_VAR="AI_AGENT_EXPECTED_ORIGIN"
    DEFAULT_ORIGIN=""
    ORIGIN_MODE="optional"
    ;;
  *impact_hub.git)
    ORIGIN_ENV_VAR="IMPACT_HUB_EXPECTED_ORIGIN"
    DEFAULT_ORIGIN="https://github.com/office-hue/impact_hub.git"
    ORIGIN_MODE="required"
    ;;
  *)
    # Fallback by path for local/offline cases.
    if [[ "$REPO_ROOT" == *"/impactshop-notes"* || "$REPO_ROOT" == *"/impactshop-notes-"* ]]; then
      ORIGIN_ENV_VAR="IMPACTSHOP_NOTES_EXPECTED_ORIGIN"
      DEFAULT_ORIGIN="https://github.com/office-hue/impactshop-notes.git"
      ORIGIN_MODE="required"
    elif [[ "$REPO_ROOT" == *"/ai-agent"* || "$REPO_ROOT" == *"/ai-agent-"* ]]; then
      ORIGIN_ENV_VAR="AI_AGENT_EXPECTED_ORIGIN"
      DEFAULT_ORIGIN=""
      ORIGIN_MODE="optional"
    elif [[ "$REPO_ROOT" == *"/impact_hub"* || "$REPO_ROOT" == *"/impact_hub-"* ]]; then
      ORIGIN_ENV_VAR="IMPACT_HUB_EXPECTED_ORIGIN"
      DEFAULT_ORIGIN="https://github.com/office-hue/impact_hub.git"
      ORIGIN_MODE="required"
    else
      echo "[install-hooks] hiba: nem támogatott repo (origin/path alapján): $REPO_ROOT" >&2
      exit 1
    fi
    ;;
esac

HOOK_DIR="$(git -C "$REPO_ROOT" rev-parse --git-path hooks)"
if [[ "$HOOK_DIR" != /* ]]; then
  HOOK_DIR="${REPO_ROOT}/${HOOK_DIR}"
fi
mkdir -p "${HOOK_DIR}"

COMMIT_TEMPLATE="${REPO_ROOT}/.gitmessage"
if [[ ! -f "${COMMIT_TEMPLATE}" ]]; then
  cat > "${COMMIT_TEMPLATE}" <<'TPL'
# type(scope): rövid összefoglaló
#
# Why:
#
# Memory-ID: none
#
# Rollback:
#
TPL
fi
git -C "${REPO_ROOT}" config commit.template "${COMMIT_TEMPLATE}"

cat > "${HOOK_DIR}/pre-commit" <<'HOOK'
#!/usr/bin/env bash
set -euo pipefail

if [[ "${IMPACT_POLICY_ALLOW_MAIN_COMMIT:-0}" == "1" ]]; then
  exit 0
fi

BRANCH="$(git branch --show-current 2>/dev/null || echo detached)"
if [[ "$BRANCH" == "main" || "$BRANCH" == "master" ]]; then
  echo "[repo-guard] Blocked commit on protected branch: $BRANCH" >&2
  echo "[repo-guard] Use feature/worktree flow:" >&2
  echo "[repo-guard]   bash scripts/start-feature-worktree.sh <feature-branch>" >&2
  echo "[repo-guard] Emergency bypass (approval required): IMPACT_POLICY_ALLOW_MAIN_COMMIT=1" >&2
  exit 1
fi
HOOK

cat > "${HOOK_DIR}/commit-msg" <<'HOOK'
#!/usr/bin/env bash
set -euo pipefail

MSG_FILE="${1:-}"
if [[ -z "${MSG_FILE}" || ! -f "${MSG_FILE}" ]]; then
  exit 0
fi

if ! rg -q '^Memory-ID:' "${MSG_FILE}"; then
  printf '\nMemory-ID: none\n' >> "${MSG_FILE}"
fi
HOOK

cat > "${HOOK_DIR}/pre-push" <<HOOK
#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="\$(git rev-parse --show-toplevel)"
BRANCH="\$(git branch --show-current 2>/dev/null || echo detached)"

resolve_ai_agent_repo() {
  local repo_root="\$1"
  local search="\$repo_root"
  local parent=""
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    if [[ "\$(basename "\$search")" == "ai-agent" && -f "\$search/scripts/dev-memory.ts" ]]; then
      echo "\$search"
      return 0
    fi
    if [[ -d "\$search/ai-agent" && -f "\$search/ai-agent/scripts/dev-memory.ts" ]]; then
      echo "\$search/ai-agent"
      return 0
    fi
    for wt in "\$search"/.worktrees/ai-agent*; do
      if [[ -d "\$wt" && -f "\$wt/scripts/dev-memory.ts" ]]; then
        echo "\$wt"
        return 0
      fi
    done
    parent="\$(cd "\$search/.." && pwd)"
    [[ "\$parent" == "\$search" ]] && break
    search="\$parent"
  done
  return 1
}

if [[ "\${IMPACT_POLICY_ALLOW_MAIN_PUSH:-0}" != "1" ]]; then
  if [[ "\$BRANCH" == "main" || "\$BRANCH" == "master" ]]; then
    echo "[repo-guard] Blocked push from protected branch: \$BRANCH" >&2
    echo "[repo-guard] Use feature/worktree flow:" >&2
    echo "[repo-guard]   bash scripts/start-feature-worktree.sh <feature-branch>" >&2
    echo "[repo-guard] Emergency bypass (approval required): IMPACT_POLICY_ALLOW_MAIN_PUSH=1" >&2
    exit 1
  fi

  while read -r local_ref local_sha remote_ref remote_sha; do
    [[ -z "\${local_ref:-}" ]] && continue
    if [[ "\$local_ref" == "refs/heads/main" || "\$remote_ref" == "refs/heads/main" || "\$local_ref" == "refs/heads/master" || "\$remote_ref" == "refs/heads/master" ]]; then
      echo "[repo-guard] Blocked direct push to main/master." >&2
      echo "[repo-guard] Open PR from feature/worktree branch instead." >&2
      exit 1
    fi
  done
fi

required_paths=(
  "scripts/start-feature-worktree.sh"
  "scripts/git-health-check.sh"
  "docs/pr-policy.md"
  ".github/pull_request_template.md"
  "PR-EXIT-CHECKLIST.md"
)
missing=0
for rel in "\${required_paths[@]}"; do
  if [[ ! -e "\$REPO_ROOT/\$rel" ]]; then
    echo "[repo-guard] Missing required policy file: \$rel" >&2
    missing=1
  fi
done
if [[ "\$missing" -ne 0 ]]; then
  exit 1
fi

SAFE_AUDIT_SCRIPT=""
search_dir="\$REPO_ROOT"
for _ in 1 2 3 4 5 6; do
  candidate="\$search_dir/scripts/safe-repo-audit.sh"
  if [[ -x "\$candidate" ]]; then
    SAFE_AUDIT_SCRIPT="\$candidate"
    break
  fi
  parent="\$(cd "\$search_dir/.." && pwd)"
  [[ "\$parent" == "\$search_dir" ]] && break
  search_dir="\$parent"
done

if [[ -z "\$SAFE_AUDIT_SCRIPT" ]]; then
  echo "[repo-guard] missing safe audit script (searched upwards from: \$REPO_ROOT)" >&2
  exit 1
fi

"\${SAFE_AUDIT_SCRIPT}" --repo "\${REPO_ROOT}" --strict --mode push

AI_AGENT_REPO="\$(resolve_ai_agent_repo "\$REPO_ROOT" 2>/dev/null || true)"
if [[ -n "\$AI_AGENT_REPO" ]] && command -v npm >/dev/null 2>&1; then
  npm --prefix "\$AI_AGENT_REPO" run -s memory:gate -- --repo "\$REPO_ROOT"
  npm --prefix "\$AI_AGENT_REPO" run -s memory:sync-pr -- --repo "\$REPO_ROOT" >/dev/null 2>&1 || true
fi

expected_origin="\${${ORIGIN_ENV_VAR}:-${DEFAULT_ORIGIN}}"
actual_origin="\$(git remote get-url origin 2>/dev/null || true)"
HOOK

if [[ "$ORIGIN_MODE" == "optional" ]]; then
  cat >> "${HOOK_DIR}/pre-push" <<'HOOK'
if [[ -n "${expected_origin}" ]]; then
  if [[ "${actual_origin}" != "${expected_origin}" ]]; then
    echo "[repo-guard] Blocked push: origin mismatch" >&2
    echo "[repo-guard] expected: ${expected_origin}" >&2
    echo "[repo-guard] actual:   ${actual_origin}" >&2
    exit 1
  fi
else
  echo "[repo-guard] origin check skipped (expected origin nincs beállítva)"
fi
HOOK
else
  cat >> "${HOOK_DIR}/pre-push" <<'HOOK'
if [[ "${actual_origin}" != "${expected_origin}" ]]; then
  echo "[repo-guard] Blocked push: origin mismatch" >&2
  echo "[repo-guard] expected: ${expected_origin}" >&2
  echo "[repo-guard] actual:   ${actual_origin}" >&2
  exit 1
fi
HOOK
fi

cat > "${HOOK_DIR}/post-commit" <<'HOOK'
#!/usr/bin/env bash
set -u

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

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[[ -n "${REPO_ROOT}" ]] || exit 0
AI_AGENT_REPO="$(resolve_ai_agent_repo "$REPO_ROOT" 2>/dev/null || true)"
[[ -n "${AI_AGENT_REPO}" ]] || exit 0
command -v npm >/dev/null 2>&1 || exit 0

REPO_NAME="$(basename "$REPO_ROOT")"
BRANCH="$(git branch --show-current 2>/dev/null || echo detached)"
COMMIT_SHA="$(git rev-parse --short HEAD 2>/dev/null || echo unknown)"

(
  npm --prefix "$AI_AGENT_REPO" run -s memory:capture -- \
    --kind session \
    --title "auto-commit:${REPO_NAME}" \
    --summary "Auto-captured commit in ${REPO_NAME} (${BRANCH}@${COMMIT_SHA})." \
    --why "Workflow auto-memory to preserve context with zero manual overhead." \
    --details "event=post-commit repo=${REPO_ROOT} branch=${BRANCH} commit=${COMMIT_SHA}" \
    --impact "Improves long-term context continuity across repositories." \
    --rollback "No runtime/prod impact; remove hooks to disable." \
    --tags "auto,git,commit,${REPO_NAME}" \
    --source "${REPO_NAME}@${COMMIT_SHA}" >/dev/null 2>&1 || true
  npm --prefix "$AI_AGENT_REPO" run -s memory:sync -- --limit 120 >/dev/null 2>&1 || true
) &

exit 0
HOOK

cat > "${HOOK_DIR}/post-merge" <<'HOOK'
#!/usr/bin/env bash
set -u

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

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[[ -n "${REPO_ROOT}" ]] || exit 0
AI_AGENT_REPO="$(resolve_ai_agent_repo "$REPO_ROOT" 2>/dev/null || true)"
[[ -n "${AI_AGENT_REPO}" ]] || exit 0
command -v npm >/dev/null 2>&1 || exit 0

WORKSPACE_ROOT="$(cd "$AI_AGENT_REPO/.." && pwd)"
if [[ ! -d "$WORKSPACE_ROOT/impact_hub" || ! -d "$WORKSPACE_ROOT/impactshop-notes" || ! -d "$WORKSPACE_ROOT/ai-agent" ]]; then
  WORKSPACE_ROOT="$AI_AGENT_REPO"
fi

STATE_DIR="$AI_AGENT_REPO/tmp/state/dev-memory"
mkdir -p "$STATE_DIR"
LAST_FILE="$STATE_DIR/last-hook-refresh.epoch"
NOW_TS="$(date +%s)"
LAST_TS="0"
if [[ -f "$LAST_FILE" ]]; then
  LAST_TS="$(cat "$LAST_FILE" 2>/dev/null || echo 0)"
fi
if [[ $((NOW_TS - LAST_TS)) -lt 1800 ]]; then
  exit 0
fi
printf '%s\n' "$NOW_TS" > "$LAST_FILE"

(
  npm --prefix "$AI_AGENT_REPO" run -s memory:refresh -- \
    --root "$WORKSPACE_ROOT" \
    --sync-limit 120 \
    --max-files 6000 \
    --max-bytes 400000 \
    --chunk-limit 4000 \
    --chunk-lines 80 \
    --chunk-overlap 20 >/dev/null 2>&1 || true
) &

exit 0
HOOK

cat > "${HOOK_DIR}/post-checkout" <<'HOOK'
#!/usr/bin/env bash
set -u

if [[ "${3:-1}" != "1" ]]; then
  exit 0
fi

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

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[[ -n "${REPO_ROOT}" ]] || exit 0
AI_AGENT_REPO="$(resolve_ai_agent_repo "$REPO_ROOT" 2>/dev/null || true)"
[[ -n "${AI_AGENT_REPO}" ]] || exit 0
command -v npm >/dev/null 2>&1 || exit 0

WORKSPACE_ROOT="$(cd "$AI_AGENT_REPO/.." && pwd)"
if [[ ! -d "$WORKSPACE_ROOT/impact_hub" || ! -d "$WORKSPACE_ROOT/impactshop-notes" || ! -d "$WORKSPACE_ROOT/ai-agent" ]]; then
  WORKSPACE_ROOT="$AI_AGENT_REPO"
fi

STATE_DIR="$AI_AGENT_REPO/tmp/state/dev-memory"
mkdir -p "$STATE_DIR"
LAST_FILE="$STATE_DIR/last-hook-refresh.epoch"
NOW_TS="$(date +%s)"
LAST_TS="0"
if [[ -f "$LAST_FILE" ]]; then
  LAST_TS="$(cat "$LAST_FILE" 2>/dev/null || echo 0)"
fi
if [[ $((NOW_TS - LAST_TS)) -lt 1800 ]]; then
  exit 0
fi
printf '%s\n' "$NOW_TS" > "$LAST_FILE"

(
  npm --prefix "$AI_AGENT_REPO" run -s memory:refresh -- \
    --root "$WORKSPACE_ROOT" \
    --sync-limit 120 \
    --max-files 6000 \
    --max-bytes 400000 \
    --chunk-limit 4000 \
    --chunk-lines 80 \
    --chunk-overlap 20 >/dev/null 2>&1 || true
) &

exit 0
HOOK

chmod +x "${HOOK_DIR}/pre-commit" "${HOOK_DIR}/commit-msg" "${HOOK_DIR}/pre-push" "${HOOK_DIR}/post-commit" "${HOOK_DIR}/post-merge" "${HOOK_DIR}/post-checkout"
echo "[install-hooks] OK: ${HOOK_DIR}/pre-commit telepítve."
echo "[install-hooks] OK: ${HOOK_DIR}/commit-msg telepítve (Memory-ID auto line)."
echo "[install-hooks] OK: ${HOOK_DIR}/pre-push telepítve."
echo "[install-hooks] OK: ${HOOK_DIR}/post-commit telepítve (auto memory capture)."
echo "[install-hooks] OK: ${HOOK_DIR}/post-merge telepítve (auto memory refresh)."
echo "[install-hooks] OK: ${HOOK_DIR}/post-checkout telepítve (auto memory refresh)."
echo "[install-hooks] commit template: ${COMMIT_TEMPLATE}"
echo "[install-hooks] enforced one-path policy aktív (commit/push/PR/deploy flow)."
