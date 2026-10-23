# Impactshop notes installed-controller lifecycle — 2026-09-27

## 2026-10-23 — T2 forráspolicy, aktiválás előtt

Az alábbi T1 bejegyzések történetiek. A T2 forráspolicy friss feladata egyetlen
PR1708 motort választ: fcd987b5fc842419e01df2ea07ebcb21f6b4d931 /
4252eacfb65d65cf9a25f96d7e962336eaf23acfd91ec6d09fcc6a4cca39cc9d.
A központi policy külön commitja 4099c5a4; nem cserélhető össze a motorcommittal.
A routing explicit v2 (6-luna/medium, 6.1-sol/high, 6-astra/high); nincs
automatikus fallback vagy retry. A régi task saját policyCommit és motorpin
alatt marad. Valódi read-only context render ezt a saját régi taskon igazolta.

A kockázati döntés az aktuális műveletre vonatkozik. Opcionális Secretary
hiánya advisory; a raw failed check nem PASS. A push/PR/merge/host határ
továbbra tényleges identitást, jogosultságot, érintett ellenőrzést és
visszaállíthatóságot követel. A telepítő belépő nem ír automatikusan hookot:
prepare/apply/status/recover/rollback csak pontos ellenőrzött tranzakcióval.
Notes T2 itt még nem publikált/aktivált; 107 sikeres helyi client-eset nem
host-canary. Részletes kiadási sorrend és további feltételek a tasktervben.

## Scope and continuity

This source-only continuation keeps the exact remote-main base and the linked
worktree marker binding correction. The new target-local bundle is limited to
`office-hue/impactshop-notes`; it does not copy the central policy wholesale.

## Authorization and boundary

`operator-approval:dev-v4-linked-worktree-fix-20260927` covers the source-only
adapter and lifecycle contracts. The installed controller is selected by its
verified loader posture and one exact retained engine digest. Product,
provider, build, deploy, VPS, runtime and secret changes remain out of scope.

## Contract decisions

- `normal-source-short-brief` is the normal source selector and accepts an
  adapter-supplied short brief.
- `dev-governance-source` is reserved for DEV and governance surfaces.
- Governance start remains `planning-only`; a plan is bound only through the
  narrow adapter route `bind --task-id <id> --plan-anchor <40hex>` (with an
  exact policy commit only when required).
- `status`, `resume` and `context --render` are read-only views. Context refresh
  and consume are supported packet operations, not claims of zero metadata
  writes or authorization; legacy Stage B remains compatible and optional.
- The disposable smoke runs installed CLI start, status, resume, context
  refresh and consume, plus negative engine, origin and governance checks.

## Evidence

`tests/dev-v4-lifecycle-installed-cli.test.sh` passed with the primary tree
hash unchanged and covered planning-only governance start, committed plan
anchor binding, false anchor rejection and extra argument rejection.
`bash -n scripts/worktree-task-start.sh`, `node --check
scripts/dev-v4-lifecycle-client.mjs`, and `git diff --check` are required
focused checks.

## 2026-10-20 — T1 compatibility source continuation

### 2026-10-21 addendum — native continuity of a bound v3 task

`continuity --mode local|push` is a separate bounded read-only check using
installed `status`. It verifies actual Git identity and original task/policy/
engine pins, requires implementation-ready, preserves any compatible marker,
rejects conflicting/unsafe/orphan state and never synthesizes legacy evidence.
Push additionally requires clean source. Result is explicitly not publication
authority. The native shell guard selects it only for an existing own v3
namespace, clears the test data-root override and never falls back on failure.
Non-v3 worktrees keep the unchanged legacy checks. Planning/status/context
remain distinct; optional Secretary absence does not gate this check.
Evidence: final client 86/86 and legacy multi-active fixture PASS; exact audit,
failure diagnosis and remaining publication prerequisites are in continuity.

Exact plan: `docs/dev-plans/dev-risk-scoped-notes-2026-10-20.md`; continuity:
`docs/continuity/dev/2026-10-20-dev-risk-scoped-notes.md`.
The source candidate is implemented and independently reviewed; the historical
smoke evidence above does not prove current host installation. Exact evidence
is 65/65 pre-audit-repair integration cases plus 12/12 post-repair targeted
cases, syntax/diff checks, and the real read-only bound Secretary render. It
is not a full final-suite run, fresh-start or installation acceptance.

The adapter must use committed policy, not candidate JSON, for an unbound
start's exact canonical retained descriptor. It reads the locally known
`refs/remotes/origin/main` once, without an extra fetch. The controller's
internal fetch and actual-base verification still apply; this is compatibility
selection, not an expected-base CAS guarantee. A real start needs a new narrow
quiet window. Missing/ambiguous/unsafe inputs or incompatible drift stop that
operation; no installed-default fallback or automatic retry is permitted.
Policy reads and the forwarded controller environment must force
`GIT_NO_REPLACE_OBJECTS=1`: local replacement refs are not committed authority.

A bound task uses the dispatcher's own pinned-engine route; current working
tree policy cannot repin it or block its read-only recovery view. An unsafe
state or orphan lifecycle directory is not treated as an unbound fresh task.
Recovery means diagnose the exact error and supply the supported missing
input/package or perform an authorized source correction. Never edit the
capsule/state/default by hand or repair a shared cache from this lane.
Known native JSON error codes are preserved only through an explicit bounded
allowlist; raw, unknown, duplicate-field and malformed output remain sanitized.

The exact new read-only form is `context --render`, without operation or
refresh/consume flags. `absent` or `unavailable` optional Secretary context is
an advisory, not `retrieved`, a consume receipt or a permit. Actual Notes
pre-implementation render returned `writes:false`, packet `absent`.

T1 retains active v1 policies and predecessor pins. It does not activate 6/6.1
routing or couple old-model requirements into hooks. After four repository
merge witnesses for central S/P plus the three targets, separately authorized
Air and Mini engine staging/canaries precede separately approved T2 root-policy
changes. Final target acceptance is later, Air then Mini. Do not invoke 5.6 or
forge a typed old-model review; real 6.1 review evidence remains distinct from
normal lifecycle publication readiness.

## 2026-10-23 — Risk-scoped DEV: Air és Mini aktiválva, élőben ellenőrizve

Ez új állapotbejegyzés; a fenti korábbi kiadási/engedélyezési státuszok
történeti tények maradnak. A T1/T2 forráskiadás és a kétgép-es aktiválás
lezárult; termékdeploy vagy adatbázis-migráció nem történt. A PR1708 motor
megmaradt, T2 miatt nem készült második motorcsomag.

A részletes forrás-/host-/hook-/canary-bizonyíték és a pontos indítási,
visszaállítási, illetve ismert advisory feltételek:
[központi aktiválási záróbejegyzés](https://github.com/office-hue/ai-agent/blob/main/docs/continuity/dev/2026-10-20-risk-scoped-target-bootstrap.md).
Az opcionális memóriahiány nem forrásmunka-blokk; hiányzó kötelező tény,
azonosság-, jogosultság- vagy integritáskockázat továbbra is blokkol.
Új feladat routingja v2 (explicit 6/6.1 + reasoning); régi feladatpin nem változott.
Secretary context-render mindkét hoston valós; hiányzó opcionális packet nem
PASS és nem engedély. A záró öt-tengelyű, külön kért 6.1 Sol/high review nem
talált blokkoló hibát; nem natív modell-végrehajtási vagy lifecycle receipt.

A jelen DOCSYNC csak a már ellenőrzött aktiválást rögzíti. Saját
commit/push/PR/merge állapotát a kiadási visszaolvasás igazolja, nem ez a szöveg.
