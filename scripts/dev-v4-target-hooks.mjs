#!/usr/bin/env node
/** Optional Notes-only hook images. Does not install or run lifecycle tooling. */
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
if (process.argv.length !== 3 || process.argv[2] !== '--render') {
  console.error('Usage: node scripts/dev-v4-target-hooks.mjs --render (read-only)');
  process.exitCode=2;
} else {
  const result=spawnSync('python3',['-B',fileURLToPath(new URL('./workspace-hooks.py',import.meta.url)),'render'],{stdio:'inherit'});
  if(result.error) console.log(JSON.stringify({status:'unavailable',changes:{},authorityGranted:false}));
  process.exitCode=result.error?0:(result.status??2);
}
