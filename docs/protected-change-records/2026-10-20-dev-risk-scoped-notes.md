## 2026-10-23 — Téves deploy-besorolás javítása és review-lezárás

### Aktuális T2 protected source record

Protected files touched: az alábbi source/config/Bástya fájlok. Koherencia:
azonos saját repo és motor, változatlan termék/WordPress/env/deploy.
Kockázatok: téves engedés, régi pin sérülése, idegen hookírás.
Védelmek: natív guard, exact admission/full-validation, negatív fixture,
külön host-grant. Smoke: korábbi107 client, map2/2, teljes adapter-fixture,
read-only Secretary; nem élő deploy-smoke. Rollback: guarded source revert,
pinek/csomagok megtartásával. Kézi termék-UI N/A, host-canary külön szükséges.

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/dev-plans/dev-risk-scoped-notes-2026-10-20.md#t2-native-protected-source-admission",
  "operatorApprovalRef": "operator-approval:dev-risk-scoped-notes-t2-20261023",
  "protectedPaths": [
    "config/dev-delivery-v2-policy.json",
    "config/dev-v4/graded-delivery.v1.json",
    "config/dev-v4/lifecycle-contract.v1.json",
    "docs/bastion-guard-status.md",
    "scripts/dev-delivery-v2-adapter.sh",
    "scripts/dev-v4-target-hooks.mjs",
    "scripts/git-health-check.sh",
    "scripts/guarded-push.sh",
    "scripts/install-hooks.sh",
    "scripts/safe-repo-audit.sh",
    "tests/dev-delivery-v2-adapter.test.sh"
  ],
  "rollbackNote": "Guarded revert of exact Notes T2 source commits only; retain old task pins, engines and live product state.",
  "smokeTags": ["dev-t2", "hermetic", "docs-sync", "deploy:guard-preflight", "deploy:checksum-verify"]
}
<!-- END PROTECTED SOURCE ADMISSION -->

A scope-tag nem távoli deploy bizonyíték. A manifest adat, nem önengedély.
A korábbi T1-record lent történeti adat, nem aktív T2 admission.

A generic remote-write diagnosztikai címke többé nem végrehajtható parancs.
A tényleges távoli/provider parancsminták és a deploy-tiltás változatlanok.
A szűk scope-bővítést Arnold külön jóváhagyta (call_357a45a03c3946a9b6a1684ae5314d9a).
A teljes natív adapter shell-fixture sikeres, benne a valós safe-audit forrás
és 11 parancsminta negatív ellenőrzése. A módosított check-map 2/2 PASS;
most hét exact check-leaf van. A korábbi 107/107 más fájlhashhez tartozik,
változatlan részeit újrahasználjuk, nem állítunk új teljes-suite PASS-t.
Mind a policy-szelet, mind a classifier/regisztráció külön 6.1 Sol/high
öt tengelyű forrásreview-ja konkrét P1/P2 nélkül lezárult.
A natív protected source-admission, tiszta commit és CI külön hátravan.
A központi kétfájlos fázislista-bővítés helyben tesztelt, még nincs kiadva.
Nincs host-, hook-, pin- vagy provider-módosítás; teljes aktiválás nem történt.

## 2026-10-23 — T2 policy véglegesítés, helyi ellenőrzés

A Notes friss feladatainak forráspolicyja a mindkét hoston ellenőrzött PR1708
motorhoz kötött (fcd987b5 / 4252eacf); explicit v2, 6/6.1 routing és
műveletenkénti kockázati szabályok. Hat véges check-leaf választ érintett
teszteket; más termék útvonala nem kap általános DEV-suite-kötelezettséget.
A teljes lifecycle-client tesztfájl 107/107 esete sikeres (Node22.23.2,
42.850s); ez nem teljes repo-CI vagy host-canary. A különutas forrásreview folyamatban.
A valódi Secretary context --render írásmentesen ellenőrzött régi motorpint
és implementation-ready saját feladatot adott; packet absent advisory.
A központi T2 PR1709 merge 4099c5a4 igazolt, védelmei visszaálltak.
Notes T2 még helyi WIP: nincs új commit/push/merge, hook- vagy taskpin-módosítás.
Részletes binding, rollback és hátralévő kapuk a saját 2026-10-20-i tasktervben.

## 2026-10-21 — T2 célzott tesztválasztás: helyi source-ready

A tényleges delivery-policy öt véges DEV-ellenőrzést kapott; az örökölt
class/profile és git-diff-check változatlan. Két új contract fixture PASS;
a valódi motor path-resolverének 16 esete PASS, idegen termék- és suffix-utak
nem választják a DEV-suite-ot. Kért független 6.1 Sol/high delta-review: nincs
konkrét P1/P2. Ez nem teljes lifecycle-, CI-, telepítési vagy aktiválási PASS.
Secretary továbbra advisory, új receipt nincs; élő hook/task/policy-pin változatlan.
Részletek és hash-kötések: docs/dev-plans/dev-risk-scoped-notes-2026-10-20.md.


