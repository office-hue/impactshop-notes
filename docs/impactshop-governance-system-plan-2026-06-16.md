# ImpactShop Governance System Plan

Datum: 2026-06-16
Statusz: canonical local governance hub
Scope: rovid, repo-helyi belepesi pont az `impactshop-notes` governance, review, continuity es protected-lane szabalyaihoz.

## Cel

Ez a dokumentum nem uj policy-t vezet be, hanem egyetlen helyi governance-hubkent osszefogja azokat a mar ervenyes kanonikus anchorokat, amelyek menten az `impactshop-notes` repo-ban a munka, review, continuity es protected-lane valtozas tortenik.

## Canonical Anchors

1. `AGENTS.md`
   - a repo-szintu munkaszabalyok, canonical policy-sorrend, continuity es protected-file minimumok
2. `docs/pr-policy.md`
   - branch/worktree fegyelem, PR/merge/deploy kapuk, protected-file es review szabalyok
3. `PR-EXIT-CHECKLIST.md`
   - merge elotti kotelezo exit-feltetelek rovid ellenorzolistaja
4. `docs/ai-assistant-canonical-policy.md`
   - a helyi AI-asszisztens es protected/perimeter policy reszletesebb referenciapontja
5. `docs/impact-challenge-canonical-baseline.md`
   - az Impact Challenge protected es regresszio-ervenyes referenciaallapota
6. `system-status-snapshot.md`
   - a repo aktualis mukodesi/allapotvaltozasi naploja
7. `notes.md`
   - session-szintu folyamatos dontes-, kockazat- es teendolog
8. `docs/bastion-guard-status.md`
   - protected/perimeter es guard-bovitesek idobelyeges evidencianaploja

## Recommended Reading Order

1. `AGENTS.md`
2. `docs/pr-policy.md`
3. `PR-EXIT-CHECKLIST.md`
4. az aktualis feladathoz kapcsolodo protected vagy baseline dokumentum
5. `system-status-snapshot.md`
6. `notes.md`

## Operating Model

1. Plan-first, szuk szelet
   - a legkisebb reviewzhato szeletet valaszd
   - a runtime, docs es deploy lane-eket ne keverd feleslegesen
2. Worktree/branch discipline
   - nem trivialis munka dedikalt worktree-ben menjen
   - a lokalis truth nem keverheto mas repo vagy mas worktree irasaival
3. Fail-closed protected lanes
   - protected vagy bastion-csatolt feluletnel a default nem a gyors modositas, hanem az explicit koherencia, kockazat es rollback
4. Continuity by default
   - valos allapotvaltozas eseten a docs + `system-status-snapshot.md` + `notes.md` folyamatosan egyutt frissuljon

## Push Gate

1. a governance, guard es policy lane valtozasai push elott fail-closed local system-plan sync gate alatt allnak;
2. ez azt jelenti, hogy a `docs/impactshop-governance-system-plan-2026-06-16.md` frissitese nem utolagos adminisztracio, hanem a helyi DEV folyamat resze.

## Decision Rules

### Docs-only slice

Docs-only szelet akkor a helyes valasztas, ha:

- a kovetkezo lepes governance, review vagy continuity hianyossagot zar
- runtime vagy protected kodhoz nem kell hozzanyulni
- a kovetkezo implementacios szeletet ezzel egyszerubb es koherensebb lesz vegrehajtani

### Protected/runtime slice

Protected vagy runtime szeletnel kotelezo:

- elozetes koherencia- es kockazatvizsgalat
- rollback vagy restore ut tiszta rogzitese
- a relevans smoke, QA vagy guard evidence rogzites
- manualis reviewer/user checklist, ha UI vagy route viselkedes erintett

## Continuity Minimum

Valos repo-allapot valtozasnal legalabb ezeket kell egyben nezni:

1. `docs/*.md`
2. `system-status-snapshot.md`
3. `notes.md` vagy `conversation-summaries/*`
4. `docs/bastion-guard-status.md`, ha uj vedett/perimeter-modul vagy uj guard-szabaly jelenik meg

## Scope Boundary

Ez a hub az `impactshop-notes` sajat helyi governance-rendjet fogja ossze. Nem valtja ki:

- a workspace-global policy-t
- a shared `ai-agent` assistant policy-t
- a protected baseline dokumentumokat
- a konkret feature-, deploy- vagy incident-runbookokat

## Natural Next Use

A dokumentum celja, hogy barmely kovetkezo helyi szeletnel legyen egy rovid, repo-beli entrypoint:

- uj munka inditasakor
- PR/review elott
- protected lane erintese elott
- continuity ellenorzeshez
