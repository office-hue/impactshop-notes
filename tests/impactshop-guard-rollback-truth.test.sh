#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
GUARD_SCRIPT="$ROOT_DIR/bin/impactshop-guard-deploy.sh"
ROLLBACK_SCRIPT="$ROOT_DIR/bin/impactshop-guard-rollback.sh"

python3 - "$GUARD_SCRIPT" <<'PY'
import sys
from pathlib import Path

source = Path(sys.argv[1]).read_text(encoding="utf-8")
rollback_path = 'rollback_script="${ROOT_DIR}/bin/impactshop-guard-rollback.sh"'
executable_gate = 'if [[ -x "$rollback_script" ]]; then'
quick_restore = 'Gyors visszaállítás: bin/impactshop-guard-rollback.sh'
missing_truth = 'Remote runtime rollback nem elérhető; valós production írás továbbra is tiltott.'
relative_checksum = 'display_path = os.path.relpath(os.path.abspath(hash_path), repo_root)'
portable_write = 'fh.write(f"{checksum}  {display_path}\\n")'

for marker in (
    rollback_path,
    executable_gate,
    quick_restore,
    missing_truth,
    relative_checksum,
    portable_write,
):
    if source.count(marker) != 1:
        raise SystemExit(f"rollback truth guard: marker count must be one: {marker}")

path_index = source.index(rollback_path)
gate_index = source.index(executable_gate)
quick_index = source.index(quick_restore)
else_index = source.index("else", quick_index)
truth_index = source.index(missing_truth)
end_index = source.index("fi", truth_index)

if not path_index < gate_index < quick_index < else_index < truth_index < end_index:
    raise SystemExit("rollback truth guard: quick restore is not executable-gated")

if 'fh.write(f"{checksum}  {hash_path}\\n")' in source:
    raise SystemExit("rollback truth guard: absolute hash path can leak into checksum label")
PY

if [[ ! -x "$ROOT_DIR/bin/impactshop-guard-rollback.sh" ]]; then
  grep -Fq 'Remote runtime rollback nem elérhető; valós production írás továbbra is tiltott.' "$GUARD_SCRIPT"
fi

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
