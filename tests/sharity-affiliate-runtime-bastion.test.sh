#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel)"
GUARD="${ROOT_DIR}/scripts/sharity-affiliate-runtime-bastion-guard.sh"
RUNTIME="${ROOT_DIR}/wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php"
BOOT="${ROOT_DIR}/wp-content/mu-plugins/impactshop-boot.php"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

cp "$RUNTIME" "$TMP_DIR/runtime.php"
cp "$BOOT" "$TMP_DIR/boot.php"

run_guard() {
  AFFILIATE_RUNTIME_SOURCE="$TMP_DIR/runtime.php" \
    AFFILIATE_BOOT_SOURCE="$TMP_DIR/boot.php" \
    bash "$GUARD" >/dev/null 2>&1
}

run_guard

python3 - "$TMP_DIR/boot.php" <<'PY'
import sys
p = sys.argv[1]
s = open(p, encoding="utf-8").read()
s = s.replace("if ($src === 'shopping-assistant')", "if ($src !== 'shopping-assistant')", 1)
open(p, "w", encoding="utf-8").write(s)
PY
if run_guard; then
  echo "FAIL: loose source gate escaped bastion" >&2
  exit 1
fi

cp "$BOOT" "$TMP_DIR/boot.php"
python3 - "$TMP_DIR/runtime.php" <<'PY'
import sys
p = sys.argv[1]
s = open(p, encoding="utf-8").read()
s = s.replace("activation_id varchar(37) NOT NULL,", "activation_id varchar(37) NOT NULL,\n      pseudo varchar(64) NOT NULL,", 1)
open(p, "w", encoding="utf-8").write(s)
PY
if run_guard; then
  echo "FAIL: raw pseudo schema escaped bastion" >&2
  exit 1
fi

cp "$RUNTIME" "$TMP_DIR/runtime.php"
python3 - "$TMP_DIR/boot.php" <<'PY'
import sys
p = sys.argv[1]
s = open(p, encoding="utf-8").read()
s = s.replace("apply_filters('impactshop_sharity_affiliate_mark_redirected'", "apply_filters('removed_transition'", 1)
open(p, "w", encoding="utf-8").write(s)
PY
if run_guard; then
  echo "FAIL: missing transition escaped bastion" >&2
  exit 1
fi

echo "sharity affiliate runtime bastion tamper test: PASS"
