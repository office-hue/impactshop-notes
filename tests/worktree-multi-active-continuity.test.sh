#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TMP_ROOT="$(mktemp -d)"
TMP_ROOT="$(cd "$TMP_ROOT" && pwd -P)"
trap 'rm -rf "$TMP_ROOT"' EXIT

REPO="$TMP_ROOT/impactshop-notes"
PRIMARY="$TMP_ROOT/.worktrees/impactshop-notes-feat-primary"
SECONDARY="$TMP_ROOT/.worktrees/impactshop-notes-feat-secondary"
mkdir -p "$REPO/scripts" "$REPO/docs" "$TMP_ROOT/.worktrees"
git -C "$REPO" init -q
git -C "$REPO" config user.email test@example.invalid
git -C "$REPO" config user.name test
git -C "$REPO" remote add origin https://github.com/office-hue/impactshop-notes.git

cp "$ROOT/scripts/worktree-coordination-sync.sh" "$REPO/scripts/"
cp "$ROOT/scripts/worktree-continuity-guard.sh" "$REPO/scripts/"
cp "$ROOT/scripts/install-hooks.sh" "$REPO/scripts/"
cp "$ROOT/scripts/guarded-push.sh" "$REPO/scripts/"
printf '%s\n' '# map' > "$REPO/docs/impactshop-notes-doc-sync-map-2026-06-23.md"
printf '%s\n' '# governance' > "$REPO/docs/impactshop-governance-system-plan-2026-06-16.md"
printf '%s\n' '# notes' > "$REPO/notes.md"
printf '%s\n' '# status' > "$REPO/system-status-snapshot.md"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/worktree-task-start.sh"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/worktree-task-start-guard.sh"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/check-protected-file-touch.sh"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/check-commit-lane.sh"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/safe-repo-audit.sh"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/git-health-check.sh"
printf '%s\n' '#!/usr/bin/env bash' > "$REPO/scripts/start-feature-worktree.sh"
printf '%s\n' '# PR policy' > "$REPO/docs/pr-policy.md"
printf '%s\n' '# PR exit' > "$REPO/PR-EXIT-CHECKLIST.md"
mkdir -p "$REPO/.github"
printf '%s\n' '# PR template' > "$REPO/.github/pull_request_template.md"
chmod +x "$REPO/scripts/"*.sh
git -C "$REPO" add .
git -C "$REPO" commit -qm base
git -C "$REPO" branch -M main
git -C "$REPO" worktree add -qb feat/primary "$PRIMARY" main
git -C "$REPO" worktree add -qb feat/secondary "$SECONDARY" main

write_evidence() {
  local cwd="$1"
  local branch="$2"
  local git_dir marker artifact
  git_dir="$(git -C "$cwd" rev-parse --absolute-git-dir)"
  marker="$git_dir/worktree-active.json"
  artifact="$git_dir/worktree-task-start-decision.json"
  python3 - "$marker" "$artifact" "$cwd" "$branch" <<'PY'
import json
import pathlib
import sys

marker = pathlib.Path(sys.argv[1])
artifact = pathlib.Path(sys.argv[2])
cwd = sys.argv[3]
branch = sys.argv[4]
marker.parent.mkdir(parents=True, exist_ok=True)
marker.write_text(json.dumps({
    'branch': branch,
    'path': cwd,
    'doc_sync_label': 'fixture',
    'doc_sync_repo_id': 'impactshop-notes',
    'doc_sync_path_prefix': 'docs/',
}, indent=2) + '\n')
artifact.write_text(json.dumps({
    'status': 'allowed',
    'decision': 'allowed',
    'currentBranch': branch,
    'currentWorktree': cwd,
    'docSyncLabel': 'fixture',
    'docSyncRepoId': 'impactshop-notes',
    'docSyncPathPrefix': 'docs/',
}, indent=2) + '\n')
PY
}

write_evidence "$PRIMARY" feat/primary
write_evidence "$SECONDARY" feat/secondary

bash "$PRIMARY/scripts/worktree-coordination-sync.sh" --repo-root "$PRIMARY" --primary "$PRIMARY" >/dev/null
bash "$SECONDARY/scripts/worktree-coordination-sync.sh" --repo-root "$SECONDARY" --register "$SECONDARY" >/dev/null

ACTIVE_FILE="$TMP_ROOT/.worktrees/ACTIVE_WORKTREE.md"
SNAP_FILE="$TMP_ROOT/.worktrees/ACTIVE_WORKTREES.md"
rg -q --fixed-strings "path: $PRIMARY" "$ACTIVE_FILE"
rg -q --fixed-strings "primary_path: $PRIMARY" "$SNAP_FILE"
rg -q --fixed-strings "## $SECONDARY" "$SNAP_FILE"
(cd "$PRIMARY" && bash scripts/worktree-continuity-guard.sh --mode push >/dev/null)

