#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENGINE="$ROOT_DIR/scripts/impactshop-exact-release-remote.py"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

python3 - "$ENGINE" <<'PY'
import ast
import pathlib
import sys

source = pathlib.Path(sys.argv[1]).read_text(encoding="utf-8")
ast.parse(source, filename=sys.argv[1], feature_version=(3, 6))
for forbidden in (
    "from __future__ import annotations",
    "dict[",
    "tuple[",
    " | None",
    "text=True",
):
    if forbidden in source:
        raise SystemExit("Python 3.6 compatibility regression: " + forbidden)
PY

APP_ROOT="$TMP_DIR/app"
TARGET_REL="wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php"
TARGET="$APP_ROOT/$TARGET_REL"
mkdir -p "$APP_ROOT/.bastion" "$(dirname "$TARGET")"
chmod 700 "$APP_ROOT/.bastion"

PAYLOAD_ONE="$TMP_DIR/payload-one.php"
PAYLOAD_TWO="$TMP_DIR/payload-two.php"
printf '%s\n' '<?php function impactshop_release_one(): bool { return true; }' > "$PAYLOAD_ONE"
printf '%s\n' '<?php function impactshop_release_two(): bool { return true; }' > "$PAYLOAD_TWO"

sha256_of() {
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$1" | awk '{print $1}'
  else
    shasum -a 256 "$1" | awk '{print $1}'
  fi
}

file_mode() {
  stat -c '%a' "$1" 2>/dev/null || stat -f '%Lp' "$1"
}

SHA_ONE="$(sha256_of "$PAYLOAD_ONE")"
SHA_TWO="$(sha256_of "$PAYLOAD_TWO")"

run_engine() {
  python3 "$ENGINE" "$@"
}

RELEASE_ABSENT="release-absent-20260820"
run_engine prepare --root "$APP_ROOT" --release-id "$RELEASE_ABSENT" \
  --target-relative "$TARGET_REL" --expected-before absent --intended-sha "$SHA_ONE" \
  | grep -Fq '"phase":"prepared"'
cp "$PAYLOAD_ONE" "$APP_ROOT/.bastion/exact-file-releases/$RELEASE_ABSENT/payload.bin"
run_engine apply --root "$APP_ROOT" --release-id "$RELEASE_ABSENT" \
  | grep -Fq '"phase":"deployed"'
test "$(sha256_of "$TARGET")" = "$SHA_ONE"
test "$(file_mode "$TARGET")" = "444"
run_engine inspect --root "$APP_ROOT" --release-id "$RELEASE_ABSENT" \
  | grep -Fq '"currentMode":"0444"'
run_engine rollback --root "$APP_ROOT" --release-id "$RELEASE_ABSENT" \
  --expected-deployed-sha "$SHA_ONE" | grep -Fq '"state":"absent"'
test ! -e "$TARGET"
if run_engine rollback --root "$APP_ROOT" --release-id "$RELEASE_ABSENT" \
  --expected-deployed-sha "$SHA_ONE" >/dev/null 2>&1; then
  echo "second rollback unexpectedly succeeded" >&2
  exit 1
fi

printf '%s\n' '<?php function impactshop_original(): bool { return true; }' > "$TARGET"
chmod 640 "$TARGET"
ORIGINAL_SHA="$(sha256_of "$TARGET")"
SIBLING="$APP_ROOT/wp-content/mu-plugins/sibling.php"
printf '%s\n' '<?php // sibling' > "$SIBLING"
SIBLING_SHA="$(sha256_of "$SIBLING")"
RELEASE_EXISTING="release-existing-20260820"
run_engine prepare --root "$APP_ROOT" --release-id "$RELEASE_EXISTING" \
  --target-relative "$TARGET_REL" --expected-before "$ORIGINAL_SHA" --intended-sha "$SHA_TWO" >/dev/null
