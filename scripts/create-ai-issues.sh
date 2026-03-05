#!/usr/bin/env bash
set -euo pipefail

REPO_NOTES="${REPO_NOTES:-office-hue/impactshop-notes}"
REPO_HUB="${REPO_HUB:-office-hue/impact_hub}"
DRY_RUN=0
SKIP_LABELS=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) DRY_RUN=1; shift ;;
    --skip-labels) SKIP_LABELS=1; shift ;;
    --notes-repo) REPO_NOTES="${2:-}"; shift 2 ;;
    --hub-repo) REPO_HUB="${2:-}"; shift 2 ;;
    -h|--help)
      cat <<'USAGE'
Használat:
  create-ai-issues.sh [--dry-run] [--skip-labels] [--notes-repo ORG/REPO] [--hub-repo ORG/REPO]
USAGE
      exit 0
      ;;
    *) echo "Ismeretlen opció: $1" >&2; exit 1 ;;
  esac
done

command -v gh >/dev/null 2>&1 || { echo "❌ gh CLI hiányzik" >&2; exit 1; }
if [[ "$DRY_RUN" -eq 0 ]]; then gh auth status >/dev/null; fi

run_cmd() {
  if [[ "$DRY_RUN" -eq 1 ]]; then
    printf 'DRY-RUN:'
    printf ' %q' "$@"
    printf '\n'
  else
    "$@"
  fi
}

create_label_set() {
  local repo="$1"
  local labels=(
    "p0:B60205:Critical"
    "p1:D93F0B:High priority"
    "security:D73A4A:Security"
    "tests:0E8A16:Tests"
    "ci:1D76DB:CI"
    "docs:0075CA:Docs"
    "ops:5319E7:Operations"
    "guard:6F42C1:Guards"
    "process:C2E0C6:Process"
    "qa:FBCA04:QA"
    "release:006B75:Release"
    "ai:8A2BE2:AI"
    "api:0052CC:API"
    "auth:C5DEF5:Auth"
    "analytics:F9D0C4:Analytics"
    "planning:EDEDED:Planning"
    "quality-gate:0B5FFF:Quality gate"
    "ai-workflow:5319E7:AI workflow"
  )
  for entry in "${labels[@]}"; do
    IFS=':' read -r name color desc <<<"$entry"
    run_cmd gh label create "$name" --repo "$repo" --color "$color" --description "$desc" --force >/dev/null
  done
}

issue_exists() {
  local repo="$1" title="$2"
  local existing
  existing="$(
    gh issue list --repo "$repo" --state all --limit 200 --search "\"$title\" in:title" --json title,url --jq '.[] | @tsv' \
      | awk -F'\t' -v t="$title" '$1==t{print $2; exit}' \
      || true
  )"
  [[ -n "$existing" ]] && { echo "↷ Kihagyva, már létezik: $title ($existing)"; return 0; }
  return 1
}

create_issue() {
  local repo="$1" title="$2" labels_csv="$3" body_file="$4"
  local -a label_args=()
  IFS=',' read -ra labels <<<"$labels_csv"
  for label in "${labels[@]}"; do label_args+=(--label "$label"); done

  if [[ "$DRY_RUN" -eq 0 ]] && issue_exists "$repo" "$title"; then
    return 0
  fi
  run_cmd gh issue create --repo "$repo" --title "$title" "${label_args[@]}" --body-file "$body_file"
}

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

if [[ "$SKIP_LABELS" -eq 0 ]]; then
  create_label_set "$REPO_NOTES"
  create_label_set "$REPO_HUB"
fi

cat > "$TMP_DIR/T-000.md" <<'BODY'
## Cél
Kanonikus parity-audit a top-level `ai-agent` és a kanonikus `impact_hub/ai-agent` között.

## Acceptance criteria
- [ ] Eltérő/hiányzó fájlok listája dokumentálva.
- [ ] Jóváhagyott migrációs lista elkészült (implementáció nélkül).
BODY
create_issue "$REPO_NOTES" "T-000 Kanonikus parity-audit (AI agent source of truth)" "p0,planning,ops" "$TMP_DIR/T-000.md"

cat > "$TMP_DIR/T-001.md" <<'BODY'
## Cél
Docs/runtime host és guard policy szinkron.

## Acceptance criteria
- [ ] Legacy host maradványok kezelve.
- [ ] Host/policy narratíva egységes.
BODY
create_issue "$REPO_NOTES" "T-001 Docs/runtime baseline szinkron" "p0,docs,ops" "$TMP_DIR/T-001.md"

