import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { renderHookImages } from '../scripts/dev-v4-target-hooks.mjs';

const client = fileURLToPath(new URL('../scripts/dev-v4-lifecycle-client.mjs', import.meta.url));

test('T2 Notes policy binds the verified engine and explicit v2 models without live authority', () => {
  const load = relative => JSON.parse(fs.readFileSync(new URL('../config/dev-v4/' + relative, import.meta.url)));
  const life = load('lifecycle-contract.v1.json'), graded = load('graded-delivery.v1.json');
  assert.equal(life.repoId, 'impactshop-notes');
  assert.deepEqual(life.acceptedRetainedEngines, [{
    sourceRepo: 'office-hue/ai-agent', sourceCommit: 'fcd987b5fc842419e01df2ea07ebcb21f6b4d931',
    digest: '4252eacfb65d65cf9a25f96d7e962336eaf23acfd91ec6d09fcc6a4cca39cc9d',
    engineContractVersion: 1, capsuleSchemaVersion: 3,
  }]);
  assert.deepEqual(life.reviewRouting, {
    schemaVersion: 2, contractId: 'dev-v4.review-routing', routeAuthority: 'task-policy-commit',
    runtimeProvenance: 'actual-model-and-reasoning-required', fallback: { state: 'unavailable', automatic: false },
    boundedImplementation: { model: 'gpt-6-luna', reasoning: 'medium' },
    planning: { model: 'gpt-6-astra', reasoning: 'high' },
    sourceReview: { model: 'gpt-6.1-sol', reasoning: 'high', minimumCount: 1 },
    authoritySecurityProduction: { model: 'gpt-6.1-sol', reasoning: 'high' },
    boundedSystemicProblem: { model: 'gpt-6-astra', reasoning: 'high' },
    maximumOrUltra: 'explicit-runtime-supported-choice-only',
    independentPlanQa: { minimumCount: 2, onlyPlanIds: ['dev-lifecycle-recovery-20260922', 'dev-lifecycle-simplification-20260922'] },
  });
  for (const name of ['maintenance', 'standard', 'protected']) {
    const route = life.reviewRouting[name === 'protected' ? 'authoritySecurityProduction' : 'boundedImplementation'];
    assert.deepEqual(graded.lanes[name].model, { id: route.model, reasoning: route.reasoning });
    assert.equal(graded.lanes[name].provider.deployments, 0);
  }
  for (const key of ['candidateAuthority', 'providerMutationAllowed', 'hostMutationAllowed', 'runtimeMutationAllowed', 'secretMaterialAllowed']) assert.equal(life[key], false);
  assert.equal(graded.globalGuards.liveMutationAllowed, false);
  assert.deepEqual(life.readOnlyCommands, ['status', 'resume']);
});

test('T2 Notes policy scopes guidance to actual hard risks and never turns warnings into permission', () => {
  const life = JSON.parse(fs.readFileSync(new URL('../config/dev-v4/lifecycle-contract.v1.json', import.meta.url)));
  assert.deepEqual(life.decisionModel, {
    schemaVersion: 1,
    hardRiskClasses: ['identity', 'foreign-work', 'secret', 'authority', 'applicable-check-risk', 'required-dependency', 'integrity', 'live-prestate', 'cas', 'uncertain-mutation'],
    advisoryDefault: true, diagnosisAfterFailedRepairs: 2, warningRationaleAuthorizesMutation: false,
    rawFailureCanBecomePass: false, futurePrerequisiteBlocksCurrentOperation: false,
  });
  assert.deepEqual(Object.keys(life.operationProfiles).sort(), ['acceptance', 'merge', 'pr-check', 'pr-create', 'provider-release', 'push', 'vps-release']);
  for (const [name, profile] of Object.entries(life.operationProfiles)) {
    assert.ok(life.operations.includes(name));
    assert.equal(profile.advisoryOnly, true); assert.equal(profile.authorityGranted, false);
    assert.ok(profile.hardBlockers.includes('identity'));
    assert.ok(profile.hardBlockers.every(value => life.decisionModel.hardRiskClasses.includes(value)));
    assert.ok(!profile.hardBlockers.includes('required-check'));
  }
});

test('T2 Notes check mapping preserves the old baseline and registers only bounded local argv', () => {
  const policy = JSON.parse(fs.readFileSync(new URL('../config/dev-delivery-v2-policy.json', import.meta.url)));
  assert.equal(policy.repoId, 'impactshop-notes');
  assert.equal(policy.taskRequirements.authority, 'single-policy-table');
  assert.deepEqual(Object.keys(policy.taskRequirements.classes).sort(),
    ['code-local', 'deploy', 'docs-only', 'governance-only', 'protected', 'unknown']);
  for (const entry of Object.values(policy.taskRequirements.classes)) {
    assert.deepEqual(entry, { requiredChecks: ['git-diff-check'] });
  }
  assert.deepEqual(policy.evidence.requiredChecksByProfile, { affected: ['git-diff-check'] });
  const command = pattern => ({ runner: 'node',
    args: ['--test', '--test-name-pattern=' + pattern, 'tests/dev-v4-lifecycle-client.test.mjs'] });
  assert.deepEqual(policy.evidence.registeredCommands, {
    'git-diff-check': [{ runner: 'bash', args: ['-lc', 'git diff --check'] }],
    'dev-notes-lifecycle-client': [command('^(?!T2 )')],
    'dev-notes-source-push': [command('^T2 Notes push ')],
    'dev-notes-hook-images': [command('^T2 Notes render'),
      { runner: 'bash', args: ['tests/install-hooks-policy-parity.test.sh'] },
      { runner: 'bash', args: ['tests/worktree-multi-active-continuity.test.sh'] }],
    'dev-notes-hook-entrypoint': [command('^T2 hook installer ')],
    'dev-notes-policy-routing': [command('^T2 Notes policy ')],
    'dev-notes-source-classification': [{ runner: 'bash', args: ['tests/dev-delivery-v2-adapter.test.sh'] }],
    'dev-notes-check-map': [command('^T2 Notes check mapping ')],
  });
});

