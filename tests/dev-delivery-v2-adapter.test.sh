#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TMP_ROOT="$(mktemp -d)"
trap 'rm -rf "$TMP_ROOT"' EXIT

PROFILE_ID='deploy-control-source:sharity-staging-cas-v1'
PROFILE_PLAN='docs/sharity-profile-sp1-sol-release-gate-2026-09-09.md#canonical-staging-cas-implementation'
PROFILE_APPROVAL='operator-approval:sharity-profile-staging-cas-20260909'
PROFILE_PATHS=(
  .deploy.staging.env
  bin/deploy-wpcontent-map.sh
  bin/impactshop-guard-rollback.sh
  docs/impactshop-guard-hashes.json
  docs/impactshop-guard-hashes.sha256
  tests/deploy-wpcontent-map-exact-file.test.sh
  tests/impactshop-guard-rollback-truth.test.sh
)
PROFILE_SUPPORT_PATHS=(
  docs/impactshop-notes-doc-sync-map-2026-06-23.md
  docs/protected-change-records/2026-09-09-sharity-profile-staging-cas.md
  docs/sharity-profile-sp1-luna-continuity-2026-09-09.md
  docs/sharity-profile-sp1-sol-release-gate-2026-09-09.md
  notes.md
  system-status-snapshot.md
)

