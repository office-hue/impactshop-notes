# TradeTracker bounded kuponolvasó – 2026-11-17

## Cél és jóváhagyott határ

A négyhálózatos kuponterv következő kis csomagja: új, additív `wp-content/mu-plugins/impactshop-tradetracker.php` írásmentes SOAP1.2 oldalolvasó és `tests/impactshop-tradetracker-coupon-page.test.php`. Szerverhívó adja a meglévő hitelesítőt; példányonként memóriabeli auth/session és fix saját site. E csomag nem tárol hitelesítőt, nem aktivál providert, nem ad publikus végpontot/CLI/cron/redirect/ledger writert. A régi, külön worktree-ben levő SOAP1.1 prototípushoz és idegen munkához nem nyúlunk. A meglévő kuponfeedbe való illesztés, teljes scan és programonkénti policy/aktiválás külön, az olvasó igazolása után következik; üres stub nem kész provider.

## Tényleges kvalifikáció

Arnold maga futtatta az előkészített egyszeri helyi próbát. A titokmentes0600 eredmény SHA256 `3967e1ddcf7b08abd87694ea3049d67d0872bf63408073e5c2d113a59baff48f`, mtimeUTC `2026-11-17T21:48:22.405533+00:00`. Saját site481183/Sharity Impact/www.sharity.hu accepted; authenticate → saját site → egy voucher → egy offer → két pontos kampányolvasás, mind6HTTP200. Voucher2513603/program23460 kódot tartalmaz, dátum2025-12-10..2026-12-31; offer2597875/program18982 nem kódos, dátum2026-11-17..2026-11-30. Mindkét pontos program accepted, cashback/discount-code allowed, Europe/Budapest, referencesSupported=true. A material `code` HTML mező nem voucherCode, URL-t nem találgatunk belőle. A valódi kód, anyag HTML, hitelesítő és session nem került az eredményfájlba/Gitbe. `credentialPersisted=false`, `activation=false`. A hatlapos olvasás nem teljes katalógus-, új parser-, publikuskupon-, adomány- vagy vásárlási elfogadás.

## Koherencia, hatások és kockázat

Az új osztály csak explicit PHP-hívásból kérdez; nincs betöltési hálózat/hook vagy meglévő pénzügyi hívó. A későbbi meglévő cache-only kuponfeed kaphatja az adatot, publikus kérés nem hívhat providert. Saját site és kampány egyezése, accepted státusz, külön aktuális traffic/publication/adomány policy szükséges. A díj/attribúció/NGO/subject/UUID útvonal ebben a forrásban nem módosul. Privát material code nem publikus tracking-link engedély.

Fix SOAP1.2 endpoint/namespace/actions, minősített WSDL SHA256 `1c38dd13079750ea188081bd261aa40e84449dc87d3035f317bd5735633a36ef`; nincs runtime WSDL-feloldás, redirect, auth-refresh vagy retry. Példány max20request/60s, max8s/request, max1MiB összes válasz; memóriabeli cookie csak fix hivatalos hostra. UTF8, NONET, DTD/ENTITY/NUL/nested/duplicate/idegen namespace elutasítás; provider fault és credential soha nem hibaüzenet. Hiányos adatok nem none/active. Éles titokelhelyezésre a korábbi egyszeri processzmemóriás engedély nem ad jogot.

## Fájlszintű kiadás és arányos ellenőrzés

Új PHP és közvetlen offline teszt; az új modul pontos védelmi bejegyzése a meglévő `impactshop-protected-files.json`, `impactshop-guard-config.json`, társ checksum/hash fájlokban. Nincs új guard/release/helper rendszer. Koherencia/új modul: Bástya-státusz, governance-plan, DOCSYNC-map, notes/status célzott forrásfrissítés. Teszt: valódi új osztály és szintetikus WP HTTP/XML, auth-cookie/saját site/kampány/voucher/offer, nil/üres/limit/XXE/namespace/auth/rate/transport; tiltott input0request és0writer. Az előző hat élő kérés a natív SoapClient proofja, nem az új osztály tesztje. PHP-lint + érintett új teszt, meglévő protected inventory/checksum-paritás és saját diff/audit. Nem kell új teljes web build vagy alias.

