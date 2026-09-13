# Sharity NGO-preferencia v4.4 — G2 impactshop-notes governance plan

Plan ID: `sharity-ngo-preference-v4-4-g2-20260913`  
Umbrella Plan ID: `sharity-ngo-preference-v4-4-20260913`  
Status: `approved-for-luna`

<!-- DEV-DELIVERY-V2-MANIFEST
{
  "schemaVersion": 2,
  "planId": "sharity-ngo-preference-v4-4-g2-20260913",
  "repo": "impactshop-notes",
  "changeImpact": "protected",
  "riskTier": "high",
  "testProfile": "protected-g2-governance",
  "releaseProfile": "source-only",
  "session": {
    "branch": "governance/sharity-ngo-preference-v4-4-g2-20260913",
    "baseRef": "origin/main",
    "baseCommit": "f18bb61e09a5e0dade3e5b5cae9b5a84865f4a2c",
    "headCommit": "f18bb61e09a5e0dade3e5b5cae9b5a84865f4a2c",
    "candidateTree": "private-evidence"
  },
  "changeAllowlist": [
    "config/dev-v4/activation-policy.v1.json",
    "config/dev-v4/repo-capabilities.v2.json",
    "scripts/dev-v4-admission.mjs",
    "scripts/worktree-task-start.sh",
    "tests/dev-v4-admission.test.mjs",
    "docs/sharity-ngo-preference-v4-4-g2-governance-terra-plan-2026-09-13.md",
    "docs/impactshop-governance-system-plan-2026-06-16.md",
    "docs/impactshop-notes-doc-sync-map-2026-06-23.md",
    "docs/bastion-guard-status.md",
    "docs/protected-change-records/2026-09-13-sharity-ngo-preference-v4-4-g2-governance.md",
    "docs/continuity/dev/2026-09-13-sharity-ngo-preference-v4-4-g2-governance.md",
    "notes.md",
    "system-status-snapshot.md"
  ],
  "budgets": {
    "push": 1,
    "pullRequest": 1,
    "merge": 1,
    "providerBuild": 0,
    "providerDeploy": 0,
    "mutationAttempt": 0
  },
  "qa": {
    "correctness": "pass",
    "regression": "pass",
    "security": "pass",
    "operational": "pass"
  },
  "documentationTargets": [
    "docs/sharity-ngo-preference-v4-4-g2-governance-terra-plan-2026-09-13.md",
    "docs/impactshop-governance-system-plan-2026-06-16.md",
    "docs/impactshop-notes-doc-sync-map-2026-06-23.md",
    "docs/bastion-guard-status.md",
    "docs/protected-change-records/2026-09-13-sharity-ngo-preference-v4-4-g2-governance.md",
    "docs/continuity/dev/2026-09-13-sharity-ngo-preference-v4-4-g2-governance.md",
    "notes.md",
    "system-status-snapshot.md"
  ]
}
DEV-DELIVERY-V2-MANIFEST -->

## 1. Scope and non-goals

Ez a G2 kizárólag az `impactshop-notes` oldali governance-előkészítés. A megadott exact base `origin/main@f18bb61e09a5e0dade3e5b5cae9b5a84865f4a2c`, tree `a7f075f5f5d8c498b935d2a5577916d365184f88`; a dedikált branch és worktree a manifestben rögzített. A meglévő schema-v2 capsule `maintenance-docs` selectorral csak ezt a tervet perzisztálja.

A későbbi G2 source candidate két, utólag használható, szűk base-owned selectort készít elő: S4 profil-consumer és S5 additív provider-activity adapter. Emellett a PHP capability dinamikus, base-owned értékelőjét, a protected-file workflow rögzítését, negatív fixture-öket és continuityt készíti elő.

Kizárt: minden `wp-content/` írás, MU-plugin vagy plugin módosítás, owner-grant runtime változtatás, REST route, DDL/DB, catalog seed, flag, WordPress staging/production, Dognet vagy más provider hívás, Vercel, VPS, secret/key/certificate, cron/watchdog, deploy vagy rollback végrehajtása. Az S4/S5 funkcionális implementáció külön, merge utáni fresh exact-main capsule feladata.

