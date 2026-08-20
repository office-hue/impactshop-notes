#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

TEST_REPO="$TMP_DIR/repo"
mkdir -p "$TEST_REPO/bin" "$TEST_REPO/docs"
cp "$ROOT_DIR/bin/impactshop-guard-preflight.sh" "$TEST_REPO/bin/impactshop-guard-preflight.sh"
chmod +x "$TEST_REPO/bin/impactshop-guard-preflight.sh"
printf '%s\n' 'protected' > "$TEST_REPO/protected.txt"

git -C "$TEST_REPO" init -q -b main
git -C "$TEST_REPO" config user.name "ImpactShop Guard Test"
git -C "$TEST_REPO" config user.email "guard-test@example.invalid"
git -C "$TEST_REPO" remote add origin https://github.com/office-hue/impactshop-preflight-test.git

python3 - "$TEST_REPO" <<'PY'
import json
import os
import sys

root = os.path.realpath(sys.argv[1])
config = {
    "repo": {
        "root": root,
        "remote": "https://github.com/office-hue/impactshop-preflight-test.git",
        "branch": "main",
    },
    "protected_files": [
        {
            "path": "protected.txt",
            "owner_repo": "https://github.com/office-hue/impactshop-preflight-test.git",
            "owner_root": root,
            "owner_branch": "main",
        }
    ],
}
with open(os.path.join(root, "docs", "impactshop-guard-config.json"), "w", encoding="utf-8") as handle:
    json.dump(config, handle)
PY

git -C "$TEST_REPO" add .
git -C "$TEST_REPO" commit -qm "initial"
printf '%s\n' 'second' > "$TEST_REPO/second.txt"
git -C "$TEST_REPO" add second.txt
git -C "$TEST_REPO" commit -qm "second"
MERGED_MAIN="$(git -C "$TEST_REPO" rev-parse HEAD)"
git -C "$TEST_REPO" update-ref refs/remotes/origin/main "$MERGED_MAIN"

git -C "$TEST_REPO" switch -qc feature/test
if IMPACTSHOP_EXACT_RELEASE=1 bash "$TEST_REPO/bin/impactshop-guard-preflight.sh" >/dev/null 2>&1; then
  echo "preflight detached test: named feature branch unexpectedly admitted" >&2
  exit 1
fi

git -C "$TEST_REPO" checkout -q --detach "$MERGED_MAIN"
if bash "$TEST_REPO/bin/impactshop-guard-preflight.sh" >/dev/null 2>&1; then
  echo "preflight detached test: detached release admitted without explicit opt-in" >&2
  exit 1
fi

accepted_output="$(IMPACTSHOP_EXACT_RELEASE=1 bash "$TEST_REPO/bin/impactshop-guard-preflight.sh")"
grep -Fq 'detached exact release at origin/main' <<< "$accepted_output"

git -C "$TEST_REPO" checkout -q --detach HEAD~1
if IMPACTSHOP_EXACT_RELEASE=1 bash "$TEST_REPO/bin/impactshop-guard-preflight.sh" >/dev/null 2>&1; then
  echo "preflight detached test: stale detached commit unexpectedly admitted" >&2
  exit 1
fi

echo "impactshop guard preflight detached test: PASS"
