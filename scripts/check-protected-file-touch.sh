#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "${ROOT_DIR}" ]]; then
  echo "[protected-touch] hiba: nem git repóban futsz." >&2
  exit 1
fi

MODEL_PATH="${ROOT_DIR}/docs/impactshop-protected-files.json"
MODE="local"
PUSH_RANGE="${SAFE_REPO_AUDIT_PUSH_RANGE:-}"

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
    *)
      echo "[protected-touch] ismeretlen opció: $1" >&2
      exit 1
      ;;
  esac
done

if [[ ! -f "$MODEL_PATH" ]]; then
  echo "[protected-touch] hiányzik a protected modell: $MODEL_PATH" >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
CHANGED_LIST="$TMP_DIR/changed-files.txt"

cd "$ROOT_DIR"

if [[ "$MODE" == "push" ]]; then
  if [[ -z "$PUSH_RANGE" ]]; then
    upstream_ref="${SAFE_REPO_AUDIT_UPSTREAM:-@{upstream}}"
    if git rev-parse --verify "$upstream_ref" >/dev/null 2>&1; then
      base="$(git merge-base HEAD "$upstream_ref")"
    else
      base="$(git hash-object -t tree /dev/null)"
    fi
    PUSH_RANGE="${base}..HEAD"
  fi
  git diff --name-only "$PUSH_RANGE" | sed '/^$/d' | sort -u > "$CHANGED_LIST"
elif [[ "$MODE" == "local" ]]; then
  # Pre-commit: CSAK a staged fájlokat ellenőrizzük — azt commitolod, azt védjük.
  git diff --cached --name-only | sed '/^$/d' | sort -u > "$CHANGED_LIST"

  # Figyelmeztetés: ha a working tree-ben (unstaged/untracked) is vannak védett fájlok.
  UNSTAGED_LIST="$TMP_DIR/unstaged-protected.txt"
  {
    git diff --name-only
    git ls-files --others --exclude-standard
  } | sed '/^$/d' | sort -u > "$TMP_DIR/wt-files.txt"

  if [[ -s "$TMP_DIR/wt-files.txt" ]] && [[ -f "$MODEL_PATH" ]]; then
    python3 - "$MODEL_PATH" "$TMP_DIR/wt-files.txt" <<'WTPY' > "$UNSTAGED_LIST"
import fnmatch, json, sys
model = json.load(open(sys.argv[1], "r", encoding="utf-8"))
files = [l.strip() for l in open(sys.argv[2], "r", encoding="utf-8") if l.strip()]
globs = model.get("protected_globs", [])
for f in files:
    if any(fnmatch.fnmatch(f, g) for g in globs):
        print(f)
WTPY
    wt_count="$(wc -l < "$UNSTAGED_LIST" | tr -d ' ')"
    if [[ "$wt_count" -gt 0 ]]; then
      echo "[protected-touch] ⚠ FIGYELMEZTETÉS: ${wt_count} védett fájl módosítva a working tree-ben (nem staged)." >&2
      echo "[protected-touch] Ezek NEM kerülnek be a commitba, de push előtt rendezni kell:" >&2
      head -10 "$UNSTAGED_LIST" | while IFS= read -r line; do echo "  ⚠ $line" >&2; done
      if [[ "$wt_count" -gt 10 ]]; then
        echo "  ... és még $((wt_count - 10)) további." >&2
      fi
    fi
  fi
else
  # Fallback (audit/scan mód): minden módosított fájl
  {
    git diff --name-only
    git diff --cached --name-only
    git ls-files --others --exclude-standard
  } | sed '/^$/d' | sort -u > "$CHANGED_LIST"
fi

if [[ ! -s "$CHANGED_LIST" ]]; then
  exit 0
fi

python3 - "$MODEL_PATH" "$CHANGED_LIST" <<'PY' >"$TMP_DIR/result.json"
import fnmatch
import json
import sys

model_path, changed_path = sys.argv[1], sys.argv[2]
model = json.load(open(model_path, "r", encoding="utf-8"))
changed = [line.strip() for line in open(changed_path, "r", encoding="utf-8") if line.strip()]

protected_globs = model.get("protected_globs", [])
additive_globs = model.get("additive_globs", [])
smoke_group_globs = model.get("smoke_group_globs", {})
required_smoke_tags = model.get("required_smoke_tags", {})

def matches(path, patterns):
    return any(fnmatch.fnmatch(path, p) for p in patterns)

protected = []
additive = []
other = []
smoke_groups = set()

for path in changed:
    if matches(path, additive_globs):
        additive.append(path)
    elif matches(path, protected_globs):
        protected.append(path)
        for group, patterns in smoke_group_globs.items():
            if matches(path, patterns):
                smoke_groups.add(group)
    else:
        other.append(path)

