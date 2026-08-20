#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d)"
REPO_TMP_DIR="$(mktemp -d "$ROOT_DIR/tests/.exact-file-test.XXXXXX")"
REPO_TMP_REL="${REPO_TMP_DIR#"$ROOT_DIR/"}"
trap 'rm -rf "$TMP_DIR" "$REPO_TMP_DIR"' EXIT

FAKE_BIN="$TMP_DIR/bin"
ENV_FILE="$TMP_DIR/deploy.env"
SSH_LOG="$TMP_DIR/ssh.log"
RSYNC_LOG="$TMP_DIR/rsync.log"
mkdir -p "$FAKE_BIN"
: > "$SSH_LOG"
: > "$RSYNC_LOG"

cat > "$FAKE_BIN/ssh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$FAKE_SSH_LOG"
command_line="$*"

if [[ "$command_line" == *"python3 - prepare "* ]]; then
  while IFS= read -r _line; do :; done
  printf '{"ok":true,"action":"prepare","phase":"prepared","releaseId":"%s","intendedSha256":"%s"}\n' \
    "$FAKE_RELEASE_ID" "$FAKE_LOCAL_SHA"
  exit 0
fi
if [[ "$command_line" == *"python3 - apply "* ]]; then
  while IFS= read -r _line; do :; done
  printf '{"ok":true,"action":"apply","phase":"deployed","releaseId":"%s","sha256":"%s","mode":"0444"}\n' \
    "$FAKE_RELEASE_ID" "$FAKE_LOCAL_SHA"
  exit 0
fi
if [[ "$command_line" == *"public_html/index.php"* ]]; then
  while IFS= read -r _line; do :; done
  echo "ok"
  exit 0
fi
if [[ "$command_line" == *"python3 - "* ]]; then
  while IFS= read -r _line; do :; done
  echo "ok_manifest:/remote/site/.bastion/protected-hashes.json:142"
  exit 0
fi
if [[ "$command_line" == *"test -d "* ]]; then
  exit 0
fi
if [[ "$command_line" == *"rsync --version"* ]]; then
  echo "rsync version 3.2.7 protocol version 31"
  exit 0
fi
exit 0
SH

cat > "$FAKE_BIN/git" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
case "$*" in
  "rev-parse --abbrev-ref HEAD") echo "${FAKE_GIT_BRANCH:-main}" ;;
  "status --porcelain=v1 --untracked-files=normal") exit 0 ;;
  "rev-parse HEAD"|"rev-parse --verify refs/remotes/origin/main") echo 0123456789abcdef0123456789abcdef01234567 ;;
  "rev-parse --short=12 HEAD") echo 0123456789ab ;;
  *) echo "unexpected fake git invocation: $*" >&2; exit 1 ;;
esac
SH

cat > "$FAKE_BIN/rsync" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$FAKE_RSYNC_LOG"
exit 0
SH
chmod +x "$FAKE_BIN/ssh" "$FAKE_BIN/rsync" "$FAKE_BIN/git"

write_env() {
  local mappings="$1"
  cat > "$ENV_FILE" <<EOF
SSH_HOST=fake.example
REMOTE_WP_CONTENT=/remote/site/wp-content
REMOTE_WP_PATH=/remote/site
DEPLOY_ENVIRONMENT=production
RSYNC_OPTS="-az --delete --delete-before --delete-during --delete-after --delete-excluded --delete-missing-args --info=progress2"
MAPPINGS='$mappings'
EOF
}

reset_logs() {
  : > "$SSH_LOG"
  : > "$RSYNC_LOG"
}

run_deploy() {
  env \
    PATH="$FAKE_BIN:$PATH" \
    FAKE_SSH_LOG="$SSH_LOG" \
    FAKE_RSYNC_LOG="$RSYNC_LOG" \
    SKIP_PREFLIGHT=1 \
    "$@" \
    bash "$ROOT_DIR/bin/deploy-wpcontent-map.sh" "--env=$ENV_FILE"
}