test('T2 Notes check mapping uses finite capability paths and covers every top-level fixture once', () => {
  const policy = JSON.parse(fs.readFileSync(new URL('../config/dev-delivery-v2-policy.json', import.meta.url)));
  const shared = 'tests/dev-v4-lifecycle-client.test.mjs';
  assert.deepEqual(policy.taskRequirements.pathRequiredChecks, {
    'notes-lifecycle-client': {
      paths: ['scripts/dev-v4-lifecycle-client.mjs', 'scripts/worktree-continuity-guard.sh',
        'config/dev-v4/lifecycle-contract.v1.json', shared],
      checks: ['dev-notes-lifecycle-client'],
    },
    'notes-source-push': {
      paths: ['scripts/guarded-push.sh', 'scripts/safe-repo-audit.sh', shared],
      checks: ['dev-notes-source-push'],
    },
    'notes-hook-images': {
      paths: ['scripts/dev-v4-target-hooks.mjs', 'scripts/safe-repo-audit.sh',
        'scripts/worktree-continuity-guard.sh', 'scripts/git-health-check.sh',
        'tests/install-hooks-policy-parity.test.sh', 'tests/worktree-multi-active-continuity.test.sh', shared],
      checks: ['dev-notes-hook-images'],
    },
    'notes-hook-entrypoint': {
      paths: ['scripts/install-hooks.sh', shared], checks: ['dev-notes-hook-entrypoint'],
    },
    'notes-check-map': {
      paths: ['config/dev-delivery-v2-policy.json', shared], checks: ['dev-notes-check-map'],
    },
    'notes-policy-routing': {
      paths: ['config/dev-v4/lifecycle-contract.v1.json', 'config/dev-v4/graded-delivery.v1.json', shared],
      checks: ['dev-notes-policy-routing'],
    },
    'notes-source-classification': {
      paths: ['scripts/dev-delivery-v2-adapter.sh', 'scripts/safe-repo-audit.sh', 'tests/dev-delivery-v2-adapter.test.sh'],
      checks: ['dev-notes-source-classification'],
    },
  });
  const nodeCommands = Object.values(policy.evidence.registeredCommands)
    .flat().filter(entry => entry.runner === 'node');
  const patterns = nodeCommands.map(entry => new RegExp(entry.args[1].slice('--test-name-pattern='.length)));
  const source = fs.readFileSync(fileURLToPath(import.meta.url), 'utf8');
  const titles = [...source.matchAll(/^test\('([^']+)'/gm)].map(match => match[1]);
  assert.ok(titles.length > 30);
  for (const title of titles) {
    assert.equal(patterns.filter(pattern => pattern.test(title)).length, 1, title);
  }
  for (const pattern of patterns) assert.ok(titles.some(title => pattern.test(title)), String(pattern));
  for (const rule of Object.values(policy.taskRequirements.pathRequiredChecks)) {
    assert.equal(new Set(rule.paths).size, rule.paths.length);
    for (const relative of rule.paths) {
      assert.ok(!relative.includes('*') && !relative.endsWith('/') && !relative.includes('..'));
      const info = fs.lstatSync(new URL('../' + relative, import.meta.url));
      assert.ok(info.isFile() && !info.isSymbolicLink(), relative);
    }
    for (const check of rule.checks) assert.ok(Object.hasOwn(policy.evidence.registeredCommands, check));
  }
});

function notesLegacyAuditFixture() {
  let source = fs.readFileSync(new URL('../scripts/safe-repo-audit.sh', import.meta.url), 'utf8');
  source = source.replace(/resolve_push_base\(\) \{[\s\S]*?\n\}\n/, "resolve_push_base() {\n  local upstream_ref=\"${SAFE_REPO_AUDIT_UPSTREAM:-@{upstream}}\"\n  local candidate=\"\"\n\n  if git rev-parse --verify \"$upstream_ref\" >/dev/null 2>&1; then\n    git merge-base HEAD \"$upstream_ref\"\n    return 0\n  fi\n\n  for candidate in \"origin/HEAD\" \"origin/main\" \"origin/master\" \"main\" \"master\"; do\n    if git rev-parse --verify \"$candidate\" >/dev/null 2>&1; then\n      git merge-base HEAD \"$candidate\"\n      return 0\n    fi\n  done\n\n  if git rev-parse --verify HEAD^ >/dev/null 2>&1; then\n    git rev-parse --verify HEAD^\n    return 0\n  fi\n\n  git hash-object -t tree /dev/null\n}\n")
    .replace('PRINT_PUSH_BASE=0\n', '')
    .replace('  --print-push-base  With --mode push, only resolve the base; does not run an audit\n', '')
    .replace('    --print-push-base)\n      PRINT_PUSH_BASE=1\n      shift\n      ;;\n', '')
    .replace(/if \[\[ "\$PRINT_PUSH_BASE" == 1 \]\]; then[\s\S]*?\nfi\n\n/, '')
    .replace('  # Both semantic guards consume the same resolved range, not their old fallback.\n  export SAFE_REPO_AUDIT_PUSH_RANGE="$PUSH_RANGE"\n', '');
  assert.equal(createHash('sha256').update(source).digest('hex'), '4b519c1510ab34955bcaeb31da4b2a3dab9f91841e64ad7437a232fe00785563');
  return source;
}
test('T2 Notes rendered hook repairs only the known legacy audit with the frozen exact-base successor', t => {
  const f = notesRenderedHookFixture(t), audit = path.join(f.scripts, 'safe-repo-audit.sh');
  const historical = notesLegacyAuditFixture();
  fs.writeFileSync(audit, historical);
  f.git('add', '.'); f.git('commit', '-m', 'historical audit fixture');
  const base = f.git('rev-parse', 'HEAD'); f.git('update-ref', 'refs/remotes/origin/main', base);
  fs.writeFileSync(path.join(f.root, 'docs/another.md'), 'docs delta\n');
  f.git('add', '.'); f.git('commit', '-m', 'docs delta');
  const head = f.git('rev-parse', 'HEAD'), result = f.push(f.row('0'.repeat(40), head));
  assert.equal(result.status, 0, result.stdout + result.stderr);
  assert.match(result.stderr, /known legacy audit: frozen exact-base/);
  assert.ok(result.stdout.includes('Range:   ' + base + '..HEAD'));
  assert.equal(fs.readFileSync(audit, 'utf8'), historical, 'old task source is not rewritten');
});

function notesRenderedHookFixture(t) {
  const f = fixture(t), scripts = path.join(f.root, 'scripts');
  fs.mkdirSync(scripts); fs.mkdirSync(path.join(f.root, 'docs')); fs.mkdirSync(path.join(f.root, '.github'));
  for (const file of ['scripts/start-feature-worktree.sh', 'scripts/git-health-check.sh',
    'docs/pr-policy.md', '.github/pull_request_template.md', 'PR-EXIT-CHECKLIST.md'])
    fs.writeFileSync(path.join(f.root, file), 'fixture\n');
  const log = path.join(f.root, '.git/hook-guard-calls');
  for (const name of ['check-protected-file-touch.sh', 'check-commit-lane.sh', 'worktree-continuity-guard.sh', 'safe-repo-audit.sh'])
    fs.writeFileSync(path.join(scripts, name), '#!/bin/bash\nprintf "%s|%s|%s\\n" "' + name
      + '" "$*" "${SAFE_REPO_AUDIT_PUSH_RANGE:-}" >> "' + log + '"\n[[ "${FIXTURE_DENY:-}" != "' + name + '" ]]\n');
  fs.writeFileSync(path.join(scripts, 'worktree-coordination-sync.sh'), '#!/bin/bash\nexit 88\n');
  f.git('add', '.'); f.git('commit', '-m', 'hook fixture base');
  const base = f.git('rev-parse', 'HEAD'); f.git('update-ref', 'refs/remotes/origin/main', base);
  f.git('checkout', '-b', 'dev/hook-fixture');
  fs.writeFileSync(path.join(f.root, 'docs/delta.md'), 'delta');
  f.git('add', '.'); f.git('commit', '-m', 'hook fixture task');
  const head = f.git('rev-parse', 'HEAD');
  const hooks = path.join(f.root, '.git/rendered-hooks'); fs.mkdirSync(hooks);
  const rendered = renderHookImages();
  for (const [name, image] of Object.entries(rendered.changes)) {
    assert.equal(image.mode, 0o755);
    fs.writeFileSync(path.join(hooks, name), Buffer.from(image.bytes, 'base64'), { mode: image.mode });
  }
  const origin = 'https://github.com/office-hue/impactshop-notes.git';
  const row = (remote = '0'.repeat(40), local = head, target = 'refs/heads/dev/hook-fixture') =>
    'refs/heads/dev/hook-fixture ' + local + ' ' + target + ' ' + remote + '\n';
  const invoke = (name, args = [], input = '', overrides = {}) => spawnSync('/bin/bash',
    [path.join(hooks, name), ...args], { cwd: f.root, env: { ...f.env, ...overrides }, encoding: 'utf8', input, timeout: 7000 });
  const push = (input = row(), overrides = {}, args = ['origin', origin]) => invoke('pre-push', args, input, overrides);
  return { ...f, scripts, log, base, head, hooks, row, invoke, push, origin, rendered };
}

test('T2 Notes rendered hooks preserve six images and exact push base without coordination writes', t => {
  const f = notesRenderedHookFixture(t), before = snapshot(f.root);
  assert.deepEqual(Object.keys(f.rendered.changes).sort(),
    ['commit-msg', 'post-checkout', 'post-commit', 'post-merge', 'pre-commit', 'pre-push']);
  const result = f.push();
  assert.equal(result.status, 0, result.stdout + result.stderr);
  const lines = fs.readFileSync(f.log, 'utf8').trim().split('\n');
  assert.equal(lines.length, 4);
  for (const line of lines) assert.ok(line.endsWith('|' + f.base + '..' + f.head), line);
  assert.ok(lines[2].startsWith('worktree-continuity-guard.sh|--mode push|'));
  assert.ok(lines[3].includes('--strict --mode push'));
  assert.deepEqual(snapshot(f.root).filter(([file]) => file !== f.log), before,
    'only the explicitly owned fixture call log may change');
});
test('T2 Notes rendered pre-commit keeps both mandatory native guards including 0644 scripts', t => {
  const f = notesRenderedHookFixture(t);
  assert.equal(f.invoke('pre-commit').status, 0);
  for (const name of ['check-protected-file-touch.sh', 'check-commit-lane.sh'])
    assert.notEqual(f.invoke('pre-commit', [], '', { FIXTURE_DENY: name }).status, 0);
  f.git('checkout', 'main');
  assert.notEqual(f.invoke('pre-commit').status, 0);
});
test('T2 Notes rendered push preserves all guard denials and rejects missing or symlink guard', t => {
  const f = notesRenderedHookFixture(t);
  for (const name of ['check-protected-file-touch.sh', 'check-commit-lane.sh', 'worktree-continuity-guard.sh', 'safe-repo-audit.sh'])
    assert.notEqual(f.push(f.row(), { FIXTURE_DENY: name }).status, 0);
  const guard = path.join(f.scripts, 'worktree-continuity-guard.sh');
  fs.unlinkSync(guard); f.git('add', '.'); f.git('commit', '-m', 'missing continuity');
  let head = f.git('rev-parse', 'HEAD');
  assert.notEqual(f.push(f.row('0'.repeat(40), head)).status, 0);
  fs.symlinkSync('check-commit-lane.sh', guard); f.git('add', '.'); f.git('commit', '-m', 'unsafe continuity');
  head = f.git('rev-parse', 'HEAD');
  assert.notEqual(f.push(f.row('0'.repeat(40), head)).status, 0);
});
test('T2 Notes rendered push denies transport/ref/head/dirty drift and uses existing remote base', t => {
  const f = notesRenderedHookFixture(t);
  assert.equal(f.push(f.row(f.base)).status, 0);
  for (const input of ['', f.row()+f.row(), f.row('0'.repeat(40), f.base),
    f.row('f'.repeat(40)), f.row('0'.repeat(40), f.head, 'refs/heads/main')])
    assert.notEqual(f.push(input).status, 0);
  assert.notEqual(f.push(f.row(), {}, ['origin', 'https://example.invalid/foreign.git']).status, 0);
  f.git('remote', 'set-url', 'origin', 'https://example.invalid/foreign.git');
  assert.notEqual(f.push().status, 0);
  f.git('remote', 'set-url', 'origin', f.origin);
  fs.writeFileSync(path.join(f.root, 'dirty.txt'), 'dirty');
  assert.notEqual(f.push().status, 0);
});
test('T2 Notes rendered commit metadata and optional hooks stay repo-local', t => {
  const f = notesRenderedHookFixture(t), message = path.join(f.root, '.git/message');
  fs.writeFileSync(message, 'fixture\n');
  assert.equal(f.invoke('commit-msg', [message]).status, 0);
  assert.equal(f.invoke('commit-msg', [message]).status, 0);
  assert.equal(fs.readFileSync(message, 'utf8').match(/Memory-ID: none/g).length, 1);
  for (const name of ['post-commit', 'post-merge', 'post-checkout'])
    assert.equal(f.invoke(name).status, 0);
});
test('T2 Notes renderer rejects delimiter injection and oversized images', () => {
  for (const value of ['\nNOTES_FROZEN_AUDIT\n', 'NOTES_FROZEN_AUDIT\necho injected',
    '#!/bin/bash\nNOTES_FROZEN_AUDIT', 'NOTES_FROZEN_AUDIT', '#!/bin/bash\n\0'])
    assert.throws(() => renderHookImages(value), /audit_source_invalid/);
  assert.throws(() => renderHookImages('x'.repeat(65536)), /hook_image_too_large/);
});

test('T2 Notes rendered hooks never execute guards through a symlinked scripts directory', t => {
  const f = notesRenderedHookFixture(t), foreign = path.join(f.home, 'foreign-scripts');
  fs.renameSync(f.scripts, foreign);
  const sentinel = path.join(f.home, 'foreign-executed');
  for (const name of ['check-protected-file-touch.sh', 'check-commit-lane.sh', 'worktree-continuity-guard.sh',
    'safe-repo-audit.sh', 'post-commit-status.sh', 'post-merge-deploy-guard.sh'])
    fs.writeFileSync(path.join(foreign, name), '#!/bin/bash\ntouch "' + sentinel + '"\n');
  fs.symlinkSync(foreign, f.scripts, 'dir');
  f.git('add', '.'); f.git('commit', '-m', 'unsafe scripts fixture');
  const head = f.git('rev-parse', 'HEAD');
  assert.notEqual(f.invoke('pre-commit').status, 0);
  assert.notEqual(f.push(f.row('0'.repeat(40), head)).status, 0);
  for (const name of ['post-commit', 'post-merge', 'post-checkout'])
    assert.equal(f.invoke(name).status, 0);
  assert.equal(fs.existsSync(sentinel), false);
});
const continuityGuard = fileURLToPath(new URL('../scripts/worktree-continuity-guard.sh', import.meta.url));
const start = ['start', '--task-id', 'fixture-task', '--selector', 'dev-governance-source', '--branch', 'dev/fixture-task'];
const accepted = { sourceRepo: 'office-hue/ai-agent', sourceCommit: '8'.repeat(40), digest: 'a'.repeat(64), engineContractVersion: 1, capsuleSchemaVersion: 3 };

function pushEntrypointFixture(t) {
  const f = fixture(t), scripts = path.join(f.root, 'scripts'), bin = path.join(f.home, 'bin');
  fs.mkdirSync(scripts); fs.mkdirSync(bin); fs.mkdirSync(path.join(f.root, 'docs'));
  for (const name of ['guarded-push.sh', 'safe-repo-audit.sh']) {
    fs.copyFileSync(new URL('../scripts/' + name, import.meta.url), path.join(scripts, name));
    fs.chmodSync(path.join(scripts, name), 0o644);
  }
  const log = path.join(f.root, '.git/fixture-guards'), pushed = path.join(f.root, '.git/fixture-push');
  const guards = ['check-commit-lane.sh', 'check-protected-file-touch.sh', 'worktree-continuity-guard.sh'];
  for (const name of guards) fs.writeFileSync(path.join(scripts, name), `#!/bin/bash\n\nprintf '%s|%s|%s\\n' '${name}' "$*" "\${SAFE_REPO_AUDIT_PUSH_RANGE:-}" >> '${log}'\n[[ "\${FIXTURE_DENY_GUARD:-}" != '${name}' ]]\n`, { mode: 0o644 });
  const coordinated = path.join(f.root, '.git/fixture-coordination');
  fs.writeFileSync(path.join(scripts, 'worktree-coordination-sync.sh'), `#!/bin/bash\ntouch '${coordinated}'\nexit 19\n`, { mode: 0o755 });
  fs.writeFileSync(path.join(f.root, 'docs/fixture.md'), 'baseline\n');
  f.git('add', '.'); f.git('commit', '-m', 'fixture entrypoints');
  const base = f.git('rev-parse', 'HEAD'); f.git('update-ref', 'refs/remotes/origin/main', base);
  f.git('checkout', '-b', 'dev/push-fixture');
  fs.writeFileSync(path.join(f.root, 'docs/fixture.md'), 'changed\n');
  f.git('add', '.'); f.git('commit', '-m', 'fixture change');
  fs.writeFileSync(path.join(bin, 'git'), `#!/bin/bash\nfor arg in "$@"; do\n if [[ "$arg" == push ]]; then printf '%s\\n' "$@" > '${pushed}'; exit 0; fi\ndone\nexec /usr/bin/git "$@"\n`, { mode: 0o755 });
  const env = { ...f.env, PATH: bin + ':' + process.env.PATH, SAFE_REPO_AUDIT_UPSTREAM: '', SAFE_REPO_AUDIT_PUSH_RANGE: '' };
  const audit = (args = [], extra = {}) => spawnSync('/bin/bash', [path.join(scripts, 'safe-repo-audit.sh'), '--repo', f.root, '--mode', 'push', ...args], { cwd: f.root, env: { ...env, ...extra }, encoding: 'utf8', timeout: 7000 });
  const push = (extra = {}) => spawnSync('/bin/bash', [path.join(scripts, 'guarded-push.sh'), 'origin', 'dev/push-fixture:dev/push-fixture'], { cwd: f.root, env: { ...env, ...extra }, encoding: 'utf8', timeout: 7000 });
  return { ...f, scripts, log, pushed, coordinated, guards, base, audit, push };
}

test('T2 Notes push first-push checks 0644 guards with one range and no coordination write', t => {
  const f = pushEntrypointFixture(t), result = f.push();
  assert.equal(result.status, 0, result.stderr + result.stdout);
  assert.equal(fs.existsSync(f.coordinated), false);
  assert.deepEqual(fs.readFileSync(f.pushed, 'utf8').trim().split('\n'), ['-C', f.root, 'push', 'origin', 'dev/push-fixture:dev/push-fixture']);
  const lines = fs.readFileSync(f.log, 'utf8').trim().split('\n');
  assert.equal(lines.length, 3);
  for (const line of lines) assert.ok(line.endsWith('|' + f.base + '..HEAD'));
  assert.match(result.stdout, new RegExp('Range:   ' + f.base + '\\.\\.HEAD'));
});
test('T2 Notes push resolves explicit base and rejects invalid or disconnected bases', t => {
  const f = pushEntrypointFixture(t);
  assert.equal(f.audit(['--print-push-base'], { SAFE_REPO_AUDIT_UPSTREAM: f.base }).stdout.trim(), f.base);
  const foreign = f.git('commit-tree', f.git('rev-parse', 'HEAD^{tree}'), '-m', 'unrelated synthetic root');
  for (const base of ['does-not-exist', '--all', foreign]) {
    const r = f.push({ SAFE_REPO_AUDIT_UPSTREAM: base });
    assert.notEqual(r.status, 0); assert.match(r.stderr, /audit base (unavailable|has no common history)/);
    assert.equal(fs.existsSync(f.pushed), false); assert.equal(fs.existsSync(f.log), false);
  }
});
test('T2 Notes push cannot skip a denied 0644 guard', t => {
  const f = pushEntrypointFixture(t);
  for (const name of f.guards) {
    assert.notEqual(f.push({ FIXTURE_DENY_GUARD: name }).status, 0);
    assert.equal(fs.existsSync(f.pushed), false);
  }
});
test('T2 Notes push uses an existing upstream and never invents a base without one', t => {
  const f = pushEntrypointFixture(t);
  f.git('update-ref', 'refs/remotes/origin/reviewed', f.base);
  // The base adapter fixture sets only origin.url; no network fetch is run.
  f.git('config', 'remote.origin.fetch', '+refs/heads/*:refs/remotes/origin/*');
  f.git('branch', '--set-upstream-to=origin/reviewed');
  f.git('update-ref', '-d', 'refs/remotes/origin/main'); f.git('branch', '-D', 'main');
  const resolved = f.audit(['--print-push-base']);
  assert.equal(resolved.status, 0, resolved.stderr); assert.equal(resolved.stdout.trim(), f.base);
  f.git('branch', '--unset-upstream'); f.git('update-ref', '-d', 'refs/remotes/origin/reviewed');
  const missing = f.audit(['--print-push-base']);
  assert.notEqual(missing.status, 0); assert.match(missing.stderr, /audit base unavailable/);
  assert.equal(fs.existsSync(f.pushed), false);
});
test('T2 Notes push rejects missing and symlinked required guards before any push', t => {
  const f = pushEntrypointFixture(t);
  for (const name of [...f.guards, 'safe-repo-audit.sh']) {
    const file = path.join(f.scripts, name); fs.renameSync(file, file + '.saved');
    assert.notEqual(f.push().status, 0);
    fs.symlinkSync(file + '.saved', file); assert.notEqual(f.push().status, 0);
    fs.unlinkSync(file); fs.renameSync(file + '.saved', file);
  }
  assert.equal(fs.existsSync(f.pushed), false); assert.equal(fs.existsSync(f.log), false);
});
test('T2 Notes push audit propagates its exact range to semantic guards', t => {
  const f = pushEntrypointFixture(t);
  for (const name of f.guards.slice(0, 2)) fs.chmodSync(path.join(f.scripts, name), 0o755);
  const result = f.audit(['--strict'], { SAFE_REPO_AUDIT_UPSTREAM: f.base });
  assert.equal(result.status, 0, result.stderr + result.stdout);
  const lines = fs.readFileSync(f.log, 'utf8').trim().split('\n');
  assert.equal(lines.length, 2);
  for (const line of lines) assert.ok(line.endsWith('|' + f.base + '..HEAD'));
  assert.equal(fs.existsSync(f.pushed), false);
});
test('T2 Notes push preserves strict sensitive-path rejection', t => {
  const f = pushEntrypointFixture(t);
  fs.writeFileSync(path.join(f.root, '.env.fixture-private'), 'FIXTURE_ONLY=not-a-real-secret\n');
  f.git('add', '.env.fixture-private'); f.git('commit', '-m', 'synthetic sensitive path');
  const result = f.push(); assert.notEqual(result.status, 0);
  assert.match(result.stdout, /STRICT MODE/); assert.equal(fs.existsSync(f.pushed), false);
});
function snapshot(dir) {
  return fs.readdirSync(dir).sort().flatMap(name => {
    const file = path.join(dir, name), stat = fs.lstatSync(file);
    return stat.isDirectory() ? [[file, stat.mode], ...snapshot(file)] : [[file, stat.mode, fs.readFileSync(file).toString('hex')]];
  });
}
function fixture(t, inheritedEnv = process.env) {
  const tmp = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'notes-client-')));
  t.after(() => fs.rmSync(tmp, { recursive: true, force: true }));
  const root = path.join(tmp, 'repo'), data = path.join(tmp, 'data'), home = path.join(tmp, 'home');
  for (const dir of [root, data, home]) fs.mkdirSync(dir, { mode: 0o700 });
  const hooks = path.join(home, 'empty-hooks'); fs.mkdirSync(hooks, { mode: 0o700 });
  const cleanEnv = Object.fromEntries(Object.entries(inheritedEnv).filter(([key]) => !key.startsWith('GIT_')));
  const env = { ...cleanEnv, HOME: home, XDG_CONFIG_HOME: home, GIT_CONFIG_NOSYSTEM: '1', GIT_CONFIG_GLOBAL: '/dev/null', DEV_V4_REPO_ROOT: root, DEV_V4_DATA_ROOT: data };
  // All Git operations below are local, synthetic setup; no fetch, controller or hooks.
  function git(...args) {
    const result = spawnSync('/usr/bin/git', ['-C', root, ...args], { env, encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr); return result.stdout.trim();
  }
  git('init', '-b', 'main'); git('config', 'core.hooksPath', hooks); git('config', 'user.name', 'Synthetic fixture'); git('config', 'user.email', 'fixture@example.invalid');
  git('config', 'remote.origin.url', 'https://github.com/office-hue/impactshop-notes.git');
  const policyDir = path.join(root, 'config/dev-v4'); fs.mkdirSync(policyDir, { recursive: true });
  const contract = path.join(policyDir, 'lifecycle-contract.v1.json'), selectors = path.join(policyDir, 'task-selectors.v1.json');
  const life = { schemaVersion: 1, contractId: 'dev-v4.lifecycle', repoId: 'impactshop-notes', engineContractVersion: 1, capsuleSchemaVersion: 3, candidateAuthority: false, supportedEngineContractVersions: [1], acceptedRetainedEngines: [accepted] };
  fs.writeFileSync(contract, JSON.stringify(life));
  fs.writeFileSync(selectors, JSON.stringify({ schemaVersion: 1, contractId: 'dev-v4.task-selectors', candidateAuthority: false, selectors: { 'dev-governance-source': { requiresTaskBrief: false }, 'normal-source-short-brief': { requiresTaskBrief: false } } }));
  function commitPolicy() { git('add', 'config'); git('commit', '-m', 'synthetic policy'); git('update-ref', 'refs/remotes/origin/main', git('rev-parse', 'HEAD')); }
  commitPolicy();
  const descriptor = path.join(data, 'packages/dev-v4', accepted.digest, accepted.sourceCommit, 'files/engine-descriptor.json');
  fs.mkdirSync(path.dirname(descriptor), { recursive: true, mode: 0o700 });
  fs.writeFileSync(descriptor, JSON.stringify({ schemaVersion: 1, ...accepted }), { mode: 0o600 });
  const loader = path.join(data, 'bin/dev-v4-office-dev'); fs.mkdirSync(path.dirname(loader), { mode: 0o700 });
  function loaderBody(body = 'process.stdout.write(JSON.stringify({fixtureOnly:true, argv:process.argv.slice(2)}));') {
    fs.writeFileSync(loader, `#!${process.execPath}\n${body}\n`, { mode: 0o700 });
  }
  loaderBody();
  const stateDir = path.join(root, '.git/dev-v4-lifecycle'), stateFile = path.join(stateDir, 'state.json');
  function bind(raw = JSON.stringify({ schemaVersion: 3, engine: { sourceCommit: '7'.repeat(40), digest: 'b'.repeat(64) } })) {
    fs.mkdirSync(stateDir, { mode: 0o700 }); fs.writeFileSync(stateFile, raw, { mode: 0o600 });
  }
  function run(args, overrides = {}) { return spawnSync(process.execPath, [client, ...args], { env: { ...env, ...overrides }, encoding: 'utf8', timeout: 10000 }); }
  function argv(args) { const result = run(args); assert.equal(result.status, 0, result.stderr); const out = JSON.parse(result.stdout); assert.equal(out.fixtureOnly, true); return out.argv; }
  function blocked(args, code) { const result = run(args); assert.equal(result.status, 1); assert.equal(result.stdout, ''); const out = JSON.parse(result.stderr); assert.equal(out.status, 'blocked'); if (code) assert.equal(out.error, code); assert.ok(!result.stderr.includes(tmp)); assert.ok(!result.stderr.includes('SECRET')); }
  return { root, home, env, data, git, contract, selectors, life, descriptor, loader, loaderBody, bind, stateDir, stateFile, commitPolicy, run, argv, blocked };
}

