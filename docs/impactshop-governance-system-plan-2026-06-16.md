# ImpactShop Governance System Plan

Datum: 2026-06-16
Statusz: canonical local governance hub
Scope: rovid, repo-helyi belepesi pont az `impactshop-notes` governance, review, continuity es protected-lane szabalyaihoz.

## 2026-08-21 Hatás Körök Human Touch route note

- Az `app.sharity.hu/hatas-korok` publikus dokumentumút Human Touch cutoverje
  additív, pontos host/path/method szűrésű MU-pluginon keresztül történik.
- A rögzített `302` cél query-mentes; admin, AJAX, REST, dev/staging útvonal,
  identity/profile-return, Offerwall és VB2026 nem része a változásnak.
- Az új route tulajdonos a protected inventory, digest, `community_route` smoke
  csoport és protected change record együttese alatt max-védett.
- Kanonikus csomag:
  `docs/HATAS-KOROK-HUMAN-TOUCH-ROUTE-TERRA-PLAN-2026-08-21.md`,
  `docs/HATAS-KOROK-HUMAN-TOUCH-ROUTE-CHECKPOINT-2026-08-21.md` és
  `docs/protected-change-records/2026-08-21-hatas-korok-human-touch-route.md`.

## 2026-08-19 Sharity affiliate runtime note

- A Shopping Assistant affiliate aktiválás új, default-off WordPress runtimeja
  csak az exact `src=shopping-assistant` lane-ben delegál a védett `/go`
  tulajdonosból.
- A providerhez kizárólag opaque `sat1` korreláció kerül; a raw pseudo, URL és
  gazdasági állítás tárolása tiltott. Az NGO és a HMAC subject mapping 45 napos
  retention alatt marad helyben.
- A runtime, a minimális boot adapter és a bástya guard együtt alkot új
  max-védett egységet. Aktiválás csak a külön ai-agent watchdog checkpoint,
  merge és guarded deploy után engedett.
- Kanonikus feature truth:
  `docs/sharity-affiliate-runtime-wp-sol-plan-2026-08-19.md` és
  `docs/sharity-affiliate-runtime-wp-sol-handover-2026-08-19.md`.

## 2026-08-19 Deploy bastion manifest guard note

- A mapping deploy kontrollsík maga is max-védett lane: az undefined remote
  manifest check vagy egy részben író dry-run nem kerülhető meg kézi
  `scp`/`rsync` használatával.
- A kanonikus szerződés merged-main-only guard wrapper, fail-closed remote
  manifest admission és valóban no-write, itemizált `DRY_RUN=1`.
- A runtime digest manifest nem önmagát és nem a deploy kontrollsíkot védi;
  ezek truthja a protected model, protected-touch, CI parity, change record és
  strict audit együttese.
- A dry-run által feltárt live-main protected drift külön operátori döntésig
  blokkolja a valós deployt; a dry-run nem tekinthető drift-jóváhagyásnak.
- Kanonikus csomag:
  `docs/impactshop-deploy-bastion-manifest-sol-plan-2026-08-19.md` és
  `docs/protected-change-records/2026-08-19-deploy-bastion-manifest-guard.md`.

## 2026-08-20 Exact-file production preview note

- A production `mu-plugins` könyvtár nem tekinthető egyetlen repo által birtokolt
  deploy egységnek: 20 live-only bejegyzés és 6 közös content drift bizonyított.
- A teljes mapping profil minden hálózati művelet előtt fail-closed validálódik;
  a páros env explicit staging/production identitást hordoz.
- Valós production mapping minden scope-ban tiltott, amíg nincs remote backup,
  CAS/hash admission, `0444` visszazárás és futtatható rollback.
- Az exact-file dry-run egyetlen repo-owned fájlra szűkít, minden `--delete*`
  opciót eltávolít, checksumot kényszerít, és a két deploy bastion teszt CI-ben
  is fut.
- Kanonikus csomag:
  `docs/impactshop-exact-file-deploy-safety-sol-plan-2026-08-20.md` és
  `docs/protected-change-records/2026-08-20-exact-file-deploy-safety.md`.

## 2026-06-23 Phase I sync note

