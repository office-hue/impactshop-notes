#!/usr/bin/env bash
set -euo pipefail

cmd="${1:-}"
shift || true
json=0
fixture=""
while (($#)); do
  case "$1" in
    --json) json=1; shift ;;
    --offline-fixture) fixture="${2:-}"; shift 2 ;;
    *) echo "usage: adapter <inspect|freeze|verify|full-validate|bastion> [--json] [--offline-fixture ROOT]" >&2; exit 2 ;;
  esac
done
case "$cmd" in inspect|freeze|verify|full-validate|bastion) ;; *) exit 2 ;; esac
[[ -z "$fixture" || "$cmd" == inspect || "$cmd" == bastion ]] || { echo "offline fixture mode is read-only" >&2; exit 1; }

script_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
if [[ -n "$fixture" ]]; then
  root="$(cd "$fixture" && pwd -P)"
else
  root="$(git -C "$script_root" rev-parse --show-toplevel)"
  [[ "$root" == "$script_root" ]] || { echo exact-current-worktree-root-required >&2; exit 1; }
fi

python3 - "$cmd" "$root" "$fixture" "$json" <<'PY'
import fnmatch
import hashlib
import json
import os
import re
import stat
import subprocess
import sys
from pathlib import Path

cmd, root_raw, fixture_raw, as_json = sys.argv[1:5]
root = Path(root_raw).resolve()
fixture = bool(fixture_raw)
reasons = []
source_reasons = []

ADMISSION_BEGIN = '<!-- BEGIN PROTECTED SOURCE ADMISSION -->'
ADMISSION_END = '<!-- END PROTECTED SOURCE ADMISSION -->'
FULL_VALIDATION_KIND = 'repo-local-protected-touch-and-commit-lane'
PROFILE_ID = 'deploy-control-source:sharity-staging-cas-v1'
PROFILE_PATHS = [
    '.deploy.staging.env',
    'bin/deploy-wpcontent-map.sh',
    'bin/impactshop-guard-rollback.sh',
    'docs/impactshop-guard-hashes.json',
    'docs/impactshop-guard-hashes.sha256',
    'tests/deploy-wpcontent-map-exact-file.test.sh',
    'tests/impactshop-guard-rollback-truth.test.sh',
]
PROFILE_UNCHANGED_PATHS = ['.deploy.production.env']
PROFILE_SUPPORT_PATHS = [
    'docs/impactshop-notes-doc-sync-map-2026-06-23.md',
    'docs/protected-change-records/2026-09-09-sharity-profile-staging-cas.md',
    'docs/sharity-profile-sp1-luna-continuity-2026-09-09.md',
    'docs/sharity-profile-sp1-sol-release-gate-2026-09-09.md',
    'notes.md',
    'system-status-snapshot.md',
]
PROFILE_RECORD_PATH = 'docs/protected-change-records/2026-09-09-sharity-profile-staging-cas.md'
PROFILE_PLAN_REF = 'docs/sharity-profile-sp1-sol-release-gate-2026-09-09.md#canonical-staging-cas-implementation'
PROFILE_APPROVAL_REF = 'operator-approval:sharity-profile-staging-cas-20260909'
PROFILE_REQUIRED_HEAD_SHA256 = {}

def run_git(*args):
    return subprocess.run(
        ['git', '-C', str(root), *args], text=True, capture_output=True, check=False
    )

def git(*args):
    result = run_git(*args)
    return result.stdout.strip() if result.returncode == 0 else ''

def has_object(spec):
    return run_git('cat-file', '-e', spec).returncode == 0

def require_commit(ref, label):
    if not re.fullmatch(r'[0-9a-f]{40}', ref or ''):
        raise ValueError('invalid-' + label + '-sha')
    resolved = git('rev-parse', '--verify', ref + '^{commit}')
    if resolved != ref:
        raise ValueError('unresolved-' + label + '-commit')
    return resolved