## 2026-10-21 — T2 hook-belépő és renderer: helyi, ellenőrzött forrás

A régi közvetlen installer helyén saját repohoz kötött, fagyasztott loader-bájtokat
ellenőrző belépő áll; nincs automatikus hook/config/alias vagy sibling írás.
Két entrypoint-teszt PASS (9 izolált Python-eset + valódi shell help); a hat-hookos
read-only renderer végső 8/8 fixture-je PASS (2.615s), a teljes meglévő paritáspróba
PASS az öt tiltott sibling/memória negatívval. A health-check korábbi általános
Node-név tiltása téves volt: a valódi foreign/memory tiltások maradnak.
Független kért 6.1 Sol/high review: a talált scripts-könyvtár-symlink P2 javítva,
külön sentinel-negatívval ellenőrizve és visszaolvasva; nincs nyitott P1/P2 ebben
a deltában. Ez nem teljes lifecycle-suite, CI, source publication vagy host PASS.
Pontos binding/diagnózis a saját tasktervben. T1/pinek és élő hookok változatlanok.
Következő: végleges T2 policy/check mapping és kiadási összekötés; Air/Mini
tranzakció/canary külön hátravan. Secretary advisory változatlan, receipt nincs.

## 2026-10-21 — T1 forrásmerge igazolva, T2 még nem aktív

[PR221](https://github.com/office-hue/impactshop-notes/pull/221) merge-elve: `2afced920b4087050b9d4587cf1cf34fd52bfbfd`,
tree `45e3ad68c2813e5e95551baf812dcbcba1e06d6c`; a candidate és a squash fa egyezik.
A kötelező candidate-CI sikeres, a teljes natív main-védelem változatlan;
nem történt bypass, kézi CI-újrafuttatás vagy termékdeploy. A központi pontos
fájlkör-kiegészítés PR1703-ban szintén merge-elve. A helyi T2-forrásmunka megmaradt,
a régi task-/motor-/policypinek nem változtak. Secretary context: writes:false,
verified motor, hiányzó packet advisory; normál lifecycle-publication PASS nincs.
Az új motor telepítése, T2 policy/hook/canary és teljes Bástya-elfogadás még hátra van.
Ez a lezáró DOCSYNC-bejegyzés helyi T2-WIP, még nem publikált dokumentációfrissítés.
A korábbi nyitott PR-ra vonatkozó sorok történetiek; a main-ablak felszabadult.

# Notes DEV T1 protected change record

## Végső natív útvonaljavítás — 2026-10-21

Kifejezett Notes-specifikus különutas forráskiadási jóváhagyás és elkülönített
6.1 Sol/high review alapján a continuity guard tracked módja100755 lett.
A módhiba miatt a valódi hook korábban kihagyta a guardot; a tartalma nem változott.
A közvetlen végrehajtás regressziós tesztje1/1 PASS, az installed pre-push
dirty-push negatív canaryja a várt continuity elutasítást adta. Ez megerősíti,
nem oldja fel a natív védelmet. A pozitív végső canary külön szükséges.
Rollback kizárólag normál guarded source revert; nincs shared hook/host/data
visszaírás. A gépi protectedPaths/rollback/smoke kör változatlan marad.

## Native source-admission binding — 2026-10-21

This block expresses the already approved Notes T1 source scope in the existing
repo-native format. It grants no product deploy, host install or central bypass.
Actual push/PR and merge authority remains separate. Only the native adapter may
produce clean-commit validation evidence; the record is not a PASS receipt.

<!-- HISTORICAL T1 SOURCE ADMISSION (not active) -->
{
  "schemaVersion": 1,
  "planRef": "docs/dev-plans/dev-risk-scoped-notes-2026-10-20.md#native-protected-source-admission",
  "operatorApprovalRef": "operator-approval:dev-risk-scoped-notes-source-20261020",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "scripts/dev-v4-lifecycle-client.mjs",
    "scripts/worktree-continuity-guard.sh"
  ],
  "rollbackNote": "Revert only the Notes T1 source commits through native guarded Git; preserve task pins, retained engines and all live runtime state.",
  "smokeTags": ["dev-t1", "hermetic", "docs-sync", "deploy:guard-preflight", "deploy:checksum-verify"]
}
<!-- END HISTORICAL T1 SOURCE ADMISSION -->

The two `deploy:*` tags are the unchanged native `deploy_guard` group's required
scope labels because this package touches `worktree-continuity-guard.sh`. They
are not a claim that production deployment or remote smoke ran. For this T1
source-only change, evidence is the reviewed guard/client hermetic fixtures,
actual read-only installed continuity preflight, checksum comparison against
the reviewed source, and unchanged deploy entrypoints/hash inventories at the
pinned base. Any future live deploy still requires its own full preflight.

