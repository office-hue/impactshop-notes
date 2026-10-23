/** Notes-only hook images; no installation, grant, provider or shared-state writes. */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const header = `#!/bin/bash
set -euo pipefail
PATH=/usr/local/libexec/ai-agent-shared-node/node/bin:/usr/bin:/bin:/usr/sbin:/sbin
export PATH GIT_NO_REPLACE_OBJECTS=1 GIT_OPTIONAL_LOCKS=0
unset NODE_OPTIONS NODE_PATH OFFICE_DEV_CONTROLLER OFFICE_DEV_ENGINE_DESCRIPTOR DEV_V4_DATA_ROOT DEV_V4_REPO_ROOT
git_read() { /usr/bin/git -c core.fsmonitor=false "$@"; }
deny() { echo "[notes-hook] $1" >&2; exit 1; }
REPO_ROOT="$(git_read rev-parse --show-toplevel)"
cd "$REPO_ROOT"
BRANCH="$(git_read branch --show-current)"
local_guard() {
  [[ -d "$REPO_ROOT/scripts" && ! -L "$REPO_ROOT/scripts" ]] || deny unsafe-local-scripts-directory
  [[ -f "$REPO_ROOT/scripts/$1" && ! -L "$REPO_ROOT/scripts/$1" ]] || deny missing-or-unsafe-local-guard
  local script="$1"; shift
  /bin/bash "$REPO_ROOT/scripts/$script" "$@"
}
`;

// In-process fixture seam only. CLI always reads the fixed own source sibling.
export function renderHookImages(auditSource = fs.readFileSync(new URL('./safe-repo-audit.sh', import.meta.url), 'utf8')) {
  if (typeof auditSource !== 'string' || !auditSource || auditSource.includes('\0')
      || auditSource.split(/\r?\n/).includes('NOTES_FROZEN_AUDIT'))
    throw new Error('audit_source_invalid');
  const preCommit = header + `
[[ -n "$BRANCH" && "$BRANCH" != main && "$BRANCH" != master ]] || deny protected-or-detached-branch
local_guard check-protected-file-touch.sh --mode local
local_guard check-commit-lane.sh --mode local
`;
  const prePush = header + `
[[ -n "$BRANCH" && "$BRANCH" != main && "$BRANCH" != master ]] || deny protected-or-detached-branch
[[ "\${1:-}" == origin && "\${2:-}" == https://github.com/office-hue/impactshop-notes.git ]] || deny transport-mismatch
[[ "$(git_read config --local --get-all remote.origin.url)" == https://github.com/office-hue/impactshop-notes.git ]] || deny origin-mismatch
[[ -z "$(git_read status --porcelain --untracked-files=all)" ]] || deny dirty-push
for rel in scripts/start-feature-worktree.sh scripts/git-health-check.sh docs/pr-policy.md .github/pull_request_template.md PR-EXIT-CHECKLIST.md scripts/safe-repo-audit.sh; do
  [[ -f "$REPO_ROOT/$rel" && ! -L "$REPO_ROOT/$rel" ]] || deny missing-or-unsafe-policy-file
done
row_count=0
push_row=''
while IFS= read -r row; do
  if [[ -n "$row" ]]; then row_count=$((row_count + 1)); push_row="$row"; fi
done
[[ "$row_count" -eq 1 ]] || deny unsupported-push-ref-count
read -r local_ref local_sha remote_ref remote_sha extra <<< "$push_row"
[[ -z "\${extra:-}" && "$local_ref" == "refs/heads/$BRANCH" && "$remote_ref" == "refs/heads/$BRANCH" ]] || deny unsupported-push-ref
[[ "$local_sha" =~ ^[0-9a-f]{40}$ && "$remote_sha" =~ ^[0-9a-f]{40}$ ]] || deny malformed-push-sha
[[ "$local_sha" == "$(git_read rev-parse HEAD)" ]] || deny pushed-head-mismatch
zero=0000000000000000000000000000000000000000
if [[ "$remote_sha" == "$zero" ]]; then
  base="$(git_read merge-base "$local_sha" refs/remotes/origin/main)" || deny missing-source-base
else
  git_read cat-file -e "$remote_sha^{commit}" || deny missing-remote-base
  git_read merge-base --is-ancestor "$remote_sha" "$local_sha" || deny non-fast-forward
  base="$remote_sha"
fi
export SAFE_REPO_AUDIT_UPSTREAM="$base" SAFE_REPO_AUDIT_PUSH_RANGE="$base..$local_sha"
local_guard check-protected-file-touch.sh --mode push
local_guard check-commit-lane.sh --mode push
local_guard worktree-continuity-guard.sh --mode push
audit_digest="$(/usr/bin/shasum -a 256 "$REPO_ROOT/scripts/safe-repo-audit.sh")"
audit_digest="\${audit_digest%% *}"
if [[ "$audit_digest" == 4b519c1510ab34955bcaeb31da4b2a3dab9f91841e64ad7437a232fe00785563 ]]; then
  echo '[notes-hook] known legacy audit: frozen exact-base compatibility reader' >&2
  /bin/bash -s -- --repo "$REPO_ROOT" --strict --mode push <<'NOTES_FROZEN_AUDIT'
` + auditSource + `
NOTES_FROZEN_AUDIT
else
  local_guard safe-repo-audit.sh --repo "$REPO_ROOT" --strict --mode push
fi
`;
  const commitMsg = `#!/bin/bash
set -euo pipefail
file="\${1:-}"
[[ -n "$file" && -f "$file" && ! -L "$file" ]] || exit 1
if ! /usr/bin/grep -q '^Memory-ID:' "$file"; then
  printf '\\nMemory-ID: none\\n' >> "$file"
fi
`;
  const optional = script => header + `
if [[ -d "$REPO_ROOT/scripts" && ! -L "$REPO_ROOT/scripts" && -f "$REPO_ROOT/scripts/${script}" && ! -L "$REPO_ROOT/scripts/${script}" ]]; then
  /bin/bash "$REPO_ROOT/scripts/${script}" || true
fi
exit 0
`;
  const scripts = {
    'pre-commit': preCommit, 'pre-push': prePush, 'commit-msg': commitMsg,
    'post-commit': optional('post-commit-status.sh'),
    'post-merge': optional('post-merge-deploy-guard.sh'),
    'post-checkout': '#!/bin/bash\nexit 0\n',
  };
  return { changes: Object.fromEntries(Object.entries(scripts).map(([name, text]) => {
    const raw = Buffer.from(text);
    if (raw.length > 65536) throw new Error('hook_image_too_large');
    return [name, { state: 'file', bytes: raw.toString('base64'), mode: 0o755,
      sha256: crypto.createHash('sha256').update(raw).digest('hex') }];
  })) };
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  if (process.argv.length !== 3 || process.argv[2] !== '--render') {
    process.stderr.write('Usage: node scripts/dev-v4-target-hooks.mjs --render (no installation)\n');
    process.exitCode = 1;
  } else process.stdout.write(JSON.stringify(renderHookImages()) + '\n');
}
