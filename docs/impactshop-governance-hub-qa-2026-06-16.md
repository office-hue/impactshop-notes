# ImpactShop Governance Hub QA

Datum: 2026-06-16
Statusz: docs-only QA check
Primary surface: `impactshop-notes` local governance / continuity
Secondary affected surfaces: local PR/review entrypoint, masterplan reference layer

## QA Questions

1. Egy uj operator egy perc alatt megtalalja-e a helyi governance-belépőt?
2. Vilagos-e, hogy a hub routing dokumentum, nem uj policyforras?
3. Kulon latszik-e a focused validation, a negative validation es a rollback minimum?
4. A continuity nyom alapjan egy kovetkezo session megerti-e, mi valtozott?
5. Nem gyengultek-e a protected/perimeter szabalyok a rovidites miatt?

## Pass Conditions

1. a hub a valos helyi anchorokra mutat
2. a hub nem allitja, hogy kivaltja az `AGENTS.md`-t vagy a `docs/pr-policy.md`-t
3. a focused validation minimum explicit
4. a negative validation minimum explicit
5. a rollback clarity minimum explicit
6. a masterplan/reference reteg visszakeresi a hubot es az auditot
7. a continuity (`notes.md`, `system-status-snapshot.md`) frissult

## Minor-Fix Examples

1. hianyzik egy anchor a rovid listabol
2. a rollback clarity implicit marad
3. a continuity nem nevezi meg a QA kort

## Blockers

1. a hub uj baseline-kent vagy policy-feluliraskent viselkedik
2. a docs-only szelet runtime igeretnek tunik
3. a protected/perimeter fail-closed szemlelet nem latszik egyertelmuen

## Result

Statusz: `pass`

Indok:

1. a hub, az audit es a continuity koherens lancot alkot
2. a helyi anchorok valosak es elerhetoek
3. a hianyzo validation/rollback minimumok most mar explicit modon latszanak
