#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
GUARD="$ROOT/scripts/dev-context-policy-guard.sh"
TMP_ROOT="$(mktemp -d)"
trap 'rm -rf "$TMP_ROOT"' EXIT

new_repo() {
  local name="$1"
  local repo="$TMP_ROOT/$name"
  mkdir -p "$repo/docs" "$repo/.github/workflows" "$repo/scripts" "$repo/wp-content/mu-plugins" "$repo/tests"
  printf '%s\n' '{"protected_globs":["wp-content/mu-plugins/locked.php"],"additive_globs":[]}' > "$repo/docs/impactshop-protected-files.json"
  printf '%s\n' '.github/workflows/locked.yml' > "$repo/.github/protected-files.txt"
  printf '%s\n' '<!-- BEGIN REPO-LOCAL DEV UPGRADE CONTRACT --> repo-local authority global prompt Luna Terra Sol worktree checkpoint git diff --check Vercel <!-- END REPO-LOCAL DEV UPGRADE CONTRACT -->' > "$repo/AGENTS.md"
  git -C "$repo" init -q
  git -C "$repo" config user.email test@example.invalid
  git -C "$repo" config user.name test
  git -C "$repo" add .
  git -C "$repo" commit -qm base
  git -C "$repo" branch -M main
  git -C "$repo" update-ref refs/remotes/origin/main "$(git -C "$repo" rev-parse main)"
  git -C "$repo" checkout -qb feat/fixture
  printf '%s\n' "$repo"
}

commit_all() {
  git -C "$1" add -A
  git -C "$1" commit -qm "$2"
}

guard_payload() {
  local repo="$1"
  DEV_DELIVERY_V2_BASE_SHA="$(git -C "$repo" rev-parse refs/remotes/origin/main)" \
    bash "$GUARD" --repo-root "$repo" --json
}

reject_guard() {
  local repo="$1"
  local label="$2"
  local payload
  set +e
  payload="$(guard_payload "$repo" 2>/dev/null)"
  local rc=$?
  set -e
  [[ $rc -ne 0 ]] || { echo "unsafe context was accepted: $label" >&2; exit 1; }
  printf '%s\n' "$payload"
}

assert_payload() {
  python3 - "$1" "$2" <<'PY'
import json, sys
p=json.loads(sys.argv[1])
if not eval(sys.argv[2], {'__builtins__': {}}, {'p': p}):
    raise AssertionError((sys.argv[2], p))
PY
}

repo="$(new_repo governance)"
printf '%s\n' 'provider-deploy and rsync are documentation fixture strings only' > "$repo/docs/example.md"
printf '%s\n' '#!/usr/bin/env bash' 'provider-deploy production' > "$repo/tests/example-fixture.sh"
commit_all "$repo" governance
payload="$(guard_payload "$repo")"
assert_payload "$payload" "p['decision']=='allowed' and p['changedPathClass']=='governance-only'"

repo="$(new_repo missing-base)"
git -C "$repo" update-ref -d refs/remotes/origin/main
if bash "$GUARD" --repo-root "$repo" --json >/dev/null 2>&1; then echo 'missing base was accepted' >&2; exit 1; fi

repo="$(new_repo non-sha-base)"
if DEV_DELIVERY_V2_BASE_SHA=main bash "$GUARD" --repo-root "$repo" --json >/dev/null 2>&1; then echo 'non-SHA base was accepted' >&2; exit 1; fi

repo="$(new_repo workflow)"
printf '%s\n' 'name: hostile' 'jobs:' '  deploy:' '    steps:' '      - run: provider-deploy production' > "$repo/.github/workflows/provider-deploy.yml"
commit_all "$repo" workflow
payload="$(reject_guard "$repo" workflow-provider-deploy)"
assert_payload "$payload" "p['changedPathClass']=='protected-or-deploy' and p['decision']=='operator-review'"

repo="$(new_repo remote-script)"
printf '%s\n' '#!/usr/bin/env bash' 'rsync payload host:/remote/path' > "$repo/scripts/deploy-remote.sh"
commit_all "$repo" remote-script
payload="$(reject_guard "$repo" script-remote-write)"
assert_payload "$payload" "p['changedPathClass']=='protected-or-deploy'"

repo="$(new_repo protected-wp)"
printf '%s\n' '<?php // protected' > "$repo/wp-content/mu-plugins/locked.php"
commit_all "$repo" protected-wp
payload="$(reject_guard "$repo" protected-wp)"
assert_payload "$payload" "p['changedPathClass']=='protected-or-deploy'"

repo="$(new_repo rename)"
printf '%s\n' '<?php // protected' > "$repo/wp-content/mu-plugins/locked.php"
commit_all "$repo" protected-base
git -C "$repo" update-ref refs/remotes/origin/main HEAD
mkdir -p "$repo/docs/archive"
git -C "$repo" mv wp-content/mu-plugins/locked.php docs/archive/locked.php
commit_all "$repo" rename
payload="$(reject_guard "$repo" protected-to-docs-rename)"
assert_payload "$payload" "p['changedPathClass']=='protected-or-deploy' and 'wp-content/mu-plugins/locked.php' in p['changedPaths'] and 'docs/archive/locked.php' in p['changedPaths']"

repo="$(new_repo unknown)"
printf '%s\n' 'runtime change' > "$repo/runtime.txt"
commit_all "$repo" unknown
payload="$(reject_guard "$repo" unknown-path)"
assert_payload "$payload" "p['changedPathClass']=='unknown' and p['decision']=='blocked'"

repo="$(new_repo broken-policy)"
perl -pi -e 's/END REPO-LOCAL DEV UPGRADE CONTRACT/END BROKEN/' "$repo/AGENTS.md"
commit_all "$repo" broken-policy
payload="$(reject_guard "$repo" incomplete-local-policy)"
assert_payload "$payload" "'missing-or-malformed-local-policy' in p['blockingReasons']"

bash -n "$GUARD"
echo 'dev context policy guard fixtures: PASS'
