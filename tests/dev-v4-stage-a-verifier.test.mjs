import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { verifyStageA } from '../scripts/dev-v4-stage-a-verifier.mjs';

const root = path.resolve(import.meta.dirname, '..');
const load = (name) => JSON.parse(fs.readFileSync(path.join(root, 'config/dev-v4', name), 'utf8'));
const fixture = (name) => JSON.parse(fs.readFileSync(path.join(root, 'tests/fixtures/dev-v4-stage-a', name), 'utf8'));
const base = () => ({ contract: load('central-contract-snapshot.v1.json'), capabilities: load('repo-capabilities.v1.json'), bastion: load('stage-a-bastion-policy.v1.json') });

test('Stage A remains pending and never ready', () => {
  const result = verifyStageA(base());
  assert.equal(result.decision, 'pending-activation');
  assert.equal(result.ready, false);
  assert.equal(result.authoritative, false);
});

test('negative fixtures cannot unlock activation', () => {
  assert.equal(verifyStageA({...base(), contract: {...base().contract, ...fixture('activation-enabled.json')}}).decision, 'blocked');
  assert.equal(verifyStageA({...base(), contract: {...base().contract, ...fixture('ready-enabled.json')}}).decision, 'blocked');
  assert.equal(verifyStageA({...base(), bastion: {...base().bastion, protectionLevel: 'standard'}}).decision, 'blocked');
  assert.equal(verifyStageA({...base(), capabilities: {...base().capabilities, activation: {decision: 'ready', ready: true}}}).decision, 'blocked');
});