new_repo() {
  local name="$1"
  local repo="$TMP_ROOT/$name"
  mkdir -p "$repo/config" "$repo/scripts" "$repo/docs/protected-change-records" \
    "$repo/.github/workflows" "$repo/wp-content/mu-plugins" "$repo/tests"
  cp "$ROOT/config/dev-delivery-v2-target-contract.json" "$ROOT/config/dev-delivery-v2-impact-policy.json" "$repo/config/"
  cp "$ROOT/scripts/dev-delivery-v2-adapter.sh" "$ROOT/scripts/check-commit-lane.sh" \
    "$ROOT/scripts/check-protected-file-touch.sh" "$repo/scripts/"
  printf '%s\n' '{"protected_globs":["wp-content/mu-plugins/locked.php",".github/workflows/locked.yml",".deploy.staging.env","bin/deploy-wpcontent-map.sh","bin/impactshop-guard-rollback.sh","docs/impactshop-guard-hashes.json","docs/impactshop-guard-hashes.sha256","tests/deploy-wpcontent-map-exact-file.test.sh","tests/impactshop-guard-rollback-truth.test.sh"],"additive_globs":["docs/protected-change-records/*.md"],"required_smoke_tags":{"deploy_guard":["deploy:guard-preflight","deploy:checksum-verify"]},"smoke_group_globs":{"deploy_guard":[".github/workflows/*.yml"]}}' > "$repo/docs/impactshop-protected-files.json"
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

new_profile_repo() {
  local name="$1"
  local repo
  repo="$(new_repo "$name")"
  mkdir -p "$repo/bin" "$repo/docs" "$repo/tests"
  printf '%s\n' 'production companion: frozen' > "$repo/.deploy.production.env"
  printf '%s\n' 'staging base' > "$repo/.deploy.staging.env"
  printf '%s\n' '#!/usr/bin/env bash' 'staging base' > "$repo/bin/deploy-wpcontent-map.sh"
  printf '%s\n' '#!/usr/bin/env bash' 'staging base' > "$repo/bin/impactshop-guard-rollback.sh"
  printf '%s\n' '{"staging":"base"}' > "$repo/docs/impactshop-guard-hashes.json"
  printf '%s\n' 'base  docs/impactshop-guard-hashes.json' > "$repo/docs/impactshop-guard-hashes.sha256"
  printf '%s\n' '#!/usr/bin/env bash' 'staging base' > "$repo/tests/deploy-wpcontent-map-exact-file.test.sh"
  printf '%s\n' '#!/usr/bin/env bash' 'staging base' > "$repo/tests/impactshop-guard-rollback-truth.test.sh"
  printf '%s\n' '# DocSync map' 'profile base' > "$repo/docs/impactshop-notes-doc-sync-map-2026-06-23.md"
  printf '%s\n' '# Profile continuity' 'profile base' > "$repo/docs/sharity-profile-sp1-luna-continuity-2026-09-09.md"
  printf '%s\n' '# Canonical staging CAS implementation' '' "Approval reference: \`$PROFILE_APPROVAL\`." > "$repo/docs/sharity-profile-sp1-sol-release-gate-2026-09-09.md"
  printf '%s\n' '# Notes' 'profile base' > "$repo/notes.md"
  printf '%s\n' '# System status' 'profile base' > "$repo/system-status-snapshot.md"
  python3 - "$repo/config/dev-delivery-v2-target-contract.json" "$repo/config/dev-delivery-v2-impact-policy.json" "$repo" "${PROFILE_PATHS[@]}" <<'PY'
import hashlib, json, pathlib, sys
contract_path, policy_path, repo_path = map(pathlib.Path, sys.argv[1:4])
contract = json.loads(contract_path.read_text())
contract['sourceAdmissionProfiles']['deploy-control-source:sharity-staging-cas-v1']['requiredHeadSha256'] = {
    path: hashlib.sha256((repo_path / path).read_bytes() + b'profile candidate change\n').hexdigest()
    for path in sys.argv[4:]
}
contract_path.write_text(json.dumps(contract, indent=2) + '\n')
policy = json.loads(policy_path.read_text())
policy['contractSha256'] = hashlib.sha256(contract_path.read_bytes()).hexdigest()
policy_path.write_text(json.dumps(policy, separators=(',', ':')) + '\n')
PY
  commit_all "$repo" profile-base
  git -C "$repo" update-ref refs/remotes/origin/main HEAD
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

write_profile_admission() {
  local repo="$1"
  python3 - "$repo/docs/protected-change-records/2026-09-09-sharity-profile-staging-cas.md" "$PROFILE_ID" "$PROFILE_PLAN" "$PROFILE_APPROVAL" "$repo/.deploy.production.env" "$repo/config/dev-delivery-v2-target-contract.json" "${PROFILE_PATHS[@]}" <<'PY'
import hashlib, json, pathlib, sys
record = pathlib.Path(sys.argv[1])
production = pathlib.Path(sys.argv[5])
contract = json.loads(pathlib.Path(sys.argv[6]).read_text())
paths = sys.argv[7:]
manifest = {
    'schemaVersion': 2,
    'admissionProfile': sys.argv[2],
    'planRef': sys.argv[3],
    'operatorApprovalRef': sys.argv[4],
    'protectedPaths': paths,
    'reviewedHeadSha256': contract['sourceAdmissionProfiles'][sys.argv[2]]['requiredHeadSha256'],
    'reviewedUnchangedPaths': {'.deploy.production.env': hashlib.sha256(production.read_bytes()).hexdigest()},
    'rollbackNote': 'revert the exact staging CAS source checkpoint',
    'smokeTags': ['deploy:guard-preflight', 'deploy:checksum-verify', 'deploy:exact-release-cas', 'deploy:exact-rollback-cas'],
}
record.write_text(
    '# Profile protected change record\n\nProtected files touched. Rollback plan. Smoke checklist.\n\n'
    '<!-- BEGIN PROTECTED SOURCE ADMISSION -->\n'
    + json.dumps(manifest, indent=2, sort_keys=True)
    + '\n<!-- END PROTECTED SOURCE ADMISSION -->\n'
)
PY
}

modify_profile_paths() {
  local repo="$1"
  local path
  for path in "${PROFILE_PATHS[@]}"; do
    printf '%s\n' 'profile candidate change' >> "$repo/$path"
  done
}

modify_profile_support_paths() {
  local repo="$1"
  local path
  for path in "${PROFILE_SUPPORT_PATHS[@]}"; do
    [[ "$path" == docs/protected-change-records/2026-09-09-sharity-profile-staging-cas.md ]] && continue
    printf '%s\n' 'profile support change' >> "$repo/$path"
  done
}

prepare_profile_candidate() {
  local repo="$1"
  modify_profile_paths "$repo"
  modify_profile_support_paths "$repo"
  write_profile_admission "$repo"
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

repo="$(new_repo copy)"
printf '%s\n' '<?php // protected unchanged blob' > "$repo/wp-content/mu-plugins/locked.php"
commit_all "$repo" protected-base
git -C "$repo" update-ref refs/remotes/origin/main HEAD
mkdir -p "$repo/docs/archive"
cp "$repo/wp-content/mu-plugins/locked.php" "$repo/docs/archive/locked-copy.php"
commit_all "$repo" copy
copy_status="$(git -C "$repo" diff --name-status -M -C --find-copies-harder refs/remotes/origin/main..HEAD)"
[[ "$copy_status" == $'C100\twp-content/mu-plugins/locked.php\tdocs/archive/locked-copy.php' ]] || { echo "C100 fixture was not created" >&2; exit 1; }
payload="$(reject_bastion "$repo" protected-to-docs-copy)"
assert_payload "$payload" "p['changedPathClass']=='protected' and 'wp-content/mu-plugins/locked.php' in p['changedPaths'] and 'docs/archive/locked-copy.php' in p['changedPaths']"

repo="$(new_repo protected-admitted)"
printf '%s\n' '<?php // protected' > "$repo/wp-content/mu-plugins/locked.php"
write_admission "$repo" wp-content/mu-plugins/locked.php
commit_all "$repo" protected-admitted
payload="$(adapter_payload "$repo" full-validate)"
assert_payload "$payload" "p['decision']=='operator-review' and p['fullValidationEvidence'] is True and p['sourceMergeAdmission'] is False"
payload="$(adapter_payload "$repo" bastion)"
assert_payload "$payload" "p['decision']=='operator-review' and p['bastionDecision']=='pass' and p['sourceMergeAdmission'] is True"
adapter_payload "$repo" freeze >/dev/null
payload="$(adapter_payload "$repo" verify)"
assert_payload "$payload" "p['checkpointTreeMatchesCandidate'] is True and p['sourceMergeAdmission'] is True and p['fullValidationEvidence'] is True"

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

repo="$(new_profile_repo profile-positive)"
prepare_profile_candidate "$repo"
commit_all "$repo" profile-positive
payload="$(adapter_payload "$repo" full-validate)"
assert_payload "$payload" "p['changedPathClass']=='protected' and p['sourceAdmissionProfile']== '$PROFILE_ID' and p['providerDeployAllowed'] is False and p['sourceMergeAdmission'] is False"
payload="$(adapter_payload "$repo" bastion)"
assert_payload "$payload" "p['changedPathClass']=='protected' and p['sourceAdmissionProfile']== '$PROFILE_ID' and p['providerDeployAllowed'] is False and p['sourceMergeAdmission'] is True"
adapter_payload "$repo" freeze >/dev/null
payload="$(adapter_payload "$repo" verify)"
assert_payload "$payload" "p['sourceAdmissionProfile']== '$PROFILE_ID' and p['providerDeployAllowed'] is False and p['checkpointTreeMatchesCandidate'] is True"

repo="$(new_profile_repo profile-same-path-content-drift)"
prepare_profile_candidate "$repo"
printf '%s\n' 'unreviewed same-path mutation' >> "$repo/bin/deploy-wpcontent-map.sh"
commit_all "$repo" profile-same-path-content-drift
payload="$(reject_bastion "$repo" profile-same-path-content-drift)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and any('head-digest-mismatch' in reason for reason in p['sourceAdmissionReasons'])"

repo="$(new_profile_repo profile-support-addition)"
git -C "$repo" rm -q notes.md
commit_all "$repo" profile-support-absent-base
git -C "$repo" update-ref refs/remotes/origin/main HEAD
prepare_profile_candidate "$repo"
commit_all "$repo" profile-support-addition
payload="$(reject_bastion "$repo" profile-support-addition)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and any('support-non-modification-forbidden' in reason for reason in p['sourceAdmissionReasons'])"

repo="$(new_profile_repo profile-subset)"
for path in "${PROFILE_PATHS[@]:0:6}"; do printf '%s\n' 'subset candidate change' >> "$repo/$path"; done
modify_profile_support_paths "$repo"
write_profile_admission "$repo"
commit_all "$repo" profile-subset
payload="$(reject_bastion "$repo" profile-subset)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and p['sourceAdmissionProfile'] is None and 'deploy-source-admission-forbidden' in p['sourceAdmissionReasons']"

repo="$(new_profile_repo profile-superset)"
prepare_profile_candidate "$repo"
printf '%s\n' '<?php // extra runtime path' > "$repo/wp-content/mu-plugins/extra-runtime.php"
commit_all "$repo" profile-superset
payload="$(reject_bastion "$repo" profile-superset)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and p['sourceAdmissionProfile'] is None and 'deploy-source-admission-forbidden' in p['sourceAdmissionReasons']"

for extra in workflow remote-script; do
  repo="$(new_profile_repo profile-$extra)"
  prepare_profile_candidate "$repo"
  if [[ "$extra" == workflow ]]; then
    printf '%s\n' 'name: extra' 'jobs:' '  deploy:' '    runs-on: ubuntu-latest' '    steps:' '      - run: provider-deploy production' > "$repo/.github/workflows/extra.yml"
  else
    printf '%s\n' '#!/usr/bin/env bash' 'rsync payload host:/remote/path' > "$repo/scripts/extra-remote.sh"
  fi
  commit_all "$repo" profile-$extra
  payload="$(reject_bastion "$repo" profile-$extra)"
  assert_payload "$payload" "p['changedPathClass']=='deploy' and p['sourceAdmissionProfile'] is None and 'deploy-source-admission-forbidden' in p['sourceAdmissionReasons']"
done

for operation in rename copy delete; do
  repo="$(new_profile_repo profile-$operation)"
  prepare_profile_candidate "$repo"
  case "$operation" in
    rename) git -C "$repo" mv bin/deploy-wpcontent-map.sh scripts/renamed-map.sh ;;
    copy) cp "$repo/bin/deploy-wpcontent-map.sh" "$repo/scripts/copied-map.sh" ;;
    delete) rm "$repo/bin/deploy-wpcontent-map.sh" ;;
  esac
  commit_all "$repo" profile-$operation
  payload="$(reject_bastion "$repo" profile-$operation)"
  assert_payload "$payload" "p['changedPathClass']=='deploy' and p['sourceAdmissionProfile'] is None and 'deploy-source-admission-forbidden' in p['sourceAdmissionReasons']"
