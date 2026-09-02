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
import json, os, re, subprocess, sys
from pathlib import Path
root = Path(sys.argv[1]).resolve(); as_json = sys.argv[2] == '1'
reasons=[]
def git(*args):
    result=subprocess.run(['git','-C',str(root),*args], text=True, capture_output=True, check=False)
    return result.stdout.strip() if result.returncode == 0 else ''
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
branch=git('branch','--show-current'); base=git('rev-parse','origin/main') or None; head=git('rev-parse','HEAD') or None; tree=git('show','-s','--format=%T','HEAD') or None
if not branch and os.environ.get('GITHUB_ACTIONS') == 'true' and os.environ.get('GITHUB_SHA') == head and re.fullmatch(r'[A-Za-z0-9._/-]+', os.environ.get('GITHUB_HEAD_REF','')):
    branch=os.environ['GITHUB_HEAD_REF']; event_path=Path(os.environ.get('GITHUB_EVENT_PATH',''))
    if event_path.is_file(): base=json.loads(event_path.read_text()).get('pull_request',{}).get('base',{}).get('sha') or base
if not branch: reasons.append('detached-head')
changed=[]
if base and head and base != head: changed=[x for x in git('diff','--name-only',f'{base}..{head}').splitlines() if x]
governance={'AGENTS.md','notes.md','system-status-snapshot.md','scripts/dev-context-policy-guard.sh','tests/dev-context-policy-guard.test.sh','docs/impactshop-governance-system-plan-2026-06-16.md','docs/impactshop-notes-doc-sync-map-2026-06-23.md'}
if not changed: path_class='governance-only'
elif all(p in governance or p.startswith(('docs/','scripts/','tests/','.github/workflows/')) for p in changed): path_class='governance-only'
elif any(p.startswith(('wp-content/','bin/','impactctl','deploy')) for p in changed): path_class='protected-or-deploy'
else: path_class='unknown'
if path_class == 'governance-only': provider='not-configured'; decision='allowed'
elif path_class == 'protected-or-deploy': provider='operator-review'; decision='operator-review'; reasons.append('protected-or-deploy-path')
else: provider='operator-review'; decision='blocked'; reasons.append('unknown-path-class')
if reasons and decision == 'allowed': decision='blocked'
payload={'schemaVersion':1,'repo':'impactshop-notes','authoritySource':'repo-local','branch':branch,'baseSha':base,'headSha':head,'treeSha':tree,'changedPathClass':path_class,'providerBuildDecision':provider,'evidenceReuseAllowed':not bool(reasons),'decision':decision,'blockingReasons':reasons}
print(json.dumps(payload, sort_keys=True) if as_json else f'[dev-context-policy] decision={decision} class={path_class}')
sys.exit(0 if decision == 'allowed' else 1)
PY
