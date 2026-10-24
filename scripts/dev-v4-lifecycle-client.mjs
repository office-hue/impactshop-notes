#!/usr/bin/env node
/** Retired compatibility entrypoint. It cannot execute the installed controller. */
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
const result = spawnSync('python3', ['-B', fileURLToPath(new URL('./workspace-guidance.py', import.meta.url)), ...process.argv.slice(2)], { stdio: 'inherit' });
if (result.error) {
  process.stdout.write(JSON.stringify({status:'retired', decision:'advisory', writes:false,
    authorityGranted:false, sourceChecks:'not-evaluated', reason:'optional_guidance_unavailable'})+'\n');
}
process.exitCode = result.error ? 0 : (result.status ?? 2);