test "$(sha256_of "$APP_ROOT/.bastion/exact-file-releases/$RELEASE_EXISTING/backup.bin")" = "$ORIGINAL_SHA"
test "$(file_mode "$APP_ROOT/.bastion/exact-file-releases/$RELEASE_EXISTING/backup.bin")" = "600"
cp "$PAYLOAD_TWO" "$APP_ROOT/.bastion/exact-file-releases/$RELEASE_EXISTING/payload.bin"
run_engine apply --root "$APP_ROOT" --release-id "$RELEASE_EXISTING" >/dev/null
chmod 644 "$TARGET"
printf '%s\n' '<?php // concurrent third state' > "$TARGET"
THIRD_SHA="$(sha256_of "$TARGET")"
if run_engine rollback --root "$APP_ROOT" --release-id "$RELEASE_EXISTING" \
  --expected-deployed-sha "$SHA_TWO" >/dev/null 2>&1; then
  echo "rollback overwrote a concurrent state" >&2
  exit 1
fi
test "$(sha256_of "$TARGET")" = "$THIRD_SHA"
chmod 644 "$TARGET"
cp "$PAYLOAD_TWO" "$TARGET"
chmod 444 "$TARGET"
run_engine rollback --root "$APP_ROOT" --release-id "$RELEASE_EXISTING" \
  --expected-deployed-sha "$SHA_TWO" >/dev/null
test "$(sha256_of "$TARGET")" = "$ORIGINAL_SHA"
test "$(file_mode "$TARGET")" = "640"
test "$(sha256_of "$SIBLING")" = "$SIBLING_SHA"

rm -f "$TARGET"
RELEASE_RACE="release-apply-race-20260820"
run_engine prepare --root "$APP_ROOT" --release-id "$RELEASE_RACE" \
  --target-relative "$TARGET_REL" --expected-before absent --intended-sha "$SHA_ONE" >/dev/null
cp "$PAYLOAD_ONE" "$APP_ROOT/.bastion/exact-file-releases/$RELEASE_RACE/payload.bin"
printf '%s\n' '<?php // appeared after prepare' > "$TARGET"
RACE_SHA="$(sha256_of "$TARGET")"
if run_engine apply --root "$APP_ROOT" --release-id "$RELEASE_RACE" >/dev/null 2>&1; then
  echo "apply ignored a compare-and-swap race" >&2
  exit 1
fi
test "$(sha256_of "$TARGET")" = "$RACE_SHA"

rm -f "$TARGET"
RELEASE_BAD_PAYLOAD="release-bad-payload-20260820"
run_engine prepare --root "$APP_ROOT" --release-id "$RELEASE_BAD_PAYLOAD" \
  --target-relative "$TARGET_REL" --expected-before absent --intended-sha "$SHA_ONE" >/dev/null
cp "$PAYLOAD_TWO" "$APP_ROOT/.bastion/exact-file-releases/$RELEASE_BAD_PAYLOAD/payload.bin"
if run_engine apply --root "$APP_ROOT" --release-id "$RELEASE_BAD_PAYLOAD" >/dev/null 2>&1; then
  echo "corrupt payload unexpectedly applied" >&2
  exit 1
fi
test ! -e "$TARGET"

ln -s "$PAYLOAD_ONE" "$TARGET"
if run_engine prepare --root "$APP_ROOT" --release-id release-symlink-20260820 \
  --target-relative "$TARGET_REL" --expected-before absent --intended-sha "$SHA_ONE" >/dev/null 2>&1; then
  echo "symlink target unexpectedly admitted" >&2
  exit 1
fi
rm -f "$TARGET"

printf '%s\n' '<?php function impactshop_backup_test(): bool { return true; }' > "$TARGET"
chmod 600 "$TARGET"
BACKUP_ORIGINAL_SHA="$(sha256_of "$TARGET")"
RELEASE_BAD_BACKUP="release-bad-backup-20260820"
run_engine prepare --root "$APP_ROOT" --release-id "$RELEASE_BAD_BACKUP" \
  --target-relative "$TARGET_REL" --expected-before "$BACKUP_ORIGINAL_SHA" --intended-sha "$SHA_ONE" >/dev/null
