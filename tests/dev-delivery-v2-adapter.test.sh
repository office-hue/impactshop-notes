#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"; A="$ROOT/scripts/dev-delivery-v2-adapter.sh"
p="$(bash "$A" inspect --json || true)"; python3 - <<'PY' "$p"
import json,sys
p=json.loads(sys.argv[1]); assert p['schemaVersion']==2 and p['authoritySource']=='repo-local'; assert p['contractSha256']=='989dd16dd30bdebb07403c1b0f88ad9a182ea0dd167fc37674438b4bc8ef0194'; assert p['changedPathClass'] in ('protected','deploy','unknown','governance-only')
PY
t="$(mktemp -d)"; trap 'rm -rf "$t"' EXIT
mkdir -p "$t/config" "$t/scripts" "$t/docs" "$t/.github/workflows" "$t/wp-content/mu-plugins"
cp "$ROOT/config/dev-delivery-v2-target-contract.json" "$ROOT/config/dev-delivery-v2-impact-policy.json" "$t/config/"; cp "$A" "$t/scripts/"
printf '%s\n' '{"protected_globs":["wp-content/mu-plugins/locked.php"],"additive_globs":[]}' > "$t/docs/impactshop-protected-files.json"
printf '%s\n' '.github/workflows/locked.yml' > "$t/.github/protected-files.txt"
git -C "$t" init -q; git -C "$t" config user.email t@x; git -C "$t" config user.name t; git -C "$t" add .; git -C "$t" commit -qm x; git -C "$t" branch -M main; git -C "$t" update-ref refs/remotes/origin/main "$(git -C "$t" rev-parse main)"; git -C "$t" checkout -qb f
bash "$A" bastion --offline-fixture "$t" --json >/dev/null
reject() { if bash "$A" bastion --offline-fixture "$t" --json >/dev/null 2>&1; then echo "unsafe candidate was accepted: $1" >&2; exit 1; fi; }
printf '%s\n' 'name: hostile' 'jobs:' '  deploy:' '    runs-on: ubuntu-latest' '    steps:' '      - run: provider-deploy production' > "$t/.github/workflows/provider-deploy.yml"; git -C "$t" add .; git -C "$t" commit -qm workflow; reject workflow-provider-deploy
printf '%s\n' '#!/usr/bin/env bash' 'rsync payload host:/remote/path' > "$t/scripts/deploy-remote.sh"; git -C "$t" add .; git -C "$t" commit -qm script; reject script-remote-write
printf '%s\n' '<?php // protected' > "$t/wp-content/mu-plugins/locked.php"; git -C "$t" add .; git -C "$t" commit -qm protected; reject protected-wp
printf '%s\n' 'unknown' > "$t/runtime.txt"; git -C "$t" add .; git -C "$t" commit -qm unknown; reject unknown-path
state="$(git -C "$t" rev-parse --git-path dev-delivery-v2)"; mkdir -p "$t/$state"; chmod 700 "$t/$state"; printf '%s\n' '{"baseSha":"forged","headSha":"forged","treeSha":"forged","changedPathClass":"protected","contractSha256":"forged","fullValidation":"forged","providerDeployAllowed":true}' > "$t/$state/full-validation.json"; chmod 600 "$t/$state/full-validation.json"; reject forged-pass-evidence
printf '\n# git push\n' >> "$t/scripts/dev-delivery-v2-adapter.sh"; reject forbidden-authority
echo 'dev delivery v2 adapter fixtures: PASS'