The executable client and native continuity guard are both protected source
surfaces for this adapter. Their reviewed hashes and tests remain those in
continuity; the older text below is historical, not an assertion that the guard
is outside the package. The admission-format repair changes documentation only.

## 2026-10-21 continuity repair addendum

Affected function: native v3 continuity readback through the existing target
client; legacy no-v3 behavior is unchanged. Risk: wrongly granting continuity
for blocked, stale, mismatched or unsafe state. Countermeasures: verified
installed status plus independent Git/pin comparisons, explicit ready policy,
no legacy fallback or synthesized marker, clean push and no publication grant.
Final client suite 86/86; legacy fixture PASS; independent five-axis review
identified the readiness assertion and its regression repair. Exact evidence
and reviewer closeout are in continuity. Bastion-status update follows in its
own guarded commit; no guard/hash inventory or product code changes.
Rollback: guarded source revert only, retaining actual task pins and packages.
Manual UI checklist: N/A (no UI touched). Live/install smoke remains pending.

Authority: existing Arnold exceptional central DEV source task; exact plan
`docs/dev-plans/dev-risk-scoped-notes-2026-10-20.md`. Source only.

## Protected files touched

`scripts/worktree-continuity-guard.sh` adds the bounded v3 dispatch ahead of
unchanged legacy checks. Original bytes at plan anchor `59f81c32708f609f35d389c9616cd48a84152aab`:
SHA-256 `04a31f122fcff1b6829fb9bc970f83b6761654bfe7136fa4911fb9d98908c101`.
The reviewed new guard hash is recorded in continuity and must match when
reapplied for the separate protected commit. No guard policy/inventory changes.

`docs/bastion-guard-status.md` receives an additive source-status entry only.
Executable change is limited to `scripts/dev-v4-lifecycle-client.mjs`, with
private tests and DocSync. No protected-file list, hash, WordPress code, hook
generator, shared hooks, active policy or installed controller change.

Exact preimage retained in Git at base
`73beb6f4598acdca22e7910d39a0088cd5f38b14:docs/bastion-guard-status.md`;
SHA-256 `9ae12a0a072db2fa2f3faa7a5896c447b53705eb169271217037fee64182341d`.
It was read back before any protected status edit. No guard hash is regenerated.

## Coherence, risk and function

Fix repo-accepted descriptor selection for fresh start; preserve bound pins,
native lane/protected guards and v1 compatibility. Independent design/source
review and fixture evidence are recorded in the plan/continuity, not forged
controller receipts. Optional Secretary packet absence is advisory.

## Rollback

Normal guarded revert of this target source package. No host/default/task-pin,
runtime, data or provider mutation is part of T1. Future installation requires
its own tested exact rollback inputs and authority.

## Smoke checklist

Node22 hermetic integration 65/65 before audit repairs, then 12/12 targeted
post-repair cases; source/test syntax and diff hygiene PASS. Independent
five-axis review closed three concrete findings; exact source/test hashes and
the real read-only bound Secretary proof are in continuity. No full final-suite
claim. Native local protected-touch and lane checks run at their own commit
boundaries; protected status finalization is separate from the ops source commit.
Manual UI: N/A, no UI/product path changed. No live smoke or production claim.

The protected-status local preflight passed using the existing guarded input
route: this exact change record, explicit source revert rollback note, and
`dev-t1,hermetic,docs-sync` smoke scope. The protected predicate/guard was not
changed or bypassed. The executable source commit had already passed normal
native hooks and exact staged-tree readback separately.
# T2 push-belépő kiegészítés — 2026-10-21

A felhasználó jóváhagyott risk-scoped DEV feladatának szükséges helyi javítása:
guarded-push.sh és safe-repo-audit.sh base-feloldás, kötelező guardok futtatása,
push közbeni koordinációs írás eltávolítása. A tényleges termék, protected-modell,
Git-hook és deploy változatlan. Nincs gyengített lane/secret/continuity ellenőrzés.
Funkciók: helyi push előellenőrzés és audit; nincs UI/WordPress/üzletiadat-érintés.
Kockázat: rossz tartomány vagy kihagyott guard; ezért pontos range/deny/0644 és
első-push regressziók szükségesek. A 8b101fc3 T1 blobok adják a rollback alapját;
nem módosítunk közös hookot vagy másik worktree-t. Nincs új élő engedély.
A végső T2 PR-be való átvitelhez a teljes finite scope és független review kell.
Kézi UI checklist: termék-UI nem érintett; CLI-range, natív hookok és PR-eredmény
ellenőrzendő az aktiváláskor. Helyi/CI ugyanazokat a változatlan szemantikus
guardokat kapja, a wrapper nem helyettesíti a natív ellenőrzést.