## 2. Context and canonical sources

Kanonikus helyi források: `AGENTS.md`, `docs/ai-assistant-canonical-policy.md`, `docs/impactshop-governance-system-plan-2026-06-16.md`, `docs/protected-file-change-checklist.md`, `config/dev-v4/activation-policy.v1.json`, `config/dev-v4/repo-capabilities.v2.json`, valamint a base-owned `scripts/dev-v4-admission.mjs`. Az umbrella terv az `sharity-ngo-preference-v4-4-20260913`; G2 nem módosíthatja annak S1–S6 ownership vagy live activation döntéseit.

## 3. Acceptance criteria

1. A kanonikus DEV-DELIVERY-V2 checker elfogadja a manifestet, a pontos planning identityt, az `approved-for-luna` státuszt, a négy QA eredményt és a kilenc kötelező headinget.
2. A G2 candidate kizárólag a manifest tizenhárom exact pathját módosítja; minden `wp-content/`, provider, deploy, VPS, runtime, secret, cron és watchdog írás kívül marad.
3. G2 implementáció előtt a capsule egyszer és visszafordíthatatlanul `maintenance-docs`-ról az existing base-owned `protected-source` selectorra vált.
4. Az új S4/S5 selectorok és PHP evaluator csak G2 merge utáni fresh exact-main capsule-ből használhatók; a G2 candidate önmagát nem admittálhatja.
5. A PHP capability kizárólag a rögzített realpath/mode/version/extensions/lint/fixture/receipt/TTL feltételekkel lehet `available`; minden eltérés fail-closed.
6. A G2 rögzíti és megszünteti a capsule-séma eltérést: a központi plan checker pontos `current={head,tree,recorded_at}` alakot fogad, míg a jelen base admission még `current.branch` mezőt követel. A planning PASS ugyanazon repo/worktree/branch/base/head identityn készült a központi alakból; az implementációs selector-váltás előtt a helyi base által megkövetelt `current.branch` visszakerül. Merge után az admission a kanonikus alakot fogadja, a starter pedig nem írja vissza az eltérő mezőt.

## 4. Design and file-level implementation plan

A candidate nem használhat saját maga által hozzáadott selectort vagy capabilityt. A terv validálása után a jelen capsule egyirányúan, az existing base-owned `protected-source` selectorra vált, és csak azután kezdődhet a G2 allowlist szerinti source munka. A váltás atomikus schema-v2 capsule update: ugyanaz a repo/path/branch/base/head/tree és G2 plan ID marad kötve; csak a selector változhat `maintenance-docs` → `protected-source`. Visszaváltás, selector-override, candidate-authored selector vagy capability receipt elfogadása tiltott.

G2 merge és exact-main readback után kizárólag új exact-main capsule választhatja ki az új S4 vagy S5 selectort. A friss capsule a merge SHA/tree-hez, az új selectorhoz, a megfelelő plan ID-hoz és annak base-owned PHP-capability döntéséhez kötődik. Hiányzó/incompatible owner contract, selector, capability, receipt vagy TTL `blocked`; `degraded` csak tervezést enged.

`config/dev-v4/activation-policy.v1.json` csak a két új, név szerinti selector szabályát kapja:

- `sharity-ngo-preference-s4-profile-consumer`: kizárólag a későbbi S4 pontos profil-consumer/protected-file/doc/test felületére;
- `sharity-ngo-preference-s5-provider-activity-adapter`: kizárólag a későbbi additív S5 MU-plugin/protected-file/doc/test felületére.

Mindkettő `protected`, requires-plan-id, és explicit PHP capabilityt kér. A selectorok nem tartalmazhatnak általános `wp-content/` prefixet, wildcardot, `apps/`, deploy, VPS, provider vagy data authority-t. Az S4 exact file listája a későbbi G2 utáni fresh planban rögzítendő; a minimális contract a load-order: `000-impactshop-owner-policy.php → impactshop-identity-panel.php → impactshop-sharity-web-session.php`. Az S5 csak új additív MU-plugin lehet, és service-only receipt/handoff feldolgozást nyújthat; közvetlen NGO, slug vagy subject inputot elutasít.

