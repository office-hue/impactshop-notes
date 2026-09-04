# Impactshop-Notes Doc Sync Map

Datum: 2026-06-23
Statusz: canonical local doc-sync map
RepoId: `impactshop-notes`
CanonicalMapPath: `docs/impactshop-notes-doc-sync-map-2026-06-23.md`
RootHubPath: `DOC-SYNC-HUB.md`
CrossRepoHubPath: `../ai-agent/DOC-SYNC-HUB.md`
OwnerRepo: `impactshop-notes`
LastVerifiedAt: `2026-08-21T14:55:00Z`
RegistryStatus: `partial`

## Cel

Ez a fajl az `impactshop-notes` repo egyetlen helyi canonical doc-sync mapje.

A celja, hogy egy helyrol lehessen feloldani:

1. a helyi governance es protected-lane truthot;
2. a VB2026 / bridge / profile-return lane doku-truthjat;
3. a helyi env/auth/runtime adaptert;
4. a core public baseline es deploy-path anchorokat;
5. a kotelezo continuity celpontokat;
6. a repo-root doc-sync hubot, ha valaki gyokerbol indulna.

## Repo Scope

Ez a map az `impactshop-notes` repora ervenyes.

Elsodleges helyi temak:

1. local governance control plane
2. protected env/auth/runtime adapter
3. protected bridge / profile-return lane
4. VB2026 NGO catalog source lane
5. core public / challenge baseline lane
6. partner/integration documentation lane

## Status Taxonomy

Ez a helyi map a kozos minimum statuszokat hasznalja:

1. `merged`
2. `partial`
3. `docs-only`
4. `drift-risk`
5. `unknown`

## Canonical Topic Map

