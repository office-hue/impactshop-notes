#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[[ -n "$REPO_ROOT" ]] || exit 1
cd "$REPO_ROOT"

LANE_GUARD="$REPO_ROOT/scripts/check-commit-lane.sh"
PROTECTED_GUARD="$REPO_ROOT/scripts/check-protected-file-touch.sh"
SAFE_AUDIT="$REPO_ROOT/scripts/safe-repo-audit.sh"
WORKTREE_CONTINUITY_GUARD="$REPO_ROOT/scripts/worktree-continuity-guard.sh"

# Required checks are not optional simply because a checkout uses mode 0644.
for guard in "$LANE_GUARD" "$PROTECTED_GUARD" "$SAFE_AUDIT" "$WORKTREE_CONTINUITY_GUARD"; do
  [[ -f "$guard" && ! -L "$guard" ]] || {
    echo "[guarded-push] Missing or unsafe required local guard: $guard" >&2
    exit 1
  }
done

# One repo-owned resolver; no fetch, empty-tree/HEAD^ fallback or silent
# replacement of an invalid explicit base. Resolution is not an audit PASS.
PUSH_BASE="$(/bin/bash "$SAFE_AUDIT" --repo "$REPO_ROOT" --mode push --print-push-base)"
[[ "$PUSH_BASE" =~ ^[0-9a-f]{40}$ ]] || {
  echo '[guarded-push] Invalid resolved base; no push attempted.' >&2
  exit 1
}
PUSH_RANGE="$PUSH_BASE..HEAD"
export SAFE_REPO_AUDIT_UPSTREAM="$PUSH_BASE" SAFE_REPO_AUDIT_PUSH_RANGE="$PUSH_RANGE"

/bin/bash "$LANE_GUARD" --mode push --push-range "$PUSH_RANGE"
/bin/bash "$PROTECTED_GUARD" --mode push --push-range "$PUSH_RANGE"
# The existing guard selects v3 or legacy. No failure fallback, marker creation
# or shared coordination write is performed by the push entrypoint.
/bin/bash "$WORKTREE_CONTINUITY_GUARD" --mode push
/bin/bash "$SAFE_AUDIT" --repo "$REPO_ROOT" --strict --mode push

# Preserve the actual Git command and native hooks; no post-push foreign writer.
exec git -C "$REPO_ROOT" push "$@"
