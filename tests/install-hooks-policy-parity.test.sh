#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TMP_ROOT="$(mktemp -d)"
chmod 700 "$TMP_ROOT"
trap 'rm -rf "$TMP_ROOT"' EXIT

REPO="$TMP_ROOT/impactshop-notes"
HOOK_DIR="$REPO/.fixture-hooks"
mkdir -p "$REPO/scripts" "$REPO/docs" "$REPO/.github" "$HOOK_DIR"

git -C "$REPO" init -q
git -C "$REPO" config user.email test@example.invalid
git -C "$REPO" config user.name test
git -C "$REPO" config core.hooksPath "$HOOK_DIR"
git -C "$REPO" remote add origin https://github.com/office-hue/impactshop-notes.git

cp "$ROOT/scripts/install-hooks.sh" "$REPO/scripts/"
cp "$ROOT/scripts/git-health-check.sh" "$REPO/scripts/"
printf '%s\n' '# PR policy fixture' > "$REPO/docs/pr-policy.md"
printf '%s\n' '# PR template fixture' > "$REPO/.github/pull_request_template.md"
printf '%s\n' '# PR exit fixture' > "$REPO/PR-EXIT-CHECKLIST.md"

for script in \
  start-feature-worktree.sh \
  safe-repo-audit.sh \
  check-protected-file-touch.sh \
  check-commit-lane.sh \
  worktree-continuity-guard.sh \
  worktree-coordination-sync.sh
do
  printf '%s\n' '#!/usr/bin/env bash' 'exit 0' > "$REPO/scripts/$script"
done
chmod +x "$REPO/scripts/"*.sh

git -C "$REPO" add .
git -C "$REPO" commit -qm base
git -C "$REPO" branch -M main
git -C "$REPO" update-ref refs/remotes/origin/main HEAD
git -C "$REPO" switch -qc feat/hook-policy-parity

mode_of() {
  stat -c '%a' "$1" 2>/dev/null || stat -f '%Lp' "$1"
}

SOURCE_HOOK_DIR="$(git -C "$ROOT" rev-parse --git-path hooks)"
if [[ "$SOURCE_HOOK_DIR" != /* ]]; then
  SOURCE_HOOK_DIR="$ROOT/$SOURCE_HOOK_DIR"
fi

snapshot_source_boundary() {
  local path
  printf 'status\n%s\n' "$(git -C "$ROOT" status --porcelain=v1 --untracked-files=all)"
  for path in \
    "$ROOT/.gitmessage" \
    "$SOURCE_HOOK_DIR/pre-commit" \
    "$SOURCE_HOOK_DIR/commit-msg" \
    "$SOURCE_HOOK_DIR/pre-push" \
    "$SOURCE_HOOK_DIR/post-commit" \
    "$SOURCE_HOOK_DIR/post-merge" \
    "$SOURCE_HOOK_DIR/post-checkout"
  do
    if [[ -f "$path" ]]; then
      printf '%s|%s|%s\n' "$path" "$(mode_of "$path")" "$(shasum -a 256 "$path" | awk '{print $1}')"
    else
      printf '%s|missing\n' "$path"
    fi
  done
}

outside_before="$(snapshot_source_boundary)"
(cd "$REPO" && bash scripts/install-hooks.sh >/dev/null)

actual_hook_dir="$(git -C "$REPO" rev-parse --git-path hooks)"
[[ "$actual_hook_dir" == "$HOOK_DIR" ]]

for hook in pre-commit commit-msg pre-push post-commit post-merge post-checkout; do
  hook_path="$actual_hook_dir/$hook"
  [[ -x "$hook_path" ]]
  case "$hook_path" in
    "$HOOK_DIR"/*) ;;
    *)
      echo "generated hook escaped fixture hook directory: $hook_path" >&2
      exit 1
      ;;
  esac
done

PRE_PUSH="$HOOK_DIR/pre-push"
rg -q -- '--strict --mode push' "$PRE_PUSH"
rg -q -- 'safe-repo-audit\.sh' "$PRE_PUSH"
rg -q -- 'worktree-continuity-guard\.sh|WORKTREE_CONTINUITY_GUARD' "$PRE_PUSH"
if rg -q -- 'resolve_ai_agent_repo|AI_AGENT_REPO|npm[[:space:]]+--prefix.*memory:' "$PRE_PUSH"; then
  echo 'generated pre-push hook contains forbidden sibling-memory dependency' >&2
  exit 1
fi

set +e
positive_output="$(cd "$REPO" && bash scripts/git-health-check.sh 2>&1)"
positive_rc=$?
set -e
if [[ "$positive_rc" -ne 0 ]]; then
  printf '%s\n' "$positive_output" >&2
  exit 1
fi
grep -Fq '[git-health-check] summary: WARN=0 FAIL=0' <<<"$positive_output"
grep -Fq '[git-health-check] OK: pre-push hook repo-local; nincs sibling-memory függés' <<<"$positive_output"

cat >> "$PRE_PUSH" <<'HOOK'
AI_AGENT_REPO="$REPO_ROOT/../ai-agent"
npm --prefix "$AI_AGENT_REPO" run -s memory:gate -- --repo "$REPO_ROOT"
HOOK

set +e
negative_output="$(cd "$REPO" && bash scripts/git-health-check.sh 2>&1)"
negative_rc=$?
set -e
if [[ "$negative_rc" -eq 0 ]]; then
  printf '%s\n' "$negative_output" >&2
  exit 1
fi
grep -Fq '[git-health-check] FAIL: pre-push hook tiltott sibling-memory függést tartalmaz' <<<"$negative_output"
grep -Fq '[git-health-check] summary: WARN=0 FAIL=1' <<<"$negative_output"

outside_after="$(snapshot_source_boundary)"
if [[ "$outside_before" != "$outside_after" ]]; then
  echo 'fixture changed the source worktree or shared hook boundary' >&2
  diff -u <(printf '%s\n' "$outside_before") <(printf '%s\n' "$outside_after") >&2 || true
  exit 1
fi

echo 'install-hooks policy parity test: PASS'
