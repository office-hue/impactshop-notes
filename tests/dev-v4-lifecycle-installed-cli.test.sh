#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
cp -R "$ROOT/config" "$TMP/config"
mkdir -p "$TMP/scripts" "$TMP/docs"
cp "$ROOT/config/dev-v4"/*.json "$TMP/config/dev-v4/"
git -C "$TMP" init -q -b main
git -C "$TMP" config user.email smoke@example.invalid
git -C "$TMP" config user.name smoke
git -C "$TMP" add . && git -C "$TMP" commit -qm base
git init -q --bare "$TMP-origin.git"
git -C "$TMP" remote add origin https://github.com/office-hue/impactshop-notes.git
git -C "$TMP" config url."file://$TMP-origin.git".insteadOf https://github.com/office-hue/impactshop-notes.git
git -C "$TMP" push -q origin HEAD:refs/heads/main
git -C "$TMP" fetch -q origin main
cp "$ROOT/scripts/dev-v4-lifecycle-client.mjs" "$TMP/scripts/"
export DEV_V4_REPO_ROOT="$TMP"
export DEV_V4_DATA_ROOT="$HOME/Library/Application Support/office-dev"
before="$(git -C "$TMP" rev-parse HEAD^{tree})"
if ! output="$(node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" start --task-id smoke-installed-cli --selector normal-source-short-brief --branch fix/smoke-installed-cli --task-brief smoke 2>&1)"; then echo "$output"; exit 1; fi
WT="$TMP/../.worktrees/$(basename "$TMP")-fix-smoke-installed-cli"
export DEV_V4_REPO_ROOT="$WT"
node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" status >/dev/null
node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" resume >/dev/null
node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" context --refresh --operation source >/dev/null
node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" context --consume --operation source >/dev/null
after="$(git -C "$TMP" rev-parse HEAD^{tree})"
test "$before" = "$after"
export DEV_V4_REPO_ROOT="$TMP"
if ! output="$(node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" start --task-id smoke-governance --selector dev-governance-source --branch fix/smoke-governance 2>&1)"; then echo "$output"; exit 1; fi
GWT="$TMP/../.worktrees/$(basename "$TMP")-fix-smoke-governance"
export DEV_V4_REPO_ROOT="$GWT"
base="$(git -C "$GWT" rev-parse HEAD)"
mkdir -p "$GWT/docs/dev-plans"
python3 - "$GWT/docs/dev-plans/smoke-governance.md" "$base" <<'PY'
import json, sys
path, base = sys.argv[1:]
value = {"schemaVersion": 2, "planId": "smoke-governance", "selectorId": "dev-governance-source", "repo": "impactshop-notes", "changeImpact": "governance-only", "riskTier": "high", "testProfile": "smoke-governance", "releaseProfile": "source-only", "session": {"branch": "fix/smoke-governance", "baseRef": "origin/main", "baseCommit": base, "headCommit": base, "candidateTree": "private-evidence"}, "changeAllowlist": ["docs/dev-plans/smoke-governance.md"], "budgets": {"push": 0, "pullRequest": 0, "merge": 0, "providerBuild": 0, "providerDeploy": 0, "mutationAttempt": 0}, "qa": {"correctness": "pass", "regression": "pass", "security": "pass", "operational": "pass"}, "documentationTargets": ["docs/dev-plans/smoke-governance.md"]}
open(path, 'w').write('# Smoke governance plan\n\n<!-- DEV-DELIVERY-V2-MANIFEST\n' + json.dumps(value, sort_keys=True) + '\nDEV-DELIVERY-V2-MANIFEST -->\n')
PY
git -C "$GWT" add docs/dev-plans/smoke-governance.md
git -C "$GWT" commit -qm "smoke: bind governance plan"
anchor="$(git -C "$GWT" rev-parse HEAD)"
node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" status | grep -q 'planning-only'
node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" bind --task-id smoke-governance --plan-anchor "$anchor" >/dev/null
node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" status | grep -q 'implementation-ready'
if node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" bind --task-id smoke-governance --plan-anchor "$(printf '0%.0s' {1..40})" >/dev/null 2>&1; then exit 1; fi
if node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" bind --task-id smoke-governance --plan-anchor "$anchor" --extra nope >/dev/null 2>&1; then exit 1; fi
if DEV_V4_DATA_ROOT="$TMP/missing-office-dev" node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" status >/dev/null 2>&1; then exit 1; fi
if node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" start --task-id bad-governance --selector dev-governance-source --branch fix/bad-governance --plan-id fake >/dev/null 2>&1; then exit 1; fi
for forbidden in 'bind --task-id x --plan-anchor y' 'policy-migrate --target x' 'reconcile --recover' 'record-review --record x' 'run-check --check-id x' 'context --accept-events x' 'unknown'; do
  if node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" $forbidden >/dev/null 2>&1; then exit 1; fi
done
git -C "$TMP" config remote.origin.url https://github.com/example.invalid/wrong.git
if node "$ROOT/scripts/dev-v4-lifecycle-client.mjs" status >/dev/null 2>&1; then exit 1; fi
echo "installed-cli-start-status-resume-context-readonly-ok"
