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

A transport két meglévő definíciójának opcionális no-auth-refresh kapcsolója a konkrét rekurzív401 kockázat legkisebb javítása. A totals NGO-feloldása a tokenes normálhandoff közvetlen regresszióját előzi meg. Mindkét deployprofil együtt kapja az igazolt új fizikaihostot; új releasehelper nem készül. Új modulok a meglévő config/hashlistába kerülnek.

## Kockázat és rollback

Dupla/tévesfelhasználói pont, balance-verseny, hibásrefund és elveszettcommit: immutablebinding, dedupe, InnoDBtransaction, actual/forgivenhistory, singleconsumer és connectionlocktulajdonellenőrzés. Hiteles finaldonationhiány: manual és laneoff. NagyprodDDL: csak explicitadditív INPLACE LOCKNONE és boundedlockwait; partialjournal; nincs COPYfallback/bulkbackfill. Prod ésstaging közösDB eltérőprefix, fullDBrestore tiltott.

Rollback: új consumer/captureoff, tartósesemények megőrzése, bizonytalanállapot readback. Exactfájlrestore csak saját SHA/CAS/remoteID és koherensfileset; clickreward és régi consumer nem élesíthető vissza. Élesgazdaságimódosítás után nincs oldDBrestore/drop/automatikuskompenzáció. Existing guardedexactdeploy, függőségek loaderelőtt, hash/mode/0444readback; nincs szélesrsync. Karbantartás/drain és webhookreplay előfeltétel mégnyitott.

## Smoke és tényleges eredmény

Tényleges helyiPASS: money/state/admin/attribution/providerfixture; clickPHP/Python; balancewritermock; no-retry429; mindkét Dognettransport401/429fixture. Izolált MariaDB10.11.19 + az éles WP6.9.4 exactwpdbforrás:78checkPASS, többek között valódi rollback/lostACK/KILLCONNECTION, párhuzamosduplicate/refunddecay, freezehold, manualzero/replay és pending→expiry→finalbinding. Nincs élőprovidereset/deploy/aktiválás. A mégfuttatandó checket nem tekintjükPASSnak.

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
