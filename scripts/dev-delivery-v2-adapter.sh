#!/usr/bin/env bash
set -euo pipefail
cmd="${1:-}"; shift || true; json=0; fixture=""
while (($#)); do case "$1" in --json) json=1; shift;; --offline-fixture) fixture="${2:-}"; shift 2;; *) echo "usage: adapter <inspect|freeze|verify|bastion> [--json] [--offline-fixture ROOT]" >&2; exit 2;; esac; done
case "$cmd" in inspect|freeze|verify|bastion) ;; *) exit 2;; esac
[[ -z "$fixture" || "$cmd" == inspect || "$cmd" == bastion ]] || { echo "offline fixture mode is read-only" >&2; exit 1; }
script_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
if [[ -n "$fixture" ]]; then root="$(cd "$fixture" && pwd -P)"; else root="$(git -C "$script_root" rev-parse --show-toplevel)"; [[ "$root" == "$script_root" ]] || { echo exact-current-worktree-root-required >&2; exit 1; }; fi
python3 - "$cmd" "$root" "$fixture" "$json" <<'PY'
import hashlib,json,os,stat,subprocess,sys
from pathlib import Path
cmd,root_raw,fixture_raw,as_json=sys.argv[1:5]; root=Path(root_raw).resolve(); fixture=bool(fixture_raw); reasons=[]
def git(*a):
 r=subprocess.run(['git','-C',str(root),*a],text=True,capture_output=True); return r.stdout.strip()
def file(p):
 x=(root/p).resolve()
 if root not in x.parents or not x.is_file() or x.is_symlink(): raise ValueError('unsafe-or-missing:'+str(p))
 return x
try:
 contract=file(Path('config/dev-delivery-v2-target-contract.json')); policy=json.loads(file(Path('config/dev-delivery-v2-impact-policy.json')).read_text()); digest=hashlib.sha256(contract.read_bytes()).hexdigest()
 if digest!=policy['contractSha256']: reasons+=['target-contract-digest-mismatch']
 c=json.loads(contract.read_text())
 if c.get('schemaVersion')!=2 or c.get('authority')!='repo-local' or c.get('centralRuntimeDependency') is not False: reasons+=['target-contract-invalid']
 if c.get('repoRoot',{}).get('production')!='exact-current-worktree-root-only' or c.get('provider',{}).get('automaticProductDeployAuthority') is not False: reasons+=['target-contract-policy-drift']
 if policy.get('protectionLevel')!='maximum': reasons+=['maximum-bastion-required']
 source=file(Path('scripts/dev-delivery-v2-adapter.sh')).read_text()
 for token in policy['forbiddenAuthorityTokens']:
  if token in source: reasons+=['forbidden-authority-token:'+token.strip()]
 branch=git('branch','--show-current'); base=git('rev-parse','origin/main'); head=git('rev-parse','HEAD'); tree=git('show','-s','--format=%T','HEAD')
 paths=(git('diff','--name-only',f'{base}..{head}') if base!=head else git('diff','--name-only','HEAD')).splitlines()
 def anyprefix(prefixes): return any(p.startswith(prefixes) for p in paths)
 if not paths: impact='governance-only'
 elif anyprefix(('wp-content/','docs/impactshop-protected','docs/impactshop-guard-')): impact='protected'
 elif anyprefix(('bin/','deploy/','.deploy.')): impact='deploy'
 elif all(p.startswith(('docs/','tests/','scripts/','.github/workflows/','config/')) or p in ('AGENTS.md','notes.md','system-status-snapshot.md','DOC-SYNC-HUB.md') for p in paths): impact='governance-only'
 else: impact='unknown'
 decision='allowed' if impact=='governance-only' else ('operator-review' if impact in ('protected','deploy') else 'blocked')
 if impact=='unknown': reasons+=['unknown-path-class']
 if reasons and decision=='allowed': decision='blocked'
 payload={'schemaVersion':2,'repo':'impactshop-notes','authoritySource':'repo-local','branch':branch or None,'baseSha':base or None,'headSha':head or None,'treeSha':tree or None,'changedPathClass':impact,'providerBuildDecision':policy['provider'].get(impact,'operator-review'),'evidenceReuseAllowed':not reasons,'decision':decision,'blockingReasons':reasons,'contractSha256':digest,'fixtureMode':fixture}
 if cmd=='bastion': payload['decision']='pass' if not reasons else 'blocked'; payload['protectionLevel']='maximum'
 if cmd=='freeze':
  if decision!='allowed': raise ValueError('candidate-not-admissible')
  state=Path(git('rev-parse','--git-path',policy['privateState']['gitPath'])); state=state if state.is_absolute() else root/state; state.mkdir(mode=0o700,parents=True,exist_ok=True); os.chmod(state,0o700); payload['candidateTreeSha']=git('write-tree'); record=state/'candidate.json'; record.write_text(json.dumps(payload,sort_keys=True)+'\n'); os.chmod(record,0o600)
 if cmd=='verify':
  state=Path(git('rev-parse','--git-path',policy['privateState']['gitPath'])); state=state if state.is_absolute() else root/state; record=state/'candidate.json'
  if not record.is_file() or stat.S_IMODE(record.stat().st_mode)!=0o600 or stat.S_IMODE(state.stat().st_mode)!=0o700: raise ValueError('private-evidence-state-invalid')
  frozen=json.loads(record.read_text()); candidate=frozen.get('candidateTreeSha')
  if candidate!=git('write-tree'): raise ValueError('candidate-index-tree-mismatch')
  if candidate!=tree: raise ValueError('checkpoint-tree-mismatch')
  payload['candidateTreeSha']=candidate; payload['checkpointTreeMatchesCandidate']=True
 print(json.dumps(payload,sort_keys=True) if as_json=='1' else '[dev-delivery-v2] decision='+payload['decision']); sys.exit(0 if payload['decision'] in ('allowed','pass') else 1)
except Exception as e: print('[dev-delivery-v2] BLOCKED '+str(e),file=sys.stderr); sys.exit(1)
PY