def resolve_base():
    explicit = os.environ.get('DEV_DELIVERY_V2_BASE_SHA')
    if explicit:
        return require_commit(explicit, 'base')
    event_path = Path(os.environ.get('GITHUB_EVENT_PATH', ''))
    if event_path.is_file():
        try:
            event_base = json.loads(event_path.read_text()).get('pull_request', {}).get('base', {}).get('sha')
        except (OSError, json.JSONDecodeError):
            raise ValueError('invalid-github-event-base')
        if event_base:
            return require_commit(event_base, 'base')
    return require_commit(git('rev-parse', '--verify', 'origin/main^{commit}'), 'base')

def file(path):
    candidate = (root / path).resolve()
    if root not in candidate.parents or not candidate.is_file() or candidate.is_symlink():
        raise ValueError('unsafe-or-missing:' + str(path))
    return candidate

def git_file(ref, path):
    result = run_git('show', ref + ':' + path)
    return result.stdout if result.returncode == 0 else ''

def git_file_bytes(ref, path):
    result = subprocess.run(
        ['git', '-C', str(root), 'show', ref + ':' + path],
        capture_output=True, check=False,
    )
    return result.stdout if result.returncode == 0 else None

def private_state():
    state = Path(git('rev-parse', '--git-path', policy['privateState']['gitPath']))
    return state if state.is_absolute() else root / state

def validation_record():
    return private_state() / 'full-validation.json'

def parse_changes(text):
    parsed = []
    for line in text.splitlines():
        fields = line.split('\t')
        if len(fields) < 2:
            continue
        status = fields[0]
        kind = status[:1]
        if kind in ('R', 'C'):
            if len(fields) < 3:
                raise ValueError('malformed-rename-copy-status')
            endpoints = [fields[1], fields[2]]
            current = [fields[2]]
        else:
            endpoints = [fields[1]]
            current = [] if kind == 'D' else [fields[1]]
        parsed.append({'status': status, 'endpoints': endpoints, 'current': current})
    return parsed

def markdown_slug(value):
    value = value.strip().lower()
    value = re.sub(r'[^a-z0-9\s-]', '', value)
    return re.sub(r'[\s-]+', '-', value).strip('-')