Forrás commit/push/PR; csak végleges head sikeres CI után új szűk mainablak a pontos merge-hez és szükséges egyszeri fetchhez, azonnali felszabadítással. Existing exact-file guard staging→production, kizárólag merge-elt új PHP fájl, friss absent/előhash és saját backup/CAS/readback. Betöltés, hash/lint/0444 és OFF külön ellenőrzött. A jelenleg aktív Dognet és a CJ OFF policy/config változatlan. A még nem futott teszt/deploy/aktiválás nem PASS.

## Mentés, rollback és kézi UI

Új fájl forrás-visszavonása normál reverttel; remote rollback az existing exact-file release saját, változatlan utóhashéről az eredeti absent/előállapotra. A releaseID/expectedSHA csak valós telepítés után rögzíthető. Nincs pénzügyi/DB restore, credentialrotáció vagy régi session visszatöltés. Kézi UI checklist: Sharity Vásárlási Segédben a meglévő Dognet három kupon/másolás/inputváltás tovább működik; TT még OFF, nem jelenhet meg igazolatlan kupon/adomány-CTA; meglévő partnermegerősítés nem romlik. Új TT éles UI-pilot csak teljes policy és vásárlási függőség után, külön eredmény.

## Kidolgozás állapota

A második, egyetlen legfeljebb1USD Hermes-csomag kifejezetten jóváhagyott: csak nyilvános WSDL, követelmények, szintetikus site/program/item/credential és titokmentes kvalifikációs tény; valódi siteID is helyettesítve a külső taskban. Task SHA256 `26457b4335a5804274f55bb090ced9ca0c3ff65c042d4c0c22955e6ff2473f1a`. A hívás befejeződött, azonosító `2239a7a0-ac78-4026-9bdc-7cdcccfb35e3`, becsült költség0,00265563USD (nem számla), újrahívás0. A draft változatlanul nem volt használható: WSDL-címre küldött volna, accepted ellenőrzés és teljes HTTP-keret hiányzott, kikapcsolható assertet használt. A helyi integráció ezeket javította; újabb providerhívás nem történt.

Új runtime és közvetlen teszt elkészült; PHP-lint,323offline assertion és teljes existing protected inventory0drift+6negatív checksum-eset sikeres. A meglévő detached exact-release preflight izolált tesztje sikeres; ez nem élő staging/prod preflight. Független Sol/high review egy P2-t talált: a következő kérés letöltési korlátja nem a teljes keret maradéka volt. Javítás: HTTP előtt maradékellenőrzés, maradék+1 sentinel korlát, teljes/majdnem teljes keret regressziós tesztek. Független javítás-readback és saját323assertion újrafuttatás sikeres; ebben a javításban nincs további konkrét P1/P2. Az új olvasóval élő parserpróba nem futott. Ez forrásjelölt, még nincs commit/merge/telepítés/aktiválás.

Protected files touched: `wp-content/mu-plugins/impactshop-tradetracker.php`, `docs/impactshop-protected-files.json`, `docs/impactshop-guard-config.json` és checksum/hash társaik. Smoke scope: `coupon:offline-contract,tradetracker:bounded-page,deploy:checksum-verify,deploy:guard-preflight`; ezek helyi ellenőrzések, a telepítési smoke külön, csak valódi kiadáskor jelenthető. Rollback a fenti exact-file terv szerint.


## 2026-11-18 – tényleges default-OFF kiadás és readback

A PR #290 pontos `e690f087e8dbe07b71c03138d4d48feaf91c7e58` headje mind a hat GitHub-ellenőrzésen sikeres; merge `a11279831939ee6147b7b2f331ecdce05c140af7`. Arnold külön legfeljebb2perces ablakában merge, egy Air/Mini main-fetch és azonos commit-readback történt, utána az ablak azonnal felszabadult. A deploy már azon kívül futott. Saját Mini exact-release checkout tiszta, detached a merge-elt commiton; nincs idegen WIP-módosítás.

