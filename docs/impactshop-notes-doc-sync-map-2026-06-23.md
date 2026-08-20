# Impactshop-Notes Doc Sync Map

Datum: 2026-06-23
Statusz: canonical local doc-sync map
RepoId: `impactshop-notes`
CanonicalMapPath: `docs/impactshop-notes-doc-sync-map-2026-06-23.md`
RootHubPath: `DOC-SYNC-HUB.md`
CrossRepoHubPath: `../ai-agent/DOC-SYNC-HUB.md`
OwnerRepo: `impactshop-notes`
LastVerifiedAt: `2026-08-20T08:22:00Z`
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
| Sharity affiliate correlation runtime | `docs/sharity-affiliate-runtime-wp-sol-plan-2026-08-19.md` | `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`, `wp-content/mu-plugins/impactshop-boot.php` | `tests/sharity-affiliate-runtime-test.php`, `tests/sharity-affiliate-runtime-bastion.test.sh`, `docs/protected-change-records/2026-08-19-sharity-affiliate-runtime.md` | `scripts/sharity-affiliate-runtime-bastion-guard.sh`, protected inventory/digest, `impactshop_sharity_affiliate_retention_cleanup` és a külön ai-agent central watchdog checkpoint | `docs/sharity-affiliate-runtime-wp-sol-handover-2026-08-19.md`, `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `partial` | A repo checkpoint default-off és deployolatlan; a `merged` státuszhoz mindkét repo merge, guarded deploy, cron/watchdog evidencia és egy emberi Árukereső canary szükséges. |
| Deploy bastion, true dry-run és exact-file preview | `docs/impactshop-deploy-bastion-manifest-sol-plan-2026-08-19.md`, `docs/impactshop-exact-file-deploy-safety-sol-plan-2026-08-20.md` | `bin/deploy-wpcontent-map.sh`, `bin/impactshop-guard-deploy.sh`, paired deploy env | `tests/deploy-wpcontent-map-bastion.test.sh`, `tests/deploy-wpcontent-map-exact-file.test.sh`, `tests/impactshop-guard-rollback-truth.test.sh`, `docs/protected-change-records/2026-08-19-deploy-bastion-manifest-guard.md`, `docs/protected-change-records/2026-08-20-exact-file-deploy-safety.md` | remote manifest fail-closed admission, no-write itemized `DRY_RUN=1`, early mapping validation, exact-file no-delete/checksum isolation, executable-gated rollback handover, repository-relative guard checksum, production real-write lock, protected-touch és strict audit | `docs/impactshop-deploy.md`, `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `partial` | A dry-run guard és exact-file preview kész. A lokális snapshot nem remote rollback, ezért a wrapper ezt már nem állítja annak; a védett digest/checksum hostfüggetlen. Valós production írás remote backup/CAS/executable rollback nélkül tiltott; staging továbbra is 404. |

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
