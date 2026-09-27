#!/usr/bin/env node
/** Target adapter: invoke the installed controller; lifecycle state stays outside the source tree. */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const root = path.resolve(process.env.DEV_V4_REPO_ROOT || process.cwd());
const repoId = 'office-hue/impactshop-notes';
const contract = path.join(root, 'config/dev-v4/lifecycle-contract.v1.json');
const selectors = path.join(root, 'config/dev-v4/task-selectors.v1.json');
const dataRoot = process.env.DEV_V4_DATA_ROOT || path.join(os.homedir(), 'Library', 'Application Support', 'office-dev');
const loader = path.join(dataRoot, 'bin', 'dev-v4-office-dev');

function fail(error) { process.stderr.write(JSON.stringify({ status: 'blocked', error }) + '\n'); process.exit(1); }
function regular(file) {
  let stat;
  try { stat = fs.lstatSync(file); } catch { fail('lifecycle_contract_missing'); }
  if (!stat.isFile() || stat.isSymbolicLink() || stat.nlink !== 1 || (stat.mode & 0o022)) fail('lifecycle_contract_unsafe');
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}
function validate(commandArgs) {
  const command = commandArgs[0];
  const allowed = {
    start: new Set(['--task-id', '--selector', '--branch', '--task-brief']),
    bind: new Set(['--task-id', '--plan-anchor', '--policy-commit']),
    status: new Set(), resume: new Set(), projection: new Set(),
    context: new Set(['--refresh', '--consume', '--operation'])
  };
  if (!allowed[command]) fail('lifecycle_command_forbidden');
  if (command === 'context') {
    if (commandArgs.length !== 4 || !['--refresh', '--consume'].includes(commandArgs[1]) || commandArgs[2] !== '--operation' ||
        !['source', 'test', 'plan-checkpoint', 'checkpoint', 'publish', 'release', 'acceptance'].includes(commandArgs[3])) {
      fail('lifecycle_context_argument_forbidden');
    }
  } else {
    for (let index = 1; index < commandArgs.length; index += 1) {
      const option = commandArgs[index];
      if (!allowed[command].has(option)) fail('lifecycle_argument_forbidden');
      if (['--task-id', '--selector', '--branch', '--task-brief', '--plan-anchor', '--policy-commit'].includes(option)) {
        if (!commandArgs[index + 1] || commandArgs[index + 1].startsWith('--')) fail('lifecycle_argument_value_missing');
        if (option === '--plan-anchor' || option === '--policy-commit') {
          if (!/^[0-9a-f]{40}$/.test(commandArgs[index + 1])) fail('lifecycle_commit_argument_invalid');
        }
        index += 1;
      }
    }
  }
  const origin = spawnSync('/usr/bin/git', ['-C', root, 'config', '--get', 'remote.origin.url'], { encoding: 'utf8' }).stdout.trim();
  if (origin !== 'https://github.com/office-hue/impactshop-notes.git') fail('origin_policy_repository_mismatch');
  const life = regular(contract), policy = regular(selectors);
  if (life.repoId !== 'impactshop-notes' || life.engineContractVersion !== 1 || life.candidateAuthority !== false ||
      life.supportedEngineContractVersions?.length !== 1 || life.supportedEngineContractVersions[0] !== 1 ||
      life.acceptedRetainedEngines?.length !== 1 || life.acceptedRetainedEngines[0].sourceRepo !== 'office-hue/ai-agent' ||
      life.acceptedRetainedEngines[0].engineContractVersion !== 1) fail('lifecycle_contract_identity_invalid');
  if (policy.selectors?.['normal-source-short-brief']?.requiresTaskBrief || policy.selectors?.['dev-governance-source']?.requiresTaskBrief) fail('selector_brief_boundary_invalid');
  const index = commandArgs.indexOf('start');
  if (index >= 0) {
    const selector = commandArgs[commandArgs.indexOf('--selector') + 1];
    if (!policy.selectors[selector]) fail('selector_unknown');
    if (selector === 'normal-source-short-brief' && !commandArgs.includes('--task-brief')) fail('task_brief_required');
    if (selector === 'dev-governance-source' && commandArgs.includes('--plan-id')) fail('governance_plan_binding_required');
  }
}
function run(args) {
  validate(args);
  let st;
  try { st = fs.lstatSync(loader); } catch { fail('installed_lifecycle_loader_absent'); }
  if (!st.isFile() || st.isSymbolicLink() || st.nlink !== 1 || st.uid !== (process.getuid?.() ?? st.uid) || !(st.mode & 0o111) || (st.mode & 0o022)) fail('installed_lifecycle_loader_unsafe');
  const forwarded = [];
  for (let index = 0; index < args.length; index += 1) {
    if (args[index] === '--task-brief') { index += 1; continue; }
    forwarded.push(args[index]);
  }
  const result = spawnSync(loader, ['--repo', root, ...forwarded], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 15000, maxBuffer: 1024 * 1024 });
  const raw = (result.stdout || result.stderr || '').trim();
  try { process.stdout.write(JSON.stringify(JSON.parse(raw)) + '\n'); } catch { fail(`lifecycle_output_invalid:${raw.slice(0, 240)}`); }
  process.exit(result.status ?? 1);
}
const args = process.argv.slice(2);
if (!args.length) fail('lifecycle_client_usage');
run(args);