- A VB2026 NGO katalogus Phase I source lane (`impactshop-vb2026-ngo-catalog.php`) additiv, de mivel uj MU-plugin es uj publikus/selection REST perem, a docs continuity minimum itt nem all meg a feature-plan es a bastion naplo frissitesenel.
- Ha a Phase I source lanehez tartozo guard/governance evidence valtozik, a `docs/bastion-guard-status.md` es ez a helyi governance hub egyutt frissitendo, hogy a local push gate ugyanazt a truthot lassa, mint a reviewer.

## 2026-07-01 Root doc-sync hub note

- A repo most mar kapott egy gyokerbol is elerheto canonical hubot: `DOC-SYNC-HUB.md`.
- Ez a hub a helyi doc-sync mapet, a worktree/continuity minimumot es a governance entrypointokat egy helyre rendezi, hogy a rootbol indulva is feloldhato legyen az aktualis temaallapot.

## 2026-06-24 Runtime starter note

- A repo megkapta a helyi runtime starter minimumot is: `scripts/worktree-task-start.sh` es `scripts/worktree-readiness-check.sh`.
- Ez tudatosan N1 szelet: marker + readiness + local starter reuse, de meg nem teljes drift/coordination enforcement.
- A helyi governance truth innentol nem csak docs entrypointot, hanem egy rovid, repo-helyi worktree-start belépőt is tartalmaz.

## 2026-06-29 Runtime N2 note

- A helyi runtime starter lane most mar kulon task-start decision/helper reteget is kapott: `scripts/worktree-task-start-guard.sh`.
- A starter lane a marker + readiness + task-start guard utan frissiti a koordinacios snapshotot, igy a per-worktree `worktree-task-start-decision.json` artifact nem csak letrejön, hanem a workspace-szintu riportba is visszaemelkedik.
- Ez meg mindig nem teljes hook-level continuity enforcement, de mar reviewer-visible evidence-et ad a doc-sync label/repo/path scope-rol es a blocked/degraded/allowed dontesrol.

## 2026-06-29 Runtime N4 note

- A helyi continuity/guard reteg is bekapcsolt: `scripts/worktree-continuity-guard.sh`.
- A pre-push hook es a `git wpush` wrapper most mar nem csak altalanos auditot futtat, hanem explicit ellenorzi a marker + decision artifact + koordinacios snapshot paritast is.
- Ezzel a local truth mar nem allhat meg az N3 snapshot lathatosagnal; push elott kotelezo continuity evidence lett belole.

## 2026-07-01 Impact Challenge pause-lock note

- A protected `impact-challenge` publikus runtime atmeneti pause-lock maintenance allapotot kapott a `wp-content/mu-plugins/zzz-impactshop-ui-lock.php` lane-ben.
- Ennél a helyi governance truth nem allhat meg a protected change recordnal: a publikus route-copy, a REST freeze truth, a donation-pool quarter feloldas, a rollback artefaktok es a local doc-sync map ugyanabban a change sorban kovetendo.
- Emiatt az ilyen maintenance override szeleteknel a minimum continuity anchor keszlet: `docs/protected-change-records/*.md`, `docs/impactshop-notes-doc-sync-map-2026-06-23.md`, `docs/bastion-guard-status.md`, `system-status-snapshot.md`, `notes.md`.

## Cel

Ez a dokumentum nem uj policy-t vezet be, hanem egyetlen helyi governance-hubkent osszefogja azokat a mar ervenyes kanonikus anchorokat, amelyek menten az `impactshop-notes` repo-ban a munka, review, continuity es protected-lane valtozas tortenik.

## Canonical Anchors

1. `DOC-SYNC-HUB.md`
2. `docs/impactshop-notes-doc-sync-map-2026-06-23.md`
3. `AGENTS.md`
   - a repo-szintu munkaszabalyok, canonical policy-sorrend, continuity es protected-file minimumok
4. `docs/pr-policy.md`
   - branch/worktree fegyelem, PR/merge/deploy kapuk, protected-file es review szabalyok
5. `PR-EXIT-CHECKLIST.md`
   - merge elotti kotelezo exit-feltetelek rovid ellenorzolistaja