test('ambient Git overrides cannot redirect fixture setup writes to another synthetic repository', t => {
  const foreign = fixture(t);
  const before = snapshot(foreign.root), foreignGit = path.join(foreign.root, '.git');
  const f = fixture(t, { ...process.env,
    GIT_DIR: foreignGit, GIT_WORK_TREE: foreign.root, GIT_COMMON_DIR: foreignGit,
    GIT_INDEX_FILE: path.join(foreignGit, 'index'), GIT_OBJECT_DIRECTORY: path.join(foreignGit, 'objects'),
    GIT_CONFIG_COUNT: '1', GIT_CONFIG_KEY_0: 'core.worktree', GIT_CONFIG_VALUE_0: foreign.root,
    GIT_CONFIG_PARAMETERS: "'core.hooksPath'='/SECRET/ambient-hooks'"
  });
  assert.notEqual(f.root, foreign.root);
  assert.equal(f.git('rev-parse', '--show-toplevel'), f.root);
  assert.deepEqual(f.argv(start), ['--repo', f.root, '--engine-descriptor', f.descriptor, ...start]);
  assert.deepEqual(snapshot(foreign.root), before);
});

test('replacement policy blobs cannot change selection and controller inherits no-replace', t => {
  const f = fixture(t), original = f.git('rev-parse', 'HEAD:config/dev-v4/lifecycle-contract.v1.json');
  const replacement = path.join(f.root, 'replacement-policy.json');
  fs.writeFileSync(replacement, JSON.stringify({ ...f.life, acceptedRetainedEngines: [{ ...accepted, digest: 'd'.repeat(64) }] }));
  const replacementHash = f.git('hash-object', '-w', replacement); f.git('replace', original, replacementHash);
  assert.equal(JSON.parse(f.git('cat-file', 'blob', original)).acceptedRetainedEngines[0].digest, 'd'.repeat(64));
  f.loaderBody('process.stdout.write(JSON.stringify({fixtureOnly:true, argv:process.argv.slice(2), noReplace:process.env.GIT_NO_REPLACE_OBJECTS}));');
  const result = f.run(start, { GIT_NO_REPLACE_OBJECTS: '0' }); assert.equal(result.status, 0, result.stderr);
  const output = JSON.parse(result.stdout); assert.equal(output.fixtureOnly, true); assert.equal(output.noReplace, '1');
  assert.deepEqual(output.argv, ['--repo', f.root, '--engine-descriptor', f.descriptor, ...start]);
});

