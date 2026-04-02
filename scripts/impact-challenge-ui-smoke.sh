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
import urllib.request

url = sys.argv[1]
html = urllib.request.urlopen(url).read().decode("utf-8", "replace")

checks = [
    (
        bool(re.search(r"ads-watch-floating-tabs\{display:\s*none!important;\}", html)),
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
