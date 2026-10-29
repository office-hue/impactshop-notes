# Impact Shop egyszerűsített pont — protected change record

## Authority és koherencia

Arnold 2026-10-29-én jóváhagyta és implementálásra kérte a fájlszintű egyszerűsített tervet. Saját featureworktree, alap d953e525. A meglévő kód módosítása szükséges a pontkezelés és a normál redirect közös belépőihez; párhuzamos új pontrendszer nem készül. Két független tervaudit javításokkal, majd külön implementációs review/célzottrecheck. Öt konkrét finding javítva; lejárat utáni merchantkötés célzottSQL-tesztben. Ez nem élesverifikáció.

Közvetlen kapcsolatok: CJ/Dognet/signedpartner→capture→globális purchase/history/level; SAT1→subject és NGO; cron→consumer/reconciliation; admin→event-bound adjust; CTA→analytics/redirect0reward; decay/vacation→ugyanaz a balance. Jogosultsági határ és idegen WIP megmarad. Guide/JYSK tartalom nem módosul.

## Protected files touched

- `.deploy.production.env`
- `.deploy.staging.env`
- `bin/preflight-check.sh`
- `wp-content/mu-plugins/impactshop-ads-watch-ui-cta-bundle.js`
- `wp-content/mu-plugins/impactshop-ads-watch.js`
- `wp-content/mu-plugins/impactshop-boot.php`
- `wp-content/mu-plugins/impactshop-cj.php`
- `wp-content/mu-plugins/impactshop-click-tracking.php`
- `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`
- `wp-content/mu-plugins/impactshop-partner-api.php`
- `wp-content/mu-plugins/impactshop-rest-coupons.php`
- `wp-content/mu-plugins/impactshop-rest-totals.php`
- `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`
- `wp-content/mu-plugins/sharity-decay-manager.php`
- `wp-content/mu-plugins/sharity-points-api.php`
- `wp-content/mu-plugins/sharity-points-cron.php`
- `wp-content/mu-plugins/sharity-points-ledger-sync.php`
- `wp-content/mu-plugins/sharity-points-manager.php`
- `wp-content/mu-plugins/sharity-points.php`
- `wp-content/mu-plugins/sharity-vacation-manager.php`
- `wp-content/mu-plugins/impactshop-dognet-points.php`
- `wp-content/mu-plugins/sharity-points-impactshop-attribution.php`
- `wp-content/mu-plugins/sharity-points-money.php`

- `wp-content/mu-plugins/sharity-level-manager.php`
- `wp-content/mu-plugins/sharity-points-impactshop-manager.php`

A transport két meglévő definíciójának opcionális no-auth-refresh kapcsolója a konkrét rekurzív401 kockázat legkisebb javítása. A totals NGO-feloldása a tokenes normálhandoff közvetlen regresszióját előzi meg. Mindkét deployprofil együtt kapja az igazolt új fizikaihostot; új releasehelper nem készül. Új modulok a meglévő config/hashlistába kerülnek.

## Kockázat és rollback

Dupla/tévesfelhasználói pont, balance-verseny, hibásrefund és elveszettcommit: immutablebinding, dedupe, InnoDBtransaction, actual/forgivenhistory, singleconsumer és connectionlocktulajdonellenőrzés. Hiteles finaldonationhiány: manual és laneoff. NagyprodDDL: csak explicitadditív INPLACE LOCKNONE és boundedlockwait; partialjournal; nincs COPYfallback/bulkbackfill. Prod ésstaging közösDB eltérőprefix, fullDBrestore tiltott.

Rollback: új consumer/captureoff, tartósesemények megőrzése, bizonytalanállapot readback. Exactfájlrestore csak saját SHA/CAS/remoteID és koherensfileset; clickreward és régi consumer nem élesíthető vissza. Élesgazdaságimódosítás után nincs oldDBrestore/drop/automatikuskompenzáció. Existing guardedexactdeploy, függőségek loaderelőtt, hash/mode/0444readback; nincs szélesrsync. Karbantartás/drain és webhookreplay előfeltétel mégnyitott.