test('unbound start selects exact committed descriptor, not installed default or candidate policy', t => {
  const f = fixture(t);
  fs.writeFileSync(path.join(f.data, 'default.json'), JSON.stringify({ digest: 'c'.repeat(64) }));
  fs.writeFileSync(f.contract, '{candidate SECRET invalid'); fs.writeFileSync(f.selectors, '{}');
  assert.deepEqual(f.argv(start), ['--repo', f.root, '--engine-descriptor', f.descriptor, ...start]);
});
test('committed HEAD policy is not the local origin/main selection base', t => {
  const f = fixture(t);
  fs.writeFileSync(f.contract, JSON.stringify({ ...f.life, acceptedRetainedEngines: [] }));
  f.git('add', 'config'); f.git('commit', '-m', 'candidate-only policy');
  assert.deepEqual(f.argv(start), ['--repo', f.root, '--engine-descriptor', f.descriptor, ...start]);
});
test('bound pin remains dispatcher-owned despite incompatible candidate and missing accepted descriptor', t => {
  const f = fixture(t); f.bind(); fs.unlinkSync(f.descriptor); fs.writeFileSync(f.contract, 'SECRET'); fs.unlinkSync(f.selectors);
  for (const args of [['status'], ['resume'], ['projection'], ['bind', '--task-id', 'fixture-task', '--plan-anchor', '1'.repeat(40)], start]) {
    assert.deepEqual(f.argv(args), ['--repo', f.root, ...args]);
  }
});
test('context render is exact and read-only; refresh and consume retain their argv', t => {
  const f = fixture(t); f.bind(); const before = fs.readFileSync(f.stateFile);
  for (const args of [['context', '--render'], ['context', '--refresh', '--operation', 'source'], ['context', '--consume', '--operation', 'test']]) assert.deepEqual(f.argv(args), ['--repo', f.root, ...args]);
  assert.deepEqual(fs.readFileSync(f.stateFile), before);
});
test('unbound render leaves lifecycle namespace absent and does not select a descriptor', t => {
  const f = fixture(t); assert.deepEqual(f.argv(['context', '--render']), ['--repo', f.root, 'context', '--render']); assert.equal(fs.existsSync(f.stateDir), false);
});
test('brief marker is validated but not forwarded, preserving the existing client contract', t => {
  const f = fixture(t), args = [...start]; args[4] = 'normal-source-short-brief';
  f.blocked(args, 'task_brief_required'); assert.deepEqual(f.argv([...args, '--task-brief', 'fixture brief']), ['--repo', f.root, '--engine-descriptor', f.descriptor, ...args]);
});
for (const [name, args] of [
  ['duplicate option', [...start, '--selector', 'dev-governance-source']], ['unknown option', [...start, '--engine-descriptor', '/SECRET']],
  ['missing required option', ['start', '--task-id', 'fixture']], ['missing value', ['bind', '--task-id']],
  ['invalid commit', ['bind', '--task-id', 'fixture', '--plan-anchor', 'SECRET']], ['unknown command', ['requirements']],
  ['render with operation', ['context', '--render', '--operation', 'source']], ['render with consume', ['context', '--render', '--consume']],
  ['unknown context operation', ['context', '--refresh', '--operation', 'SECRET']], ['extra status arg', ['status', 'SECRET']]
]) test(`rejects ${name} before dispatcher execution`, t => { const f = fixture(t); f.blocked(args); });
for (const kind of ['empty directory', 'journal only', 'malformed JSON', 'duplicate JSON', 'wrong schema', 'insecure file', 'insecure directory', 'symlink file', 'hardlink file', 'symlink directory']) {
  test(`does not treat ${kind} as an unbound task`, t => {
    const f = fixture(t);
    if (kind === 'empty directory' || kind === 'journal only') { fs.mkdirSync(f.stateDir, { mode: 0o700 }); if (kind === 'journal only') fs.writeFileSync(path.join(f.stateDir, 'state.json.journal'), '{}'); }
    else {
      f.bind();
      if (kind === 'malformed JSON') fs.writeFileSync(f.stateFile, 'SECRET not json');
      if (kind === 'duplicate JSON') fs.writeFileSync(f.stateFile, '{"schemaVersion":3,"schemaVersion":3}');
      if (kind === 'wrong schema') fs.writeFileSync(f.stateFile, '{"schemaVersion":2}');
      if (kind === 'insecure file') fs.chmodSync(f.stateFile, 0o644);
      if (kind === 'insecure directory') fs.chmodSync(f.stateDir, 0o755);
      if (kind === 'hardlink file') fs.linkSync(f.stateFile, path.join(f.root, 'state-link'));
      if (kind === 'symlink file') { fs.renameSync(f.stateFile, path.join(f.root, 'state-target')); fs.symlinkSync(path.join(f.root, 'state-target'), f.stateFile); }
      if (kind === 'symlink directory') { fs.renameSync(f.stateDir, path.join(f.root, 'state-target-dir')); fs.symlinkSync(path.join(f.root, 'state-target-dir'), f.stateDir); }
    }
    f.blocked(start);
  });
}
for (const kind of ['missing', 'wrong commit', 'wrong digest', 'symlink', 'hardlink', 'insecure', 'duplicate JSON', 'symlink ancestor']) {
  test(`refuses ${kind} descriptor without default fallback`, t => {
    const f = fixture(t);
    if (kind === 'missing') fs.unlinkSync(f.descriptor);
    if (kind === 'wrong commit' || kind === 'wrong digest') fs.writeFileSync(f.descriptor, JSON.stringify({ schemaVersion: 1, ...accepted, [kind === 'wrong commit' ? 'sourceCommit' : 'digest']: 'd'.repeat(kind === 'wrong commit' ? 40 : 64) }));
    if (kind === 'duplicate JSON') fs.writeFileSync(f.descriptor, '{"schemaVersion":1,"schemaVersion":1}');
    if (kind === 'insecure') fs.chmodSync(f.descriptor, 0o666);
    if (kind === 'hardlink') fs.linkSync(f.descriptor, path.join(f.root, 'descriptor-link'));
    if (kind === 'symlink') { fs.renameSync(f.descriptor, path.join(f.root, 'descriptor-target')); fs.symlinkSync(path.join(f.root, 'descriptor-target'), f.descriptor); }
    if (kind === 'symlink ancestor') { const dir = path.dirname(f.descriptor), dest = path.join(f.root, 'files-target'); fs.renameSync(dir, dest); fs.symlinkSync(dest, dir); }
    f.blocked(start);
  });
}
for (const kind of ['ambiguous engines', 'malformed contract', 'symlink policy', 'unknown selector']) {
  test(`rejects committed ${kind}`, t => {
    const f = fixture(t);
    if (kind === 'ambiguous engines') fs.writeFileSync(f.contract, JSON.stringify({ ...f.life, acceptedRetainedEngines: [accepted, accepted] }));
    if (kind === 'malformed contract') fs.writeFileSync(f.contract, 'SECRET {');
    if (kind === 'symlink policy') { fs.unlinkSync(f.contract); fs.symlinkSync('task-selectors.v1.json', f.contract); }
    if (kind === 'unknown selector') { const p = JSON.parse(fs.readFileSync(f.selectors)); delete p.selectors['dev-governance-source']; fs.writeFileSync(f.selectors, JSON.stringify(p)); }
    f.commitPolicy(); f.blocked(start);
  });
}
test('wrong or multiple origins are rejected without echoing credentials', t => {
  const f = fixture(t); f.git('config', 'remote.origin.url', 'https://SECRET@example.invalid/repo'); f.blocked(['status'], 'origin_policy_repository_mismatch');
  f.git('config', 'remote.origin.url', 'https://github.com/office-hue/impactshop-notes.git'); f.git('config', '--add', 'remote.origin.url', 'https://SECRET@example.invalid/repo'); f.blocked(start);
});
test('missing local main snapshot and non-main checkout fail closed', t => {
  const f = fixture(t); f.git('update-ref', '-d', 'refs/remotes/origin/main'); f.blocked(start, 'lifecycle_git_read_failed');
  f.git('update-ref', 'refs/remotes/origin/main', f.git('rev-parse', 'HEAD')); f.git('checkout', '-b', 'dev/not-primary'); f.blocked(start, 'lifecycle_start_requires_primary_main');
});
test('linked unbound worktree cannot start but bound reads use its own metadata under hostile Git overrides', t => {
  const foreign = fixture(t), before = snapshot(foreign.root), foreignGit = path.join(foreign.root, '.git');
  const f = fixture(t, { ...process.env, GIT_DIR: foreignGit, GIT_WORK_TREE: foreign.root, GIT_COMMON_DIR: foreignGit, GIT_CONFIG_COUNT: '1', GIT_CONFIG_KEY_0: 'core.worktree', GIT_CONFIG_VALUE_0: foreign.root });
  const linked = path.join(path.dirname(f.root), 'linked'); f.git('worktree', 'add', '-b', 'dev/linked', linked);
  const r = f.run(start, { DEV_V4_REPO_ROOT: linked }); assert.equal(r.status, 1); assert.equal(JSON.parse(r.stderr).error, 'lifecycle_start_requires_primary_main');
  const gitDir = f.git('-C', linked, 'rev-parse', '--absolute-git-dir');
  assert.equal(path.dirname(gitDir), path.join(f.root, '.git/worktrees'));
  assert.equal(fs.realpathSync(gitDir), gitDir);
  fs.mkdirSync(path.join(gitDir, 'dev-v4-lifecycle'), { mode: 0o700 }); fs.writeFileSync(path.join(gitDir, 'dev-v4-lifecycle/state.json'), JSON.stringify({ schemaVersion: 3, engine: { sourceCommit: '7'.repeat(40), digest: 'b'.repeat(64) } }), { mode: 0o600 });
  const bound = f.run(['status'], { DEV_V4_REPO_ROOT: linked }); assert.equal(bound.status, 0, bound.stderr); assert.deepEqual(JSON.parse(bound.stdout).argv, ['--repo', linked, 'status']);
  assert.deepEqual(snapshot(foreign.root), before);
});
for (const [name, code] of [['stale fetched policy', 'retained_engine_not_accepted_by_repository_policy'], ['existing branch', 'task_branch_already_exists_or_reserved']]) {
  test(`preserves safe native ${name} error without raw details`, t => {
    const f = fixture(t); f.loaderBody(`process.stderr.write(JSON.stringify({status:'blocked',error:${JSON.stringify(code)},details:'SECRET /private/secret'}));process.exit(1);`);
    f.blocked(start, code);
  });
}
for (const [name, raw] of [
  ['unknown code', JSON.stringify({ status: 'blocked', error: 'SECRET /private/secret' })],
  ['duplicate code', '{"status":"blocked","error":"task_identity_mismatch","error":"SECRET"}'],
  ['malformed JSON', 'SECRET /private/secret {'],
  ['wrong status', JSON.stringify({ status: 'SECRET', error: 'task_identity_mismatch' })]
]) test(`sanitizes nonzero controller ${name}`, t => {
  const f = fixture(t); f.loaderBody(`process.stderr.write(${JSON.stringify(raw)});process.exit(1);`); f.blocked(['status'], 'lifecycle_controller_failed');
});
for (const body of ['process.stdout.write("SECRET /private/secret invalid JSON");', 'process.stderr.write("SECRET /private/secret");process.exit(2);', 'process.stdout.write("{\\"x\\":1,\\"x\\":2}");']) {
  test('controller output and argument failures remain sanitized', t => { const f = fixture(t); f.loaderBody(body); f.blocked(['status']); });
}