payload="$(cd "$SECONDARY" && bash scripts/worktree-continuity-guard.sh --json --mode push)"
python3 - "$payload" <<'PY'
import json, sys
p=json.loads(sys.argv[1])
assert p['decision'] == 'degraded', p
assert p['blockingReasons'] == [], p
assert p['warnings'] == ['non-primary-active-worktree'], p
PY

printf '%s\n' 'new checkpoint' >> "$SECONDARY/notes.md"
git -C "$SECONDARY" add notes.md
git -C "$SECONDARY" commit -qm secondary-checkpoint
set +e
stale_payload="$(cd "$SECONDARY" && bash scripts/worktree-continuity-guard.sh --json --mode push)"
stale_rc=$?
set -e
[[ "$stale_rc" -ne 0 ]]
python3 - "$stale_payload" <<'PY'
import json, sys
p=json.loads(sys.argv[1])
assert 'active-worktrees-head-mismatch' in p['blockingReasons'], p
PY

bash "$SECONDARY/scripts/worktree-coordination-sync.sh" --repo-root "$SECONDARY" --register "$SECONDARY" >/dev/null
(cd "$SECONDARY" && bash scripts/worktree-continuity-guard.sh --mode push >/dev/null)

python3 - "$ACTIVE_FILE" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
path.write_text(path.read_text().replace("generation: ", "generation: drift-", 1))
PY
set +e
generation_payload="$(cd "$SECONDARY" && bash scripts/worktree-continuity-guard.sh --json --mode push)"
generation_rc=$?
set -e
[[ "$generation_rc" -ne 0 ]]
python3 - "$generation_payload" <<'PY'
import json, sys
p=json.loads(sys.argv[1])
assert 'coordination-generation-mismatch' in p['blockingReasons'], p
PY
bash "$SECONDARY/scripts/worktree-coordination-sync.sh" --repo-root "$SECONDARY" --register "$SECONDARY" >/dev/null

printf '%s\n' 'dirty' >> "$SECONDARY/notes.md"
set +e
dirty_payload="$(cd "$SECONDARY" && bash scripts/worktree-continuity-guard.sh --json --mode push)"
dirty_rc=$?
set -e
[[ "$dirty_rc" -ne 0 ]]
python3 - "$dirty_payload" <<'PY'
import json, sys
p=json.loads(sys.argv[1])
assert 'current-worktree-dirty' in p['blockingReasons'], p
PY
git -C "$SECONDARY" restore notes.md

mkdir "$TMP_ROOT/.worktrees/.impactshop-notes-coordination.lock"
set +e
bash "$SECONDARY/scripts/worktree-coordination-sync.sh" --repo-root "$SECONDARY" --register "$SECONDARY" >/dev/null 2>&1
lock_rc=$?
set -e
[[ "$lock_rc" -ne 0 ]]
rmdir "$TMP_ROOT/.worktrees/.impactshop-notes-coordination.lock"

FOREIGN="$TMP_ROOT/foreign"
mkdir -p "$FOREIGN"
git -C "$FOREIGN" init -q
set +e
bash "$SECONDARY/scripts/worktree-coordination-sync.sh" \
  --repo-root "$SECONDARY" --register "$FOREIGN" >/dev/null 2>&1
foreign_rc=$?
set -e
[[ "$foreign_rc" -ne 0 ]]

bash "$SECONDARY/scripts/worktree-coordination-sync.sh" \
  --repo-root "$SECONDARY" --primary "$SECONDARY" >/dev/null
rg -q --fixed-strings "path: $SECONDARY" "$ACTIVE_FILE"
(cd "$SECONDARY" && bash scripts/worktree-continuity-guard.sh --mode push >/dev/null)

(cd "$REPO" && bash scripts/install-hooks.sh >/dev/null)
HOOK_DIR="$(git -C "$REPO" rev-parse --git-path hooks)"
if [[ "$HOOK_DIR" != /* ]]; then HOOK_DIR="$REPO/$HOOK_DIR"; fi
! rg -q 'resolve_ai_agent_repo|AI_AGENT_REPO|npm --prefix .*memory:' \
  "$ROOT/scripts/guarded-push.sh" "$ROOT/scripts/install-hooks.sh" \
  "$ROOT/scripts/start-feature-worktree.sh" "$HOOK_DIR"
rg -q 'WORKTREE_COORDINATION_SYNC' "$HOOK_DIR/pre-push"
rg -q -- '--register "\$REPO_ROOT"' "$HOOK_DIR/pre-push"

echo "worktree multi-active continuity: PASS"
