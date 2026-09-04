#!/usr/bin/env bash
set -euo pipefail
cmd="${1:-}"; shift || true; json=0; fixture=""
while (($#)); do case "$1" in --json) json=1; shift;; --offline-fixture) fixture="${2:-}"; shift 2;; *) echo "usage: adapter <inspect|freeze|verify|full-validate|bastion> [--json] [--offline-fixture ROOT]" >&2; exit 2;; esac; done
case "$cmd" in inspect|freeze|verify|full-validate|bastion) ;; *) exit 2;; esac
[[ -z "$fixture" || "$cmd" == inspect || "$cmd" == bastion ]] || { echo "offline fixture mode is read-only" >&2; exit 1; }
script_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
if [[ -n "$fixture" ]]; then root="$(cd "$fixture" && pwd -P)"; else root="$(git -C "$script_root" rev-parse --show-toplevel)"; [[ "$root" == "$script_root" ]] || { echo exact-current-worktree-root-required >&2; exit 1; }; fi
python3 - "$cmd" "$root" "$fixture" "$json" <<'PY'
import fnmatch,hashlib,json,os,re,stat,subprocess,sys
from pathlib import Path
cmd,root_raw,fixture_raw,as_json=sys.argv[1:5]; root=Path(root_raw).resolve(); fixture=bool(fixture_raw); reasons=[]
def git(*a):
 r=subprocess.run(['git','-C',str(root),*a],text=True,capture_output=True); return r.stdout.strip() if r.returncode==0 else ''
def has_object(spec):
 return subprocess.run(['git','-C',str(root),'cat-file','-e',spec],text=True,capture_output=True).returncode==0
def require_commit(ref,label):
 if not re.fullmatch(r'[0-9a-f]{40}',ref or ''): raise ValueError('invalid-'+label+'-sha')
 resolved=git('rev-parse','--verify',ref+'^{commit}')
 if resolved!=ref: raise ValueError('unresolved-'+label+'-commit')
 return resolved
def resolve_base():
 explicit=os.environ.get('DEV_DELIVERY_V2_BASE_SHA')
 if explicit is not None and explicit!='': return require_commit(explicit,'base')
 event_path=Path(os.environ.get('GITHUB_EVENT_PATH',''))
 if event_path.is_file():
  try: event_base=json.loads(event_path.read_text()).get('pull_request',{}).get('base',{}).get('sha')
  except (OSError,json.JSONDecodeError): raise ValueError('invalid-github-event-base')
  if event_base: return require_commit(event_base,'base')
 return require_commit(git('rev-parse','--verify','origin/main^{commit}'),'base')
def file(p):
 x=(root/p).resolve()
 if root not in x.parents or not x.is_file() or x.is_symlink(): raise ValueError('unsafe-or-missing:'+str(p))
 return x
def git_file(ref,p):
 r=subprocess.run(['git','-C',str(root),'show',ref+':'+p],text=True,capture_output=True)
 return r.stdout if r.returncode==0 else ''
def private_state():
 state=Path(git('rev-parse','--git-path',policy['privateState']['gitPath']))
 return state if state.is_absolute() else root/state