def parse_admission_manifest(record_path, protected_paths):
    text = git_file(head, record_path)
    if ADMISSION_BEGIN not in text or ADMISSION_END not in text:
        return None, 'protected-change-record-manifest-missing:' + record_path
    if text.count(ADMISSION_BEGIN) != 1 or text.count(ADMISSION_END) != 1:
        return None, 'protected-change-record-manifest-ambiguous:' + record_path
    raw = text.split(ADMISSION_BEGIN, 1)[1].split(ADMISSION_END, 1)[0].strip()
    try:
        manifest = json.loads(raw)
    except json.JSONDecodeError:
        return None, 'protected-change-record-manifest-invalid:' + record_path
    required_v1 = {
        'schemaVersion', 'planRef', 'operatorApprovalRef', 'protectedPaths',
        'rollbackNote', 'smokeTags'
    }
    schema = manifest.get('schemaVersion')
    if schema == 1:
        if set(manifest) != required_v1:
            return None, 'protected-change-record-schema-invalid:' + record_path
    elif schema == 2:
        required_v2 = required_v1 | {
            'admissionProfile', 'reviewedHeadSha256', 'reviewedUnchangedPaths'
        }
        if set(manifest) != required_v2:
            return None, 'protected-change-record-schema-invalid:' + record_path
        if manifest.get('admissionProfile') != PROFILE_ID:
            return None, 'protected-change-record-profile-invalid:' + record_path
        reviewed = manifest.get('reviewedUnchangedPaths')
        if (
            not isinstance(reviewed, dict)
            or set(reviewed) != set(PROFILE_UNCHANGED_PATHS)
            or any(not isinstance(value, str) or not re.fullmatch(r'[0-9a-f]{64}', value) for value in reviewed.values())
        ):
            return None, 'protected-change-record-unchanged-paths-invalid:' + record_path
        if manifest.get('protectedPaths') != PROFILE_PATHS:
            return None, 'protected-change-record-path-coverage-mismatch:' + record_path
        if manifest.get('reviewedHeadSha256') != PROFILE_REQUIRED_HEAD_SHA256:
            return None, 'protected-change-record-reviewed-head-invalid:' + record_path
        if set(protected_paths) != set(PROFILE_PATHS):
            return None, 'protected-change-record-path-coverage-mismatch:' + record_path
        if manifest.get('planRef') != PROFILE_PLAN_REF:
            return None, 'protected-change-record-plan-ref-invalid:' + record_path
        if manifest.get('operatorApprovalRef') != PROFILE_APPROVAL_REF:
            return None, 'protected-change-record-approval-ref-invalid:' + record_path
    else:
        return None, 'protected-change-record-schema-invalid:' + record_path
    recorded_paths = manifest.get('protectedPaths')
    if not isinstance(recorded_paths, list) or len(recorded_paths) != len(set(recorded_paths)):
        return None, 'protected-change-record-paths-invalid:' + record_path
    if schema == 1 and set(recorded_paths) != set(protected_paths):
        return None, 'protected-change-record-path-coverage-mismatch:' + record_path
    plan_ref = manifest.get('planRef')
    approval_ref = manifest.get('operatorApprovalRef')
    if not isinstance(plan_ref, str) or '#' not in plan_ref:
        return None, 'protected-change-record-plan-ref-invalid:' + record_path
    plan_path, fragment = plan_ref.split('#', 1)
    if not plan_path.startswith('docs/') or '..' in Path(plan_path).parts:
        return None, 'protected-change-record-plan-ref-invalid:' + record_path
    plan_text = git_file(head, plan_path)
    headings = {
        markdown_slug(line.lstrip('#').strip())
        for line in plan_text.splitlines() if line.startswith('#')
    }
    if not plan_text or fragment not in headings:
        return None, 'protected-change-record-plan-ref-unresolved:' + record_path
    if not isinstance(approval_ref, str) or not re.fullmatch(r'operator-approval:[a-z0-9][a-z0-9._:-]{7,127}', approval_ref):
        return None, 'protected-change-record-approval-ref-invalid:' + record_path
    if approval_ref not in plan_text:
        return None, 'protected-change-record-approval-ref-unresolved:' + record_path
    if not isinstance(manifest.get('rollbackNote'), str) or not manifest['rollbackNote'].strip():
        return None, 'protected-change-record-rollback-invalid:' + record_path
    tags = manifest.get('smokeTags')
    if not isinstance(tags, list) or not tags or any(not isinstance(tag, str) or not tag.strip() for tag in tags):
        return None, 'protected-change-record-smoke-invalid:' + record_path
    manifest['recordPath'] = record_path
    manifest['profileExact'] = schema == 2
    return manifest, None

def resolve_admission_manifest(protected_paths):
    candidates = sorted({
        path
        for change in changes
        for path in change['current']
        if path.startswith('docs/protected-change-records/') and path.endswith('.md')
    })
    valid = []
    invalid = []
    profile_attempted = False
    for record_path in candidates:
        raw = git_file(head, record_path)
        if '"admissionProfile"' in raw or '"schemaVersion": 2' in raw:
            profile_attempted = True
        manifest, error = parse_admission_manifest(record_path, protected_paths)
        if manifest:
            valid.append(manifest)
        elif ADMISSION_BEGIN in git_file(head, record_path):
            invalid.append(error)
    if len(valid) == 1:
        return valid[0], [], profile_attempted
    if len(valid) > 1:
        return None, ['protected-change-record-manifest-ambiguous'], profile_attempted
    return None, invalid or ['protected-change-record-exact-admission-missing'], profile_attempted