## Smoke és tényleges eredmény

Tényleges helyiPASS: money/state/admin/attribution/providerfixture; clickPHP/Python; balancewritermock; no-retry429; mindkét Dognettransport401/429fixture. Izolált MariaDB10.11.19 + az éles WP6.9.4 exactwpdbforrás:78checkPASS, többek között valódi rollback/lostACK/KILLCONNECTION, párhuzamosduplicate, freezehold, manualzero/replay és pending→expiry→finalbinding. Nincs élőprovidereset/deploy/aktiválás. A tényleges refund/decay átfedést csak az alábbi javított118checkes futás igazolja. A mégfuttatandó checket nem tekintjükPASSnak.

Kiadásutáni kötelező, MÉG NEM FUTTATOTT route/UI-smoke címkék:

- `browser:chrome` — live/UI pending
- `browser:webkit` — live/UI pending
- `flow:cta-click` — live/UI pending
- `flow:go-deal` — live/UI pending
- `flow:legacy-pool-visibility` — live/UI pending
- `flow:message-popup` — live/UI pending
- `flow:points-jump` — live/UI pending
- `flow:profile-return-account` — live/UI pending
- `flow:profile-return-restore` — live/UI pending
- `flow:reward-accumulation` — live/UI pending
- `flow:saved-offers-open` — live/UI pending
- `flow:video-start` — live/UI pending
- `route:factlens-vb-prod` — live/UI pending
- `route:impact-challenge` — live/UI pending
- `route:impactshop` — live/UI pending
- `route:profil` — live/UI pending

- `deploy:checksum-verify` — live/UI pending
- `deploy:guard-preflight` — live/UI pending

## Rövid kézi UI checklist

- Impact Shop és Impact Challenge: CTA navigál; nem ígér és nem ad kattintási jutalmat; videojutalom továbbra működik.
- Profil: pont/szint/history egyezik a ténylegesen igazolt jóváírással; visszavonás nem viszi0alá.
- Dognet/CJ redirect: a kiválasztott NGO megmarad, opaque token nem lesz NGO/nyerspseudo.
- Meglévő JYSK/pontflow, identityreturn és videó: érintett regresszióellenőrzés, nincs tartalmi módosítás.

DOCSYNC: termékterv/rewarddocs/partnercontract/notes/status/guardstatus és governancehivatkozás célzottan; nincs globálismemória/helperbővítés.

## 2026-10-29 — Elfogadott MNB-részletszabály folytatása

Felhasználói döntés: végleges jutalék 50%-a, a véglegessé válás napján
elérhető legutóbbi hivatalos MNB-jegyzés, egyetlen egész-HUF half-up
kerekítés. Érintett: meglévő money és CJ adapter, capture JSON-snapshot,
15 perces cron és a két meglévő Dognet transport no-refresh ága.
Koherencia/kockázat: a fenti gazdasági lánc változatlan; árfolyam-ismétlés,
részleges korrekció téves refundja és posting nélküli locking a konkrét
kockázat. Snapshot a meglévő event_jsonban, nincs új séma vagy release út.
Dognet cache nélküli no-refresh hívás nem indíthat kerülő login/retryt.
Ellenőrzés: money/MNB SOAP fixture, CJ final/duplicate/full/partial/cursor,
no-token transport, meglévő SQL state/manager regresszió arányosan.
Kézi UI: profil pont/szint/history, CTA továbbra0, redirect és NGO megmarad.
Rollback: új lane off; tartós esemény marad; guarded exact-file backup/CAS.
Ez a bekezdés felhatalmazás és terv, nem teszt- vagy deploy-PASS.


## 2026-10-29 — élő shared porttal való kompatibilitás

