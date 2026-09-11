import fs from 'node:fs'; import crypto from 'node:crypto';
const root=new URL('..',import.meta.url); const load=n=>JSON.parse(fs.readFileSync(new URL(`config/dev-v4/${n}`,root),'utf8')); const c=load('central-contract-snapshot.v2.json');
export function verifyStageB(){return c.centralMergeSha==='94db78c66b21979c9511594344649a518a4d31d8'&&c.centralTree==='6fd0f87b40b74e74abce72caf03a48280f7659ab'&&Object.values(c).every(v=>v!==true)?{decision:'valid-unverified',central:c}:{decision:'blocked'};}
