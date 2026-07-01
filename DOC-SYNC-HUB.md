# Impactshop-Notes Doc Sync Hub

Datum: 2026-07-01
Statusz: canonical root doc-sync hub
Scope: a repo-root szintu belépőpont, amely a helyi doc-sync mapet, a worktree/continuity minimumot es a governance beléptetést egy helyre rendezi.

## Cél

Ez a fajl a repo gyokerbol elerheto, rovid index arra, hogy egy adott tema helyi dokumentacios allapota hol talalhato.

Hasznalat:

1. elobb ezt a hubot olvasd;
2. utana a helyi canonical mapet;
3. majd a temahoz tartozo governance / QA / continuity anchorokat.

## Helyi canonical anchors

1. `docs/impactshop-notes-doc-sync-map-2026-06-23.md`
2. `docs/impactshop-governance-system-plan-2026-06-16.md`
3. `docs/impactshop-governance-hub-coherence-audit-2026-06-16.md`
4. `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md`
5. `docs/worktree-coordination-sync.md`
6. `docs/worktree-continuity-guard.md`

## Worktree / continuity minimum

1. `scripts/start-feature-worktree.sh`
2. `scripts/worktree-task-start.sh`
3. `scripts/worktree-readiness-check.sh`
4. `scripts/worktree-task-start-guard.sh`
5. `scripts/worktree-coordination-sync.sh`
6. `scripts/worktree-continuity-guard.sh`

## Cross-repo root pointer

1. `../ai-agent/DOC-SYNC-HUB.md`

## Continuity targets

1. `notes.md`
2. `system-status-snapshot.md`
3. `docs/bastion-guard-status.md`

## Fenntartasi szabaly

Ha uj helyi doc-sync map, worktree helper, vagy continuity guard jelenik meg, ezt a hubot kell elso helyen frissiteni, utana a local mapet, majd a kapcsolt governance / snapshot / notes anchorokat.
