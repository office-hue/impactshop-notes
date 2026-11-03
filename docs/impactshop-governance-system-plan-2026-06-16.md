## 2026-11-01 Sharity P2w share-template hash sync

Only impactshop-ngo-card.php's approved share-template hash and manifest
checksum change. Guard logic/inventory unchanged; full existing hash checker
and six negative cases pass. See P2w protected change record and notes.
No deployment/activation; unrelated protected paths remain byte-identical.

## 2026-11-01 Sharity P3b inventory extension

The approved new Notes-owned display FX reader joins the existing protected
file inventory. Only its hash, the config self-hash and manifest checksum change;
guard logic and release rules stay unchanged. See protected change record
`2026-10-31-sharity-international-preferences.md` P3b. No deployment/activation.

## 2026-10-29 — meglévő Impact Shop műveletek osztálykiemelése

A valóban eltérő éles Offerwall shared pont/szint/decay port megőrzéséhez
az Impact Shop műveletek saját osztályba kerülnek. Egy új domainfájl a
meglévő Bástya config/hashlistában; guard-kód, jogosultság és kiadási
mechanizmus változatlan. A shared modulok rögzített kanonikus/live másolatok.
Forrás, staging és éles állapot: a 2026-10-29 protected record és snapshot.

## 2026-10-28 — egyetlen review-zott JYSK-digest követése

Külön operátori jóváhagyással csak a #231 merge-elt bridge hash-bejegyzése és
a manifest külső checksumja változik. A guard kódja/policy-ja, minden más hash
és a runtime forrása változatlan. Célzott strukturális/inventory/negatív teszt;
forrás-only adoptálás, nem kiadás vagy aktiválás. Rekord és rollback:
`docs/jysk-game-bridge-2026-10-27.md` aktuális baseline-szakasza.

## 2026-10-28 — egyetlen CI-digest célzott baseline-javítása

Explicit operátori scope: a #197-ről stale CI-hash követése az ellenőrzött
#215/#224/#225 merge-elt forrásig, és a manifest külső checksumjának egyeztetése.
Nincs guard-/CI-policy- vagy logikaváltás, más digest/inventory/env/runtime nem változik.
Valódi guardchecker-pozitív/negatív helyi fixture, eredeti CI/suite változatlan;
backup/rollback/érintettség: `docs/protected-change-records/2026-10-28-jysk-ci-guard-digest.md`.
Nem telepítés vagy aktiválás, a JYSK háromfájlos kiadási jogosultságot nem bővíti.

## 2026-10-25 — Notes audit: valódi finding elsőbbsége, rollout visszaállítva

A #225 merge `cab09c88ad2a976a8b774798e685148ad3b4fc22` fája
`f5cf2fcb4c0f7c417efbfc50bd03c302c034e12f`; a required hosted CI sikeres,
protection nem változott. A következő Air-aktiválás saját natív canary-je
valódi hibát talált: hiányzó product manifest eldobta a már megtalált secretet.
A támogatott Air rollback és 37WT HEAD/index/WIP/source readback sikeres;
régi hook/config visszaállt, current nincs. Mini26WT érintetlen, nincs deploy.

A follow-up a megtalált content/product hibát megtartja: exit1 elsőbbséget
élvez a későbbi product-data/path/runtime unavailable előtt. Hiányzó adat
önmagában továbbra advisory, nem sikeres product-audit vagy DEV-kapu.
25/25 érintett supportfixture PASS (20.715s), benne 3 új kombinált negatív;
független main diffreview lezárt, további material finding nélkül.
Repair/rollback/öt tengely: `docs/notes-workspace-support-repair-2026-10-24.md`;
rekord: `docs/protected-change-records/2026-10-25-notes-audit-findings.md`.
Új forráspublikálás/CI/merge és csak utána új csomag+hostcanary szükséges.
A lentebbi 2026-10-24-i állapotok történetiek.

## 2026-10-24 — Notes stabil support repair: terv, még nem aktivált

