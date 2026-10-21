import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const client = fileURLToPath(new URL('../scripts/dev-v4-lifecycle-client.mjs', import.meta.url));
const continuityGuard = fileURLToPath(new URL('../scripts/worktree-continuity-guard.sh', import.meta.url));
const start = ['start', '--task-id', 'fixture-task', '--selector', 'dev-governance-source', '--branch', 'dev/fixture-task'];
const accepted = { sourceRepo: 'office-hue/ai-agent', sourceCommit: '8'.repeat(40), digest: 'a'.repeat(64), engineContractVersion: 1, capsuleSchemaVersion: 3 };
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
  return { root, data, git, contract, selectors, life, descriptor, loader, loaderBody, bind, stateDir, stateFile, commitPolicy, run, argv, blocked };
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