`config/dev-v4/repo-capabilities.v2.json` új/finomított `php-runtime` capabilityt deklarál, de nem állíthatja előre `available`-nek. `scripts/dev-v4-admission.mjs` az immutable base policyból értékel: kizárólag `/opt/homebrew/bin/php` vagy `/usr/bin/php` resolved realpath, regular executable, nem group/world-writable mód, PHP `>= 8.1`, `json`, `hash`, `sodium`, `mysqli`, lint és hermetikus fixture PASS, valamint valid environment receipt és TTL együtt jelenthet `available` állapotot. A receipt provenance base-owned/host-receipt, identity- és scope-bound; candidate self-attestation, PATH, stale receipt, hiányzó extension vagy fixture hiba fail-closed.

`tests/dev-v4-admission.test.mjs` pozitív és negatív fixture-ökkel ellenőrzi a selector/capability parser, path allowlist, insecure executable, verzió/extension hiány, lint/fixture failure, lejárt vagy idegen receipt, selector self-use, valamint `wp-content/`/provider/deploy extra path tiltását.

`scripts/worktree-task-start.sh` a merge utáni capsule-oknál a központi checker kanonikus `current={head,tree,recorded_at}` alakját írja. `scripts/dev-v4-admission.mjs` a jelen, base által létrehozott capsule `current.branch` mezőjét csak erre a G2 átmenetre fogadja el, miközben a repo/branch/path/head/tree kötést változatlanul ellenőrzi; a merge utáni fresh capsule már nem hordozza az eltérő mezőt. A candidate nem állíthat elő új base-authorityt, és a saját admission-módosítását nem használhatja a G2 engedélyezésére.

A nyolc dokumentációs/continuity path a protected-file checklist szerint tartalmazza a koherencia- és kockázatértékelést, érintett funkciók listáját, future UI checklistet, rollbacket, exact file listát és a source-only state-et. A G2 maga nem érint protected runtime fájlt; a workflow csak a későbbi S4/S5 protected touch kötelező előfeltételét rögzíti.

## 5. Risk, coherence, and security review

S4 a meglévő owner-grant v1 integrációján profil-default panelt vezet be, csak a kompatibilis verifier és `subject_ref_for_pseudo()` belső HMAC authority után. Owner contract v1 vagy owner-grant schema v2 hiányánál 503/feature-off; profile lane context-write 403 még payload/store hozzáférés előtt. A cookie/grant/pseudo nem hagyhatja el a PHP processt, account switch/logout revokál, a generic nonce csak kiegészítő CSRF védelem.

S5 a browser/provider felől kizárólag `activity_id` és egyszer használható handoff token útján fogad jelet. A source képez subjectet opaque sessionből és immutable activity receiptet ad; a provider adapter sem NGO/slug/subject paramétert, sem silent hardcoded-slug fallbacket nem fogad el. Állapotgép: `prepared → dispatching → provider_confirmed | provider_unknown | failed`; a confirmation nem purchase/commission/settlement igazolás, `provider_unknown` nem retryzható. Alapértelmezetten off.

- Koherencia: a G2 csak selector/evaluator governance-t készít elő; S4/S5 runtime ownership, protected file listája és UI a későbbi fresh capsule feladata.
- Regresszió: az existing `maintenance-docs`, `standard-source` és `protected-source` lane-ek nem lazulhatnak, és nincs általános `wp-content/` write prefix.
- Biztonság: PATH, candidate receipt, stale TTL, hiányzó extension, direct NGO/slug/subject input és silent fallback tiltott. A protected-file record a későbbi runtime touch kötelező előfeltétele, nem deploy felhatalmazás.
- Operáció: a manifest csak egy source push/PR/merge keretet enged; nincs provider build/deploy vagy live mutation. A pre-existing `.codex/context/` generated context nem G2 candidate path és érintetlen marad.