| Topic | Master doc | Implementation truth | QA / audit truth | Runtime / guard evidence | Continuity target | Status | Notes |
|---|---|---|---|---|---|---|---|
| Local governance control plane | `docs/impactshop-governance-system-plan-2026-06-16.md` | `AGENTS.md`, `docs/pr-policy.md`, `PR-EXIT-CHECKLIST.md`, `docs/ai-assistant-canonical-policy.md`, `scripts/worktree-task-start.sh`, `scripts/worktree-task-start-guard.sh`, `scripts/worktree-readiness-check.sh`, `scripts/worktree-coordination-sync.sh`, `scripts/worktree-continuity-guard.sh` | `docs/impactshop-governance-hub-coherence-audit-2026-06-16.md`, `docs/worktree-coordination-sync.md`, `docs/worktree-continuity-guard.md` | `scripts/safe-repo-audit.sh --strict --mode push`, `scripts/git-health-check.sh`, `bash scripts/worktree-readiness-check.sh --json`, `bash scripts/worktree-task-start-guard.sh --json`, `bash scripts/worktree-continuity-guard.sh --json`, workspace `.worktrees/ACTIVE_WORKTREE.md`, workspace `.worktrees/ACTIVE_WORKTREES.md`, per-worktree `worktree-task-start-decision.json` | `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `partial` | A helyi governance hub mar megvolt; most mar a runtime starter lane mellett a prunable-tolerans koordinacios snapshot minimum, a task-start decision artifact snapshotba visszaemelt truthja, valamint a hook-szintu continuity guard is ide tartozik, de a teljes drift/default-activation enforcement meg kesobbi fazis. |
| Env / auth / runtime protected adapter | `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md` | `docs/impactshop-guard-config.json`, `docs/impactshop-protected-files.json`, `bin/impactshop-guard-deploy.sh` | protected change recordok a `docs/protected-change-records/` alatt | `scripts/safe-repo-audit.sh --strict --mode push`, protected inventory jelenlet | `notes.md`, `system-status-snapshot.md` | `merged` | A repo egyik legerzekenyebb lane-je; fail-closed truth mar kulon adapteren all. |
| Protected bridge / profile-return lane | `docs/protected-change-records/2026-06-14-vb2026-profile-return-session-carry.md` | `wp-content/mu-plugins/impactshop-identity-panel.php`, `wp-content/mu-plugins/impactshop-identity-panel.js`, `wp-content/mu-plugins/impactshop-factlens-identity-bridge.php` | protected change recordok es local guard config | protected inventory + deploy-path evidence | `notes.md`, `system-status-snapshot.md` | `partial` | Kulonosen drift-erzekeny lane, mert a docs truth, a WP runtime es a protected inventory kulon tud szetcsuszni. |
| VB2026 NGO catalog source lane | `docs/VB2026-SHARITY-NGO-CATALOG-AND-SELECTION-PLAN-2026-06-23.md` | `impactshop-vb2026-ngo-catalog.php`, a kapcsolodo source selection REST lane-ek | `docs/VB2026-SHARITY-NGO-CATALOG-PHASE1-IMPLEMENTATION-PACK-2026-06-23.md` | local protected/runtime evidence a source lane-re | `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `partial` | Friss lane, amelynel a docs continuity minimum mar ki lett mondva a governance hubban is. |
| Core public baseline / challenge lane | `docs/public-pages-canonical-baseline.md` | public page templatek, deploy path, guarded public files, `wp-content/mu-plugins/zzz-impactshop-ui-lock.php` maintenance override lane | `docs/impactshop-production-deploy-path-audit-2026-04-20.md`, `docs/impact-challenge-canonical-baseline.md`, `docs/protected-change-records/2026-07-01-impact-challenge-pause-lock.md` | deploy-path audit + protected inventory evidence + live hotfix rollback artefaktok | `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `partial` | A public baseline es a protected deploy path egyutt ad helyi runtime truthot; 2026-07-01-tol ide tartozik az atmeneti Impact Challenge pause-lock/frozen-standings lane is, amely a publikus routeot fail-closed maintenance allapottal zarja vissza. |
| Partner / integration documentation lane | `docs/partner-master-checklist.md` ha letezik, kulonben `docs/README.md` partner szekcio | partner-specifikus docs, `tools/partner-*`, `docs/api/README.md` | partner release / security / webhook checklist dokumentumok | partner QA tooling es release checklist | `notes.md` | `docs-only` | Nagy doku-tomb, de jelenleg nincs kulon egyhelyes local index a canonical mapen kivul. |
| Sharity affiliate correlation runtime | `docs/sharity-shopping-opaque-sat1-production-cutover-sol-plan-2026-08-21.md`, `docs/sharity-affiliate-runtime-wp-sol-plan-2026-08-19.md` | `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`, `wp-content/mu-plugins/impactshop-boot.php` | `tests/sharity-affiliate-runtime-test.php`, `tests/sharity-affiliate-runtime-bastion.test.sh`, `docs/protected-change-records/2026-08-21-sharity-shopping-opaque-sat1-production-cutover.md`, `docs/protected-change-records/2026-08-23-vb2026-autobanner-canonical-affiliate-bind.md` | `scripts/sharity-affiliate-runtime-bastion-guard.sh`, protected inventory/digest, active `impactshop_sharity_affiliate_retention_cleanup`, exact-main ai-agent central watchdog és postactivation admission | `docs/sharity-affiliate-runtime-wp-sol-handover-2026-08-19.md`, `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `live-pending-human-canary` | Runtime option/schema/table/egyetlen cleanup hook aktív és admitted. A `shopping-assistant` és az exact `vb2026-autobanner` lane egyaránt opaque SAT1-et használ; 2026-08-23 exact release-ek: runtime `20260823T092444Z-4ab348480ead-17e8ae00`, boot `20260823T092538Z-4ab348480ead-13d6733f`, `0444/0555`. Egy emberi termékdeeplink/SAT1/NGO correlation ellenőrzés maradt. |
| Deploy bastion, exact release és rollback | `docs/impactshop-deploy-bastion-manifest-sol-plan-2026-08-19.md`, `docs/impactshop-exact-file-deploy-safety-sol-plan-2026-08-20.md`, `docs/impactshop-exact-release-admission-sol-plan-2026-08-20.md` | `bin/deploy-wpcontent-map.sh`, `bin/impactshop-guard-deploy.sh`, `bin/impactshop-guard-preflight.sh`, `bin/impactshop-guard-rollback.sh`, `scripts/impactshop-exact-release-remote.py`, paired deploy env | `tests/deploy-wpcontent-map-bastion.test.sh`, `tests/deploy-wpcontent-map-exact-file.test.sh`, `tests/impactshop-exact-release-remote.test.sh`, `tests/impactshop-guard-preflight-detached.test.sh`, `tests/impactshop-guard-rollback-truth.test.sh`, `docs/protected-change-records/2026-08-20-exact-release-admission.md` | remote manifest fail-closed admission, no-write `DRY_RUN=1`, exact no-delete isolation, clean named-main vagy detached origin/main + expected-state admission, private backup, apply/rollback CAS, staged PHP lint, atomic replace, target `0444` és parent-mode relock, protected-touch és strict audit | `docs/impactshop-deploy.md`, `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `live-exact-file` | A default-off runtime release után a boot adapter frissítése is ugyanazon exact-file tranzakciós lane-en ment ki. Release `20260821T145250Z-1716e6fc2761-6892b1d3` egy targetet módosított; rollback inspect, target `0444` és parent `0555` zöld. Broad production write továbbra is tiltott. |
| Hatás Körök Human Touch public route | `docs/HATAS-KOROK-HUMAN-TOUCH-ROUTE-TERRA-PLAN-2026-08-21.md` | `wp-content/mu-plugins/impactshop-hatas-korok-human-touch-route.php`, változatlan legacy `impact-community.php` fallback | `tests/hatas-korok-human-touch-route.test.py`, `docs/protected-change-records/2026-08-21-hatas-korok-human-touch-route.md` | `scripts/hatas-korok-post-deploy-smoke.sh`, protected inventory/digest, `community_route` smoke group, exact-file guarded release | `docs/HATAS-KOROK-HUMAN-TOUCH-ROUTE-CHECKPOINT-2026-08-21.md`, `docs/hatas-korok-post-deploy-checklist.md`, `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `implementation-ready` | Csak a pontos production dokumentumút ad query-mentes `302` átadást az új Human Touch oldalnak; dev/staging, API, identity/profile-return, Offerwall és VB2026 kizárt. |