done

for ref_kind in approval plan; do
  repo="$(new_profile_repo profile-wrong-$ref_kind-ref)"
  prepare_profile_candidate "$repo"
  python3 - "$repo/docs/protected-change-records/2026-09-09-sharity-profile-staging-cas.md" "$ref_kind" <<'PY'
import pathlib, sys
path = pathlib.Path(sys.argv[1])
text = path.read_text()
if sys.argv[2] == 'approval':
    text = text.replace('operator-approval:sharity-profile-staging-cas-20260909', 'operator-approval:wrong-profile-ref')
else:
    text = text.replace('docs/sharity-profile-sp1-sol-release-gate-2026-09-09.md#canonical-staging-cas-implementation', 'docs/sharity-profile-sp1-sol-release-gate-2026-09-09.md#wrong-plan-ref')
path.write_text(text)
PY
  commit_all "$repo" profile-wrong-$ref_kind-ref
  payload="$(reject_bastion "$repo" profile-wrong-$ref_kind-ref)"
  assert_payload "$payload" "p['changedPathClass']=='deploy' and 'protected-change-record-$ref_kind-ref-invalid' in ' '.join(p['sourceAdmissionReasons'])"
done

repo="$(new_profile_repo profile-changed-companion)"
modify_profile_paths "$repo"
modify_profile_support_paths "$repo"
printf '%s\n' 'companion changed' >> "$repo/.deploy.production.env"
write_profile_admission "$repo"
commit_all "$repo" profile-changed-companion
payload="$(reject_bastion "$repo" profile-changed-companion)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and 'deploy-source-admission-forbidden' in p['sourceAdmissionReasons']"