## 6. QA evidence

| QA | Ellenőrzés | Elvárt eredmény |
| --- | --- | --- |
| QA-1 correctness | Selector, plan ID, capsule one-way transition és PHP fixture contract statikus reviewja | S4/S5 csak exact későbbi scope-ra készül elő; G2 nem self-admitted | pass |
| QA-2 regression | Existing Stage B policy/selector és protected inventory összevetése | `maintenance-docs`, `standard-source`, `protected-source` változatlan; `wp-content/` és runtime érintetlen | pass |
| QA-3 security | PHP provenance/TTL és owner/provider trust-boundary review | Nincs PATH/self-attestation bypass; S4/S5 no-direct-input és privacy contracts preserved | pass |
| QA-4 operational/docs | Candidate manifest, protected-file record, continuity és rollback review | Egy push/PR/merge source budget; nulla provider/deploy/VPS/runtime mutation | pass |

A Luna candidate freeze előtt futtatandó: `node scripts/dev-v4-admission.mjs --phase0`, `node --test tests/dev-v4-admission.test.mjs`, célzott JSON/schema fixturek, `git diff --check`, valamint a repo-local DocSync/continuity és protected-file workflow ellenőrzések. A PHP hermetikus fixture és lint csak az evaluator elkészülte után kötelező; capability hiánya nem javítható local installlal vagy cache bypass-szal.

## 7. Rollback and observability

G2 source rollback csak protected Git revert a merge utáni exact source checkpointból. Semmilyen remote/data/provider rollback nincs, mert G2 nem ír ilyen állapotot. A későbbi S4/S5 aktiválás rollbackje az umbrella terv sorrendjét követi: attribution off, UI off, API off; history/catalog/activity nem törölhető és `provider_unknown` nem küldhető újra. Megfigyelhetőség kizárólag privacy-redacted source/capsule/receipt evidence; raw pseudo, grant, subject, provider credential vagy runtime payload nem kerül dokumentumba vagy receiptbe.

## 8. Luna implementation chunks

### Chunk 1 — Protected G2 selector/capability governance

- Files and interfaces: `config/dev-v4/activation-policy.v1.json`, `config/dev-v4/repo-capabilities.v2.json`, `scripts/dev-v4-admission.mjs`, `scripts/worktree-task-start.sh`, and `tests/dev-v4-admission.test.mjs`.
- Preconditions: planning manifest passes; capsule has atomically switched to existing `protected-source`; exact base/head identity holds.
- Exact change: add the future-only S4/S5 narrow selectors, PHP base-owned capability evaluator, canonical capsule writer/read compatibility, and positive/negative fixtures without runtime calls or `wp-content/` access.
- Validation: Phase 0 admission, isolated Node fixtures, PHP lint/hermetic fixture where evaluator permits it, and `git diff --check`.
- Done when: no candidate path exceeds the manifest, no selector self-admits G2, and every unavailable PHP condition fails closed.

### Chunk 2 — Protected-file continuity record

- Files and interfaces: the eight manifest-listed documentation/continuity paths only.
- Preconditions: Chunk 1 candidate is frozen with exact path inventory.
- Exact change: record protected-file prerequisites for later S4/S5, risk/coherence, future manual UI checklist, exact candidate identity, rollback, and source-only state.
- Validation: local DocSync/continuity workflow, manifest/path comparison, and `git diff --check`.
- Done when: documentation has no runtime/deploy claim and matches the frozen candidate.

## 9. Handoff decision

Luna kizárólag a manifest tizenhárom pathján dolgozhat, a `protected-source` capsule selector igazolása után. Bármely `wp-content` igény, selector/capability authoritás-ellentmondás, owner contract mismatch, PHP `blocked/degraded`, protected inventory drift, provider/VPS/deploy szükséglet vagy extra path Terra/Sol és külön fresh plan kapu. A source merge után G2 exact-main readback kötelező, és S4/S5 csak új capsule-ból indulhat.