try:
    contract = file(Path('config/dev-delivery-v2-target-contract.json'))
    policy = json.loads(file(Path('config/dev-delivery-v2-impact-policy.json')).read_text())
    digest = hashlib.sha256(contract.read_bytes()).hexdigest()
    if digest != policy['contractSha256']:
        reasons.append('target-contract-digest-mismatch')
    contract_data = json.loads(contract.read_text())
    if contract_data.get('schemaVersion') != 2 or contract_data.get('authority') != 'repo-local' or contract_data.get('centralRuntimeDependency') is not False:
        reasons.append('target-contract-invalid')
    if contract_data.get('repoRoot', {}).get('production') != 'exact-current-worktree-root-only' or contract_data.get('provider', {}).get('automaticProductDeployAuthority') is not False:
        reasons.append('target-contract-policy-drift')
    profiles = contract_data.get('sourceAdmissionProfiles')
    profile = profiles.get(PROFILE_ID) if isinstance(profiles, dict) else None
    expected_profile = {
        'schemaVersion': 2,
        'protectedPaths': PROFILE_PATHS,
        'requiredUnchangedPaths': PROFILE_UNCHANGED_PATHS,
        'requiredChangedSupportPaths': PROFILE_SUPPORT_PATHS,
        'planRef': PROFILE_PLAN_REF,
        'operatorApprovalRef': PROFILE_APPROVAL_REF,
        'providerDeployAllowed': False,
    }
    profile_head_sha256 = profile.get('requiredHeadSha256') if isinstance(profile, dict) else None
    profile_without_hashes = {
        key: value for key, value in profile.items() if key != 'requiredHeadSha256'
    } if isinstance(profile, dict) else None
    valid_profile_hashes = (
        isinstance(profile_head_sha256, dict)
        and set(profile_head_sha256) == set(PROFILE_PATHS)
        and all(
            isinstance(value, str) and re.fullmatch(r'[0-9a-f]{64}', value)
            for value in profile_head_sha256.values()
        )
    )
    if (
        not isinstance(profiles, dict)
        or set(profiles) != {PROFILE_ID}
        or profile_without_hashes != expected_profile
        or not valid_profile_hashes
    ):
        reasons.append('source-admission-profile-invalid')
    else:
        PROFILE_REQUIRED_HEAD_SHA256.update(profile_head_sha256)
    if policy.get('protectionLevel') != 'maximum':
        reasons.append('maximum-bastion-required')
    if policy.get('providerDeployAllowed') is not False or policy.get('provider', {}).get('automaticProductDeployAuthority') is not False:
        reasons.append('policy-provider-authority-drift')
    source = file(Path('scripts/dev-delivery-v2-adapter.sh')).read_text()
    for token in policy['forbiddenAuthorityTokens']:
        if token in source:
            reasons.append('forbidden-authority-token:' + token.strip())

    branch = git('branch', '--show-current')
    base = resolve_base()
    head = require_commit(git('rev-parse', '--verify', 'HEAD^{commit}'), 'head')
    tree = git('show', '-s', '--format=%T', 'HEAD')
    if not re.fullmatch(r'[0-9a-f]{40}', tree or '') or not has_object(tree + '^{tree}'):
        raise ValueError('unresolved-head-tree')
    diff = run_git('diff', '--no-ext-diff', '--name-status', '-M', '-C', '--find-copies-harder', f'{base}..{head}')
    if diff.returncode != 0:
        raise ValueError('base-head-diff-failed')
    changes = parse_changes(diff.stdout)
    paths = sorted({path for change in changes for path in change['endpoints']})
    current_paths = sorted({path for change in changes for path in change['current']})

    protected_model = json.loads(file(Path('docs/impactshop-protected-files.json')).read_text())
    protected_globs = protected_model.get('protected_globs', [])
    protected_list = [
        line.strip() for line in file(Path('.github/protected-files.txt')).read_text().splitlines()
        if line.strip() and not line.lstrip().startswith('#')
    ]
    def listed_protected(path):
        return any(fnmatch.fnmatch(path, pattern) for pattern in protected_globs + protected_list)
    def protected_path(path):
        return listed_protected(path) or path.startswith((
            'wp-content/', 'scripts/', 'bin/', 'deploy/', '.deploy.',
            '.github/workflows/', 'config/', 'docs/impactshop-protected',
            'docs/impactshop-guard-'
        ))
    protected_paths = sorted(path for path in paths if protected_path(path))

    executable_extensions = ('.sh', '.py', '.js', '.mjs', '.ts', '.yml', '.yaml')
    provider_cli = '(?:' + 'ver' + 'cel' + r'\s+(?:deploy|--prod)|' + 'rail' + 'way' + r'\s+(?:up|deploy))'
    remote_write = re.compile(
        r'\b(git\s+push|gh\s+pr\s+(?:create|merge)|ssh\s+(?:-[a-z]|[^\s"\x27]+@)|scp\s+|'
        r'rsync(?:\s|$)|curl(?:\s|$)|wget(?:\s|$)|provider[ -]deploy|'
        r'remote[ -]write|' + provider_cli + ')',
        re.I,
    )
    def executable_surface(path):
        return (
            path.endswith(executable_extensions)
            and (
                path.startswith(('scripts/', 'bin/', 'deploy/'))
                or (path.startswith('.github/workflows/') and path.endswith(('.yml', '.yaml')))
            )
        )
    content_deploy = any(
        executable_surface(path) and remote_write.search(git_file(head, path))
        for path in current_paths
    )
    deploy_path = any(path.startswith(('bin/', 'deploy/', '.deploy.')) for path in paths)
    governance_paths = ('docs/', 'tests/')
    governance_files = {'AGENTS.md', 'notes.md', 'system-status-snapshot.md', 'DOC-SYNC-HUB.md'}
    if not paths:
        ordinary_impact = 'governance-only'
    elif content_deploy or deploy_path:
        ordinary_impact = 'deploy'
    elif protected_paths:
        ordinary_impact = 'protected'
    elif all(path.startswith(governance_paths) or path in governance_files for path in paths):
        ordinary_impact = 'governance-only'
    else:
        ordinary_impact = 'unknown'

    manifest, manifest_reasons, profile_attempted = resolve_admission_manifest(protected_paths)
    profile_reasons = []
    profile_exact = False
    if manifest and manifest.get('profileExact'):
        by_path = {path: change for change in changes for path in change['endpoints']}
        expected_paths = set(PROFILE_PATHS + PROFILE_SUPPORT_PATHS)
        if set(paths) != expected_paths:
            profile_reasons.append('source-admission-profile-paths-not-exact')
        if manifest['recordPath'] != PROFILE_RECORD_PATH or manifest['recordPath'] not in paths:
            profile_reasons.append('source-admission-profile-record-missing')
        if any(change['status'][:1] in ('D', 'R', 'C') for change in changes):
            profile_reasons.append('source-admission-profile-rename-copy-delete-forbidden')
        if any(path not in by_path or by_path[path]['status'] != 'M' for path in PROFILE_PATHS):
            profile_reasons.append('source-admission-profile-non-modification-forbidden')
        if any(
            path != PROFILE_RECORD_PATH
            and (path not in by_path or by_path[path]['status'] != 'M')
            for path in PROFILE_SUPPORT_PATHS
        ):
            profile_reasons.append('source-admission-profile-support-non-modification-forbidden')
        if by_path.get(PROFILE_RECORD_PATH, {}).get('status') != 'A':
            profile_reasons.append('source-admission-profile-record-addition-required')
        for protected_path, expected_digest in PROFILE_REQUIRED_HEAD_SHA256.items():
            content = git_file_bytes(head, protected_path)
            if content is None or hashlib.sha256(content).hexdigest() != expected_digest:
                profile_reasons.append('source-admission-profile-head-digest-mismatch:' + protected_path)
        for unchanged_path in PROFILE_UNCHANGED_PATHS:
            before = git_file_bytes(base, unchanged_path)
            after = git_file_bytes(head, unchanged_path)
            if before is None or after is None or before != after:
                profile_reasons.append('source-admission-profile-unchanged-companion-mismatch:' + unchanged_path)
                continue
            reviewed_digest = manifest['reviewedUnchangedPaths'][unchanged_path]
            if hashlib.sha256(after).hexdigest() != reviewed_digest:
                profile_reasons.append('source-admission-profile-reviewed-digest-mismatch:' + unchanged_path)
        extra = sorted(set(paths) - expected_paths)
        if extra:
            profile_reasons.append('source-admission-profile-extra-path:' + extra[0])
        profile_exact = not profile_reasons
    elif profile_attempted:
        profile_reasons.extend(manifest_reasons)

    impact = 'protected' if profile_exact else ordinary_impact
    if profile_attempted and not profile_exact:
        impact = 'deploy'
    decision = 'allowed' if impact == 'governance-only' else ('operator-review' if impact in ('protected', 'deploy') else 'blocked')
    if impact == 'unknown':
        reasons.append('unknown-path-class')
    if reasons and decision == 'allowed':
        decision = 'blocked'
    payload = {
        'schemaVersion': 2,
        'repo': 'impactshop-notes',
        'authoritySource': 'repo-local',
        'branch': branch or None,
        'baseSha': base,
        'headSha': head,
        'treeSha': tree,
        'candidateTreeSha': tree,
        'changedPathClass': impact,
        'changedPaths': paths,
        'protectedChangedPaths': protected_paths,
        'providerBuildDecision': policy['provider'].get(impact, 'operator-review'),
        'providerDeployAllowed': False,
        'sourceAdmissionProfile': PROFILE_ID if profile_exact else None,
        'evidenceReuseAllowed': not reasons,
        'decision': decision,
        'blockingReasons': reasons,
        'sourceAdmissionReasons': [],
        'contractSha256': digest,
        'fixtureMode': fixture,
        'sourceMergeAdmission': False,
    }

    evidence_expected = {
        'baseSha': base,
        'headSha': head,
        'treeSha': tree,
        'changedPathClass': impact,
        'contractSha256': digest,
        'fullValidation': FULL_VALIDATION_KIND,
        'providerDeployAllowed': False,
        'sourceAdmissionProfile': PROFILE_ID if profile_exact else None,
        'protectedChangeRecord': manifest.get('recordPath') if manifest else None,
        'planRef': manifest.get('planRef') if manifest else None,
        'operatorApprovalRef': manifest.get('operatorApprovalRef') if manifest else None,
    }

    if cmd == 'full-validate':
        if fixture:
            raise ValueError('fixture-full-validation-forbidden')
        if run_git('diff', '--quiet').returncode != 0 or run_git('diff', '--cached', '--quiet').returncode != 0 or run_git('status', '--porcelain').stdout.strip():
            raise ValueError('dirty-worktree-evidence-forbidden')
        if protected_paths and manifest_reasons:
            raise ValueError(manifest_reasons[0])
        checks = [['bash', 'scripts/check-commit-lane.sh', '--mode', 'push', '--push-range', base + '..' + head]]
        if protected_paths:
            env = dict(
                os.environ,
                BASTION_OVERRIDE='1',
                BASTION_CHANGE_RECORD=manifest['recordPath'],
                BASTION_ROLLBACK_NOTE=manifest['rollbackNote'],
                BASTION_SMOKE_TAGS=','.join(manifest['smokeTags']),
            )
            checks.append(['bash', 'scripts/check-protected-file-touch.sh', '--mode', 'push', '--push-range', base + '..' + head])
        else:
            env = dict(os.environ)
        for command in checks:
            checked = subprocess.run(command, cwd=str(root), text=True, capture_output=True, env=env, check=False)
            if checked.returncode:
                raise ValueError('full-validation-failed:' + command[1])
        state = private_state()
        state.mkdir(mode=0o700, parents=True, exist_ok=True)
        os.chmod(state, 0o700)
        evidence = {'schemaVersion': 2, **evidence_expected}
        validation_record().write_text(json.dumps(evidence, sort_keys=True) + '\n')
        os.chmod(validation_record(), 0o600)
        payload['fullValidationEvidence'] = True

    def validate_evidence():
        record = validation_record()
        if not record.is_file() or not private_state().is_dir():
            return ['full-validation-evidence-missing']
        if stat.S_IMODE(record.stat().st_mode) != 0o600 or stat.S_IMODE(private_state().stat().st_mode) != 0o700:
            return ['full-validation-evidence-permissions-invalid']
        try:
            frozen = json.loads(record.read_text())
        except (OSError, json.JSONDecodeError):
            return ['full-validation-evidence-invalid']
        expected = {'schemaVersion': 2, **evidence_expected}
        if set(frozen) != set(expected) or any(frozen.get(key) != value for key, value in expected.items()):
            return ['full-validation-evidence-mismatch']
        payload['fullValidationEvidence'] = True
        return []

    if cmd in ('bastion', 'freeze'):
        payload['protectionLevel'] = 'maximum'
        payload['bastionDecision'] = 'pass' if not reasons else 'blocked'
        if impact == 'governance-only' and not reasons:
            payload['sourceMergeAdmission'] = True
        elif impact == 'protected':
            source_reasons.extend(manifest_reasons)
            source_reasons.extend(validate_evidence())
            if not source_reasons and not reasons:
                payload['sourceMergeAdmission'] = True
                payload['protectedChangeRecord'] = manifest['recordPath']
                payload['planRef'] = manifest['planRef']
                payload['operatorApprovalRef'] = manifest['operatorApprovalRef']
        elif impact == 'deploy':
            source_reasons.extend(validate_evidence())
            source_reasons.extend(manifest_reasons)
            source_reasons.extend(profile_reasons)
            source_reasons.append('deploy-source-admission-forbidden')
        elif impact == 'unknown':
            source_reasons.append('unknown-source-admission-forbidden')
        payload['sourceAdmissionReasons'] = list(dict.fromkeys(source_reasons))
        payload['evidenceReuseAllowed'] = not reasons and not source_reasons

    if cmd == 'freeze':
        if not payload['sourceMergeAdmission']:
            raise ValueError('candidate-not-admissible')
        state = private_state()
        state.mkdir(mode=0o700, parents=True, exist_ok=True)
        os.chmod(state, 0o700)
        payload['candidateTreeSha'] = git('write-tree')
        candidate_record = state / 'candidate.json'
        candidate_record.write_text(json.dumps(payload, sort_keys=True) + '\n')
        os.chmod(candidate_record, 0o600)

    if cmd == 'verify':
        state = private_state()
        candidate_record = state / 'candidate.json'
        if not candidate_record.is_file() or stat.S_IMODE(candidate_record.stat().st_mode) != 0o600 or stat.S_IMODE(state.stat().st_mode) != 0o700:
            raise ValueError('private-evidence-state-invalid')
        frozen = json.loads(candidate_record.read_text())
        candidate = frozen.get('candidateTreeSha')
        identity = {
            'baseSha': base,
            'headSha': head,
            'treeSha': tree,
            'contractSha256': digest,
            'providerDeployAllowed': False,
            'sourceAdmissionProfile': PROFILE_ID if profile_exact else None,
        }
        if any(frozen.get(key) != value for key, value in identity.items()) or frozen.get('sourceMergeAdmission') is not True:
            raise ValueError('candidate-evidence-identity-mismatch')
        if candidate != git('write-tree'):
            raise ValueError('candidate-index-tree-mismatch')
        if candidate != tree:
            raise ValueError('checkpoint-tree-mismatch')
        payload['candidateTreeSha'] = candidate
        payload['checkpointTreeMatchesCandidate'] = True
        payload['sourceMergeAdmission'] = True
        payload['fullValidationEvidence'] = frozen.get('fullValidationEvidence') is True
        for key in ('protectedChangeRecord', 'planRef', 'operatorApprovalRef'):
            if key in frozen:
                payload[key] = frozen[key]

    print(json.dumps(payload, sort_keys=True) if as_json == '1' else '[dev-delivery-v2] decision=' + payload['decision'])
    if cmd == 'full-validate':
        ok = payload.get('fullValidationEvidence') is True
    elif cmd == 'bastion':
        ok = payload.get('bastionDecision') == 'pass' and payload.get('sourceMergeAdmission') is True
    elif cmd == 'freeze':
        ok = payload.get('sourceMergeAdmission') is True
    elif cmd == 'verify':
        ok = payload.get('checkpointTreeMatchesCandidate') is True
    else:
        ok = payload['decision'] == 'allowed'
    sys.exit(0 if ok else 1)
except Exception as error:
    print('[dev-delivery-v2] BLOCKED ' + str(error), file=sys.stderr)
    sys.exit(1)
PY
