#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 <impact-challenge-url>" >&2
  exit 2
fi

url="$1"
if [[ "$url" != *\?* && "$url" != */ ]]; then
  url="${url}/"
fi

python3 - "$url" <<'PY'
import re
import sys
import urllib.error
import urllib.request

url = sys.argv[1]
try:
    with urllib.request.urlopen(url, timeout=10) as response:
        html = response.read().decode("utf-8", "replace")
except urllib.error.HTTPError as exc:
    print(
        f"❌ Impact Challenge UI smoke FAILED: HTTP hiba ({exc.code}) a(z) {url} elérésekor: {exc.reason}",
        file=sys.stderr,
    )
    raise SystemExit(1)
except urllib.error.URLError as exc:
    print(
        f"❌ Impact Challenge UI smoke FAILED: hálózati hiba a(z) {url} elérésekor: {exc.reason}",
        file=sys.stderr,
    )
    raise SystemExit(1)

checks = [
    (
        bool(
            re.search(
                r"ads-watch-floating-tabs\s*\{\s*display\s*:\s*none\s*!important\s*;",
                html,
            )
        ),
        "legacy floating tabs hidden rule",
    ),
    (
        'class="sharity-action-bar"' in html,
        "8-icon action bar shell",
    ),
]

required_bars = ["video", "tasks", "shop", "donate", "account", "ngo", "message", "stats"]
for bar in required_bars:
    checks.append((f'data-bar="{bar}"' in html, f'data-bar=\"{bar}\"'))

failed = [label for ok, label in checks if not ok]
if failed:
    for label in failed:
        print(f"❌ Impact Challenge UI smoke FAILED: hiányzik -> {label}", file=sys.stderr)
    raise SystemExit(1)

print("✅ Impact Challenge UI smoke OK")
PY
