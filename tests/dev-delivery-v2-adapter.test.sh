#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"; A="$ROOT/scripts/dev-delivery-v2-adapter.sh"
p="$(bash "$A" inspect --json)"; python3 - <<'PY' "$p"
import json,sys
p=json.loads(sys.argv[1]); assert p['schemaVersion']==2 and p['authoritySource']=='repo-local'; assert p['contractSha256']=='989dd16dd30bdebb07403c1b0f88ad9a182ea0dd167fc37674438b4bc8ef0194'
PY
bash "$A" bastion --json >/dev/null
t="$(mktemp -d)"; trap 'rm -rf "$t"' EXIT; mkdir -p "$t/config" "$t/scripts"; cp "$ROOT/config/dev-delivery-v2-target-contract.json" "$ROOT/config/dev-delivery-v2-impact-policy.json" "$t/config/"; cp "$A" "$t/scripts/"; git -C "$t" init -q; git -C "$t" config user.email t@x; git -C "$t" config user.name t; git -C "$t" add .; git -C "$t" commit -qm x; git -C "$t" branch -M main; git -C "$t" update-ref refs/remotes/origin/main "$(git -C "$t" rev-parse main)"; git -C "$t" checkout -qb f
bash "$A" inspect --offline-fixture "$t" --json >/dev/null
if bash "$A" freeze --offline-fixture "$t" --json >/dev/null 2>&1; then exit 1; fi
git -C "$t" update-ref -d refs/remotes/origin/main
if bash "$A" inspect --offline-fixture "$t" --json >/dev/null 2>&1; then echo 'missing base was accepted' >&2; exit 1; fi
git -C "$t" update-ref refs/remotes/origin/main "$(git -C "$t" rev-parse main)"
if DEV_DELIVERY_V2_BASE_SHA=main bash "$A" inspect --offline-fixture "$t" --json >/dev/null 2>&1; then echo 'non-SHA base was accepted' >&2; exit 1; fi
printf '%s\n' 'runtime change' > "$t/runtime.txt"; git -C "$t" add runtime.txt; git -C "$t" commit -qm runtime
if bash "$A" inspect --offline-fixture "$t" --json >/dev/null 2>&1; then echo 'non-governance change was accepted' >&2; exit 1; fi
printf '\n# git push\n' >> "$t/scripts/dev-delivery-v2-adapter.sh"; if bash "$A" bastion --offline-fixture "$t" --json >/dev/null 2>&1; then exit 1; fi
echo 'dev delivery v2 adapter fixtures: PASS'