## Continuity Targets

Helyi continuity truthok:

1. `notes.md`
2. `system-status-snapshot.md`

Felteteles continuity target:

1. `docs/bastion-guard-status.md` ha protected/perimeter modul vagy guard bovitese tortenik

## Drift-Risk Notes

1. Az `impactshop-notes` repo-ban a protected bridge lane es a VB2026 source lane a legerzekenyebb a docs/runtime/inventory driftre.
2. A helyi governance hub mar rendezett belepesi pont volt, de a konkret tematerek tovabbra is tobb dokumentum kozt voltak szetosztva.
3. A cross-repo root hub az `ai-agent` oldalon el, ezert a helyi mapnek explicit modon vissza kell mutatnia arra.
4. A partner/integration doku nagy mennyisegu, de jelenleg inkabb docs-tomb, mint egyhelyes canonical truth.
5. A protected Challenge maintenance override kulonosen drift-erzekeny, mert a publikus route-copy, a REST freeze truth, a donation-pool quarter feloldas es a live rollback evidencia egyutt ad koherens allapotot.

## Natural Next Step

Innen a kovetkezo legkisebb hasznos szelet:

1. a repo-root `DOC-SYNC-HUB.md` karbantartasa, ha uj local map vagy helper jelenik meg;
2. a merge utani kozponti `ai-agent` writeback (`N5`), hogy az N4 continuity truth is bekeruljon az all-repo rollout matrixba;
3. kulon local child map a protected bridge/VB2026 lane-re, ha a drift-guard mar gepi topic-level feloldast is kap;
4. magasabb continuity/default-activation follow-up, ha a coordination riportalas mar stabil.

## 2026-09-02 DEV context policy rollout

`AGENTS.md`, `scripts/dev-context-policy-guard.sh`, its fixture, readiness,
existing CI wiring, governance plan, bastion status, `notes.md` and the system
snapshot form one docsync unit. Global prompt or memory cannot waive it.

## 2026-08-23 VB2026 autobanner affiliate continuity

Canonical evidence: docs/protected-change-records/2026-08-23-vb2026-autobanner-canonical-affiliate-bind.md. Runtime/boot, lifecycle tests, maximum bastion, notes, status and bastion status move together. Source placement is exact vb2026-autobanner; NGO selection remains owned by VB2026 and no economic truth is inferred.
| Impact Shopping UNice CJ canary | `docs/protected-change-records/2026-09-03-impact-shopping-unice-cj-adapter.md` | `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php` | `tests/sharity-affiliate-runtime-test.php`, `tests/sharity-affiliate-runtime-bastion.test.sh` | `scripts/sharity-affiliate-runtime-bastion-guard.sh`, existing retention cron, ai-agent central watchdog | `docs/sharity-affiliate-runtime-wp-sol-handover-2026-08-19.md`, `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `source-ready-default-off` | One exact UNice/CJ program, canonical session subject, trusted NGO and one-use opaque handoff; production deploy/enable evidence pending. |

## 2026-09-04 DEV delivery v2 target admission

The contract snapshot, maximum-bastion adapter, fixture and existing `CI /
validate` job form one local governance docsync unit. Candidate evidence is
private worktree Git metadata; no provider or product deploy authority exists.

## 2026-09-04 DEV delivery v2 base-binding correction

The same docsync unit now includes exact 40-hex/rev-object base, head and tree
admission plus a successful exact base-to-head diff. CI fetches and verifies
the PR base before injecting it into both local guards; missing/ref-invalid
state is blocked rather than classified as an empty governance diff. Fixtures
cover missing base, a non-SHA supplied base and a non-governance changed path.