Konkrét kockázat: a kiadás felülírná az éles Offerwall közös pontportot.
A három shared modul byte-azonos rögzített canonical/live másolat; production
írásuk nem szükséges. Meglévő Impact Shop kód saját osztályba mozog,
level false visszagörgetést vált ki. Első/shop/referral jutalom üzleti
jogosultság ellenőrzésével másik rootról is folytatható; shared strict dedupe
változatlan. Érintett: loader, capture/bind, admin resolve, consumer,
post-commit reconciliation; közös gazdasági szabály nem változik.
118 valódi SQL-check és célzott fixturek sikeresek; review P2 javítva és
visszaellenőrizve. Részletek/korábbi fixture-korlátok: termékterv utolsó szakasza.
Bástya: az új manager a meglévő inventory/hashlistában. UI checklist fent,
kiegészítésként a meglévő Offerwall jutalom megjelenését is ellenőrizni kell.
Rollback: lane off, esemény marad, guarded backup/CAS; az éles shared fájlokat
nem írjuk felül. Nincs új séma vagy release-rendszer, nincs új deploy/aktiválás.


## Éles UI alapján szűk handoff follow-up — 80d6eb7f után

Előállapot: #238/80d6eb7f staging/production telepítve, schema/mode/hash és HTTP
visszaolvasva, CJ aktív; két rendes poll előrelépett, új esemény0. A meglévő saját
profil5891/Bronze és öt history sor DBazonos. A CJcoupon→Fillout→NGO próba
WPadminloginra futott; nincs intent/bind/pontváltozás. Cached click_url üres,
destination fallbackot a meglévő safe redirect adminra cserélte. Egy pontos hiteles
CJlink-read0rekord; nem ismételtük. Az ownercookieStrict külső első visszatérése
nem azonos a hiteles saját-origin kéréssel; valódi javított UI-próba még nyitott.

Koherencia/funkciók: meglévő boot go/go-deal→SAT1 prepare→bind/redirect;
CJcachedlinkpicker→generátor→allowlist; Netflix CJcouponlista két cacheszinttel.
Csak két runtimefájl és meglévő attributionfixture változik. A saját-origin200
landing fix belső URLt és nem-authority loopmarkert használ, JSON_HEX kódolással,
no-cache/no-referrerrel. Ownercsak a meglévő ellenőrzésből; nincs új grant/cookiepolicy.
Második ownerhiány/present-invalid controlled profilhiba, nem néma legacypontút.
Anonim/no-producer és meglévő Sharitysource út megmarad. Csak működő CJtracking
választható; végső URL existing wp_validate_redirect(emptyfallback), allowlist nem bővül.
Unavailablelink már bind előtt hibázik; az ilyen advertiser kuponja nem jelenik meg.

Kockázat: hoploop, payloadmódosulás, rawpseudo jogosultsággá emelése, adminfallback,
stale HTMLben elérhetetlen kupon. Két egymást követő független tervaudit között
átvezetve: failedowner controlledhiba; készURL validate; két cacheszint invalidálása.
Független implementációreview újP1/P2nélkül, attributionfixture különreviewerfutásban
isPASS. A fixture tényleges exit-bearing handlert izolált childPHPban futtat;
valódi cookieviselkedést, providert, SQLt vagy telepítést nem szimulálPASSnak.
Money/state/schema/sharedport változatlan, korábbi118SQL eredmény újrahasználva.

Kiadás/rollback: meglévő PR/requiredCI/quietwindowmerge/exactfilebackupCAS út;
nincs DDL vagy új teljes karbantartás. Staging majd production. Invalidate csak
impactshop_cj_coupons_v1 és a tényleges shortcodeparaméterből számolt érintett
impactshop_fragment_ kulcs; nincs blanketcacheflush. Két saját fájl pontos korábbi
SHAja rollback, consumer/captureoff szükség esetén, legacy marad0; nincs DBrestore.
Javítás jelenleg forráskész, nem merge-elt/telepített/élőben elfogadott.