assert_no_network() {
  [[ ! -s "$SSH_LOG" ]]
  [[ ! -s "$RSYNC_LOG" ]]
}

cd "$ROOT_DIR"

write_env 'tests -> mu-plugins'
reset_logs
if no_scope_output="$(run_deploy 2>&1)"; then
  echo "exact-file deploy test: real unscoped production was accepted" >&2
  exit 1
fi
grep -q "production deploy csak IMPACTSHOP_DEPLOY_FILE" <<< "$no_scope_output"
assert_no_network

reset_logs
if real_exact_output="$(run_deploy \
  IMPACTSHOP_DEPLOY_FILE=tests/deploy-wpcontent-map-exact-file.test.sh 2>&1)"; then
  echo "exact-file deploy test: real exact production was accepted without backup/CAS" >&2
  exit 1
fi
grep -q "IMPACTSHOP_EXACT_RELEASE=1 szükséges" <<< "$real_exact_output"
assert_no_network

reset_logs
if missing_expected_output="$(run_deploy \
  IMPACTSHOP_EXACT_RELEASE=1 \
  IMPACTSHOP_DEPLOY_FILE=tests/deploy-wpcontent-map-exact-file.test.sh 2>&1)"; then
  echo "exact-file deploy test: release without expected remote state was accepted" >&2
  exit 1
fi
grep -q "IMPACTSHOP_EXPECT_REMOTE_SHA256" <<< "$missing_expected_output"
assert_no_network

write_env 'tests -> mu-plugins
wp-content/plugins/impact-short codes-legacy -> plugins/impact-short codes-legacy'
reset_logs
if malformed_output="$(run_deploy DRY_RUN=1 2>&1)"; then
  echo "exact-file deploy test: unsafe mapping was accepted" >&2
  exit 1
fi
grep -q "Nem biztonságos mapping source" <<< "$malformed_output"
assert_no_network

write_env 'tests -> mu-plugins'
for invalid_scope in \
  'tests' \
  'tests/does-not-exist.php' \
  'bin/deploy-wpcontent-map.sh' \
  '../outside.php'; do
  reset_logs
  if invalid_output="$(run_deploy DRY_RUN=1 IMPACTSHOP_DEPLOY_FILE="$invalid_scope" 2>&1)"; then
    echo "exact-file deploy test: invalid scope was accepted: $invalid_scope" >&2
    exit 1
  fi
  assert_no_network
done

ln -s "$ROOT_DIR/tests/deploy-wpcontent-map-exact-file.test.sh" \
  "$REPO_TMP_DIR/link.php"
write_env "$REPO_TMP_REL -> mu-plugins"
reset_logs
if symlink_output="$(run_deploy DRY_RUN=1 IMPACTSHOP_DEPLOY_FILE="$REPO_TMP_REL/link.php" 2>&1)"; then
  echo "exact-file deploy test: symlink scope was accepted" >&2
  exit 1
fi
grep -q "nem symlinkelt fájl" <<< "$symlink_output"
assert_no_network

write_env 'tests -> mu-plugins'
reset_logs
valid_output="$(run_deploy \
  DRY_RUN=1 \
  IMPACTSHOP_DEPLOY_FILE=tests/deploy-wpcontent-map-exact-file.test.sh)"

grep -q "EXACT FILE: tests/deploy-wpcontent-map-exact-file.test.sh" <<< "$valid_output"
grep -q "WP maintenance és post-deploy smoke kihagyva" <<< "$valid_output"
grep -q -- "--checksum" "$RSYNC_LOG"
grep -q -- "-n" "$RSYNC_LOG"
grep -q -- "--itemize-changes" "$RSYNC_LOG"
if grep -q -- "--delete" "$RSYNC_LOG"; then
  echo "exact-file deploy test: delete option reached scoped rsync" >&2
  exit 1