function continuityFixture(t) {
  const f = fixture(t), base = f.git('rev-parse', 'HEAD');
  f.git('checkout', '-b', 'dev/fixture-task');
  for (const name of ['docs/impactshop-notes-doc-sync-map-2026-06-23.md', 'docs/impactshop-governance-system-plan-2026-06-16.md', 'notes.md', 'system-status-snapshot.md']) {
    fs.mkdirSync(path.dirname(path.join(f.root, name)), { recursive: true });
    fs.writeFileSync(path.join(f.root, name), 'Synthetic continuity document\n');
  }
  f.git('add', '.'); f.git('commit', '-m', 'synthetic task delta');
  const head = f.git('rev-parse', 'HEAD'), tree = f.git('rev-parse', 'HEAD^{tree}');
  const state = { schemaVersion: 3, repoId: 'impactshop-notes', taskId: 'fixture-task', selectorId: 'dev-governance-source',
    policyState: 'implementation-ready', revision: 2, branch: 'dev/fixture-task', engine: accepted,
    base: { ref: 'origin/main', commit: base }, identity: { codeBase: base, policyCommit: base, integrationBase: base, planAnchorCommit: head, head, tree } };
  f.bind(JSON.stringify(state));
  const observation = { status: 'current', taskId: state.taskId, selectorId: state.selectorId, policyState: state.policyState, revision: 2,
    branch: state.branch, head, tree, dirty: false, identity: structuredClone(state.identity), engine: { state: 'verified', expected: accepted }, writes: false };
  const respond = (value = observation, prefix = '') => f.loaderBody(`${prefix}\nif (JSON.stringify(process.argv.slice(2)) !== ${JSON.stringify(JSON.stringify(['--repo', f.root, 'status']))}) process.exit(9); process.stdout.write(${JSON.stringify(JSON.stringify(value))});`);
  respond(); return { ...f, state, observation, respond };
}
test('continuity verifies installed current v3 without synthesizing a legacy marker or changing state', t => {
  const f = continuityFixture(t), before = fs.readFileSync(f.stateFile);
  const result = f.run(['continuity', '--mode', 'push']); assert.equal(result.status, 0, result.stderr);
  const report = JSON.parse(result.stdout); assert.equal(report.authority, 'installed-v3-continuity-only');
  assert.equal(report.publicationAuthorized, false); assert.equal(report.writes, false);
  assert.deepEqual(report.lifecycle, f.state.identity); assert.notEqual(report.lifecycle.codeBase, report.head);
  assert.equal(report.selectorId, 'dev-governance-source'); assert.equal(fs.existsSync(path.join(f.root, '.git/worktree-active.json')), false);
  assert.deepEqual(fs.readFileSync(f.stateFile), before);
});
for (const [label, mutate] of [
  ['stale status', o => o.status = 'stale'], ['journal recovery', o => o.status = 'recovery-required'],
  ['pending handoff', o => o.sourceMergeHandoff = { recoveryRequired: true }],
  ['unverified engine', o => o.engine.state = 'unverified'], ['wrong pin', o => o.engine.expected = { ...accepted, digest: 'd'.repeat(64) }],
  ['wrong task', o => o.taskId = 'other'], ['wrong selector', o => o.selectorId = 'maintenance-docs'],
  ['wrong branch', o => o.branch = 'dev/foreign'], ['wrong head', o => o.head = '1'.repeat(40)],
  ['wrong tree', o => o.tree = '1'.repeat(40)], ['wrong base', o => o.identity.codeBase = o.head],
  ['wrong policy', o => o.identity.policyCommit = o.head], ['wrong anchor', o => o.identity.planAnchorCommit = null],
  ['wrong revision', o => o.revision++], ['write claim', o => o.writes = true], ['dirty mismatch', o => o.dirty = true]
]) test(`continuity refuses ${label}`, t => { const f = continuityFixture(t); mutate(f.observation); f.respond(); f.blocked(['continuity', '--mode', 'push'], 'lifecycle_continuity_unverified'); });
test('continuity rejects foreign state even if synthetic dispatcher repeats it', t => {
  const f = continuityFixture(t); f.state.repoId = 'foreign'; fs.writeFileSync(f.stateFile, JSON.stringify(f.state));
  f.blocked(['continuity', '--mode', 'push'], 'lifecycle_continuity_identity_mismatch');
});
test('continuity detects state mutation during dispatcher read', t => {
  const f = continuityFixture(t); f.respond(f.observation, `require('node:fs').appendFileSync(${JSON.stringify(f.stateFile)}, ' ');`);
  f.blocked(['continuity', '--mode', 'push'], 'lifecycle_continuity_changed');
});
for (const policyState of ['blocked', 'planning-only', 'stale', 'unknown']) test(`continuity refuses matching non-ready policy ${policyState}`, t => {
  const f = continuityFixture(t);
  f.state.policyState = policyState; f.observation.policyState = policyState;
  fs.writeFileSync(f.stateFile, JSON.stringify(f.state)); f.respond();
  const before = fs.readFileSync(f.stateFile);
  for (const mode of ['local', 'push']) f.blocked(['continuity', '--mode', mode], 'lifecycle_continuity_policy_not_ready');
  assert.deepEqual(fs.readFileSync(f.stateFile), before);
});
test('continuity allows local dirty context but refuses dirty push without writes', t => {
  const f = continuityFixture(t); fs.writeFileSync(path.join(f.root, 'untracked'), 'fixture'); f.observation.dirty = true; f.respond();
  assert.equal(f.run(['continuity', '--mode', 'local']).status, 0);
  f.blocked(['continuity', '--mode', 'push'], 'lifecycle_continuity_dirty_push');
});
test('continuity preserves and verifies an existing compatible legacy marker', t => {
  const f = continuityFixture(t), file = path.join(f.root, '.git/worktree-active.json');
  const raw = JSON.stringify({ branch: f.state.branch, path: f.root, repo: f.state.repoId, base: f.state.base, selector: f.state.selectorId });
  fs.writeFileSync(file, raw, { mode: 0o600 }); assert.equal(f.run(['continuity', '--mode', 'push']).status, 0); assert.equal(fs.readFileSync(file, 'utf8'), raw);
});
for (const kind of ['foreign branch', 'wrong base', 'wrong selector', 'symlink']) test(`continuity rejects ${kind} legacy marker`, t => {
  const f = continuityFixture(t), file = path.join(f.root, '.git/worktree-active.json');
  const marker = { branch: f.state.branch, path: f.root, repo: f.state.repoId, base: f.state.base, selector: f.state.selectorId };
  if (kind === 'foreign branch') marker.branch = 'dev/foreign';
  if (kind === 'wrong base') marker.base = { ref: 'origin/main', commit: f.state.identity.head };
  if (kind === 'wrong selector') marker.selector = 'maintenance-docs';
  if (kind === 'symlink') fs.symlinkSync(f.stateFile, file); else fs.writeFileSync(file, JSON.stringify(marker), { mode: 0o600 });
  const before = fs.lstatSync(file).isSymbolicLink() ? fs.readlinkSync(file) : fs.readFileSync(file, 'utf8');
  f.blocked(['continuity', '--mode', 'push'], kind === 'symlink' ? 'lifecycle_file_unsafe_or_missing' : 'lifecycle_continuity_legacy_conflict');
  assert.equal(fs.lstatSync(file).isSymbolicLink() ? fs.readlinkSync(file) : fs.readFileSync(file, 'utf8'), before);
});
for (const args of [['continuity'], ['continuity', '--mode', 'release'], ['continuity', '--mode', 'push', '--mode', 'local']]) test(`continuity rejects argv ${args.join(' ')}`, t => {
  const f = fixture(t); f.blocked(args, 'lifecycle_continuity_argument_forbidden');
});
test('continuity never falls back from missing or orphan v3 state', t => {
  const f = fixture(t); f.blocked(['continuity', '--mode', 'push'], 'lifecycle_state_missing');
  fs.mkdirSync(f.stateDir, { mode: 0o700 }); f.blocked(['continuity', '--mode', 'push']);
});
test('native executable continuity wrapper uses exact v3 mode without legacy fallback', t => {
  // Native hooks discover this guard with -x and execute it directly, not bash.
  fs.accessSync(continuityGuard, fs.constants.X_OK);
  const f = fixture(t); fs.mkdirSync(f.stateDir, { mode: 0o700 });
  fs.mkdirSync(path.join(f.root, 'scripts'));
  fs.writeFileSync(path.join(f.root, 'scripts/dev-v4-lifecycle-client.mjs'), 'console.log(JSON.stringify({argv:process.argv.slice(2), root:process.env.DEV_V4_REPO_ROOT, data:process.env.DEV_V4_DATA_ROOT??null}));process.exit(7);');
  const result = spawnSync(continuityGuard, ['--json', '--mode', 'push'], { cwd: f.root, env: { ...process.env, PATH: path.dirname(process.execPath) + ':' + process.env.PATH, DEV_V4_REPO_ROOT: '/foreign', DEV_V4_DATA_ROOT: '/foreign' }, encoding: 'utf8' });
  assert.equal(result.error, undefined);
  assert.equal(result.status, 7); assert.deepEqual(JSON.parse(result.stdout), { argv: ['continuity', '--mode', 'push'], root: f.root, data: null });
  assert.equal(fs.existsSync(path.join(f.root, '.git/worktree-active.json')), false);
});