cat > "$TMP_DIR/T-002.md" <<'BODY'
## Cél
API auth fail-closed egységesítés.

## Acceptance criteria
- [ ] Nincs fail-open endpoint API key hiányában.
- [ ] Egységes auth ellenőrzés a kritikus endpointokon.
- [ ] API key query/body policy szűkítve és dokumentálva.
BODY
create_issue "$REPO_HUB" "T-002 AI gateway auth fail-closed egységesítés" "p0,security,api" "$TMP_DIR/T-002.md"

cat > "$TMP_DIR/T-003.md" <<'BODY'
## Cél
Trusted role binding megerősítése és tesztelése.

## Acceptance criteria
- [ ] Aláírás nélküli role nem emel jogosultságot.
- [ ] Negatív + pozitív auth teszt kész.
- [ ] Signature séma dokumentálva.
BODY
create_issue "$REPO_HUB" "T-003 Trusted role binding hardening" "p0,security,auth" "$TMP_DIR/T-003.md"

cat > "$TMP_DIR/T-004.md" <<'BODY'
## Cél
Smoke tesztlánc javítása valós tesztfájlokra.

## Acceptance criteria
- [ ] `test:smoke` determinisztikusan lefut.
- [ ] CI-ben reprodukálható.
BODY
create_issue "$REPO_HUB" "T-004 Törött AI smoke tesztlánc javítás" "p0,tests,ci" "$TMP_DIR/T-004.md"

cat > "$TMP_DIR/T-006-notes.md" <<'BODY'
## Cél
Release gate és pre-push ellenőrzés validálása (notes).

## Acceptance criteria
- [ ] Gate futás dokumentált.
- [ ] Új clone install/verify lépés dokumentált.
BODY
create_issue "$REPO_NOTES" "T-006 Release gate validálás (notes)" "p0,release,guard" "$TMP_DIR/T-006-notes.md"

cat > "$TMP_DIR/T-006-hub.md" <<'BODY'
## Cél
Release gate és pre-push ellenőrzés validálása (hub).

## Acceptance criteria
- [ ] Gate futás dokumentált.
- [ ] Új clone install/verify lépés dokumentált.
BODY
create_issue "$REPO_HUB" "T-006 Release gate validálás (hub)" "p0,release,guard" "$TMP_DIR/T-006-hub.md"

cat > "$TMP_DIR/T-101.md" <<'BODY'
## Cél
Change-profile mátrix (fájlminta -> kötelező check).
BODY
create_issue "$REPO_NOTES" "T-101 Change-profile mátrix" "p1,process,qa" "$TMP_DIR/T-101.md"

cat > "$TMP_DIR/T-102.md" <<'BODY'
## Cél
Path-szintű instrukciók bevezetése, külön `legal-agent.instructions.md` fájllal.
BODY
create_issue "$REPO_NOTES" "T-102 Path instrukciók + legal-agent" "p1,docs,ai-workflow" "$TMP_DIR/T-102.md"

cat > "$TMP_DIR/T-103-notes.md" <<'BODY'
## Cél
PR template szigorítás (risk/evidence/rollback/scope).
BODY
create_issue "$REPO_NOTES" "T-103 PR template szigorítás (notes)" "p1,process,quality-gate" "$TMP_DIR/T-103-notes.md"

cat > "$TMP_DIR/T-103-hub.md" <<'BODY'
## Cél
PR template szigorítás (risk/evidence/rollback/scope).
BODY
create_issue "$REPO_HUB" "T-103 PR template szigorítás (hub)" "p1,process,quality-gate" "$TMP_DIR/T-103-hub.md"

cat > "$TMP_DIR/T-104.md" <<'BODY'
## Cél
Security baseline: secret scanning + push protection + dependabot (első kör).
BODY
create_issue "$REPO_HUB" "T-104 Security baseline (secret scanning/dependabot)" "p1,security,ci" "$TMP_DIR/T-104.md"

cat > "$TMP_DIR/T-105.md" <<'BODY'
## Cél
Release evidence automata gyűjtés.
BODY
create_issue "$REPO_NOTES" "T-105 Release evidence automata" "p1,release,ops" "$TMP_DIR/T-105.md"

cat > "$TMP_DIR/T-106.md" <<'BODY'
## Cél
Read-only dev advisor digest specifikáció.
BODY
create_issue "$REPO_NOTES" "T-106 Read-only advisor digest spec" "p1,analytics,planning" "$TMP_DIR/T-106.md"

echo "✅ Kész. Issue csomag feldolgozva."
