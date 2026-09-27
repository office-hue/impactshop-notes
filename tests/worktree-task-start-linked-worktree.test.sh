#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TMP_ROOT="$(mktemp -d)"
trap 'rm -rf "$TMP_ROOT"' EXIT

REPO="$TMP_ROOT/repo"
mkdir -p "$REPO/scripts" "$REPO/docs" "$TMP_ROOT/.worktrees"
WT="$(cd "$TMP_ROOT/.worktrees" && pwd -P)/linked"
cp "$ROOT/scripts/worktree-task-start.sh" "$REPO/scripts/"
cp "$ROOT/scripts/worktree-task-start-guard.sh" "$REPO/scripts/"
cp "$ROOT/scripts/worktree-readiness-check.sh" "$REPO/scripts/"
cp "$ROOT/scripts/worktree-coordination-sync.sh" "$REPO/scripts/"
cp "$ROOT/scripts/worktree-continuity-guard.sh" "$REPO/scripts/"
cp "$ROOT/scripts/start-feature-worktree.sh" "$REPO/scripts/"
printf '%s\n' '# docs' > "$REPO/docs/impactshop-notes-doc-sync-map-2026-06-23.md"
printf '%s\n' '# governance' > "$REPO/docs/impactshop-governance-system-plan-2026-06-16.md"
printf '%s\n' '# notes' > "$REPO/notes.md"
printf '%s\n' '# status' > "$REPO/system-status-snapshot.md"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/safe-repo-audit.sh"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/git-health-check.sh"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/dev-context-policy-guard.sh"
chmod +x "$REPO/scripts/"*.sh

git -C "$REPO" init -q
git -C "$REPO" config user.email test@example.invalid
git -C "$REPO" config user.name test
git -C "$REPO" remote add origin https://github.com/office-hue/impactshop-notes.git
git -C "$REPO" add .
git -C "$REPO" commit -qm base
git -C "$REPO" branch -M main
git -C "$REPO" update-ref refs/remotes/origin/main "$(git -C "$REPO" rev-parse HEAD)"
COMMON_GIT_DIR="$(cd "$REPO" && git rev-parse --git-common-dir)"
[[ "$COMMON_GIT_DIR" = /* ]] || COMMON_GIT_DIR="$REPO/$COMMON_GIT_DIR"
COORD_DIR="$COMMON_GIT_DIR/office-hue-worktree-coordination"
mkdir -p "$COORD_DIR"
printf 'path: %s\n' "$REPO" > "$COORD_DIR/ACTIVE_WORKTREE.md"

git -C "$REPO" worktree add -qb feat/linked "$WT" main >/dev/null
cp "$ROOT/scripts/worktree-task-start.sh" "$WT/scripts/"
cp "$ROOT/scripts/worktree-task-start-guard.sh" "$WT/scripts/"
cp "$ROOT/scripts/worktree-readiness-check.sh" "$WT/scripts/"
cp "$ROOT/scripts/worktree-coordination-sync.sh" "$WT/scripts/"
cp "$ROOT/scripts/worktree-continuity-guard.sh" "$WT/scripts/"

set +e
output="$(cd "$REPO" && PATH=/usr/bin:/bin bash scripts/worktree-task-start.sh feat/linked --resume --doc-sync-label linked-test --doc-sync-repo-id impactshop-notes --doc-sync-path-prefix docs/)"
task_rc=$?
set -e
[[ "$task_rc" -eq 0 ]] || { printf '%s\n' "$output" >&2; exit "$task_rc"; }
[[ "$output" == *"path: $WT"* ]]
[[ "$output" == *"decision: degraded"* ]]
[[ "$output" != *"missing-worktree-active-marker"* ]]
grep -Fx "path: $REPO" "$COORD_DIR/ACTIVE_WORKTREE.md" >/dev/null

marker="$(git -C "$WT" rev-parse --git-path worktree-active.json)"
artifact="$(git -C "$WT" rev-parse --git-path worktree-task-start-decision.json)"
[[ -f "$marker" ]]
[[ -f "$artifact" ]]
python3 - "$artifact" "$WT" <<'PY'
import json
import sys

payload = json.loads(open(sys.argv[1], encoding="utf-8").read())
assert payload["currentWorktree"] == sys.argv[2], payload
PY

echo "worktree task-start linked-worktree binding: ok"
