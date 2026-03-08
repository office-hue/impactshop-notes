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

cat > "${HOOK_DIR}/pre-push" <<HOOK
#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="\$(git rev-parse --show-toplevel)"
BRANCH="\$(git branch --show-current 2>/dev/null || echo detached)"

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

chmod +x "${HOOK_DIR}/pre-commit" "${HOOK_DIR}/pre-push"
echo "[install-hooks] OK: ${HOOK_DIR}/pre-commit telepítve."
echo "[install-hooks] OK: ${HOOK_DIR}/pre-push telepítve."
echo "[install-hooks] enforced one-path policy aktív (commit/push/PR/deploy flow)."
