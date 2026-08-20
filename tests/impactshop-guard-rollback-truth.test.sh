#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
GUARD_SCRIPT="$ROOT_DIR/bin/impactshop-guard-deploy.sh"
ROLLBACK_SCRIPT="$ROOT_DIR/bin/impactshop-guard-rollback.sh"

python3 - "$GUARD_SCRIPT" <<'PY'
import sys
from pathlib import Path

source = Path(sys.argv[1]).read_text(encoding="utf-8")
local_snapshot = 'Lokális source snapshot elkészült: ${latest_snap}'
runtime_truth = 'Remote runtime rollback parancsot csak sikeres exact release ad release ID + deployed SHA értékekkel.'
relative_checksum = 'display_path = os.path.relpath(os.path.abspath(hash_path), repo_root)'
portable_write = 'fh.write(f"{checksum}  {display_path}\\n")'

for marker in (
    local_snapshot,
    runtime_truth,
    relative_checksum,
    portable_write,
):
    if source.count(marker) != 1:
        raise SystemExit(f"rollback truth guard: marker count must be one: {marker}")

snapshot_index = source.index(local_snapshot)
truth_index = source.index(runtime_truth)
if not snapshot_index < truth_index:
    raise SystemExit("rollback truth guard: snapshot/runtime truth order invalid")

if 'bin/impactshop-guard-rollback.sh ${latest_snap}' in source:
    raise SystemExit("rollback truth guard: local snapshot leaked into remote rollback command")

if 'fh.write(f"{checksum}  {hash_path}\\n")' in source:
    raise SystemExit("rollback truth guard: absolute hash path can leak into checksum label")
PY

test -x "$ROLLBACK_SCRIPT"
grep -Fq '[[ $APPLY_MODE -eq 0 ]]' "$ROLLBACK_SCRIPT"
grep -Fq 'if [[ $PRODUCTION_CONFIRMED -ne 1 ]]' "$ROLLBACK_SCRIPT"
grep -Fq 'EXPECTED_DEPLOYED_SHA' "$ROLLBACK_SCRIPT"
grep -Fq 'python3 - rollback' "$ROLLBACK_SCRIPT"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
mkdir -p "$TMP_DIR/bin"
SSH_LOG="$TMP_DIR/ssh.log"
: > "$SSH_LOG"
cat > "$TMP_DIR/bin/ssh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$FAKE_SSH_LOG"
while IFS= read -r _line; do :; done
printf '%s\n' '{"ok":true}'
SH
chmod +x "$TMP_DIR/bin/ssh"

run_rollback() {
  env PATH="$TMP_DIR/bin:$PATH" FAKE_SSH_LOG="$SSH_LOG" bash "$ROLLBACK_SCRIPT" "$@"
}

if run_rollback --release-id=release-test-20260820 --apply --expected-deployed-sha="$(printf 'a%.0s' {1..64})" >/dev/null 2>&1; then
  echo "rollback truth guard: mutating rollback accepted without --production" >&2
  exit 1
fi
test ! -s "$SSH_LOG"

if run_rollback --production --apply --release-id=release-test-20260820 >/dev/null 2>&1; then
  echo "rollback truth guard: mutating rollback accepted without expected SHA" >&2
  exit 1
fi
test ! -s "$SSH_LOG"

run_rollback --release-id=release-test-20260820 >/dev/null
grep -Fq 'python3 - inspect --root /home/sharityh/app --release-id release-test-20260820' "$SSH_LOG"

: > "$SSH_LOG"
EXPECTED_SHA="$(printf 'b%.0s' {1..64})"
run_rollback --production --apply --release-id=release-test-20260820 \
  --expected-deployed-sha="$EXPECTED_SHA" >/dev/null
grep -Fq "python3 - rollback --root /home/sharityh/app --release-id release-test-20260820 --expected-deployed-sha $EXPECTED_SHA" "$SSH_LOG"

echo "impactshop guard rollback truth test: PASS"
