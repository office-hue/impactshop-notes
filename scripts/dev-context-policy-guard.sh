#!/usr/bin/env bash
set -euo pipefail
json=0
repo_root="$(git rev-parse --show-toplevel 2>/dev/null || true)"
while (($#)); do
  case "$1" in
    --json) json=1; shift ;;
    --repo-root) [[ $# -ge 2 ]] || { echo "missing --repo-root" >&2; exit 2; }; repo_root="$2"; shift 2 ;;
    -h|--help) echo "Usage: bash scripts/dev-context-policy-guard.sh [--json] [--repo-root PATH]"; exit 0 ;;
    *) echo "unknown argument: $1" >&2; exit 2 ;;
  esac
done
[[ -n "$repo_root" && ( -d "$repo_root/.git" || -f "$repo_root/.git" ) ]] || { echo "not a git worktree" >&2; exit 1; }
python3 - "$repo_root" "$json" <<'PY'
import fnmatch, json, os, re, subprocess, sys
from pathlib import Path
root = Path(sys.argv[1]).resolve(); as_json = sys.argv[2] == '1'
reasons=[]
def git(*args):
    result=subprocess.run(['git','-C',str(root),*args], text=True, capture_output=True, check=False)
    return result.stdout.strip() if result.returncode == 0 else ''
def has_object(spec):
    return subprocess.run(['git','-C',str(root),'cat-file','-e',spec], text=True, capture_output=True, check=False).returncode == 0
def require_commit(ref, label):
    if not re.fullmatch(r'[0-9a-f]{40}', ref or ''):
        reasons.append('invalid-'+label+'-sha')
        return None
    resolved=git('rev-parse','--verify',ref+'^{commit}')
    if resolved != ref:
        reasons.append('unresolved-'+label+'-commit')
        return None
    return resolved
def resolve_base():
    explicit=os.environ.get('DEV_DELIVERY_V2_BASE_SHA')
    if explicit is not None and explicit != '':
        return require_commit(explicit, 'base')
    event_path=Path(os.environ.get('GITHUB_EVENT_PATH',''))
    if event_path.is_file():
        try:
            event_base=json.loads(event_path.read_text()).get('pull_request',{}).get('base',{}).get('sha')
        except (OSError, json.JSONDecodeError):
            reasons.append('invalid-github-event-base')
            return None
        if event_base:
            return require_commit(event_base, 'base')
    return require_commit(git('rev-parse','--verify','origin/main^{commit}'), 'base')
agents = root/'AGENTS.md'; marker='<!-- BEGIN REPO-LOCAL DEV UPGRADE CONTRACT -->'; end='<!-- END REPO-LOCAL DEV UPGRADE CONTRACT -->'
required=['repo-local authority','global prompt','Luna','Terra','Sol','worktree','checkpoint','git diff','--check','Vercel']
if not agents.is_file(): reasons.append('missing-local-agents')
else:
    text=agents.read_text(encoding='utf-8', errors='replace')
    if marker not in text or end not in text or text.index(marker) >= text.index(end): reasons.append('missing-or-malformed-local-policy')
    else:
        block=text[text.index(marker):text.index(end)+len(end)]
        for token in required:
            if token not in block: reasons.append('local-policy-missing:'+token)
branch=git('branch','--show-current'); base=resolve_base(); head=require_commit(git('rev-parse','--verify','HEAD^{commit}'), 'head'); tree=git('show','-s','--format=%T','HEAD') or None
if not re.fullmatch(r'[0-9a-f]{40}', tree or '') or not has_object(tree+'^{tree}'):
    reasons.append('unresolved-head-tree')
if not branch and os.environ.get('GITHUB_ACTIONS') == 'true' and os.environ.get('GITHUB_SHA') == head and re.fullmatch(r'[A-Za-z0-9._/-]+', os.environ.get('GITHUB_HEAD_REF','')):
    branch=os.environ['GITHUB_HEAD_REF']