print(json.dumps({
    "protected": protected,
    "additive": additive,
    "other": other,
    "smoke_groups": sorted(smoke_groups),
    "required_tags": sorted({tag for group in smoke_groups for tag in required_smoke_tags.get(group, [])})
}, ensure_ascii=True))
PY

protected_count="$(python3 - "$TMP_DIR/result.json" <<'PY'
import json, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
print(len(data["protected"]))
PY
)"

if [[ "$protected_count" -eq 0 ]]; then
  exit 0
fi

if [[ "${BASTION_OVERRIDE:-0}" != "1" ]]; then
  echo "[protected-touch] Blocked: protected fájl módosítás override nélkül." >&2
  python3 - "$TMP_DIR/result.json" <<'PY' >&2
import json, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
print("[protected-touch] Érintett protected fájlok:")
for path in data["protected"]:
    print(f"  - {path}")
PY
  echo "[protected-touch] Szükséges: BASTION_OVERRIDE=1 + change record + rollback + smoke tag." >&2
  exit 2
fi

change_record="${BASTION_CHANGE_RECORD:-}"
if [[ -z "$change_record" ]]; then
  change_record="$(python3 - "$TMP_DIR/result.json" <<'PY'
import json, os, sys
data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
for path in data["protected"] + data["additive"] + data["other"]:
    if path.startswith("docs/protected-change-records/") and os.path.basename(path) != "TEMPLATE.md":
        print(path)
        break
PY
)"
fi

if [[ -z "$change_record" || ! -f "$ROOT_DIR/$change_record" ]]; then
  echo "[protected-touch] Blocked: hiányzik a protected change record." >&2
  echo "[protected-touch] Adj meg egy létező fájlt: BASTION_CHANGE_RECORD=docs/protected-change-records/<file>.md" >&2
  exit 3
fi

python3 - "$ROOT_DIR/$change_record" "$TMP_DIR/result.json" <<'PY'
import json
import os
import sys

record_path, result_path = sys.argv[1], sys.argv[2]
text = open(record_path, "r", encoding="utf-8").read().lower()
data = json.load(open(result_path, "r", encoding="utf-8"))

marker_groups = {
    "protected files touched": ["protected files touched", "touched files", "érintett fájl", "erintett fajl"],
    "rollback": ["rollback", "rollback plan", "rollback terv", "rollback leírás", "rollback leiras"],
    "smoke": ["smoke", "smoke checklist", "smoke scope", "smoke tag", "ellenőrzőlista", "ellenorzolista"],
}
missing = [label for label, variants in marker_groups.items() if not any(variant in text for variant in variants)]
if missing:
    print(f"[protected-touch] Blocked: a change record hiányos, hiányzik: {', '.join(missing)}", file=sys.stderr)
    sys.exit(6)

basenames = [os.path.basename(path).lower() for path in data["protected"]]
if basenames and not any(name in text for name in basenames):
    print("[protected-touch] Blocked: a change record nem hivatkozik az érintett protected fájlokra.", file=sys.stderr)
    sys.exit(6)
PY

if [[ -z "${BASTION_ROLLBACK_NOTE:-}" ]]; then
  echo "[protected-touch] Blocked: hiányzik a rollback leírás." >&2
  echo "[protected-touch] Add meg: BASTION_ROLLBACK_NOTE='rövid rollback terv'" >&2
  exit 4
fi

if [[ -z "${BASTION_SMOKE_TAGS:-}" ]]; then
  echo "[protected-touch] Blocked: hiányzik a smoke scope." >&2
  echo "[protected-touch] Add meg: BASTION_SMOKE_TAGS='tag1,tag2'" >&2
  exit 5
fi

python3 - "$TMP_DIR/result.json" "${BASTION_SMOKE_TAGS:-}" <<'PY'
import json
import sys

data = json.load(open(sys.argv[1], "r", encoding="utf-8"))
provided = {tag.strip() for tag in sys.argv[2].split(",") if tag.strip()}
required = set(data.get("required_tags", []))
missing = sorted(required - provided)
if missing:
    print("[protected-touch] Blocked: a smoke scope nem fedi le a kötelező tageket.", file=sys.stderr)
    print("[protected-touch] Hiányzó tagek:", ", ".join(missing), file=sys.stderr)
    if data.get("smoke_groups"):
      print("[protected-touch] Érintett smoke group-ok:", ", ".join(data["smoke_groups"]), file=sys.stderr)
    sys.exit(7)
PY

echo "[protected-touch] OK: protected módosítás override alatt engedélyezve."
echo "[protected-touch] Change record: ${change_record}"
echo "[protected-touch] Rollback: ${BASTION_ROLLBACK_NOTE}"
echo "[protected-touch] Smoke: ${BASTION_SMOKE_TAGS}"