Saját ág: `dev/retirement-notes-support-20261024`; exact base:
`7417e18bad21a31a781917003af7b8ea61335838` (#224), tree
`802bf7224b10cb2de8a807a60025e7b81802a7be`.
Egyszeri exact-commit fetch történt; nincs main-chase. Air 37 + Mini 26 régi
WT-ben nincs helyi helper, mindegyik régi staged/foreign-WIP guardot használ.
Ezért a korábbi forrásmerge nem teljes hookaktiválás.

Repair plan és pontos 13 fájlos kör:
`docs/notes-workspace-support-repair-2026-10-24.md`.
Stabil Notes-specifikus, verziózott csomag + explicit trusted tool-root;
adat/manifest/record mindig a cél-WT, nem a supportcsomag.
A négy product checker ellenőrzési logikája és forrása változatlan.
Elfogadás: tényleges secret/protected/foreign-WIP/affiliate/Impi/root-drift
pozitív-negatív fixture; default local működés változatlan.
Most nincs commit/push/CI-attempt, hostcsomag-/hookapply vagy termékdeploy.

Helyi forrásjelölt: 22/22 új izolált fixture PASS; a régi érintett suite 34/35,
majd az egyetlen szöveges no-PASS regresszió javítva és külön 1/1 PASS.
Négy Python AST + CI YAML parse + diff-check OK; négy productchecker byte-változatlan.
Manifest-nonobject/symlinkciklus unavailable/advisory, nem natív DEV-kapu.
Független main-review két konkrét lelete javítva: metadata-típus/ciklus és
shell-child Python importárnyékolás; markeríró targetmodul nem fut a valódi
protected/lane ellenőrzésnél. Ez nem az alábbi önreview megállapítása.
Részletes eredmény, trust-határ és öt tengelyű review a fenti repair planben.

## 2026-10-24 — DEV-kivezetés: helyi forrás, aktiválás nélkül

A kijelölt `dev/retirement-20261024` clone base-e
`bbf2345416fe4aa2f4b49c8f02e57fa4ceb1a77c`.
Az engine/capsule/selector/modell/admission/repin kényszer kivezetve;
a kompatibilitási belépő `retired/advisory`, nem permission vagy PASS.
Natív Git, opcionális saját hook/audit, explicit backup/CAS/rollback telepítő;
nincs központ/sibling írás, automatikus fetch, hosttelepítés vagy termékdeploy.
A WordPress/Hatás Körök/affiliate/guide/JYSK/identity védelem és tényleges
deploy/restore kód megmaradt. Részletek:
`docs/dev-retirement-2026-10-24.md`;
protected rekord: `docs/protected-change-records/2026-10-24-dev-retirement.md`.

Evidencia: 34/34 retirement fixture + 1 új célzott CI content-only fixture; öt deploy/rollback mockteszt;
affiliate tamper fixture; Impi source audit; helyi natív titokscan sikeres.
Az exact-file fixture saját hiányzó staging-guard warningja látható maradt.
Az implementációs tesztkörben nem futott staging SSH, Codex bridge, provider vagy host-apply.
Forráspublikálás külön jóváhagyott PR-útvonalon; merge és deploy nem engedélyezett.
Ez nem teljes repo-CI/repo-audit, kliensbetöltés vagy élő acceptance.
A korábbi DEV-engine/pin kapuleírások történetiek; a termékszabályokat
az aktuális saját forrásból kell alkalmazni.

## 2026-10-23 — Téves deploy-besorolás javítása és review-lezárás

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

# ImpactShop Governance System Plan

## T2 push-forrásellenőrzés — 2026-10-21

Helyi, nem commitolt T2 javítás: egyetlen audit-base resolver, hibás explicit
ref elutasítása, kötelező regular/nem symlink guardok Bash-futtatása, azonos
range továbbadása. A wrapper koordinációs és post-push writerhívása megszűnt;
a telepített natív hook saját koordinációs viselkedése NEM változott.
Hat célzott regresszió PASS; az utólag hozzáadott upstream/no-base eset külön
1/1 PASS. Ez nem új teljes client-suite vagy CI PASS. Két fixture-setup hibát
diagnosztizáltunk: hiányzó home/env visszaadás, majd hiányzó szintetikus fetch
refspec. Mindkettő célzott javítás után sikeres; valódi fetch nem történt.
Független, kért 6.1 Sol/high review a két shellfájlban és első hat fixture-ben
nem talált P1/P2 hibát. A későbbi upstream fixture nincs e review-val lefedve.
Secretary context: writes:false, retained engine verified, packet absent
advisory. A saját feladat pinje és a publikált T1 HEAD8b101fc3 változatlan.
A T1 PR221 korábban igazolt required CI-je zöld; T2 push/merge/host-aktiválás
nincs. Kiadás előtt a központi T2 exact scope-ba a safe-repo-audit.sh is kell,
valamint a végleges T2 plan/selector/check mapping és review. A központi
fagyasztott T1 csomagot nem módosítottuk. Nem állítunk teljes-rollout készültséget.

## 2026-10-21 — Bound v3 native continuity repair

The existing risk-scoped T1 plan adds one owned path,
`scripts/worktree-continuity-guard.sh`, alongside the lifecycle client and its
tests. Bound v3 continuity uses installed status and actual pins, never a
fabricated v2 marker/default selector. Implementation-ready and exact current
identity are separate checks; clean push is required and no publication permit
is issued. No-v3 behavior and independent planning/context reads stay unchanged.
Final affected client suite 86/86 and legacy multi-active fixture PASS. Audit/
failure details are in `docs/continuity/dev/2026-10-20-dev-risk-scoped-notes.md`.
Central finite T1 scope extension and native publication remain pending; no
root policy, shared hook, product runtime or host installation changed.

Datum: 2026-06-16
Statusz: canonical local governance hub
Scope: rovid, repo-helyi belepesi pont az `impactshop-notes` governance, review, continuity es protected-lane szabalyaihoz.

## 2026-10-27 JYSK game source extension

The new default-off bridge joins the identity/points protected source inventory.
Only its new file and the explicitly approved pseudo writer/level transaction
fix receive updated hashes. Existing guard logic and remote/runtime state do not
change. Read-only production parity is proven through the Mini's own S59 key
against we040 with the known host-key alias; real database/staging acceptance and
independent review remain pending. See `docs/jysk-game-bridge-2026-10-27.md` and
`docs/protected-change-records/2026-10-27-jysk-canonical-points-transaction.md`.

## 2026-09-11 Sharity profile hidden push sync

## 2026-09-11 Sharity profile Elementor render-filter sync

The producer guard now uses Elementor's canonical boolean
`elementor/frontend/widget/should_render` filter (`accepted_args=2`) for exact
AdSense widgets and executable-marker-bearing `html`/`text-editor` settings.
The old `before_render` setter path is removed. No output rewrite,
auth/data/DB/cookie, shared dependency, provider, VPS, runtime, cron or
watchdog authority is added.

## 2026-09-11 Sharity profile AdSense hook-order sync

The profile producer guard removes the exact Site Kit callback before ordinary
`template_redirect` dispatch and retains a late catch-up scan. Generic HTML
settings are suppressed only for actual script, `ins`, or narrow AdSense URL
markers, so explanatory code samples remain visible. No output rewrite,
auth/data/DB/cookie, shared dependency, provider, VPS, runtime, cron or
watchdog authority is added.

## 2026-09-11 Sharity profile AdSense producer sync

The profile route now suppresses the exact Site Kit AdSense producer callback
after its `template_redirect` registration, and only matching generic
Elementor `html` widget settings with the two narrow AdSense signatures. This
does not use output buffering or rendered-HTML replacement and does not add
auth, data, DB, cookie, dependency, provider, VPS, runtime, cron or watchdog
authority. The live marker/CLS result remains a separate remeasurement gate.

The profile/data-role hidden push selector now explicitly reserves its
responsive height with `display:block !important` and `visibility:hidden`.
The HTML hidden attribute, JS/auth behavior and all global/shared dependency
boundaries remain unchanged; mobile `<0.1` is still a staging gate.

## 2026-09-11 Sharity profile responsive CLS sync

The profile-only inline stylesheet now reserves exact responsive dimensions for
the asynchronous push, points, votes and history blocks, including a hidden
push reservation. This is a bounded staging CLS correction with no auth, JS,
global/shared dependency, provider/VPS/database, cron or watchdog authority.
The mobile `<0.1` target remains a remeasurement gate.

## 2026-09-11 Sharity profile early-enqueue sync

The profile identity assets are now enqueued during the ordinary
`wp_enqueue_scripts` hook only when the canonical profile route is active.
This closes the late-inline-stylesheet CLS source without adding global CSS,
shared dependencies, provider/VPS/database authority, cron or watchdog state.
The staging `<0.1` CLS and zero-overflow results remain explicit remeasurement
gates.

## 2026-09-11 Sharity profile CLS correction sync

The profile-only identity panel now reserves space for its async points,
compact-summary and last-NGO blocks. This is a source-only layout-stability
fix for the staging Playwright CLS finding; no global CSS, shared dependency,
provider, VPS, database, runtime, cron or watchdog authority is added. The
mobile `<0.1` target remains an explicit staging remeasurement gate.

## 2026-09-11 Sharity profile subdirectory route sync

The staging UI blocker was a hosting-prefix mismatch: its WordPress home is
`/impactshop-staging`, while production is root-mounted. The source-only Luna
correction keeps the exact `home_url('/')` prefix in the shared profile route
classifier and derives the one-time reveal route/cookie path from the same
canonical helper. Root behavior, near-miss rejection, host/origin checks and
owner-grant policy remain unchanged. The continuity, protected change record,
guard hashes, notes and focused hermetic route tests are synchronized. No
provider, VPS, database, runtime, cron or watchdog authority is added.

## 2026-09-10 Sharity profile summary closure note

- A Luna checkpoint A kizárólag source-only profile summary és cache/privacy
  csomag: additive `identity_state`/`votes_available`, current-cookie
  SELECT-only vote read, fail-closed pseudo bootstrap és producer-szintű
  `/profil` AdSense/Site Kit/Elementor suppression.
- Az identity és AdSense források maximum-protected touchként, külön
  protected change recorddal, rollback- és smoke-scope-pal kerültek commitba;
  nincs push, PR, merge, provider, staging, production, schema execution,
  cron vagy watchdog authority.
- Kanonikus csomag:
  `docs/sharity-profile-summary-live-closure-luna-continuity-20260910.md` és
  `docs/protected-change-records/2026-09-10-sharity-profile-summary-checkpoint-a.md`.

## 2026-09-10 Sharity owner-policy Terra follow-up

- A conditional ads-watch debug route is absent-safe only while
  `impactshop_ads_watch_debug_enabled()` is false; a registered route still
  receives exact method/callback self-test coverage.
- Profile UI pseudo mutations are active-state-only, including vacation,
  last-NGO reset, push refresh/click and credential-save award. Failed initial
  or restore owner-cookie issuance restores the prior valid pseudo or expires
  the new one, with safe-disable on header restoration failure.
- VB2026 service admission uses the validated Bearer
  `x-sharity-pseudo-id` as the sole target principal. Invalid/partial bearer
  headers never fall back to a browser cookie, preserving native controls.

## 2026-09-10 Sharity public portal redirects note

- A két nyugdíjazott `adomany-automata-portal` út additív, production-only
  MU-pluginon keresztül ad át a `https://sharity.hu/` nyilvános főoldalnak.
- A fix `302` cél query-mentes; csak az exact host/path GET/HEAD ág él, míg
  admin, REST, AJAX, guide, donation/auction, identity és gazdasági writerek
  változatlanok.
- A modul a protected inventory, digest és `public_portal_redirects` smoke
  csoport alatt maximum-védett. Cronos/watchdog nem szükséges, mert nincs
  háttérfolyamat vagy állapotírás.
- Kanonikus csomag:
  `docs/sharity-public-portal-redirects-sol-plan-2026-09-10.md` és
  `docs/protected-change-records/2026-09-10-sharity-public-portal-redirects.md`.

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

## 2026-08-21 Sharity Shopping opaque production cutover note

- Az affiliate runtime, séma, retention cleanup és central watchdog aktív;
  az exact `src=shopping-assistant` boot adapter merged-main fájlként került
  productionre az egyfájlos CAS release lane-en.
- A provider felé csak opaque `sat1` mehet `d1` értékként, a raw pseudo és
  `data5` tiltott. A helyi correlation továbbra sem vásárlási, jutalék- vagy
  elszámolási truth.
- A max-védett állapotot a protected inventory/digest, a mutációs bástyateszt,
  a release manifest/rollback inspect és a central postactivation admission
  együtt igazolja; új cron/watchdog tuple nem keletkezett.
- Egy emberi Árukereső echo/correlation canary még kötelező, automatizált
  affiliate kattintás tiltott.
- Kanonikus release truth:
  `docs/sharity-shopping-opaque-sat1-production-cutover-sol-plan-2026-08-21.md`
  és
  `docs/protected-change-records/2026-08-21-sharity-shopping-opaque-sat1-production-cutover.md`.

## 2026-08-19 Deploy bastion manifest guard note

## 2026-09-10 Owner-policy maximum-bastion remediation sync

- The Sharity profile closure now treats the central owner-policy registry as
  a maximum-bastion source: every route has one method, effective policy,
  expected callback and source identity, and runtime self-test fails closed on
  any mismatch. VB2026 native service authorization remains endpoint-owned.
- Owner-grant mutation requires actual InnoDB readback; implicit engine
  conversion is rejected and remains a staging/operator prerequisite.


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

## 2026-09-10 Multi-active continuity note

- A kozos active pointer explicit primary szerepet jelent; egy worktree startja
  vagy push elotti regisztracioja ezt nem veheti at hallgatolagosan.
- A koordinacios pointer es snapshot lock alatt, azonos generation azonositoval
  keszul. A publikalo worktree exact branch/full HEAD/clean/decision truthja
  kotelezo; stale, dirty, generation-drift es lock race fail-closed.
- Egy ervenyes non-primary worktree `degraded` warninggal publikacio-kepes, igy
  a parhuzamos feature worktree-k nem irjak felul es nem uldozik egymas shared
  pointeret.
- A starter, coordination, continuity, guarded push es generalt hook contract
  teljesen repo-local. Nem keres es nem futtat sibling `ai-agent` worktree-t,
  memoria parancsot vagy shared Node dependency fat.
- A control-plane scriptek, CI contract es hermetikus linked-worktree teszt a
  deploy-guard maximum-protected keszletebe tartoznak.

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
4. a runtime worktree-start lane-nel a marker + decision + generation-bound
   coordination snapshot paritast, valamint a publikalo worktree exact
   full-HEAD/clean truthjat a local continuity guard kotelezoen ervenyesiti;
5. a coordination pointer, snapshot es lock kizarolag az adott repository common
   Git directoryjanak privat nevtereben authoritative. A workspace-global regi
   fajlok csak read-only, same-repo migracios forrasok, mas repository pointere
   nem blokkolhatja es nem irhatja felul az `impactshop-notes` truthot.

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

## 2026-08-23 Impi source-owner perimeter

- Az `impact-community-impi-source.php` additív, default-off, kizárólag GET
  projekció. Külön legalább 64 karakteres Impi credential, explicit circle-ID
  allowlist, 24 aktivitásos/4 kB-os felső korlát és forrásoldali PII-redakció
  tartozik hozzá.
- A forrás nem kaphat publication route-ot, SQL writert, browser-session
  fallbackot, legacy Impi/profile credentialet, cron/Cronos vagy második
  watchdog tulajdont. Identity, profile-return, points, votes, rewards, money,
  Offerwall és VB2026 változatlan protected perem.
- A plugin exact SHA-ja a nem titkos authority policyben lockolt; a külön
  `impact-impi-source-bastion-audit.py` fail-closed ellenőrzi a writer-,
  credential-, public-permission- és publication-driftet. A guard, policy,
  `docs/bastion-guard-status.md` és protected change record ugyanazon source
  release continuity-lánc része.
- Az internal route létezése is védett információ: hiányzó vagy hibás service
  auth/request-ID esetén mind a permission, mind a callback ág kizárólag
  `404 context_not_found` választ adhat. A maximum bastion ezt külön számlált
  forrásmarkerrel és hermetikus teszttel védi.
- A source merge nem runtime aktiválás. Secret, numerikus pilot ID, WordPress
  deploy és flag csak külön, operátori SOL admissionben jelenhet meg. A 30 nap
  maximum retention, nem kötelező várakozási idő.

## 2026-08-23 VB2026 autobanner exact affiliate source

- Affiliate source admission is an exact allowlist governance boundary.
- The only admitted values are shopping-assistant and vb2026-autobanner; a
  broad match or third value must fail the maximum bastion.
- Source placement, NGO mapping and HMAC subject are local correlation truth;
  provider-facing attribution remains opaque SAT1.
- The source extension cannot widen provider, identity, reward, vote, money or
  settlement authority and does not create a scheduler.
- Production closure is recorded in
  `docs/protected-change-records/2026-08-23-vb2026-autobanner-canonical-affiliate-bind.md`;
  `docs/bastion-guard-status.md` may contain only the matching exact-file
  release IDs, deployed checksums and rollback/admission evidence.
# DEV upgrade (2026-09-02): repository-local policy guard is authoritative over
# global prompts; governance-only changes reuse existing CI/affected filters.

## 2026-09-03 UNice CJ canary protected runtime

- The existing affiliate runtime may expose one separately gated CJ canary only:
  partner `unice`, program `cj-5824323-15487360`, fixed landing-only Max Click
  target and trusted NGO `gyoztesek-egyesulete`.
- Browser input cannot choose provider, program, destination or outbound query.
  The server-to-server intent requires the canonical active Sharity session and
  service credential, then returns only a random single-use Sharity handoff.
- The outbound CJ URL is built only after transactional handoff consumption and
  contains exactly one opaque `sid=sat1_...` parameter. Raw pseudo/session data,
  click-as-support claims and every financial or NGO Card writer remain outside
  this perimeter.
- The CJ flag is default-off; rollout and rollback use the exact-file protected
  release path. Existing Dognet behavior and the existing retention scheduler
  remain unchanged, and no additional cron or watchdog owner may be created.
- Canonical evidence is
  `docs/protected-change-records/2026-09-03-impact-shopping-unice-cj-adapter.md`;
  the runtime, source/mutation bastion, hash registry, status snapshot, notes and
  local doc-sync map form one required continuity unit.

## 2026-09-03 protected clean-main hash reconciliation

- A protected hash may be reconciled only to exact bytes already present on
  clean `origin/main`, with the introducing reviewed commit identified and
  explicit operator approval recorded.
- This admission is limited to `impactshop-boot.php@da343a6a` and
  `.github/workflows/ci.yml@c20f9c5f`; bulk guard initialization or unrelated
  baseline changes remain forbidden.
- Provenance, exact two-entry diff, mutation detection and clean-main release
  preflight are mandatory. The reconciliation does not authorize deployment of
  either artifact and does not weaken exact-file release CAS/backup controls.
- Canonical evidence is
  `docs/protected-change-records/2026-09-03-protected-main-hash-reconciliation.md`.

## 2026-09-04 DEV delivery v2 target adapter

The local adapter snapshots the audited target contract, verifies its digest,
classifies impact fail-closed and keeps candidate evidence in active-worktree
Git metadata only. It cannot deploy or invoke a provider.

## 2026-09-04 DEV delivery v2 base-binding correction

Both local guards now require an exact lowercase 40-hex base and HEAD that
resolve to commit objects, an exact HEAD tree object, and a successful
`base..HEAD` name-only diff before they classify a candidate. A missing,
non-SHA, unresolved base or failed diff is blocking and cannot become an empty
governance-only candidate. The existing `CI / validate` job keeps its name,
fetches full history, fetches and verifies the exact PR base SHA, then binds
that SHA to both guards through `DEV_DELIVERY_V2_BASE_SHA`.

Scope is guard/CI evidence only: no WordPress route, product runtime, provider,
remote write, scheduler, watchdog or legacy PHP exact-release engine changed.
Primary risk is a checkout without the referenced commit; it now fails closed.
Parity evidence is the two guard fixtures covering missing base, non-SHA base
and a non-governance changed path.

## 2026-09-04 DEV delivery v2 protected-admission correction

`scripts/**`, `.github/workflows/**`, deploy/config/guard paths and every path
listed by either protected-file inventory are now protected at minimum; unknown
paths remain blocked. Executable candidate content that requests provider deploy
or remote write is classified as deploy even when its pathname looks like
governance. `bastion` preserves the classifier decision and reports a separate
`bastionDecision`; it can pass a protected/deploy source candidate only after
the adapter itself has created exact base/HEAD/tree-bound private evidence by
running the existing commit-lane and protected-touch checks. Provider deploy is
always false. CI verifies the checked-out head/tree against that evidence.

## 2026-09-04 DEV delivery v2 P0 convergence

Operator approval reference:
`operator-approval:dev-delivery-v2-p0-fix3-20260904`.

- A name-status osztályozás rename/copy esetén a régi és az új pathot is
  értékeli, így protected fájl dokumentációs helyre mozgatása sem válhat
  governance-only változássá.
- A full validation kizárólag base/HEAD/tree-kötött evidence; nem írja át az
  `operator-review` döntést és önmagában nem ad source admissiont.
- Protected, nem-deploy source admission csak a changed recordban lévő exact,
  géppel olvasható teljes protected path-lista, feloldható tervhivatkozás,
  operátori approval ref és egyező privát validációs evidencia együttállásakor
  lehetséges. Deploy osztályban mindig tiltott.
- A tartalmi remote-write keresés kizárólag changed script/bin/deploy vagy CI
  workflow végrehajtható surface-en fut; docs és tests fixture szöveg nem
  minősíti át a változást deploynak.
## 2026-09-09 Sharity staging CAS source-admission bootstrap

The repo-local DEV-v2 contract contains one fixed source-admission profile for
the already completed Sharity staging CAS candidate. It is not a general deploy
class and grants no provider or remote authority. The profile requires the exact
seven protected CAS modifications plus the exact six continuity/support paths,
an immutable plan and approval reference, and a schema-v2 record whose reviewed
SHA-256 matches the byte-identical production companion at base and head.
Every protected candidate file also has a contract-pinned HEAD SHA-256 that the
manifest must echo and the adapter verifies from raw Git blob bytes.

The admission control itself is isolated on a separate branch and closes through
the ordinary schema-v1 protected lane, so it cannot admit itself. Control-plane
contract, policy, adapter, tests and CI remain maximum-protected and belong to
the deploy-guard smoke group. No runtime scheduler, cron or watchdog is involved.

# 2026-09-09 Sharity Profile SP1 evidence sync

The Sharity Profile SP1 package changes no governance executable or admission
semantics. Its `docs/bastion-guard-status.md` update records the existing
maximum-protection model applied to the already inventoried identity PHP/JS
surfaces, while candidate runtime hashes are pinned in the existing manifest.
Source publication remains guarded feature-branch only; staging/production
authority, shared hooks and deploy policy are unchanged. The canonical topic
map, status snapshot, notes, protected change record and package continuity are
synchronized in the same reviewed range.

The package stays entirely inside its dedicated `impactshop-notes` worktree.
The `ai-agent`-only `worktree-shared-deps.sh check --node-only` is explicitly
`not-applicable`; no sibling worktree or shared dependency repair is an
admission input. Protected source eligibility comes only from the repo-local
DEV-v2 adapter, its exact six-path manifest, the recorded source-only operator
approval and private base/HEAD/tree-bound full-validation evidence.

## 2026-09-10 — Sharity source-authority admission sync

The `impactshop-notes` Sharity NGO preference package is a source-only,
default-off additive adapter. Its protected admission is limited to the disabled
`.php.off` module and the bastion record; it has no provider, VPS, database,
secret, activation or deployment authority. The repository-local DEV-v2 adapter
and exact change record remain the source of truth for candidate evidence. This
governance sync is documentation only and does not change global DEV or release
semantics.

## 2026-09-10 Sharity staging CAS current-main repin

The existing exact staging CAS admission profile is rebound to the two future
manifest blobs derived from `origin/main@44547312`. The complete current
protected inventory is retained, while the already reviewed deploy, rollback
and test bytes stay fixed. The existing `.github/workflows/ci.yml` manifest key
is also corrected to its raw `44547312` SHA-256 `ab43c08a72c69d56187771b5b4f8cf81deda401d22685adf4cd84f0d52886015`;
no new inventory keys are added. The ordinary protected lane admits only this
metadata change; provider deployment, S59 access and runtime activation remain denied.
The deterministic future manifest uses `generated_at`
`2026-09-10T20:30:00+00:00` and pins manifest/checksum blob SHA-256 values
`a4863e43c5ea5c3ce6a407215764fe9cd33dec2897954d7eaec1044d5e32cea6` /
`b5fdeb82a868101e96ba5407ea32eb5403270ee6ebc1e90fdbf8d6e63f078c14`.

## 2026-09-10 Sharity owner-policy method-map admission sync

The staging-discovered WordPress REST method-map compatibility correction uses
the existing ordinary schema-v1 protected lane. The record covers the changed
owner-policy blob and its bástya metadata exactly; it does not add or modify a
schema-v2 source profile, adapter behavior, provider deployment authority or
remote execution path. Malformed mixed/indexed/non-boolean maps remain denied
as complete values, and exact callback matching remains mandatory. Staging is
kept fail-closed until live self-test PASS; production is unchanged. No cron,
Cronos guard or watchdog is introduced.
## 2026-09-10 Sharity profile bootstrap REST/quota checkpoint

The protected profile follow-up remains source-only and binds the two runtime
files `wp-content/mu-plugins/impactshop-boot.php` and
`wp-content/mu-plugins/impactshop-identity-panel.php` to the exact schema-v1
record `docs/protected-change-records/2026-09-10-sharity-profile-bootstrap-quota.md`.
The shared init/HTML cookie-touch predicate excludes query REST and
subdirectory `wp-json` paths while preserving the legacy query override. This
does not disable the public `GET /impact/v1/identity/profile` handler: its
no-cookie path intentionally owns exactly one pending issuance through the
same common prune/quota issuer. Public owner-grant issuance prunes expired
pending rows in a bounded transaction and enforces the 256-row pending quota
before token/cookie creation; verified recovery bypasses only the public quota.
The lock/read is bounded concurrency control under the normal InnoDB isolation
contract; non-default isolation remains a staging verification gate. No
provider, remote, database, cron or watchdog authority is added.
## 2026-09-11 addendum — profile dock source boundary

The Sharity profile lane retires the rendered legacy eight-action floating bar
in favor of one compact, read-only profile summary plus an explicit existing
account sign-in action. It reuses the established cookie-bound identity and
points reads and adds no authority, mutation, schema, scheduler or provider
surface. Protected hashes, rollback and route/profile smoke remain mandatory.
# DEV v4 Stage B v2 sync — 2026-09-11

The candidate remains v2-only on the exact Stage A base. A new task becomes
v4-aware only after a merged descendant contains the Stage B contract and a
fresh capsule binds origin/main, HEAD, tree and branch. No live authority is
added.

## Sharity NGO preference v4.4 G2 governance (2026-09-13)

G2 is source-only governance: reserved S4 profile-consumer and S5 additive
provider-activity selectors are protected, plan-bound, narrow, and PHP-gated.
The dynamic PHP receipt is base-owned with a 15-minute TTL; runtime, provider,
WordPress, deploy, VPS and secret mutation remain out of scope.

## 2026-09-27 — Linked worktree task-start marker binding

The task-start helper correction is a governance and continuity maintenance
change. Readiness, task-start guard and coordination sync now resolve from the
new linked worktree, preserving marker, decision-artifact and snapshot identity.
The change adds no product surface, provider/build/deploy/VPS/runtime/secret/
cron/watchdog authority. It is not a new module; the bastion status inventory
therefore requires no extension.

Focused linked-worktree and multi-active continuity tests pass, as do shell
syntax and `git diff --check`.

## 2026-10-14 — Impact Shop donation-points product selector R1

The source-only governance candidate registers the exact protected
`impactshop-donation-points-source-r1` selector for the already audited
donation-proportional global points package. The selector is Plan-ID-bound,
requires base-owned PHP/Git/static-test capabilities and names exactly 46
files; `writeSurfaceMatch=exact` prevents literal file names from admitting
suffix paths and includes staged index paths in its own source inventory. There
is no wildcard or broad directory authority; legacy selectors are unchanged.

Admission fixtures prove that an unmerged candidate cannot self-register the
selector, a merged base still requires a Plan ID, the exact surface is admitted
and both an additional path and a file-prefix collision are blocked. Product
source, provider, runtime, database,
scheduler, secret and production state remain unchanged. Merge of this
governance package is a prerequisite for a new exact-main product task, not a
product activation.

## 2026-10-19 — Pre-push policy parity R1

The bounded governance repair preserves the merged multi-active contract:
generated hooks remain repo-local and must not discover or run sibling
`ai-agent` memory or dependency commands. The stale health-check and policy
claims are aligned to that contract and verified in a disposable Git fixture.
No shared hook, product, provider, runtime, secret or deployment state changes.

The fixture pins `core.hooksPath` below its private temporary repository,
checks all generated hook paths and a bounded outside snapshot, and exercises
both the clean generated-hook PASS and an injected sibling-memory FAIL. The
hook generator itself remains byte-unchanged.

## 2026-10-20 — Risk-scoped DEV Notes T1 compatibility

Bound source task: `dev-risk-scoped-notes-20261020`, approved manifest and
supported bind at `9149a1735d3b32ac52d9e2006626d6bebb1d46c8`; exact plan
`docs/dev-plans/dev-risk-scoped-notes-2026-10-20.md`. The targeted client fix
selects a repo-accepted committed-policy descriptor for an unbound start and
preserves dispatcher-selected old pins for bound tasks. The narrow read-only
Secretary view is `context --render`; a missing optional packet is not a permit.

This is an existing adapter correction, not a new product module. It changes
no WordPress, protected inventory/hash, shared hook or installed default.
Active root policy stays v1. No real task-start/fetch is part of local tests.
T1 compatibility source, engine staging and T2 target activation are separate;
new-engine staging on both Air and Mini must precede root routing-schema-2.
Implementation checks and final acceptance must be read from the exact
continuity record, not inferred from plan/bind status.

## 2026-10-23 — PR222 multi-active fixture javítás, jóváhagyott forrásmunka

Arnold call_a409168c52bc4d16a430e84f2a7f2456 jóváhagyása egy új T2-útvonalra:
`tests/worktree-multi-active-continuity.test.sh`. CI run40013184982 validate
hibája: a régi teszt automatikus hookírást várt a no-argument telepítőtől.
Repair: az írásmentes helpet hookhash/mód és lokális config előtte/utána
ellenőrzéssel vizsgáljuk; a valódi renderer hat képét név/digest/mód-validálás
után kizárólag disposable repo hookmappájába írja a fixture. A pre-push továbbra
continuityt ellenőrizzen, de ne végezzen koordinációs regisztrációt.
Minden korábbi multi-active, foreign-worktree, HEAD-, generation-, dirty- és
lock-negatív teszt megmarad. A véges hook-check mapping és saját terv ugyanazt
az egy tesztútvonalat kapja. Production installer, hook, guard nem módosul.
Koherencia/kockázat: kizárólag teszt és checkkiválasztás; nem lehet a fixture
írásából host-telepítési engedély. Backup a f5c01c05 Git-forrás; rollback saját
forrás-revert, taskpin/csomag és idegen WIP változtatása nélkül. UI nem érintett.
Célzott shell-fixture, mapping és központi exact-scope teszt, majd független
6.1 Sol/high review szükséges; az eredmény külön lezárásban, itt nem előlegezett PASS.
Nincs új push/CI/merge, védelemmódosítás, host/hook aktiválás vagy termékdeploy.

### Szűk fixture-javítás ellenőrzése lezárva

Teljes multi-active shell-fixture PASS; mapping2/2 PASS (54ms); központi
exact-path negatív1/1 és finite-delta1/1 PASS. Független, kért gpt-6.1-sol/high
notes_multi_active_delta_review öt tengelyen nem talált P1/P2-t; nem normál
régi pinelt lifecycle receipt. Fixture SHA256:
1a8ba6faa6fde2c936b9e118429621a9e4954861331ec9f547a0aa741566a26b.
Notes strict helyi audit PASS a jóváhagyott protected-change recorddal;
a record nélküli korábbi tiltás helyes, nem utólagos PASS. Secretary rendered,
writes:false, régi motor verified, packet absent advisory. Git diff-check PASS.
Kiadási commitok, új candidate-CI és élesítés külön hátravan; nincs live módosítás.

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


### 2026-10-29 célzott szinkron

Az Impact Shop donationpoints egyszerűsített candidate új runtimefájljai a meglévő Bástya config/hash-védelembe kerülnek. Nincs DEVadmission/újguard/releaseengine. Lásd [termékterv](dev-plans/impactshop-donation-proportional-points-source-r1-2026-10-13.md). Source, merge és live állapot külön; liveprovider/drain feltételek még nyitottak.

### 2026-10-29 — Célzott MNB/CJ hash-szinkron

A jóváhagyott50%/MNB-szabály hat meglévő runtime fájlt érint; a meglévő hashjegyzék ezek tényleges tartalmát követi. A védett fájllista, guard-kód, jogosultsági és release-modell nem változik. Célzott money/provider/SQL teszt és független review; részletek a2026-10-29-es points change record és termékterv alatt.

### 2026-11-02 Donation D1 source inventory

Existing guard inventory extends to the new donation price loader and package JSON only. No policy/guard behavior change. See protected-change-records/2026-11-02-sharity-donation-prices.md for scope and acceptance.

### 2026-11-02 D2a donation quote pin

Existing inventory only: refresh sharity-ngo-donations.php after owner-bound quote and explicit additive CLI schema tests. No config/rule changes, no unrelated baseline refresh. Protected record and validation: docs/protected-change-records/2026-11-02-sharity-donation-prices.md. Runtime activation remains OFF.

## 2026-11-02 — D2b célzott donation inventory

A meglévő Bástya inventory három új saját fájlra bővül: sharity-ngo-donations/stripe-checkout.php, composer.json, composer.lock. Config önhash/oldalkocsik frissülnek; idegen hash és szabály változatlan. 197 offline valódi SDK-vizsgálat és független review, részletek a 2026-11-02-es donation protected-recordban. Nincs új guard, runtime-loader, endpoint vagy aktiválás.

## 2026-11-02 — D2c célzott donation inventory

Csak sharity-ngo-donations/orders.php került a meglévő védelembe; config önhash és checksumoldalkocsik frissültek, idegen pin/szabály változatlan. Teljes tényleges inventory és6 izolált hash-eset sikeres.77 valódi MariaDB-ellenőrzés és független scoped review; nincs loader/route/aktiválás. Részletes előállapot, scope, kockázat, rollback és kézi checklist: protected-change-records/2026-11-02-sharity-donation-prices.md §57. Korábbi pénzügyi adat megmarad.

## 2026-11-02 D2d célzott inventory

Két új saját donation-modul: http.php/runtime.php, meglévő donation loader pin és config önhash/oldalkocsik. Idegen pin és guard-szabály változatlan. Teljes inventory+6 hash-eset sikeres; auth/SDK/párhuzamos SQL határ célzottan tesztelt, független review blokkoló nélkül. Nincs telepítés/aktiválás. Koherencia, funkciólista, rollback és kézi checklist: protected-change-records/2026-11-02-sharity-donation-prices.md §58.


### 2026-11-02 D2e — aláírt webhook és session-helyreállítás

Saját új donation/webhook.php, kulcs nélküli signature SDK-loader, explicit inbox-séma/GET-only recovery.174 valódi MariaDB-,207 HTTP- és10 loader/konfigurációs ellenőrzés sikeres; független forrásreview blokkoló nélkül. Nincs valós providerhívás, pénzügyi könyvelés, szavazat vagy aktiválás. A kontrollált saját tesztkonténer eltávolítva. Teljes scope, kockázat, javítás, költség és kiadási/UI/rollback lista: docs/protected-change-records/2026-11-02-sharity-donation-prices.md §59. Hermes037 egyhívásos becslés0,003256799USD, tényleges díj ismeretlen; fő működési hibáit Codex javította. További pénzügyi egyeztetés/account/UI/D3/D4 és VB7/Sharing12 nyitott. Forráskész nem merge/deploy/aktiválás.

D2e helyi Bástya-visszaolvasás: teljes meglévő inventory nulla drift;6 izolált negatív/hash eset sikeres. Guardlogika és távoli szabály nem módosult.


## 60. D2f — eredeti terhelés/díj olvasó, inaktív (2026-11-02)

N adapter + új admin finance-read CLI + additív loader: eredeti session/PI/charge/balance, Amplifier platform alkalmazásdíj és saját balance legfeljebb5 GET-tel. Pontos minorösszegek, külön tényleges költség/alaprész, külön devizák; refund/dispute/pending jelzés, payment_verified=false és allocation_eligible=false. Nincs adatbázis/pénzügyi író vagy aktiválás.397 offline pénzügyi/CLI ellenőrzés,197 adapterregresszió,12 friss folyamatú loadereset sikeres. Valódi SDK, helyettesített HTTP és CLI WPDB; nem éles/sandbox elfogadás. Független forrásreview blokkoló nélkül, külön futási teszt nélkül.

Részletes szerződés, források, pontos tesztparancsok, release/rollback/kézi UI-checklist és Hermes-javításlista: impactshop-notes `docs/protected-change-records/2026-11-02-sharity-donation-prices.md` §60, PR244. Hermes0381hívás/17451token/becsült0,002093USD, tényleges összköltség ismeretlen; a hibás első tervezetet Codex javította, megtakarítás nem állított. Secretary termékcheckpoint és remote-SHA külön visszaolvasandó.

Következő: teljes refund/dispute/díjkorrekciós mozgás-egyeztetés és tartós pénzügyi napló, nettó80/20-cél és kitettségi tartalék. Account/díj/felelősségi bizonyíték, UI fizetési lánc, D3/D4, affiliate/Offerwall és VB7/Sharing12 nyitott. Az eredeti charge nettója nem refund utáni aktuális nettó. Saját forráscsomag és célzott teszt kész; merge/telepítés/aktiválás/élő elfogadás nem történt. Maincsendablak nincs. Megőrzött átadás: SUBSTACK-HANDOVER.md.


### D2g 2026-11-02

Csak a sajat donation stripe-checkout.php es finance-read.php hash frissul; nincs uj guard/modul/idegen pin. Reszletes indok, teszt, release es rollback: protected-change-records/2026-11-02-sharity-donation-prices.md 61. fejezet. Aktivalas nincs.

### D2h 2026-11-02

Termekcommit ada1a5b4. Uj sajat finance-ledger.php inventorybejegyzes es hash; sajat fo loader, finance-read.php, a bizonyitott race miatt orders.php es config onhash/oldalkocsi frissul. Idegen pin es guardlogika valtozatlan. 281 MariaDB (ebbol105 D2h),400 penzugyi/CLI,461 adjustment,13 runtime ellenorzes sikeres. Fuggetlen forrasreview lelet nelkul. Erintett funkciok, smoke, release/rollback: protected-change-records/2026-11-02-sharity-donation-prices.md 63. fejezet. Meg nincs merge/telepites/aktivalas.


### D2i 2026-11-02

Termékcommit94344f09. Kizárólag saját finance-ledger.php és finance-read.php két hash frissül, nincs új inventory vagy guardlogika/idegen pin.936 MariaDB-assertion (655 D2i, fixture-checkekkel),401 pénzügyi/CLI+461 adjustment,13 runtime; független review lelet nélkül. Írásmentes admin pénzmozgásleltár, --apply elutasított, platformtételek accountbizonyítékig külön feloldatlanok. Részletek/smoke/release/rollback: protected-change-records/2026-11-02-sharity-donation-prices.md §64. Nincs merge/telepítés/aktiválás.


### D2j 2026-11-02

Termékcommit ee69bdb3. Csak a három saját donation main/orders/http hash és a JSON oldalkocsi checksum frissül. Nincs új inventory, guardlogika vagy idegen pin.101 quote+264 HTTP,950 MariaDB order/finance+18 quote,401 pénzügyi/CLI+461 adjustment+13 runtime,65 webteszt és független forrásreview sikeres. Kiválasztott mód/deviza explicit true, privát owner quote-read; nincs aktiválás. Részletek, smoke, release/rollback: protected-change-records/2026-11-02-sharity-donation-prices.md §65. Az első hashfrissítő lépés a checksum-formátum téves feltételezése miatt még írás előtt megállt; a tényleges JSON-oldalkocsi formátummal javítva, a végső ellenőrzés külön.


### D2k 2026-11-02

Termékcommit e3164546. Csak a saját donation főmodul hash és a JSON-oldalkocsi frissül. Publikus, írásmentes NGO mód/deviza elérhetőség; hiányzó függőség tilt, accountadat nem látható. 129 quote-assertion,13 frissfolyamat-loader,134 webteszt és célzott TypeScript-ellenőrzés sikeres; két review-lelet javítva és visszaolvasva. Korábbi változatlan DB-bizonyíték újrahasználva. Teljes web-typecheck idegen függőség/cache hibák miatt nem PASS. Nincs új inventory, guardlogika, idegen pin vagy aktiválás. Részletek, smoke, release/rollback: protected-change-records/2026-11-02-sharity-donation-prices.md §66.
## 2026-11-02 — shopping guard szűk illesztése

A jóváhagyott megerősített Dognet POST a meglévő affiliate-runtime modulban
konstruál linket; a guard csak e szakaszban engedi, a régi peremeket megtartja.
Nincs új guard vagy kiadási rendszer. Negatív suite sikeres, éles kiadás még nincs;
rekord: `protected-change-records/2026-11-02-shopping-purchase.md`.


### Sharity §67 — Notes main integrálva

Saját merge8c1800e9, távoli main d5ff78fe; külön jóváhagyott ablak felszabadítva. Hat docs/hash konfliktus rendezve, main shopping/auth és saját lokalizáció megőrizve;72 PHP+38 Chrome+guard sikeres. Nincs PR-merge/telepítés/aktiválás. Részletek: protected-change-records/2026-11-02-sharity-donation-prices.md §67.


## D2l movement-regiszter — saját baseline (2026-11-02)

Termékcommit bd17406b457456e15f600f9ad6fccbacda8cd45d. Kizárólag saját finance-ledger.php/finance-read.php hash és manifest-oldalkocsi frissül; nincs új inventory, idegen pin vagy guardlogika.1060 valódi MariaDB assertion (110 új registry),401offline financial/CLI,13runtime, független kód-/tesztreview sikeres. Kockázat, pontos CLI, funkciólista, kézi kiadási checklist, backup/rollback: protected-change-records/2026-11-02-sharity-donation-prices.md §68. A regiszter nem egyeztetett adomány vagy fedezet. Nincs mainművelet, telepítés vagy aktiválás.


## D2m backing — saját baseline (2026-11-02)

Termékcommit `bd38f6626d274dc4689cd23fa4a2d1420abf43c3`. Kizárólag saját donation finance-ledger/read két hash és manifest-oldalkocsi frissül; nincs új modul/inventory vagy idegen pin/guardlogika.1187 valódi MariaDB assertion (127backing),401offline financial/CLI,13runtime, független forrás- és tesztreview javítások után leletmentes. Pontos scope/CLI, saját operátori proof korlátai, funkciólista, kiadás/rollback/kézi checklist: protected-change-records/2026-11-02-sharity-donation-prices.md §69. Prepared-only belső foglalás, nem igazolt pénz vagy végrehajtható allokáció. Mainművelet, telepítés, aktiválás nincs.

## D2n target — saját baseline (2026-11-02)

Termékcommit057107ec75483b37ac48165646e0c8dea46568c0. Kizárólag a két saját finance-ledger/read hash, generated_at és manifestchecksum változik; eredeti timestamp és idegen pinek megmaradnak. Új inventory/guardlogika nincs.1274MariaDB (87új target),401offlineCLI,13runtime, független forrásreview leletmentes. Részlet, funkciólista és kiadás/rollback/kézi UI: protected-change-records/2026-11-02-sharity-donation-prices.md §70. Előzetes nettó cél és ismeretlen teljes liability külön; nem aktivált vagy pénzügyileg elfogadott.


## D2o account binding - own reviewed baseline (2026-11-02)

Product f61e09fb2a154e5e2bd6db3419c0e73f0dca6369. Only own stripe-checkout/finance-read
hashes, generated_at and manifest checksum change; original timestamp, inventory,
foreign pins and guard logic unchanged. 90 account checks including native two-connection
CAS/races;197checkout/401financeCLI/13runtime/129quotes, independent final review no findings.
Scope/proof/CLI/rollout/rollback/manual UI: protected-change-records/2026-11-02-sharity-donation-prices.md section71.
Source only; direct binding still needs real operational evidence. No main/deploy/activation.


## D2p platform movements — own reviewed baseline (2026-11-02)

Product `8fb07ce5ff991ce5209591afeca22968498dec6a`. Only own stripe-checkout.php and finance-ledger.php hashes,
generated_at and manifest checksum change; original timestamp, inventory, foreign pins
and guard logic remain unchanged. Actual platform account is read without Stripe-Account;
exact fee/refund movements share the existing registry, with legacy evidence preserved and
platform totals separated from NGO totals. Amplifier allocation and activation remain disabled.
1340 native MariaDB assertions (66 new D2p),437 finance/CLI,495 adjustments,197 checkout,
13 fresh runtime checks passed; independent source review found no issues and did not run tests.
Scope, manual UI checklist, rollout and rollback: protected-change-records/2026-11-02-sharity-donation-prices.md section72.
No main operation, deploy or activation.


## D2q verified counterleg — own reviewed baseline (2026-11-02)

Product `8dceb22e54c4ed5209585882768ae6bc403cee43`. Only the two own stripe-checkout.php /
finance-ledger.php hashes, generated_at and manifest checksum change. Original timestamp,
inventory, foreign pins and guard logic are unchanged. Exact native NGO credit and
combined-account conservation permit only a provisional net80/20 target; full liability
unknown and all financial activation false.1407 native MariaDB (67 D2q),437 finance/CLI,
521 adjustment (26 D2q SDK),197 checkout,13 runtime; independent final source review clear.
Existing rollout/rollback/manual UI checklist and costs: protected change record section73.
The local pin-update assertion first compared JSON key order; it stopped before any write.
Corrected to compare the exact two-key set; no foreign pin or guard was changed.
No main window, live money, deployment or activation.

## D2r Checkout observation — own reviewed baseline (2026-11-03)

Product11dd44d3a84c028a84bd1127a52e74bec12c89a4. Only the own webhook.php and
finance-read.php pins and manifest checksum change; inventory, metadata, foreign pins
and guard logic are unchanged. Bounded inbox processing uses existing finance authority,
no provider POST, execution or activation. Actual1767 native DB checks (360 drain),
437 CLI,13 runtime, PHP lint; independent final readback closed both review findings.
Full protected inventory zero drift and6 hash/checksum cases passed. Existing scope,
release, rollback, UI checklist and costs: protected record section75. The first staged
wrapper reported unavailable product guards while the two separate own pins were unstaged;
direct staged guards then passed. Baseline audit requires the existing explicit protected
change record/rollback/smoke environment; its first invocation without them was rejected.
This does not weaken or replace the guard. No main, deployment or activation.


## 2026-11-03 — D2s saját pénzügyi olvasó baseline

Csak sharity-ngo-donations/finance-ledger.php és http.php saját pinje változik.
Új modul/inventory/policy/guardlogika nincs.1839 natív DB (72 D2s),309 HTTP,
437 finance/CLI,13 runtime,65 web és scoped TypeScript sikeres. A credit
megjelenítés és a null→0 review-lelet javítva; végső független visszaolvasás leletmentes; reviewer nem futtatott tesztet.
Koherencia/érintettség/kockázat/saját rollback/kézi UI-checklist: protected record§76.
Két existing GET, eredeti owner/POST és pénzügyi adatok megőrzése. Nincs
mainművelet/merge/telepítés/aktiválás vagy pénzügyi elfogadás.


## 2026-11-03 — D2t saját lifecycle baseline

Csak a saját donation stripe-checkout.php és webhook.php pinje frissül,
existing inventory/guardlogika/idegen hash változatlan.2116 natív DB (277 D2t),
197 adapter,437 CLI,521 adjustment,13 runtime sikeres; stale state/balance
review-lelet javítva és célzottan visszaolvasva. Koherencia, kockázat, scope,
kiadás/rollback és kézi UI-checklist: protected change record§77. A tényleges
guard inventory nulla drift és6 checksum eset sikeresen ellenőrizve.
Forrásmunka; nincs mainablak, merge/deploy/aktiválás vagy pénzügyi elfogadás.

D2t termékcommit:946d8c2c4ef01a1fba4bbd549e1f57d85a21b54b.
