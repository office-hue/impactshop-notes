#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "${ROOT_DIR}" ]]; then
  echo "[commit-lane] hiba: nem git repóban futsz." >&2
  exit 1
fi

MODEL_PATH="${ROOT_DIR}/docs/impactshop-protected-files.json"
MODE="local"
PUSH_RANGE="${SAFE_REPO_AUDIT_PUSH_RANGE:-}"

resolve_push_base() {
  local upstream_ref="${SAFE_REPO_AUDIT_UPSTREAM:-@{upstream}}"
  local candidate=""
  if git rev-parse --verify "$upstream_ref" >/dev/null 2>&1; then
    git merge-base HEAD "$upstream_ref"
    return 0
  fi

  for candidate in "origin/HEAD" "origin/main" "origin/master" "main" "master"; do
    if git rev-parse --verify "$candidate" >/dev/null 2>&1; then
      git merge-base HEAD "$candidate"
      return 0
    fi
  done

  if git rev-parse --verify HEAD^ >/dev/null 2>&1; then
    git rev-parse --verify HEAD^
    return 0
  fi

  git hash-object -t tree /dev/null
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --mode)
      MODE="${2:-local}"
      shift 2
      ;;
    --push-range)
      PUSH_RANGE="${2:-}"
      shift 2
      ;;
    -h|--help)
      echo "Usage: check-commit-lane.sh [--mode local|push] [--push-range <range>]"
      exit 0
      ;;
    *)
      echo "[commit-lane] ismeretlen opció: $1" >&2
      exit 1
      ;;
  esac
done

if [[ ! -f "$MODEL_PATH" ]]; then
  echo "[commit-lane] hiányzik a protected modell: $MODEL_PATH" >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
cd "$ROOT_DIR"

classify_paths() {
  local label="$1"
  local input_file="$2"
  python3 - "$MODEL_PATH" "$label" "$input_file" <<'PY'
import fnmatch
import json
import sys

model_path, label, input_path = sys.argv[1], sys.argv[2], sys.argv[3]
model = json.load(open(model_path, "r", encoding="utf-8"))
paths = [line.strip() for line in open(input_path, "r", encoding="utf-8") if line.strip()]

protected_globs = model.get("protected_globs", [])
additive_globs = model.get("additive_globs", [])

ops_patterns = [
    ".deploy.production.env",
    ".deploy.staging.env",
    ".github/**",
    "AGENTS.md",
    "PR-EXIT-CHECKLIST.md",
    "bin/**",
    "scripts/**",
    "docs/pr-policy.md",
    "docs/ai-assistant-canonical-policy.md",
    "docs/bastion-*.md",
    "docs/impactshop-guard-*.md",
    "docs/impactshop-guard-*.json",
    "docs/impactshop-guard-*.sha256",
]

docs_patterns = [
    "docs/**",
    "notes.md",
    "conversation-summaries/**",
    "system-status-snapshot.md",
    ".codex/**",
]

def matches(path, patterns):
    return any(fnmatch.fnmatch(path, p) for p in patterns)

lane_map = {}

for path in paths:
    if path.startswith("docs/protected-change-records/"):
        lane = "docs"
    elif matches(path, additive_globs):
        lane = "additive"
    elif matches(path, protected_globs):
        lane = "protected"
    elif matches(path, ops_patterns):
        lane = "ops"
    elif matches(path, docs_patterns):
        lane = "docs"
    elif path.startswith("wp-content/") or path.startswith("mu-plugins/") or path.startswith("apps/") or path.startswith("src/") or path.startswith("lib/") or path.startswith("services/") or path.startswith("packages/") or path.startswith("config/") or path.startswith("types/"):
        lane = "app-content"
    else:
        lane = "misc"
    lane_map.setdefault(lane, []).append(path)

primary_lanes = sorted([lane for lane in lane_map.keys() if lane not in {"docs", "misc"}])

print(json.dumps({
    "label": label,
    "lane_map": lane_map,
    "primary_lanes": primary_lanes
}, ensure_ascii=True))
PY
}

if [[ "$MODE" == "push" ]]; then
  if [[ -z "$PUSH_RANGE" ]]; then
    base="$(resolve_push_base)"
    PUSH_RANGE="${base}..HEAD"
  fi

  COMMITS_FILE="$TMP_DIR/commits.txt"
  git rev-list --reverse "$PUSH_RANGE" > "$COMMITS_FILE"
  [[ -s "$COMMITS_FILE" ]] || exit 0

  while IFS= read -r commit; do
    [[ -n "$commit" ]] || continue
    FILES="$TMP_DIR/${commit}.files"
    git diff-tree --no-commit-id --name-only -r "$commit" | sed '/^$/d' | sort -u > "$FILES"
    [[ -s "$FILES" ]] || continue
    RESULT="$TMP_DIR/${commit}.json"
    classify_paths "commit:${commit}" "$FILES" > "$RESULT"
    lane_count="$(python3 - "$RESULT" <<'PY'
