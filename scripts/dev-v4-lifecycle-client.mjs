#!/usr/bin/env node
/** Target adapter: invoke the installed controller; lifecycle state stays outside the source tree. */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const root = path.resolve(process.env.DEV_V4_REPO_ROOT || process.cwd());
const dataRoot = path.resolve(process.env.DEV_V4_DATA_ROOT || path.join(os.homedir(), 'Library', 'Application Support', 'office-dev'));
const loader = path.join(dataRoot, 'bin', 'dev-v4-office-dev');

function fail(error) { process.stderr.write(JSON.stringify({ status: 'blocked', error }) + '\n'); process.exit(1); }
function json(raw, optional = false) {
  // Detect duplicate keys too: JSON.parse otherwise silently replaces authority fields.
  try {
    const parsed = JSON.parse(raw); let at = 0;
    const space = () => { while (at < raw.length && /\s/.test(raw[at])) at++; };
    function string() { const start = at++; while (at < raw.length) { if (raw[at++] === '\\') at++; else if (raw[at - 1] === '"') return JSON.parse(raw.slice(start, at)); } throw new Error(); }
    function value() {
      space();
      if (raw[at] === '{') {
        at++; space(); const keys = new Set(); if (raw[at] === '}') { at++; return; }
        do { space(); const key = string(); if (keys.has(key)) throw new Error(); keys.add(key); space(); at++; value(); space(); } while (raw[at++] === ',');
      } else if (raw[at] === '[') {
        at++; space(); if (raw[at] === ']') { at++; return; } do { value(); space(); } while (raw[at++] === ',');
      } else if (raw[at] === '"') string();
      else while (at < raw.length && !/[\s,\]}]/.test(raw[at])) at++;
    }
    value(); if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) throw new Error(); return parsed;
  } catch { if (optional) return null; fail('lifecycle_json_invalid'); }
}
function regular(file, privateFile = false, executable = false) {
  let fd;
  try {
    const stat = fs.lstatSync(file);
    if (!stat.isFile() || stat.isSymbolicLink() || stat.nlink !== 1 || stat.uid !== process.getuid() ||
        (stat.mode & (privateFile ? 0o077 : 0o022)) || (executable && !(stat.mode & 0o111)) || stat.size > 1048576) throw new Error();
    fd = fs.openSync(file, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW);
    const opened = fs.fstatSync(fd); if (stat.ino !== opened.ino || stat.dev !== opened.dev) throw new Error();
    const raw = fs.readFileSync(fd, 'utf8'), after = fs.fstatSync(fd), named = fs.lstatSync(file);
    if (after.size !== opened.size || after.mtimeMs !== opened.mtimeMs || after.ctimeMs !== opened.ctimeMs || named.ino !== opened.ino || named.dev !== opened.dev) throw new Error();
    return raw;
  } catch { fail('lifecycle_file_unsafe_or_missing'); }
  finally { if (fd !== undefined) fs.closeSync(fd); }
}
function canonicalDirectory(dir) {
  if (fs.realpathSync(dir) !== dir || !fs.lstatSync(dir).isDirectory()) fail('lifecycle_directory_unsafe');
}
function installedPath(file) {
  canonicalDirectory(dataRoot);
  const rootStat = fs.lstatSync(dataRoot);
  if (rootStat.uid !== process.getuid() || (rootStat.mode & 0o022)) fail('lifecycle_directory_unsafe');
  for (let dir = path.dirname(file); dir !== dataRoot; dir = path.dirname(dir)) {
    if (!dir.startsWith(dataRoot + path.sep)) fail('lifecycle_directory_unsafe');
    const stat = fs.lstatSync(dir);
    if (!stat.isDirectory() || stat.isSymbolicLink() || stat.uid !== process.getuid() || (stat.mode & 0o022)) fail('lifecycle_directory_unsafe');
  }
}
// Ignore caller Git overrides; these identity reads never fetch or invoke hooks.
const env = Object.fromEntries(Object.entries(process.env).filter(([key]) => !key.startsWith('GIT_')));
env.GIT_TERMINAL_PROMPT = '0';
// Both this selection and the historical controller must read the original Git objects.
env.GIT_NO_REPLACE_OBJECTS = '1';
const safeControllerErrors = new Set([
  'retained_engine_not_accepted_by_repository_policy', 'task_branch_already_exists_or_reserved',
  'start_requires_canonical_primary_main_checkout', 'start_requires_head_at_exact_main',
  'start_requires_clean_worktree', 'existing_state_refuse_start', 'origin_policy_repository_mismatch',
  'bind_requires_clean_worktree', 'task_identity_mismatch', 'bound_plan_identity_mismatch',
  'bound_plan_authority_mismatch', 'bound_plan_manifest_missing_or_ambiguous',
  'selector_transition_not_allowed', 'git_rev_parse_failed', 'lifecycle_state_missing', 'cannot_resume_uninitialized'
]);
function git(args) {
  const result = spawnSync('/usr/bin/git', ['-c', 'core.fsmonitor=false', '-C', root, ...args], { encoding: 'utf8', env, timeout: 3000, maxBuffer: 1048576 });
  if (result.error || result.status !== 0) fail('lifecycle_git_read_failed');
  return result.stdout.trim();
}
function validate(commandArgs) {
  const command = commandArgs[0];
  const allowed = {
    start: new Set(['--task-id', '--selector', '--branch', '--task-brief']),
    bind: new Set(['--task-id', '--plan-anchor', '--policy-commit']),
    status: new Set(), resume: new Set(), projection: new Set(),
    context: new Set(['--refresh', '--consume', '--operation', '--render'])
  };
  if (!allowed[command]) fail('lifecycle_command_forbidden');
  if (command === 'context') {
    if (commandArgs.length === 2 && commandArgs[1] === '--render') return;
    if (commandArgs.length !== 4 || !['--refresh', '--consume'].includes(commandArgs[1]) || commandArgs[2] !== '--operation' ||
        !['source', 'test', 'plan-checkpoint', 'checkpoint', 'publish', 'release', 'acceptance'].includes(commandArgs[3])) {
      fail('lifecycle_context_argument_forbidden');
    }
  } else {
    const seen = new Set();
    for (let index = 1; index < commandArgs.length; index += 1) {
      const option = commandArgs[index];
      if (!allowed[command].has(option) || seen.has(option)) fail('lifecycle_argument_forbidden');
      seen.add(option);
      if (['--task-id', '--selector', '--branch', '--task-brief', '--plan-anchor', '--policy-commit'].includes(option)) {
        if (!commandArgs[index + 1] || commandArgs[index + 1].startsWith('-') || /[\x00-\x1f\x7f]/.test(commandArgs[index + 1])) fail('lifecycle_argument_value_missing');
        if (option === '--plan-anchor' || option === '--policy-commit') {
          if (!/^[0-9a-f]{40}$/.test(commandArgs[index + 1])) fail('lifecycle_commit_argument_invalid');
        }
        index += 1;
      }
    }
    const required = command === 'start' ? ['--task-id', '--selector', '--branch'] : command === 'bind' ? ['--task-id', '--plan-anchor'] : [];
    if (required.some(key => !seen.has(key))) fail('lifecycle_argument_required');
  }
}
function bound() {
  const gitDir = git(['rev-parse', '--absolute-git-dir']); canonicalDirectory(gitDir);
  const dir = path.join(gitDir, 'dev-v4-lifecycle'); let stat;
  try { stat = fs.lstatSync(dir); } catch (error) { if (error.code === 'ENOENT') return false; throw error; }
  if (!stat.isDirectory() || stat.isSymbolicLink() || stat.uid !== process.getuid() || (stat.mode & 0o077)) fail('lifecycle_state_unsafe');
  // Orphan directories/journals are recovery state, never an unbound default route.
  const state = json(regular(path.join(dir, 'state.json'), true));
  if (state.schemaVersion !== 3 || !state.engine || !/^[0-9a-f]{64}$/.test(state.engine.digest) || !/^[0-9a-f]{40}$/.test(state.engine.sourceCommit)) fail('lifecycle_state_invalid');
  return true;
}
function committedJson(base, relative) {
  const row = git(['ls-tree', base, '--', relative]), match = /^100644 blob ([0-9a-f]{40})\t([^\n]+)$/.exec(row);
  if (!match || match[2] !== relative) fail('lifecycle_policy_blob_invalid');
  return json(git(['cat-file', 'blob', match[1]]));
}
function startDescriptor(commandArgs) {
  if (git(['rev-parse', '--absolute-git-dir']) !== path.resolve(root, git(['rev-parse', '--git-common-dir'])) || git(['symbolic-ref', '--quiet', 'HEAD']) !== 'refs/heads/main') fail('lifecycle_start_requires_primary_main');
  // One local snapshot. The native controller revalidates its later fetched base before writes.
  const base = git(['rev-parse', '--verify', 'refs/remotes/origin/main^{commit}']);
  if (!/^[0-9a-f]{40}$/.test(base)) fail('lifecycle_base_invalid');
  const life = committedJson(base, 'config/dev-v4/lifecycle-contract.v1.json'), policy = committedJson(base, 'config/dev-v4/task-selectors.v1.json');
  if (life.schemaVersion !== 1 || life.contractId !== 'dev-v4.lifecycle' || life.repoId !== 'impactshop-notes' || life.engineContractVersion !== 1 || life.capsuleSchemaVersion !== 3 || life.candidateAuthority !== false ||
      life.supportedEngineContractVersions?.length !== 1 || life.supportedEngineContractVersions[0] !== 1 ||
      life.acceptedRetainedEngines?.length !== 1 || life.acceptedRetainedEngines[0].sourceRepo !== 'office-hue/ai-agent' ||
      life.acceptedRetainedEngines[0].engineContractVersion !== 1 || life.acceptedRetainedEngines[0].capsuleSchemaVersion !== 3 ||
      !/^[0-9a-f]{40}$/.test(life.acceptedRetainedEngines[0].sourceCommit) || !/^[0-9a-f]{64}$/.test(life.acceptedRetainedEngines[0].digest)) fail('lifecycle_contract_identity_invalid');
  if (policy.schemaVersion !== 1 || policy.contractId !== 'dev-v4.task-selectors' || policy.candidateAuthority !== false) fail('selector_policy_invalid');
  if (policy.selectors?.['normal-source-short-brief']?.requiresTaskBrief || policy.selectors?.['dev-governance-source']?.requiresTaskBrief) fail('selector_brief_boundary_invalid');
  const selector = commandArgs[commandArgs.indexOf('--selector') + 1];
  if (!Object.hasOwn(policy.selectors || {}, selector)) fail('selector_unknown');
  if (selector === 'normal-source-short-brief' && !commandArgs.includes('--task-brief')) fail('task_brief_required');
  const accepted = life.acceptedRetainedEngines[0];
  const descriptor = path.join(dataRoot, 'packages', 'dev-v4', accepted.digest, accepted.sourceCommit, 'files', 'engine-descriptor.json');
  installedPath(descriptor);
  const actual = json(regular(descriptor));
  if (actual.schemaVersion !== 1 || ['sourceRepo', 'sourceCommit', 'digest', 'engineContractVersion', 'capsuleSchemaVersion'].some(key => actual[key] !== accepted[key])) fail('lifecycle_descriptor_identity_mismatch');
  return descriptor;
}
function run(args) {
  validate(args);
  canonicalDirectory(root);
  if (git(['rev-parse', '--show-toplevel']) !== root || git(['config', '--local', '--get-all', 'remote.origin.url']) !== 'https://github.com/office-hue/impactshop-notes.git') fail('origin_policy_repository_mismatch');
  const hasBoundTask = bound();
  const descriptor = args[0] === 'start' && !hasBoundTask ? startDescriptor(args) : null;
  installedPath(loader); regular(loader, false, true);
  const forwarded = [];
  for (let index = 0; index < args.length; index += 1) {
    if (args[index] === '--task-brief') { index += 1; continue; }
    forwarded.push(args[index]);
  }
  const result = spawnSync(loader, ['--repo', root, ...(descriptor ? ['--engine-descriptor', descriptor] : []), ...forwarded], { encoding: 'utf8', env, stdio: ['ignore', 'pipe', 'pipe'], timeout: 15000, maxBuffer: 1024 * 1024 });
  if (result.error) fail('lifecycle_controller_unavailable');
  if (result.status !== 0) {
    const output = json(result.stderr || result.stdout || '', true);
    fail(output?.status === 'blocked' && safeControllerErrors.has(output.error) ? output.error : 'lifecycle_controller_failed');
  }
  process.stdout.write(JSON.stringify(json(result.stdout || '')) + '\n');
}
const args = process.argv.slice(2);
if (!args.length) fail('lifecycle_client_usage');
try { run(args); } catch { fail('lifecycle_client_failed'); }
