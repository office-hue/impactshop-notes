#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const ROOT = path.resolve(import.meta.dirname, '..');
const MAX = 262144;
const EXPECTED = Object.freeze({
  repoId: '1173292974',
  centralRepo: 'office-hue/ai-agent',
  centralMergeSha: '94db78c66b21979c9511594349a518a4d31d8',
  centralTree: '6fd0f87b40b74e74abce72caf03a48280f7659ab',
  operationsPackageSha256: '229649232d28644a85321f43f0f7b266bdb8a05cc6d5329d8b40c84b154d43dd',
});
const REQUIRED_DENY_LANES = Object.freeze(['provider', 'deploy', 'vps', 'secret', 'build', 'runtime', 'cron', 'watchdog', 'hook-install', 'network-write']);
const read = (file) => {
  const fd = fs.openSync(file, fs.constants.O_RDONLY | (fs.constants.O_NOFOLLOW || 0));
  try { const st = fs.fstatSync(fd); if (!st.isFile() || st.size > MAX) throw new Error('bounded_input_rejected'); return fs.readFileSync(fd, 'utf8'); }
  finally { fs.closeSync(fd); }
};
const load = (file) => JSON.parse(read(file));
const digest = (value) => crypto.createHash('sha256').update(value).digest('hex');
const fail = (reason) => ({ schemaVersion: 1, decision: 'blocked', status: 'valid-unverified', authoritative: false, ready: false, reason });

export function verifyStageA({ contract, capabilities, bastion } = {}) {
  try {
    if (!contract || contract.schemaVersion !== 1 || contract.contractId !== 'dev-v4.impactshop-notes.central-contract-snapshot') return fail('central_contract_invalid');
    if (Object.entries(EXPECTED).some(([key, value]) => contract[key] !== value)) return fail('central_identity_mismatch');
    if (contract.activationAllowed !== false || contract.readyAllowed !== false || contract.providerMutationAllowed !== false || contract.buildAllowed !== false || contract.deployAllowed !== false || contract.vpsMutationAllowed !== false || contract.runtimeMutationAllowed !== false || contract.secretMaterialAllowed !== false || contract.schedulerMutationAllowed !== false) return fail('unsafe_activation_flag');
    if (!capabilities || capabilities.schemaVersion !== 1 || capabilities.repoId !== 'impactshop-notes' || capabilities.activation?.ready !== false) return fail('capability_snapshot_invalid');
    if (!Array.isArray(capabilities.capabilities) || capabilities.capabilities.some((x) => !x || typeof x.id !== 'string' || !['available','degraded','blocked','not-applicable','required'].includes(x.state))) return fail('capability_entry_invalid');
    if (!bastion || bastion.protectionLevel !== 'maximum' || bastion.activationAllowed !== false || bastion.readyAllowed !== false || !Array.isArray(bastion.forbiddenOperations)) return fail('bastion_invalid');
    if (REQUIRED_DENY_LANES.some((lane) => !bastion.forbiddenOperations.includes(lane))) return fail('bastion_scope_incomplete');
    return { schemaVersion: 1, decision: 'pending-activation', status: 'valid-unverified', authoritative: false, ready: false, contractDigest: digest(JSON.stringify(contract)), capabilityDigest: digest(JSON.stringify(capabilities)) };
  } catch (error) { return fail(error.message); }
}

if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(import.meta.filename)) {
  const result = verifyStageA({
    contract: load(path.join(ROOT, 'config/dev-v4/central-contract-snapshot.v1.json')),
    capabilities: load(path.join(ROOT, 'config/dev-v4/repo-capabilities.v1.json')),
    bastion: load(path.join(ROOT, 'config/dev-v4/stage-a-bastion-policy.v1.json')),
  });
  process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
  process.exitCode = result.decision === 'pending-activation' ? 0 : 2;
}