6. `docs/ai-assistant-canonical-policy.md`
   - a helyi AI-asszisztens es protected/perimeter policy reszletesebb referenciapontja
7. `docs/impact-challenge-canonical-baseline.md`
   - az Impact Challenge protected es regresszio-ervenyes referenciaallapota
8. `system-status-snapshot.md`
   - a repo aktualis mukodesi/allapotvaltozasi naploja
9. `notes.md`
   - session-szintu folyamatos dontes-, kockazat- es teendolog
10. `docs/bastion-guard-status.md`
   - protected/perimeter es guard-bovitesek idobelyeges evidencianaploja
11. `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md`
   - a local env/auth/runtime guard adapter konkret helyi szerzodese

## Recommended Reading Order

1. `DOC-SYNC-HUB.md`
2. `AGENTS.md`
3. `docs/impactshop-notes-doc-sync-map-2026-06-23.md`
4. `docs/pr-policy.md`
5. `PR-EXIT-CHECKLIST.md`
6. az aktualis feladathoz kapcsolodo protected vagy baseline dokumentum
7. `system-status-snapshot.md`
8. `notes.md`

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
5. Env/auth/runtime adapter discipline
   - protected env, bridge es profile-return lane csak a helyi adapter-szerzodes szerint tekintheto `allowed` allapotunak

## Push Gate

1. a governance, guard es policy lane valtozasai push elott fail-closed local system-plan sync gate alatt allnak;
2. ez azt jelenti, hogy a `docs/impactshop-governance-system-plan-2026-06-16.md` frissitese nem utolagos adminisztracio, hanem a helyi DEV folyamat resze.
3. env/auth/runtime lane valtozasnal a `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md` is kotelezo continuity anchor.
4. a runtime worktree-start lane-nel a marker + decision + coordination snapshot paritast a local continuity guard kotelezoen ervenyesiti.

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
- env/auth/runtime vagy bridge drift vizsgalatakor

## 2026-08-20 deploy rollback-truth kiterjesztés

- A deploy guard lokális source snapshotja nem nevezhető remote runtime
  rollbacknak.
- Gyors visszaállítási parancs csak ténylegesen létező és futtatható rollback
  entrypoint mellett jelenhet meg.
- A fallback üzenetnek fail-closed módon rögzítenie kell a hiányzó remote
  rollbackot és a fennmaradó production write blokkot; ezt CI guard védi.

## 2026-08-20 exact production release admission

- A broad production mapping továbbra is fail-closed; valós írás csak egy exact
  fájlra, clean named `main` vagy detached `HEAD == origin/main` worktree és
  kanonikus production profil mellett mehet. Named feature/stale detached tiltott.
- A remote release truth privát, sémázott manifest: explicit eredeti
  absent/SHA/mode, intended SHA, phase és release ID.
- Prepare után az apply ismét CAS-t végez, staged PHP lintet futtat, atomikusan
  cserél és SHA + `0444` relockot ellenőriz.
- A rollback read-only alapállású és csak release ID + deployed SHA + explicit
  production apply mellett állít vissza; live drift esetén nem ír.
- Kanonikus terv és evidencia:
  `docs/impactshop-exact-release-admission-sol-plan-2026-08-20.md`,
  `docs/protected-change-records/2026-08-20-exact-release-admission.md`.
- A guard wrapper `deploy-*` azonosítója kizárólag lokális source snapshot.
  Remote rollback authorityt csak sikeres exact apply által kiadott release ID
  és deployed SHA együtt adhat; a két namespace nem aliasolható.
- A guard checksum labelje repository-relatív; abszolút host/worktree path nem
  válhat kanonikus vagy commitolt governance truth részévé.
- A production release-engine toolchain truth Python 3.6.8 + standard library;
  az engine-nek ezen a nyelvtanon kell futnia, távoli interpreter-frissítés nem
  része az exact release-nek. A kompatibilitást külön AST/API guard védi.
- A max-protected exact parent tartós módja nem lazítható. A remote lockon belüli
  owner-write ablak csak inode/owner validációval, az egyetlen exact műveletre
  nyílhat, majd minden kimeneten az eredeti módra kell visszazárni és ellenőrizni.