repo="$(new_profile_repo profile-reviewed-digest-mismatch)"
prepare_profile_candidate "$repo"
python3 - "$repo/docs/protected-change-records/2026-09-09-sharity-profile-staging-cas.md" <<'PY'
import pathlib, re, sys
path = pathlib.Path(sys.argv[1])
path.write_text(re.sub(r'("\.deploy\.production\.env": ")[0-9a-f]{64}("\s*)', r'\g<1>' + ('0' * 64) + r'\2', path.read_text()))
PY
commit_all "$repo" profile-reviewed-digest-mismatch
payload="$(reject_bastion "$repo" profile-reviewed-digest-mismatch)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and any('reviewed-digest-mismatch' in reason for reason in p['sourceAdmissionReasons'])"

repo="$(new_profile_repo profile-unknown-profile)"
prepare_profile_candidate "$repo"
python3 - "$repo/docs/protected-change-records/2026-09-09-sharity-profile-staging-cas.md" <<'PY'
import pathlib, sys
path = pathlib.Path(sys.argv[1]); path.write_text(path.read_text().replace('deploy-control-source:sharity-staging-cas-v1', 'deploy-control-source:unknown-profile'))
PY
commit_all "$repo" profile-unknown-profile
payload="$(reject_bastion "$repo" profile-unknown-profile)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and any('profile-invalid' in reason for reason in p['sourceAdmissionReasons'])"