const hookEntrypointFixtureHarness = String.raw`
import contextlib, hashlib, io, json, os, pathlib, sys, tempfile, unittest
from unittest.mock import patch
from types import SimpleNamespace
source = pathlib.Path(sys.argv[1]).read_text()
python = source.split("<<'OFFICE_DEV_TARGET_HOOK_ENTRYPOINT'\n", 1)[1].rsplit("\nOFFICE_DEV_TARGET_HOOK_ENTRYPOINT", 1)[0]
scope = {"__name__": "fixture"}
exec(compile(python, "reviewed-wrapper-fixture", "exec"), scope)

class EntrypointTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = pathlib.Path(self.tmp.name).resolve()
        self.home = self.root / "native-home"
        self.home.mkdir()
        self.repo = self.root / "own repo"
        self.script = self.repo / "scripts" / scope["SCRIPT_NAME"]
        self.script.parent.mkdir(parents=True)
        self.script.write_text(source)
        self.loader = self.home / "Library/Application Support/office-dev/bin/dev-v4-target-hooks"
        self.loader.parent.mkdir(parents=True, mode=0o700)
        self.payload = b'import json,sys\nprint(json.dumps({"argv":sys.argv,"file":__file__}))\nraise SystemExit(17)\n'
        self.set_loader(self.payload)
        self.base = ["--transaction-id", "22222222-2222-4222-8222-222222222222",
                     "--expected-bootstrap-digest", "b"*64,
                     "--expected-launcher-digest", hashlib.sha256(self.payload).hexdigest()]
    def set_loader(self, raw):
        if self.loader.exists():
            self.loader.chmod(0o600)
        self.loader.write_bytes(raw)
        self.loader.chmod(0o500)
    def invoke(self, operation="status", arguments=None, environment=None):
        output = io.StringIO()
        with patch.object(scope["sys"], "platform", "darwin"), patch.object(scope["pwd"], "getpwuid",
                return_value=SimpleNamespace(pw_dir=str(self.home))), patch.dict(os.environ,
                {"HOME":str(self.home), **(environment or {})}, clear=True), contextlib.redirect_stdout(output):
            previous = sys.argv[:]
            try:
                try:
                    code = scope["run"](str(self.script), [operation, *(self.base if arguments is None else arguments)])
                except SystemExit as error:
                    code = error.code
            finally:
                sys.argv = previous
        lines = output.getvalue().splitlines()
        return code, json.loads(lines[-1]) if lines else None
    def denied(self, **kwargs):
        code, result = self.invoke(**kwargs)
        self.assertEqual(code, 2)
        self.assertIs(result["writes"], False)
        self.assertFalse(result["authorityGranted"])
    def test_every_operation_binds_own_root_and_literal_target(self):
        previous = pathlib.Path.cwd()
        try:
            os.chdir(self.root)
            for operation in ("prepare","status","apply","recover","rollback"):
                code, result = self.invoke(operation)
                self.assertEqual(code,17)
                argv = result["argv"]
                self.assertEqual(argv[:6], [str(self.loader),operation,"--expected-target",scope["TARGET"],"--expected-repo",str(self.repo)])
                self.assertNotIn("--expected-launcher-digest",argv)
                self.assertEqual(result["file"],str(self.loader))
                self.assertIn("b"*64,argv)
        finally:
            os.chdir(previous)
    def test_environment_selection_is_rejected(self):
        for env in ({"HOME":str(self.root)},{"OFFICE_DEV_CONTROLLER":"/foreign"},
                    {"GIT_DIR":"/foreign"},{"PYTHONPATH":"/foreign"},{"CODEX_HOME":"/foreign"}):
            self.denied(environment=env)
    def test_bad_hash_and_bad_input_are_denied_before_dispatch(self):
        for args in (self.base[:-1]+["a"*64], self.base+["--request","relative"],
                     self.base+["--expected-manifest-digest","bad"],
                     ["--transaction-id","bad",*self.base[2:]], self.base+self.base[:2]):
            self.denied(arguments=args)
    def test_caller_cannot_supply_scope_unknown_or_abbreviated_flag(self):
        for extra in (["--expected-target","foreign"],["--expected-repo","/foreign"],
                      ["--expected-launcher-dig","a"*64],["--foreign","anything"]):
            code,result=self.invoke(arguments=self.base+extra)
            self.assertEqual(code,2)
            self.assertIsNone(result)
    def test_missing_symlink_hardlink_and_writable_loader_are_denied(self):
        self.loader.unlink()
        self.denied()
        other=self.root/"other.py"; other.write_bytes(self.payload);other.chmod(0o500)
        self.loader.symlink_to(other)
        self.denied()
        self.loader.unlink();os.link(other,self.loader)
        self.denied()
        self.loader.unlink();self.set_loader(self.payload);self.loader.chmod(0o700)
        self.denied()
    def test_ancestor_symlink_is_denied(self):
        parent=self.loader.parent
        destination=parent.with_name("other-bin")
        parent.rename(destination);parent.symlink_to(destination,target_is_directory=True)
        self.denied()
    def test_exact_snapshot_executes_even_if_path_changes_after_read(self):
        original=scope["snapshot"]
        def raced(file):
            raw=original(file)
            self.set_loader(b'raise RuntimeError("unverified bytes executed")\n')
            return raw
        with patch.dict(scope, {"snapshot":raced}):
            code,result=self.invoke()
        self.assertEqual(code,17)
        self.assertEqual(result["argv"][3],scope["TARGET"])
    def test_dispatch_exception_never_claims_no_effect(self):
        payload=b'raise ValueError("simulated post-dispatch uncertainty")\n'
        self.set_loader(payload)
        code,result=self.invoke(arguments=self.base[:-1]+[hashlib.sha256(payload).hexdigest()])
        self.assertEqual(code,2)
        self.assertIsNone(result["writes"])
        self.assertTrue(result["recoveryRequired"])
        self.assertEqual(result["nextAction"],"status")
    def test_symlink_script_cannot_redirect_repo_identity(self):
        other=self.script.with_name("other.sh");self.script.rename(other);self.script.symlink_to(other)
        self.denied()

unittest.main(argv=["fixture"], verbosity=1)
`;

