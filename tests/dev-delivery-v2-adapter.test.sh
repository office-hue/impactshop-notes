#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TMP_ROOT="$(mktemp -d)"
trap 'rm -rf "$TMP_ROOT"' EXIT

new_repo() {
  local name="$1"
  local repo="$TMP_ROOT/$name"
  mkdir -p "$repo/config" "$repo/scripts" "$repo/docs/protected-change-records" \
    "$repo/.github/workflows" "$repo/wp-content/mu-plugins" "$repo/tests"
  cp "$ROOT/config/dev-delivery-v2-target-contract.json" "$ROOT/config/dev-delivery-v2-impact-policy.json" "$repo/config/"
  cp "$ROOT/scripts/dev-delivery-v2-adapter.sh" "$ROOT/scripts/check-commit-lane.sh" \
    "$ROOT/scripts/check-protected-file-touch.sh" "$repo/scripts/"
  printf '%s\n' '{"protected_globs":["wp-content/mu-plugins/locked.php",".github/workflows/locked.yml"],"additive_globs":["docs/protected-change-records/*.md"],"required_smoke_tags":{"deploy_guard":["deploy:guard-preflight","deploy:checksum-verify"]},"smoke_group_globs":{"deploy_guard":[".github/workflows/*.yml"]}}' > "$repo/docs/impactshop-protected-files.json"
  printf '%s\n' '.github/workflows/locked.yml' > "$repo/.github/protected-files.txt"
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

adapter_payload() {
  local repo="$1"
  shift
  DEV_DELIVERY_V2_BASE_SHA="$(git -C "$repo" rev-parse refs/remotes/origin/main)" \
    bash "$repo/scripts/dev-delivery-v2-adapter.sh" "$@" --json
}

assert_payload() {
  local payload="$1"
  local expression="$2"
  python3 - "$payload" "$expression" <<'PY'
import json, sys
payload=json.loads(sys.argv[1])
if not eval(sys.argv[2], {'__builtins__': {}, 'any': any}, {'p': payload}):
    raise AssertionError((sys.argv[2], payload))
PY
}

reject_bastion() {
  local repo="$1"
  local label="$2"
  local payload
  set +e
  payload="$(adapter_payload "$repo" bastion 2>/dev/null)"
  local rc=$?
  set -e
  [[ $rc -ne 0 ]] || { echo "unsafe candidate was accepted: $label" >&2; exit 1; }
  printf '%s\n' "$payload"
}

write_admission() {
  local repo="$1"
  shift
  local approval='operator-approval:fixture-approved-20260904'
  cat > "$repo/docs/fixture-plan.md" <<EOF
# Fixture plan

## DEV delivery v2 fixture plan

Approval reference: \`$approval\`.
EOF
  python3 - "$repo/docs/protected-change-records/fixture.md" "$approval" "$@" <<'PY'
import json, pathlib, sys
record=pathlib.Path(sys.argv[1])
manifest={
    'schemaVersion': 1,
    'planRef': 'docs/fixture-plan.md#dev-delivery-v2-fixture-plan',
    'operatorApprovalRef': sys.argv[2],
    'protectedPaths': sys.argv[3:],
    'rollbackNote': 'revert the exact fixture commit',
    'smokeTags': ['deploy:guard-preflight','deploy:checksum-verify'],
}
record.write_text(
    '# Fixture protected change record\n\nProtected files touched. Rollback plan. Smoke checklist.\n\n'
    '<!-- BEGIN PROTECTED SOURCE ADMISSION -->\n'
    + json.dumps(manifest, indent=2, sort_keys=True)
    + '\n<!-- END PROTECTED SOURCE ADMISSION -->\n'
)
PY
}

mutate_evidence() {
  local repo="$1"
  local field="$2"
  local value="$3"
  local state
  state="$(git -C "$repo" rev-parse --git-path dev-delivery-v2)"
  [[ "$state" == /* ]] || state="$repo/$state"
  state="$state/full-validation.json"
  python3 - "$state" "$field" "$value" <<'PY'
import json, pathlib, sys
path=pathlib.Path(sys.argv[1]); data=json.loads(path.read_text()); data[sys.argv[2]]=sys.argv[3]; path.write_text(json.dumps(data,sort_keys=True)+'\n')
PY
  chmod 600 "$state"
}

repo="$(new_repo governance)"
printf '%s\n' 'documentation example: provider-deploy production; rsync payload host:/remote' > "$repo/docs/example.md"
printf '%s\n' '#!/usr/bin/env bash' 'provider-deploy production' > "$repo/tests/example-fixture.sh"
commit_all "$repo" governance
payload="$(adapter_payload "$repo" bastion)"
assert_payload "$payload" "p['changedPathClass']=='governance-only' and p['sourceMergeAdmission'] is True"

repo="$(new_repo workflow)"
printf '%s\n' 'name: hostile' 'jobs:' '  deploy:' '    runs-on: ubuntu-latest' '    steps:' '      - run: provider-deploy production' > "$repo/.github/workflows/provider-deploy.yml"
commit_all "$repo" workflow
payload="$(reject_bastion "$repo" workflow-provider-deploy)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and p['bastionDecision']=='pass' and p['sourceMergeAdmission'] is False"

repo="$(new_repo remote-script)"
printf '%s\n' '#!/usr/bin/env bash' 'rsync payload host:/remote/path' > "$repo/scripts/deploy-remote.sh"
commit_all "$repo" remote-script
payload="$(reject_bastion "$repo" script-remote-write)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and p['sourceMergeAdmission'] is False"

repo="$(new_repo protected-wp)"
printf '%s\n' '<?php // protected' > "$repo/wp-content/mu-plugins/locked.php"
commit_all "$repo" protected-wp
payload="$(reject_bastion "$repo" protected-wp)"
assert_payload "$payload" "p['changedPathClass']=='protected' and 'protected-change-record-exact-admission-missing' in p['sourceAdmissionReasons']"

repo="$(new_repo rename)"
printf '%s\n' '<?php // protected' > "$repo/wp-content/mu-plugins/locked.php"
commit_all "$repo" protected-base
git -C "$repo" update-ref refs/remotes/origin/main HEAD
mkdir -p "$repo/docs/archive"
git -C "$repo" mv wp-content/mu-plugins/locked.php docs/archive/locked.php
commit_all "$repo" rename
payload="$(reject_bastion "$repo" protected-to-docs-rename)"
assert_payload "$payload" "p['changedPathClass']=='protected' and 'wp-content/mu-plugins/locked.php' in p['changedPaths'] and 'docs/archive/locked.php' in p['changedPaths']"

repo="$(new_repo protected-admitted)"
printf '%s\n' '<?php // protected' > "$repo/wp-content/mu-plugins/locked.php"
write_admission "$repo" wp-content/mu-plugins/locked.php
commit_all "$repo" protected-admitted
payload="$(adapter_payload "$repo" full-validate)"
assert_payload "$payload" "p['decision']=='operator-review' and p['fullValidationEvidence'] is True and p['sourceMergeAdmission'] is False"
payload="$(adapter_payload "$repo" bastion)"
assert_payload "$payload" "p['decision']=='operator-review' and p['bastionDecision']=='pass' and p['sourceMergeAdmission'] is True"

repo="$(new_repo deploy-never-admitted)"
printf '%s\n' 'name: hostile' 'jobs:' '  deploy:' '    runs-on: ubuntu-latest' '    steps:' '      - run: provider-deploy production' > "$repo/.github/workflows/locked.yml"
write_admission "$repo" .github/workflows/locked.yml
commit_all "$repo" deploy-candidate
payload="$(adapter_payload "$repo" full-validate)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and p['fullValidationEvidence'] is True and p['sourceMergeAdmission'] is False"
payload="$(reject_bastion "$repo" deploy-full-validation)"
assert_payload "$payload" "p['bastionDecision']=='pass' and p['sourceMergeAdmission'] is False and 'deploy-source-admission-forbidden' in p['sourceAdmissionReasons']"

repo="$(new_repo forged-record)"
printf '%s\n' '<?php // protected' > "$repo/wp-content/mu-plugins/locked.php"
write_admission "$repo" wp-content/mu-plugins/not-the-changed-file.php
commit_all "$repo" forged-record
payload="$(reject_bastion "$repo" forged-record)"
assert_payload "$payload" "any('path-coverage-mismatch' in reason for reason in p['sourceAdmissionReasons'])"

repo="$(new_repo partial-record)"
printf '%s\n' '<?php // protected' > "$repo/wp-content/mu-plugins/locked.php"
printf '%s\n' '# Partial record' 'Protected files touched. Rollback plan. Smoke checklist.' '<!-- BEGIN PROTECTED SOURCE ADMISSION -->' '{"schemaVersion":1}' '<!-- END PROTECTED SOURCE ADMISSION -->' > "$repo/docs/protected-change-records/partial.md"
commit_all "$repo" partial-record
payload="$(reject_bastion "$repo" partial-record)"
assert_payload "$payload" "any('schema-invalid' in reason for reason in p['sourceAdmissionReasons'])"

for mismatch in treeSha baseSha; do
  repo="$(new_repo wrong-$mismatch)"
  printf '%s\n' '<?php // protected' > "$repo/wp-content/mu-plugins/locked.php"
  write_admission "$repo" wp-content/mu-plugins/locked.php
  commit_all "$repo" "wrong-$mismatch"
  adapter_payload "$repo" full-validate >/dev/null
  mutate_evidence "$repo" "$mismatch" 0000000000000000000000000000000000000000
  payload="$(reject_bastion "$repo" "wrong-$mismatch-evidence")"
  assert_payload "$payload" "'full-validation-evidence-mismatch' in p['sourceAdmissionReasons']"
done

repo="$(new_repo forbidden-authority)"
printf '\n# git push\n' >> "$repo/scripts/dev-delivery-v2-adapter.sh"
payload="$(reject_bastion "$repo" forbidden-authority)"
assert_payload "$payload" "any(reason.startswith('forbidden-authority-' + 'token:') for reason in p['blockingReasons'])"

echo 'dev delivery v2 adapter fixtures: PASS'