repo="$(new_profile_repo profile-evidence-tamper)"
prepare_profile_candidate "$repo"
commit_all "$repo" profile-evidence-tamper
adapter_payload "$repo" full-validate >/dev/null
mutate_evidence "$repo" sourceAdmissionProfile tampered
payload="$(reject_bastion "$repo" profile-evidence-source-profile)"
assert_payload "$payload" "'full-validation-evidence-mismatch' in p['sourceAdmissionReasons']"

repo="$(new_profile_repo profile-provider-flag)"
python3 - "$repo/config/dev-delivery-v2-impact-policy.json" <<'PY'
import json, pathlib, sys
path = pathlib.Path(sys.argv[1]); data = json.loads(path.read_text()); data['providerDeployAllowed'] = True; path.write_text(json.dumps(data, separators=(',', ':')) + '\n')
PY
commit_all "$repo" profile-provider-flag
payload="$(reject_bastion "$repo" profile-provider-flag)"
assert_payload "$payload" "p['bastionDecision']=='blocked' and p['sourceMergeAdmission'] is False and p['blockingReasons'] != []"

repo="$(new_profile_repo profile-provider-authority)"
python3 - "$repo/config/dev-delivery-v2-target-contract.json" "$repo/config/dev-delivery-v2-impact-policy.json" <<'PY'
import hashlib, json, pathlib, sys
contract = pathlib.Path(sys.argv[1]); data = json.loads(contract.read_text()); data['provider']['automaticProductDeployAuthority'] = True; contract.write_text(json.dumps(data, indent=2) + '\n')
policy = pathlib.Path(sys.argv[2]); pdata = json.loads(policy.read_text()); pdata['contractSha256'] = hashlib.sha256(contract.read_bytes()).hexdigest(); policy.write_text(json.dumps(pdata, separators=(',', ':')) + '\n')
PY
commit_all "$repo" profile-provider-authority
payload="$(reject_bastion "$repo" profile-provider-authority)"
assert_payload "$payload" "p['bastionDecision']=='blocked' and p['sourceMergeAdmission'] is False and p['blockingReasons'] != []"

repo="$(new_profile_repo profile-digest-drift)"
python3 - "$repo/config/dev-delivery-v2-impact-policy.json" <<'PY'
import json, pathlib, sys
path = pathlib.Path(sys.argv[1]); data = json.loads(path.read_text()); data['contractSha256'] = '0' * 64; path.write_text(json.dumps(data, separators=(',', ':')) + '\n')
PY
commit_all "$repo" profile-digest-drift
payload="$(reject_bastion "$repo" profile-digest-drift)"
assert_payload "$payload" "p['bastionDecision']=='blocked' and p['sourceMergeAdmission'] is False and 'target-contract-digest-mismatch' in p['blockingReasons']"

repo="$(new_profile_repo profile-self-admission)"
prepare_profile_candidate "$repo"
printf '%s\n' '# bounded self-admission fixture' >> "$repo/scripts/dev-delivery-v2-adapter.sh"
commit_all "$repo" profile-self-admission
payload="$(reject_bastion "$repo" profile-self-admission)"
assert_payload "$payload" "p['changedPathClass']=='deploy' and p['sourceAdmissionProfile'] is None and 'deploy-source-admission-forbidden' in p['sourceAdmissionReasons']"

repo="$(new_repo forbidden-authority)"
printf '\n# git push\n' >> "$repo/scripts/dev-delivery-v2-adapter.sh"
payload="$(reject_bastion "$repo" forbidden-authority)"
assert_payload "$payload" "p['bastionDecision']=='blocked' and p['sourceMergeAdmission'] is False and p['blockingReasons'] != []"

echo 'dev delivery v2 adapter fixtures: PASS'
