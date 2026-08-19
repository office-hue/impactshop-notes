#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

FAKE_BIN="$TMP_DIR/bin"
SOURCE_DIR="$TMP_DIR/source"
ENV_FILE="$TMP_DIR/deploy.env"
SSH_LOG="$TMP_DIR/ssh.log"
RSYNC_LOG="$TMP_DIR/rsync.log"
mkdir -p "$FAKE_BIN" "$SOURCE_DIR"
: > "$SOURCE_DIR/runtime.php"
: > "$SSH_LOG"
: > "$RSYNC_LOG"

cat > "$FAKE_BIN/ssh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$FAKE_SSH_LOG"
command_line="$*"

if [[ "$command_line" == *"python3 - "* ]]; then
  while IFS= read -r _line; do :; done
  printf '%s\n' "${FAKE_MANIFEST_STATUS:-ok_manifest:/remote/site/.bastion/protected-hashes.json:142}"
  exit 0
fi
if [[ "$command_line" == *"test -d "* ]]; then
  [[ "${FAKE_MISSING_TARGET:-0}" != "1" ]]
  exit
fi
if [[ "$command_line" == *"rsync --version"* ]]; then
  echo "rsync version 3.2.7 protocol version 31"
  exit 0
fi
exit 0
SH

cat > "$FAKE_BIN/rsync" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$FAKE_RSYNC_LOG"
exit 0
SH
chmod +x "$FAKE_BIN/ssh" "$FAKE_BIN/rsync"

cat > "$ENV_FILE" <<EOF
SSH_HOST=fake.example
REMOTE_WP_CONTENT=/remote/site/wp-content
REMOTE_WP_PATH=/remote/site
RSYNC_OPTS="-az --delete"
MAPPINGS="$SOURCE_DIR -> mu-plugins"
EOF

run_dry() {
  env \
    PATH="$FAKE_BIN:$PATH" \
    FAKE_SSH_LOG="$SSH_LOG" \
    FAKE_RSYNC_LOG="$RSYNC_LOG" \
    FAKE_MANIFEST_STATUS="${FAKE_MANIFEST_STATUS:-}" \
    FAKE_MISSING_TARGET="${FAKE_MISSING_TARGET:-0}" \
    DRY_RUN=1 \
    SKIP_PREFLIGHT=1 \
    bash "$ROOT_DIR/bin/deploy-wpcontent-map.sh" "--env=$ENV_FILE"
}

success_output="$(FAKE_MANIFEST_STATUS="ok_manifest:/remote/site/.bastion/protected-hashes.json:142" run_dry)"
grep -q "Bastion manifest ellenőrzés OK" <<< "$success_output"
grep -q "könyvtárlétrehozás kihagyva" <<< "$success_output"
grep -q "WP maintenance és post-deploy smoke kihagyva" <<< "$success_output"
grep -q -- "-n" "$RSYNC_LOG"
grep -q -- "--itemize-changes" "$RSYNC_LOG"
if grep -Eq "mkdir|wp --path|cache flush|cron event|rewrite flush" "$SSH_LOG"; then
  echo "deploy mapping bastion test: dry-run remote mutation detected" >&2
  exit 1
fi

: > "$SSH_LOG"
: > "$RSYNC_LOG"
if invalid_output="$(FAKE_MANIFEST_STATUS="invalid_schema:/remote/site/.bastion/protected-hashes.json" run_dry 2>&1)"; then
  echo "deploy mapping bastion test: invalid manifest was accepted" >&2
  exit 1
fi
grep -q "Bastion manifest elutasítva" <<< "$invalid_output"
[[ ! -s "$RSYNC_LOG" ]]

: > "$SSH_LOG"
: > "$RSYNC_LOG"
if missing_output="$(FAKE_MISSING_TARGET=1 run_dry 2>&1)"; then
  echo "deploy mapping bastion test: missing target was accepted" >&2
  exit 1
fi
grep -q "remote wp-content cél nem létezik" <<< "$missing_output"
[[ ! -s "$RSYNC_LOG" ]]
if grep -Eq "mkdir|wp --path|cache flush|cron event|rewrite flush" "$SSH_LOG"; then
  echo "deploy mapping bastion test: blocked dry-run attempted mutation" >&2
  exit 1
fi

grep -q 'root / ".bastion" / "protected-hashes.json"' "$ROOT_DIR/bin/deploy-wpcontent-map.sh"
grep -q 'missing_manifest)' "$ROOT_DIR/bin/deploy-wpcontent-map.sh"

echo "deploy wp-content map bastion test: PASS"