A meglévő `bin/impactshop-guard-deploy.sh` kizárólag az új `wp-content/mu-plugins/impactshop-tradetracker.php` fájlt telepítette, staging után productionre. Mindkét friss előállapot absent; a release saját mentés/CAS/readback mellett készült, nincs kézi rsync/remote writer vagy új kiadási rendszer.

| Cél | Tényleges releaseID | SHA256 | Fájl / parent mód |
| --- | --- | --- | --- |
| staging | `20261118T060709Z-a11279831939-8c22e8e6` | `db93f31e05e4eb9eae3eeaaf61898d6c7adabcf678c4b90fb3312cd4fd4f3ff2` | `0444` / `0755` |
| production | `20261118T060821Z-a11279831939-652b4b61` | `db93f31e05e4eb9eae3eeaaf61898d6c7adabcf678c4b90fb3312cd4fd4f3ff2` | `0444` / `0555` |

Mindkét oldalon a PHP-lint sikeres, az új osztály és két metódusa betöltött. A forrás323offline assertion és a független review eredményét változatlan kód mellett újra használjuk; nincs új valódi SOAP-parserpróba. A kuponfeed `tradetracker.implemented=false`, enabled=false, refreshStatus=not_checked állapota helyesen változatlan: ez standalone olvasó, nem kész feedadapter. Nincs hitelesítő-elhelyezés vagy aktiválás. Staging minden providerOFF, nincs órás ütemező. Production Dognet ON/complete/errornull, checkedAt `2026-11-18T05:41:05Z`, validUntil `2026-11-18T07:41:05Z`, órás hook ütemezett; cache-only VisionExpress projekció available/complete/3ajánlat,0upstream. CJ OFF/not_checked; Admitad és TT OFF/not_checked. Konfiguráció fingerprint `65d2f4907742401d76645a4b046b3e8bda83b8b0c41c621a5f5dad96d19ee2af`, policy fingerprint `73de191668b3520121da2b25940571432ce0e4a5193af38aa4ff17b3e87ed128`: előtte/utána azonos. A névtelen éles RESTGET401. A WP-CLI meglévő stderr-zaja staging11/prod10sor; a production stdout utólagos5soros kimenete miatt a kiadási JSON objektumot külön dekódoltuk, nem futtattuk újra az olvasást emiatt. A célzott readback nem teljesrendszer-/teljesítmény-/kéziUI- vagy vásárlási PASS.

### Tényleges rollback

Csak a saját változatlan utóhashről, az eredeti absent állapotra, az existing guard útján:

```sh
bin/impactshop-guard-rollback.sh --production --apply --release-id=20261118T060821Z-a11279831939-652b4b61 --expected-deployed-sha=db93f31e05e4eb9eae3eeaaf61898d6c7adabcf678c4b90fb3312cd4fd4f3ff2
bin/impactshop-guard-rollback.sh --staging --apply --release-id=20261118T060709Z-a11279831939-8c22e8e6 --expected-deployed-sha=db93f31e05e4eb9eae3eeaaf61898d6c7adabcf678c4b90fb3312cd4fd4f3ff2
```

TT márOFF; sem Dognet-disable, sem config/policy-írás vagy DB-restore nem kell ehhez a standalone fájlhoz. Tényleges rollback nem történt. Backupot most nem töröltünk; kézi UI-review még függő a fenti checklist szerint. Webbuild/alias/DNS/Legal/pénzügyi adat változatlan. A teljes négyhálózatos aktiválás továbbra nyitott: új TT-parser élő proof, teljes scan/feed/érvényesség/publication+adomány kompatibilitás és megfelelő titokkezelési felhatalmazás; CJ/Admitad saját korábbi nyitott feltételei külön megmaradnak.
