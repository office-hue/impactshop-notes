# Impactshop-Notes Doc Sync Map

Datum: 2026-06-23
Statusz: canonical local doc-sync map
RepoId: `impactshop-notes`
CanonicalMapPath: `docs/impactshop-notes-doc-sync-map-2026-06-23.md`
RootHubPath: `../ai-agent/DOC-SYNC-HUB.md`
OwnerRepo: `ai-agent`
LastVerifiedAt: `2026-06-29T16:05:00Z`
RegistryStatus: `partial`

## Cel

Ez a fajl az `impactshop-notes` repo egyetlen helyi canonical doc-sync mapje.

A celja, hogy egy helyrol lehessen feloldani:

1. a helyi governance es protected-lane truthot;
2. a VB2026 / bridge / profile-return lane doku-truthjat;
3. a helyi env/auth/runtime adaptert;
4. a core public baseline es deploy-path anchorokat;
5. a kotelezo continuity celpontokat.

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
| Local governance control plane | `docs/impactshop-governance-system-plan-2026-06-16.md` | `AGENTS.md`, `docs/pr-policy.md`, `PR-EXIT-CHECKLIST.md`, `docs/ai-assistant-canonical-policy.md`, `scripts/worktree-task-start.sh`, `scripts/worktree-task-start-guard.sh`, `scripts/worktree-readiness-check.sh`, `scripts/worktree-coordination-sync.sh` | `docs/impactshop-governance-hub-coherence-audit-2026-06-16.md`, `docs/worktree-coordination-sync.md` | `scripts/safe-repo-audit.sh --strict --mode push`, `scripts/git-health-check.sh`, `bash scripts/worktree-readiness-check.sh --json`, `bash scripts/worktree-task-start-guard.sh --json`, workspace `.worktrees/ACTIVE_WORKTREE.md`, workspace `.worktrees/ACTIVE_WORKTREES.md`, per-worktree `worktree-task-start-decision.json` | `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `partial` | A helyi governance hub mar megvolt; most mar a runtime starter lane mellett a prunable-tolerans koordinacios snapshot minimum es a task-start decision artifact snapshotba visszaemelt truthja is tartozik hozza, de a teljes drift/coordination enforcement meg kesobbi fazis. |
| Env / auth / runtime protected adapter | `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md` | `docs/impactshop-guard-config.json`, `docs/impactshop-protected-files.json`, `bin/impactshop-guard-deploy.sh` | protected change recordok a `docs/protected-change-records/` alatt | `scripts/safe-repo-audit.sh --strict --mode push`, protected inventory jelenlet | `notes.md`, `system-status-snapshot.md` | `merged` | A repo egyik legerzekenyebb lane-je; fail-closed truth mar kulon adapteren all. |
| Protected bridge / profile-return lane | `docs/protected-change-records/2026-06-14-vb2026-profile-return-session-carry.md` | `wp-content/mu-plugins/impactshop-identity-panel.php`, `wp-content/mu-plugins/impactshop-identity-panel.js`, `wp-content/mu-plugins/impactshop-factlens-identity-bridge.php` | protected change recordok es local guard config | protected inventory + deploy-path evidence | `notes.md`, `system-status-snapshot.md` | `partial` | Kulonosen drift-erzekeny lane, mert a docs truth, a WP runtime es a protected inventory kulon tud szetcsuszni. |
| VB2026 NGO catalog source lane | `docs/VB2026-SHARITY-NGO-CATALOG-AND-SELECTION-PLAN-2026-06-23.md` | `impactshop-vb2026-ngo-catalog.php`, a kapcsolodo source selection REST lane-ek | `docs/VB2026-SHARITY-NGO-CATALOG-PHASE1-IMPLEMENTATION-PACK-2026-06-23.md` | local protected/runtime evidence a source lane-re | `notes.md`, `system-status-snapshot.md`, `docs/bastion-guard-status.md` | `partial` | Friss lane, amelynel a docs continuity minimum mar ki lett mondva a governance hubban is. |
| Core public baseline / challenge lane | `docs/public-pages-canonical-baseline.md` | public page templatek, deploy path, guarded public files | `docs/impactshop-production-deploy-path-audit-2026-04-20.md`, `docs/impact-challenge-canonical-baseline.md` | deploy-path audit + protected inventory evidence | `notes.md`, `system-status-snapshot.md` | `partial` | A public baseline es a protected deploy path egyutt ad helyi runtime truthot. |
| Partner / integration documentation lane | `docs/partner-master-checklist.md` ha letezik, kulonben `docs/README.md` partner szekcio | partner-specifikus docs, `tools/partner-*`, `docs/api/README.md` | partner release / security / webhook checklist dokumentumok | partner QA tooling es release checklist | `notes.md` | `docs-only` | Nagy doku-tomb, de jelenleg nincs kulon egyhelyes local index a canonical mapen kivul. |

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

## Natural Next Step

Innen a kovetkezo legkisebb hasznos szelet:

1. a local runtime starter lane kovetkezo szuk bovitese: doc-sync/path presetek vagy topic-level filterek;
2. kulon local child map a protected bridge/VB2026 lane-re, ha a drift-guard mar gepi topic-level feloldast is kap;
3. magasabb continuity/drift enforcement follow-up, ha a coordination riportalas mar stabil.