fi
grep -q "tests/deploy-wpcontent-map-exact-file.test.sh" "$RSYNC_LOG"
grep -q "fake.example:/remote/site/wp-content/mu-plugins/deploy-wpcontent-map-exact-file.test.sh" "$RSYNC_LOG"
if grep -q "deploy-wpcontent-map-bastion.test.sh" "$RSYNC_LOG"; then
  echo "exact-file deploy test: sibling source leaked into rsync" >&2
  exit 1
fi
if grep -Eq "mkdir|wp --path|cache flush|cron event|rewrite flush" "$SSH_LOG"; then
  echo "exact-file deploy test: dry-run remote mutation detected" >&2
  exit 1
fi

reset_logs
FAKE_RELEASE_ID="release-test-20260820"
RELEASE_REPO="$REPO_TMP_DIR/release-repo"
mkdir -p "$RELEASE_REPO/bin" "$RELEASE_REPO/scripts" "$RELEASE_REPO/tests"
cp "$ROOT_DIR/bin/deploy-wpcontent-map.sh" "$RELEASE_REPO/bin/deploy-wpcontent-map.sh"
cp "$ROOT_DIR/scripts/impactshop-exact-release-remote.py" "$RELEASE_REPO/scripts/impactshop-exact-release-remote.py"
cp "$ROOT_DIR/tests/deploy-wpcontent-map-exact-file.test.sh" "$RELEASE_REPO/tests/release-source.php"
cat > "$RELEASE_REPO/.deploy.production.env" <<'EOF'
SSH_HOST=fake.example
REMOTE_WP_CONTENT=/home/sharityh/app/wp-content
REMOTE_WP_PATH=/home/sharityh/app
DEPLOY_ENVIRONMENT=production
RSYNC_OPTS="-az --delete --delete-before --info=progress2"
MAPPINGS='tests -> mu-plugins'
EOF
FAKE_LOCAL_SHA="$(shasum -a 256 "$RELEASE_REPO/tests/release-source.php" | awk '{print $1}')"
release_output="$(env \
  PATH="$FAKE_BIN:$PATH" \
  FAKE_SSH_LOG="$SSH_LOG" \
  FAKE_RSYNC_LOG="$RSYNC_LOG" \
  FAKE_RELEASE_ID="$FAKE_RELEASE_ID" \
  FAKE_LOCAL_SHA="$FAKE_LOCAL_SHA" \
  FAKE_GIT_BRANCH=HEAD \
  SKIP_PREFLIGHT=1 \
  IMPACTSHOP_EXACT_RELEASE=1 \
  IMPACTSHOP_EXPECT_REMOTE_SHA256=absent \
  IMPACTSHOP_RELEASE_ID="$FAKE_RELEASE_ID" \
  IMPACTSHOP_DEPLOY_FILE=tests/release-source.php \
  bash "$RELEASE_REPO/bin/deploy-wpcontent-map.sh" --production)"

grep -q "Exact release deployed: id=$FAKE_RELEASE_ID sha256=$FAKE_LOCAL_SHA mode=0444" <<< "$release_output"
grep -q -- "--release-id=$FAKE_RELEASE_ID --expected-deployed-sha=$FAKE_LOCAL_SHA" <<< "$release_output"
grep -q "python3 - prepare --root /home/sharityh/app --release-id $FAKE_RELEASE_ID" "$SSH_LOG"
grep -q "python3 - apply --root /home/sharityh/app --release-id $FAKE_RELEASE_ID" "$SSH_LOG"
grep -q "tests/release-source.php" "$RSYNC_LOG"
grep -q "fake.example:/home/sharityh/app/.bastion/exact-file-releases/$FAKE_RELEASE_ID/payload.bin" "$RSYNC_LOG"
if grep -q -- "--delete" "$RSYNC_LOG"; then
  echo "exact-file deploy test: delete option reached real release payload upload" >&2
  exit 1
fi
if grep -q "deploy-wpcontent-map-bastion.test.sh" "$RSYNC_LOG"; then
  echo "exact-file deploy test: sibling leaked into real release payload upload" >&2
  exit 1
fi

echo "deploy wp-content exact-file test: PASS"