def validation_record(): return private_state()/'full-validation.json'
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
 branch=git('branch','--show-current'); base=resolve_base(); head=require_commit(git('rev-parse','--verify','HEAD^{commit}'),'head'); tree=git('show','-s','--format=%T','HEAD')
 if not re.fullmatch(r'[0-9a-f]{40}',tree or '') or not has_object(tree+'^{tree}'): raise ValueError('unresolved-head-tree')
 diff=subprocess.run(['git','-C',str(root),'diff','--no-ext-diff','--name-status',f'{base}..{head}'],text=True,capture_output=True)
 if diff.returncode!=0: raise ValueError('base-head-diff-failed')
 changes=[]
 for line in diff.stdout.splitlines():
  fields=line.split('\t')
  if len(fields)>=2:
   changes.append((fields[0],fields[-1]))
 paths=[p for _,p in changes]
 def anyprefix(prefixes): return any(p.startswith(prefixes) for p in paths)
 protected_model=json.loads(file(Path('docs/impactshop-protected-files.json')).read_text())
 protected_globs=protected_model.get('protected_globs',[])
 protected_list=file(Path('.github/protected-files.txt')).read_text().splitlines()
 protected_list=[p.strip() for p in protected_list if p.strip() and not p.lstrip().startswith('#')]
 def listed_protected(p): return any(fnmatch.fnmatch(p,pat) for pat in protected_globs+protected_list)
 executable=('.sh','.py','.js','.mjs','.ts','.yml','.yaml')
 provider_terms=('ver'+'cel','rail'+'way')
 remote_write=re.compile(r'\b(git\s+push|gh\s+pr|ssh\s+|scp\s+|rsync\s+|curl\s+|wget\s+|'+ '|'.join(provider_terms) + r'|provider[ _-]?deploy|remote[ _-]?write|deploy\s*(?:-|_|:|\())',re.I)
 content_deploy=any(p.endswith(executable) and remote_write.search(git_file(head,p)) for status,p in changes if not status.startswith('D'))
 if not paths: impact='governance-only'
 elif content_deploy or anyprefix(('bin/','deploy/','.deploy.')): impact='deploy'
 elif any(listed_protected(p) for p in paths) or anyprefix(('wp-content/','scripts/','.github/workflows/','config/','docs/impactshop-protected','docs/impactshop-guard-')): impact='protected'
 elif all(p.startswith(('docs/','tests/')) or p in ('AGENTS.md','notes.md','system-status-snapshot.md','DOC-SYNC-HUB.md') for p in paths): impact='governance-only'
 else: impact='unknown'
 decision='allowed' if impact=='governance-only' else ('operator-review' if impact in ('protected','deploy') else 'blocked')
 if impact=='unknown': reasons+=['unknown-path-class']
 if reasons and decision=='allowed': decision='blocked'
 payload={'schemaVersion':2,'repo':'impactshop-notes','authoritySource':'repo-local','branch':branch or None,'baseSha':base or None,'headSha':head or None,'treeSha':tree or None,'changedPathClass':impact,'providerBuildDecision':policy['provider'].get(impact,'operator-review'),'evidenceReuseAllowed':not reasons,'decision':decision,'blockingReasons':reasons,'contractSha256':digest,'fixtureMode':fixture}
 payload['candidateTreeSha']=tree
 payload['sourceMergeAdmission']=False
 if cmd=='full-validate':
  if fixture: raise ValueError('fixture-full-validation-forbidden')
  if git('diff','--quiet') or git('diff','--cached','--quiet'): raise ValueError('dirty-worktree-evidence-forbidden')
  record_path='docs/protected-change-records/2026-09-04-dev-delivery-v2-admission-hardening.md'
  env=dict(os.environ,BASTION_OVERRIDE='1',BASTION_CHANGE_RECORD=record_path,BASTION_ROLLBACK_NOTE='revert the exact candidate commit before any source merge',BASTION_SMOKE_TAGS='deploy:guard-preflight,deploy:checksum-verify')
  for command in (['bash','scripts/check-commit-lane.sh','--mode','push','--push-range',base+'..'+head],['bash','scripts/check-protected-file-touch.sh','--mode','push','--push-range',base+'..'+head]):
   checked=subprocess.run(command,cwd=str(root),text=True,capture_output=True,env=env)
   if checked.returncode: raise ValueError('full-validation-failed:'+command[1])
  state=private_state(); state.mkdir(mode=0o700,parents=True,exist_ok=True); os.chmod(state,0o700)
  evidence={'schemaVersion':1,'baseSha':base,'headSha':head,'treeSha':tree,'changedPathClass':impact,'contractSha256':digest,'fullValidation':'repo-local-protected-touch-and-commit-lane','providerDeployAllowed':False}
  validation_record().write_text(json.dumps(evidence,sort_keys=True)+'\n'); os.chmod(validation_record(),0o600)
  payload['fullValidationEvidence']=True; payload['sourceMergeAdmission']=impact in ('governance-only','protected','deploy') and not reasons
 if cmd=='bastion':
  payload['protectionLevel']='maximum'; payload['bastionDecision']='blocked'
  record=validation_record()
  if impact in ('protected','deploy') or decision=='operator-review':
   if not record.is_file() or stat.S_IMODE(record.stat().st_mode)!=0o600 or stat.S_IMODE(private_state().stat().st_mode)!=0o700: reasons.append('full-validation-evidence-missing')
   else:
    frozen=json.loads(record.read_text())
    if any(frozen.get(k)!=v for k,v in {'baseSha':base,'headSha':head,'treeSha':tree,'changedPathClass':impact,'contractSha256':digest,'fullValidation':'repo-local-protected-touch-and-commit-lane','providerDeployAllowed':False}.items()): reasons.append('full-validation-evidence-mismatch')
    else: payload['fullValidationEvidence']=True; payload['sourceMergeAdmission']=not reasons
  if not reasons and decision in ('allowed','operator-review'): payload['bastionDecision']='pass'
 if cmd=='freeze':
  if decision!='allowed':
   record=validation_record()
   if not record.is_file(): raise ValueError('candidate-not-admissible')
   frozen=json.loads(record.read_text())
   expected={'baseSha':base,'headSha':head,'treeSha':tree,'changedPathClass':impact,'contractSha256':digest,'fullValidation':'repo-local-protected-touch-and-commit-lane','providerDeployAllowed':False}
   if any(frozen.get(k)!=v for k,v in expected.items()): raise ValueError('candidate-full-validation-evidence-mismatch')
   payload['fullValidationEvidence']=True; payload['sourceMergeAdmission']=not reasons
  if not payload['sourceMergeAdmission'] and decision!='allowed': raise ValueError('candidate-not-admissible')
  state=private_state(); state.mkdir(mode=0o700,parents=True,exist_ok=True); os.chmod(state,0o700); payload['candidateTreeSha']=git('write-tree'); record=state/'candidate.json'; record.write_text(json.dumps(payload,sort_keys=True)+'\n'); os.chmod(record,0o600)
 if cmd=='verify':
  state=private_state(); record=state/'candidate.json'
  if not record.is_file() or stat.S_IMODE(record.stat().st_mode)!=0o600 or stat.S_IMODE(state.stat().st_mode)!=0o700: raise ValueError('private-evidence-state-invalid')
  frozen=json.loads(record.read_text()); candidate=frozen.get('candidateTreeSha')
  if candidate!=git('write-tree'): raise ValueError('candidate-index-tree-mismatch')
  if candidate!=tree: raise ValueError('checkpoint-tree-mismatch')
  payload['candidateTreeSha']=candidate; payload['checkpointTreeMatchesCandidate']=True
 print(json.dumps(payload,sort_keys=True) if as_json=='1' else '[dev-delivery-v2] decision='+payload['decision']); sys.exit(0 if (cmd=='bastion' and payload.get('bastionDecision')=='pass') or (cmd in ('full-validate','freeze') and payload.get('sourceMergeAdmission')) or (cmd=='verify' and payload.get('checkpointTreeMatchesCandidate')) or payload['decision']=='allowed' else 1)
except Exception as e: print('[dev-delivery-v2] BLOCKED '+str(e),file=sys.stderr); sys.exit(1)
PY
