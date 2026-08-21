# Hatás Körök Human Touch production route — implementation-ready plan

Status: `approved-for-luna`

## 1. Scope and non-goals

Scope: az `app.sharity.hu/hatas-korok[/]` production GET/HEAD route additív,
visszafordítható átvezetése a már éles
`https://sharity.hu/hatas-korok` Human Touch felületre.

Falszifikálható hipotézis: egy új, priority 1 `template_redirect` MU-plugin
pontosan 302-t és query nélküli, konstans Human Touch `Location` fejlécet ad a
két production pathra, miközben a REST, admin/AJAX, `/hatas-korok-dev` és
`/impactshop-staging/hatas-korok-dev` ágak változatlanok.

Nem cél és tiltott változtatás:

- `impact-community.php`, `impact-community-app.php` és guide runtime;
- identity, profil-visszatérés, pont, szavazat, reward, pénz és callback writer;
- Offerwall vagy FactLens VB2026 runtime;
- cookie, query, profil vagy egyéb azonosító továbbítása;
- 301-es permanens átirányítás, scheduler, cron vagy watchdog.

## 2. Context and canonical sources

- Repo/owner: `office-hue/impactshop-notes`, `origin/main`.
- Base: `ef830af303d863c57999f0869a9fbbc6e7b86dde`.
- Dedicated worktree: `impactshop-notes-fix-hatas-korok-human-touch-route-20260821`.
- Live evidence: az app route 200-as legacy PHP HTML-t ad; a Sharity route 200-as
  Human Touch oldalt ad `Hatás Körök — Közösségek, nem követők` címmel.
- Canonical rules: `AGENTS.md`, `docs/impact-challenge-canonical-baseline.md`,
  `docs/protected-file-change-checklist.md`, `docs/pr-policy.md`,
  `docs/impactshop-deploy.md`.
- Existing legacy handler and APIs remain the rollback/reference oracle.
- Operator approval: a felhasználó a feltárt domain/runtime eltérés után
  kifejezetten kérte a hiba javítását.

## 3. Acceptance criteria

1. Production app route GET és HEAD válasza 302.
2. A `Location` pontosan `https://sharity.hu/hatas-korok`; query nem kerül át.
3. A céloldal 200 és Human Touch title/marker található benne.
4. Admin, AJAX, REST, nem támogatott metódus, dev és staging-dev nem redirectel.
5. Auth-status és circles REST payload shape változatlan és zöld.
6. Identity/profile-return, VB2026, Offerwall és gazdasági writer diffje nulla.
7. Az új modul maximális bástyavédelmet és hash-lockot kap.
8. Célzott és szükséges teljes tesztek, `git diff --check`, docsync/continuity,
   strict audit és checkpoint commit zöld.
9. Egyetlen push/PR/squash merge után csak merged exact-main, CAS/backup/relock
   exact-file production deploy történik.

## 4. Design and file-level implementation plan

- Új `wp-content/mu-plugins/impactshop-hatas-korok-human-touch-route.php`:
  ABSPATH guard; exact `app.sharity.hu` host; exact production path; GET/HEAD;
  admin/AJAX/REST kizárás; hardcoded query nélküli target; `nocache_headers()`;
  `wp_redirect(..., 302, ...)`; priority 1; exit.
- `scripts/hatas-korok-post-deploy-smoke.sh`: a legacy HTML-marker contract
  helyett exact 302/Location, target 200/Human Touch, query-drop, dev/staging-dev
  no-redirect és változatlan auth/circles shape.
- `tests/hatas-korok-human-touch-route.test.py`: statikus/tamper contract a
  szűk host/path/method, konstans target, priority és tiltott query-forwarding
  ellenőrzésére.
- `docs/hatas-korok-post-deploy-checklist.md`: új route truth és kézi mobil/
  desktop UI checklist.
- Bástya: `docs/impactshop-protected-files.json`,
  `docs/impactshop-guard-config.json`, `docs/impactshop-guard-config.sha256`,
  `docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`.
- Continuity: új protected change record, `docs/bastion-guard-status.md`,
  `notes.md`, `system-status-snapshot.md`.