Kézi UI: /impactshop/→csak elérhető CJkupon→NGOválasztás→egyszeri saját-origin
folytatás→tényleges affiliate/merchant cél, opaqueSAT1 és azonosNGO a tárolt intentben;
pont5891és history kattintástól változatlan. Nincs pénzügyi vásárlás. /profil/ szint,
pont/history és LegacyPool egyezzen; hiányzóowner kapjon profilhibát, ne adminlogint.
Az eredeti20természetes eseményes pénzügyi elfogadás és további providercontract nyitott.


## 2026-10-29 — Dognet normál jóváírás implementálva, publikálás előtt

Saját ág `feat/impactshop-dognet-confirmed-20261029`, indulóHEAD fb8cbaad,
a #239 merge fájlfájával azonos alap. Három runtime-fájl: Dognet-adapter,
a két meglévő transzport opcionális raw módja. Nincs DDL, core/shared
pontport-, globális Dognet-csatorna-, frontend- vagy release-rendszer-csere.

Hiteles read-only sale-minta: channel26081, campaign357, rtypeS, pozitív
total_cost, string order_id. A hivatalos API order_id-t használ rendeléshez;
namespace+campaign:order_id a vásárlás, rawid az esemény. Nincs idfallback.
EUR/EuropeBratislava korábbi accountbizonyíték újrahasználva. A jóváhagyott
postcutover SAT1 időkorlát explicit source-jelölést kap; nem pontos purchase-time.
P stabil kötést ment, A a tényleges jutalék50%-át MNBvel egyszer kerekíti.
A/D/összegváltozás és több raw rekord egy order alatt kézi; nincs feltételezett refund.
4oldal/4új FX-nap/poll, tartós egészlap-kurzor, saját ellenőrzött DB-zár;
provider/MNBhiba nem dob kivételt a közös cronba. Globális adchannel0 marad.

Tényleges helyi ellenőrzés: providerfixture, mindkét valódi transportfixture,
money/MNBfixture, három PHP-lint és139 valódi saját MariaDB SQL-check sikeres.
A SQL tényleges correlate/prepare/capture/manager kódot futtat, lejárt intentet,
P→A orderváltást, duplikációt és megváltozott végleges összeget is vizsgál.
Független review: P→A rawroot-megkerülés és zero-sale P javítva; második
körben Unicode-basis mérethatár javítva, célzott független recheck újP1/P2 nélkül.

Tokenellátás meglévő6órás auto-banner cronból, nem új authfolyamat: éles
callback10, következőfutás2026-10-30 00:32:33UTC, token cache jelen van,
lejárat21:36:03UTC. A pontpoll lejáratkor explicit hibával várhat a rendes
cache-megújításig (legfeljebb a következő6órás sikeres kör), eseményt nem veszít.
Ez ismert késleltetés, nem folyamatos tokenrendelkezésreállási PASS.
Host WP-CLI max_execution_time0; adapter hálózati becslés legfeljebb160s+DB.

Publikálás/CI/merge/telepítés/aktiválás még nincs ebből a változásból.
Kiadás: két transport előbb, adapter utoljára, Dognet OFF, exact-file backup/CAS;
bootstrap/hash/mode/kupontranszport smoke, aztán kizárólag Dognet lane+scanfrom.
Nincs teljes adatbázis-visszatöltés. Gazdasági rollbackkor a scanfrom ÉS a
Dognet namespace kikapcsolása szükséges a queued események miatt, CJ marad.
Kézi UIlista: ImpactShop kuponlista, aktuális partner→NGO→merchant; kattintás0;
profilpont/szint/history csak igazolt vásárlásból. Ezek új éles futtatása még nyitott.
Decathlon nem használható mintának; Árukereső korábbi CPC útpróba nem pénzügyi elfogadás.
