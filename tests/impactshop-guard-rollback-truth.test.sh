#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
GUARD_SCRIPT="$ROOT_DIR/bin/impactshop-guard-deploy.sh"

python3 - "$GUARD_SCRIPT" <<'PY'
import sys
from pathlib import Path

source = Path(sys.argv[1]).read_text(encoding="utf-8")
rollback_path = 'rollback_script="${ROOT_DIR}/bin/impactshop-guard-rollback.sh"'
executable_gate = 'if [[ -x "$rollback_script" ]]; then'
quick_restore = 'Gyors visszaállítás: bin/impactshop-guard-rollback.sh'
missing_truth = 'Remote runtime rollback nem elérhető; valós production írás továbbra is tiltott.'

for marker in (rollback_path, executable_gate, quick_restore, missing_truth):
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
PY

if [[ ! -x "$ROOT_DIR/bin/impactshop-guard-rollback.sh" ]]; then
  grep -Fq 'Remote runtime rollback nem elérhető; valós production írás továbbra is tiltott.' "$GUARD_SCRIPT"
fi

echo "impactshop guard rollback truth test: PASS"