cp "$PAYLOAD_ONE" "$APP_ROOT/.bastion/exact-file-releases/$RELEASE_BAD_BACKUP/payload.bin"
run_engine apply --root "$APP_ROOT" --release-id "$RELEASE_BAD_BACKUP" >/dev/null
printf 'corrupt\n' > "$APP_ROOT/.bastion/exact-file-releases/$RELEASE_BAD_BACKUP/backup.bin"
if run_engine rollback --root "$APP_ROOT" --release-id "$RELEASE_BAD_BACKUP" \
  --expected-deployed-sha "$SHA_ONE" >/dev/null 2>&1; then
  echo "corrupt backup unexpectedly restored" >&2
  exit 1
fi
test "$(sha256_of "$TARGET")" = "$SHA_ONE"

INVALID_REL="wp-content/mu-plugins/invalid-runtime.php"
INVALID_TARGET="$APP_ROOT/$INVALID_REL"
INVALID_PAYLOAD="$TMP_DIR/invalid.php"
printf '%s\n' '<?php function broken syntax(' > "$INVALID_PAYLOAD"
INVALID_SHA="$(sha256_of "$INVALID_PAYLOAD")"
RELEASE_INVALID_PHP="release-invalid-php-20260820"
run_engine prepare --root "$APP_ROOT" --release-id "$RELEASE_INVALID_PHP" \
  --target-relative "$INVALID_REL" --expected-before absent --intended-sha "$INVALID_SHA" >/dev/null
cp "$INVALID_PAYLOAD" "$APP_ROOT/.bastion/exact-file-releases/$RELEASE_INVALID_PHP/payload.bin"
if run_engine apply --root "$APP_ROOT" --release-id "$RELEASE_INVALID_PHP" >/dev/null 2>&1; then
  echo "invalid staged PHP unexpectedly applied" >&2
  exit 1
fi
test ! -e "$INVALID_TARGET"

MANIFEST_REL="wp-content/mu-plugins/manifest-runtime.php"
MANIFEST_TARGET="$APP_ROOT/$MANIFEST_REL"
RELEASE_BAD_MANIFEST="release-bad-manifest-20260820"
run_engine prepare --root "$APP_ROOT" --release-id "$RELEASE_BAD_MANIFEST" \
  --target-relative "$MANIFEST_REL" --expected-before absent --intended-sha "$SHA_ONE" >/dev/null
BAD_MANIFEST_DIR="$APP_ROOT/.bastion/exact-file-releases/$RELEASE_BAD_MANIFEST"
cp "$PAYLOAD_ONE" "$BAD_MANIFEST_DIR/payload.bin"
printf '%s\n' '{}' > "$BAD_MANIFEST_DIR/manifest.json"
chmod 600 "$BAD_MANIFEST_DIR/manifest.json"
if run_engine apply --root "$APP_ROOT" --release-id "$RELEASE_BAD_MANIFEST" >/dev/null 2>&1; then
  echo "corrupt manifest unexpectedly applied" >&2
  exit 1
fi
test ! -e "$MANIFEST_TARGET"

MODE_REL="wp-content/mu-plugins/mode-runtime.php"
MODE_TARGET="$APP_ROOT/$MODE_REL"
RELEASE_BAD_MODE="release-bad-mode-20260820"
run_engine prepare --root "$APP_ROOT" --release-id "$RELEASE_BAD_MODE" \
  --target-relative "$MODE_REL" --expected-before absent --intended-sha "$SHA_ONE" >/dev/null
BAD_MODE_DIR="$APP_ROOT/.bastion/exact-file-releases/$RELEASE_BAD_MODE"
cp "$PAYLOAD_ONE" "$BAD_MODE_DIR/payload.bin"
chmod 755 "$BAD_MODE_DIR"
if run_engine apply --root "$APP_ROOT" --release-id "$RELEASE_BAD_MODE" >/dev/null 2>&1; then
  echo "world-readable release directory unexpectedly admitted" >&2
  exit 1
fi
test ! -e "$MODE_TARGET"

echo "impactshop exact release remote test: PASS"