test('T2 hook installer entrypoint freezes its loader and binds only its own repo', () => {
  const script = new URL('../scripts/install-hooks.sh', import.meta.url);
  const python = process.platform === 'darwin' ? '/usr/bin/python3' : 'python3';
  const result = spawnSync(python, ['-I', '-B', '-c', hookEntrypointFixtureHarness, script.pathname],
    { encoding: 'utf8', timeout: 15000 });
  assert.equal(result.status, 0, result.stderr + result.stdout);
  assert.match(result.stderr, /Ran 9 tests/);
});

test('T2 hook installer help is non-mutating and has no automatic legacy fallback', t => {
  const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'hook-entrypoint-help-'));
  t.after(() => fs.rmSync(temporary, { recursive: true, force: true }));
  const script = new URL('../scripts/install-hooks.sh', import.meta.url);
  const source = fs.readFileSync(script, 'utf8');
  assert.doesNotMatch(source, /install_hook\s*\(|git .*config|WORKSPACE_ROOT|mkdir -p|cat >|memory:|coordination-sync/);
  for (const argv of [[], ['--help']]) {
    const result = spawnSync('/bin/bash', [script.pathname, ...argv],
      { cwd: temporary, env: { HOME: temporary, PATH: '/usr/bin:/bin' }, encoding: 'utf8', timeout: 5000 });
    assert.equal(result.status, 0, result.stderr + result.stdout);
    assert.match(result.stdout, /--expected-launcher-digest/);
    assert.deepEqual(fs.readdirSync(temporary), []);
  }
});