Success path: WordPress minden MU-plugint betölt, majd a priority 1 callback a
szűk production route-on még a legacy priority 4 render előtt 302-vel lezár.
Failure/non-match path: a callback visszatér, így az örökölt handler és minden
API változatlanul működik.

Adatmigráció nincs. A redirect célja nem függ request inputtól.

## 5. Risk, coherence, and security review

- Nyílt redirect/query szivárgás: konstans cél, csak a path olvasódik, exact
  Location teszt; query és cookie nincs továbbítva.
- Dev/staging regresszió: host+path+method allowlist, külön negatív tesztek.
- API/identity regresszió: REST/admin/AJAX kizárás, legacy és identity fájlokhoz
  nincs touch, meglévő API shape smoke megmarad.
- Redirect cache/rollback: 302 + nocache; egyetlen additív fájl exact release,
  release-ID backup/CAS és eltávolítható rollback.
- Hook-koherencia: priority 1 megelőzi a legacy priority 4 renderelést; más
  route-on no-op.
- Érintett funkciók: kizárólag az app production Hatás Körök HTML belépőpont.
  Nem érintett: tagság, poszt, szavazás, REST, admin, test mode, profil,
  VB2026, Offerwall, affiliate, cron/watchdog.
- Deploy: csak reviewed merged main, exact-file guard; cél SHA/mód/Location és
  rollback release ID rögzítendő. Productionön 0444 visszazárás kötelező.

## 6. QA evidence

| QA | Method | Expected evidence | Result |
| --- | --- | --- | --- |
| QA-1 correctness | Live headers + legacy handler inspection | Legacy 200 and Human Touch 200 mismatch proven; priority 1 exact redirect closes the gap | PASS |
| QA-2 regression | REST/dev/staging/identity/VB inventory | Only production route is in scope; negative and API tests specified | PASS |
| QA-3 security | Constant-target and request-boundary review | No user input, query, cookie or identifier can enter Location | PASS |
| QA-4 operational/docs | Worktree, deploy guard, rollback and continuity inspection | Clean exact-main release path, required docs/hash locks and smoke commands identified | PASS |

Plan validation: `npm run codex:dev-plan:check -- --plan
docs/HATAS-KOROK-HUMAN-TOUCH-ROUTE-TERRA-PLAN-2026-08-21.md` from the canonical
`ai-agent` toolchain.

## 7. Rollback and observability

Before deploy: read-only remote absence/SHA/mode check, exact-file dry-run,
merged-main equality and release admission. Deploy engine creates release-ID
manifest and backup, CAS-validates, PHP-lints and closes mode 0444.

Rollback: run the deploy engine által kiírt exact
`bin/impactshop-guard-rollback.sh --production --apply` command with the
release manifestben szereplő release ID és deployed SHA értékekkel. For a first
install this removes only the unchanged deployed file. Then rerun route/API/
dev/VB smoke. No database rollback exists or is needed.

No cron/watchdog is added because the behavior is synchronous and fully covered
by the existing deploy smoke plus route availability supervision.

## 8. Luna implementation chunks

### Chunk 1 — Additive route cutover, bastion and release

- Files and interfaces: the new MU-plugin, route smoke, focused test, protected
  manifests/checksums and continuity documents listed in section 4.
- Preconditions: clean dedicated worktree, this plan passes, target Human Touch
  route is live, operator approval recorded.
- Exact change: implement the exact production-only hardcoded 302, update the
  route contract, lock the new module, validate, checkpoint, one PR/merge and
  one exact-file guarded deploy.
- Validation: PHP lint, focused Python test, shell syntax/smoke fixtures,
  protected-touch/strict audit, JSON/hash parity, required repository tests,
  `git diff --check`, docsync, post-deploy live route/API/dev/VB checks.
- Done when: app route resolves to the Human Touch design, protected surfaces
  remain unchanged, production file is 0444, rollback evidence and checkpoint
  commit are recorded.

## 9. Handoff decision

The architecture is additive and has no unresolved implementation choice.
Operator approval is explicit; identity, economic and VB2026 surfaces are
excluded. The plan is implementation-ready for one bounded Luna chunk. Actual
production mutation remains gated by merged-main exact-file release admission.