if not branch: reasons.append('detached-head')
changed=[]
current=[]
if base and head:
    diff=subprocess.run(['git','-C',str(root),'diff','--no-ext-diff','--name-status','-M','-C',f'{base}..{head}'], text=True, capture_output=True)
    if diff.returncode != 0:
        reasons.append('base-head-diff-failed')
    else:
        for line in diff.stdout.splitlines():
            fields=line.split('\t')
            if len(fields) >= 2:
                status=fields[0]
                kind=status[:1]
                if kind in ('R','C'):
                    if len(fields) < 3:
                        reasons.append('malformed-rename-copy-status')
                        continue
                    changed.extend((fields[1],fields[2]))
                    current.append(fields[2])
                else:
                    changed.append(fields[1])
                    if kind != 'D':
                        current.append(fields[1])
        changed=sorted(set(changed))
        current=sorted(set(current))
def git_file(ref, path):
    result=subprocess.run(['git','-C',str(root),'show',ref+':'+path], text=True, capture_output=True, check=False)
    return result.stdout if result.returncode == 0 else ''
protected_globs=[]; protected_list=[]
try:
    protected_globs=json.loads((root/'docs/impactshop-protected-files.json').read_text(encoding='utf-8')).get('protected_globs',[])
    protected_list=[line.strip() for line in (root/'.github/protected-files.txt').read_text(encoding='utf-8').splitlines() if line.strip() and not line.lstrip().startswith('#')]
except (OSError,json.JSONDecodeError):
    reasons.append('protected-policy-unreadable')
def listed_protected(path): return any(fnmatch.fnmatch(path, pattern) for pattern in protected_globs+protected_list)
executable=('.sh','.py','.js','.mjs','.ts','.yml','.yaml')
remote_write=re.compile(
    r'\b(git\s+push|gh\s+pr\s+(?:create|merge)|ssh\s+(?:-[a-z]|[^\s"\x27]+@)|scp\s+|'
    r'rsync(?:\s|$)|curl(?:\s|$)|wget(?:\s|$)|provider[ -]deploy|'
    r'remote[ -]write|vercel\s+(?:deploy|--prod)|railway\s+(?:up|deploy))',
    re.I,
)
def executable_surface(path):
    return path.endswith(executable) and (
        path.startswith(('scripts/','bin/','deploy/'))
        or (path.startswith('.github/workflows/') and path.endswith(('.yml','.yaml')))
    )
content_deploy=any(executable_surface(path) and remote_write.search(git_file(head,path)) for path in current)
governance={'AGENTS.md','notes.md','system-status-snapshot.md','docs/impactshop-governance-system-plan-2026-06-16.md','docs/impactshop-notes-doc-sync-map-2026-06-23.md'}
if not changed: path_class='governance-only'
elif content_deploy or any(p.startswith(('bin/','deploy/','.deploy.')) for p in changed): path_class='protected-or-deploy'
elif any(listed_protected(p) or p.startswith(('wp-content/','scripts/','.github/workflows/','config/')) for p in changed): path_class='protected-or-deploy'
elif all(p in governance or p.startswith(('docs/','tests/')) for p in changed): path_class='governance-only'
else: path_class='unknown'
if path_class == 'governance-only': provider='not-configured'; decision='allowed'
elif path_class == 'protected-or-deploy': provider='operator-review'; decision='operator-review'; reasons.append('protected-or-deploy-path')
else: provider='operator-review'; decision='blocked'; reasons.append('unknown-path-class')
if reasons and decision == 'allowed': decision='blocked'
payload={'schemaVersion':1,'repo':'impactshop-notes','authoritySource':'repo-local','branch':branch,'baseSha':base,'headSha':head,'treeSha':tree,'changedPathClass':path_class,'changedPaths':changed,'providerBuildDecision':provider,'evidenceReuseAllowed':not bool(reasons),'decision':decision,'blockingReasons':reasons}
print(json.dumps(payload, sort_keys=True) if as_json else f'[dev-context-policy] decision={decision} class={path_class}')
sys.exit(0 if decision == 'allowed' else 1)
PY