import json, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
print(len(data["primary_lanes"]))
PY
)"
    if [[ "$lane_count" -gt 1 ]]; then
      python3 - "$RESULT" "$commit" <<'PY' >&2
import json, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
commit = sys.argv[2]
print(f"[commit-lane] Blocked: a push-range commit több elsődleges lane-t kever ({commit}).")
print(f"[commit-lane] Primary lanes: {', '.join(data['primary_lanes'])}")
for lane, paths in sorted(data["lane_map"].items()):
    if lane in {"docs", "misc"}:
        continue
    print(f"[commit-lane] {lane}:")
    for path in paths[:8]:
        print(f"  - {path}")
print("[commit-lane] Külön commitokba bontsd: protected / additive / app-content / ops.")
PY
      exit 2
    fi
  done < "$COMMITS_FILE"

  echo "[commit-lane] OK: push-range commit lane check passed."
  exit 0
fi

STAGED_FILE="$TMP_DIR/staged.txt"
UNSTAGED_FILE="$TMP_DIR/unstaged.txt"
git diff --cached --name-only | sed '/^$/d' | sort -u > "$STAGED_FILE"
{
  git diff --name-only
  git ls-files --others --exclude-standard
} | sed '/^$/d' | sort -u > "$UNSTAGED_FILE"

if [[ ! -s "$STAGED_FILE" ]]; then
  echo "[commit-lane] OK: nincs staged változás."
  exit 0
fi

STAGED_RESULT="$TMP_DIR/staged.json"
classify_paths "staged" "$STAGED_FILE" > "$STAGED_RESULT"

staged_lane_count="$(python3 - "$STAGED_RESULT" <<'PY'
import json, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
print(len(data["primary_lanes"]))
PY
)"

if [[ "$staged_lane_count" -gt 1 ]]; then
  python3 - "$STAGED_RESULT" <<'PY' >&2
import json, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
print("[commit-lane] Blocked: a staged commit több elsődleges lane-t kever.")
print(f"[commit-lane] Primary lanes: {', '.join(data['primary_lanes'])}")
for lane, paths in sorted(data["lane_map"].items()):
    if lane in {"docs", "misc"}:
        continue
    print(f"[commit-lane] {lane}:")
    for path in paths[:8]:
        print(f"  - {path}")
print("[commit-lane] Egy commit = egy elsődleges lane. A docs csak kísérő lehet.")
PY
  exit 2
fi

if [[ -s "$UNSTAGED_FILE" ]]; then
  UNSTAGED_RESULT="$TMP_DIR/unstaged.json"
  classify_paths "unstaged+untracked" "$UNSTAGED_FILE" > "$UNSTAGED_RESULT"
  python3 - "$STAGED_RESULT" "$UNSTAGED_RESULT" <<'PY' > "$TMP_DIR/cross.json"
import json, sys
staged = json.load(open(sys.argv[1], "r", encoding="utf-8"))
unstaged = json.load(open(sys.argv[2], "r", encoding="utf-8"))
staged_primary = [lane for lane in staged["primary_lanes"] if lane not in {"docs", "misc"}]
staged_main = staged_primary[0] if len(staged_primary) == 1 else ""
cross = []
for lane in unstaged["primary_lanes"]:
    if lane not in {"docs", "misc"} and lane != staged_main:
        cross.append(lane)
print(json.dumps({
    "staged_main": staged_main,
    "cross": sorted(set(cross)),
    "unstaged_lane_map": unstaged["lane_map"]
}, ensure_ascii=True))
PY
  cross_count="$(python3 - "$TMP_DIR/cross.json" <<'PY'
import json, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
print(len(data["cross"]))
PY
)"
  if [[ "$cross_count" -gt 0 ]]; then
    python3 - "$TMP_DIR/cross.json" <<'PY' >&2
import json, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
print("[commit-lane] Blocked: a staged commit mellett másik elsődleges lane is nyitva van az unstaged/untracked állapotban.")
print(f"[commit-lane] Staged lane: {data['staged_main'] or 'none'}")
print(f"[commit-lane] Ütköző lane-ek: {', '.join(data['cross'])}")
for lane in data["cross"]:
    print(f"[commit-lane] {lane}:")
    for path in data["unstaged_lane_map"].get(lane, [])[:8]:
        print(f"  - {path}")
print("[commit-lane] Előbb tisztítsd szét: stash, külön worktree vagy külön commit.")
PY
    exit 3
  fi
fi

python3 - "$STAGED_RESULT" <<'PY'
import json, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
print(f"[commit-lane] {data['label']} lane summary:")
for lane, paths in sorted(data["lane_map"].items()):
    print(f"  - {lane}: {len(paths)} file")
    for path in paths[:8]:
        print(f"      * {path}")
    if len(paths) > 8:
        print(f"      * ... (+{len(paths)-8} more)")
PY
echo "[commit-lane] OK: staged commit lane check passed."
