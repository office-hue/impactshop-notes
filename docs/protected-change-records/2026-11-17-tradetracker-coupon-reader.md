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


## Következő kis csomag: TT kuponfeed adapter, továbbra OFF

Célzott forrás: `impactshop-coupon-feed.php` meglévő provider-dispatch/mapping/offer-validátor bővítése TT-re, külön új offline teszt; a már minősített SOAP-oldalolvasó újragenerálása nélkül. Első draftként szűrt követelmény/interfész/szintetikus task előkészítve a saját ignored `tmp/tt-feed-20261118/task-tt-feed-003.md` fájlban, SHA256 `c24955fc085a772644187d9543cd768a88aec0ea1549f153967ee00fc2bd62b3`. Arnold az új, egyetlen legfeljebb1USD-os szűrt csomagot külön jóváhagyta. Hermes execution `ea23af84-edf6-4aff-b93e-e21c27d34870`, completed, recovery0, becsült0,00189792USD (nem számla), Secretary pending/terminal remoteACK. A SOAP-transport változatlan; a draft szerkezete részben felhasznált, jelentős helyi javítással. A draft hibás dátumirányát, hiányzó kereteket/mappingvalidálást és kikapcsolható assert-tesztjeit nem alkalmaztuk. Újabb hívás nincs. A feed forrásjelölt és a valódi függvényeket futtató offline teszt elkészült, még nincs új commit/merge/deploy/aktiválás.

Meglévő szerződés: egy processzmemóriás TT-client, explicit policy, a meglévő lock/fence/non-autoload normalizált cache és cache-only public projekció. Hívó által biztosítandó: megfelelő saját credential/client, friss site/program/publication/adomány-kompatibilitás, minősített dátum- és resourcejelentés. Új API: minimális TT mapping/date/normalizer/fullscan függvények; tartós titoktároló, új writer/DB/cron/release-rendszer nem készül. Két resource teljes, bounded lapozása kell; ismeretlen/hiányos adat nem none vagy aktív ajánlat. Privát HTML-kreatívból nem találgatunk linket.

Konkrét kockázat: a közös kuponfeedben a Dognet/CJ működés romolhat, az ismeretlen dátum vagy linkigény hamis publikus ajánlatot eredményezhet. Válasz: additív providerág, existing protected hash frissítés kizárólag saját runtime-ra, meglévő Dognet/CJ tesztek + célzott TT suite, egy arányos független forrásreview; a pénzügyi/NGO/handoff ág nem változik. Aktiválás nincs; a policy hiánya fail-closed. Exact-file staging→prod OFF, előhash/backup/CAS/readback, egy webbuild vagy új kiadási rendszer nélkül. Rollback csak a saját feed utóhashéről az előző kiadásra; jelenleg nincs új releaseID. Későbbi TT policy/config-írás globális fingerprintje a Dognet snapshotot is érvénytelenítheti, ezért ilyen aktiváláskor a meglévő Dognet-feltételek megőrzése és azonnali saját frissítés szükséges, nem most.

Friss discovery kizárólag az éles WP-CLI környezetben: TT/Admitad nevű user constant/env és optionnév nem található; értéket nem exportáltunk. Ez nem általános FPM/Keychain-hiánybizonyíték. A böngészőben most TT/Mitgo loginfelület van; a TT e-mail/jelszó mező üres. Korábbi API/account-kvalifikáció érvényes történeti bizonyíték, új parser-live és recurring secret input továbbra nyitott. A nyilvános keresés nem adott minősített null/inclusive-end szemantikát; ilyet nem töltünk ki találgatással. UI-checklist a meglévő kupon/másolás/inputváltás/partnermegerősítés, TT továbbra OFF; új kézi UI-PASS nincs.


### TT feed forrásjelölt – célzott ellenőrzés és kiadási határ

A meglévő `impactshop-coupon-feed.php` kapott TT mapping/date/normalizer/fullscan ágakat és a meglévő refresh/cache/public-allowlist/cron dispatch TT-támogatását. Egy processzmemóriás kliens kezeli az auth/site/kampány és mindkét resource összes kérését; rövid lap/sentinel nélkül nincs teljes scan. A kért oldal, site/kampány/resource/ID-sorrend és kumulatív20call/60s/1MiB keret ellenőrzött. Null/end-inclusive/resource/redeemMode jelentés explicit minősített mapping; ismeretlen vagy hibás jelölt binding-szinten withheld, nem false none. A kezdődátum nem csúszik egy nappal; zárónap a minősített inclusive döntés szerint következő helyi éjfél, DST-nél23/25órás nap. Leírás és feltétel együtt megőrzött; privát HTML/URL nem lesz publikus link.

**Új caller-supplied konfigurációs bemenet**, nem létezőként állított secret: `IMPACTSHOP_TRADETRACKER_CUSTOMER_ID` és `IMPACTSHOP_TRADETRACKER_PASSPHRASE` szerverenv, vagy env hiányában azonos nevű meglévő constant. Csak az explicit TT refresh olvassa; a forrás nem ír konfigurációt, kulcsot, sessiont vagy külső fiókot. Hiányuk0providerkéréses auth_unavailable; az actual authhiba/cooldown az existing CJ state-mechanizmust használja TT-re is. Most nem történt titok-elhelyezés vagy TT-policy/enable. A többi hálózat változatlan.

Új teszt `tests/impactshop-coupon-tradetracker.test.php` újra használja a meglévő offline WP-fixture-t és232Dognet/CJ regressziót;178további TT assertion sikeres, explicit throwing assertionökkel. Független Sol/high source review egy P2 végső hálózatcímke-hibát talált: a publikus allowlist Dognetre írta át a TT-címkét. Javítva, végsőprojekció-regresszióval; független readback232+164assertion sikeres, további konkrét P1/P2 a javításban nincs. Ezután14további keret/no-writer/withheld teszt ugyanazon változatlan forráson sikeres, végső232+178. PHP-lint és teljes existing protected inventory0drift+6negatív checksum-eset sikeres. A standalone TT-client323tesztjét változatlan osztály mellett nem futtatjuk újra csak a számlálóért. Nincs új webváltozás: a meglévő olvasó/UI már kezeli a TradeTracker hálózatot és kuponmezőket.

Protected files touched: meglévő `impactshop-coupon-feed.php`, csak saját digest a meglévő manifest/checksum alatt; nincs új inventory/guard/CI/release. Koherencia: Dognet/CJ forrásbeli főút megőrzött, TT ág explicit. Érintett funkciók: provider státusz, explicit refresh, meglévő snapshot és service-auth cache-only feed; pénzügyi/NGO/handoff/redirect/legacy sync nem változik. Commit/push/PR/CI után új rövid mainablak csak a pontos merge/fetchhez, azonnali felszabadítással. Egy meglévő exact-file staging→production feedkiadás OFF; friss előhash/backup/CAS/readback. Forráskész/merge/telepítés/aktiválás külön. E csomaghoz új parser-live/valós snapshot/publikáció/policy/kupon+adomány/kéziUI- vagy vásárlási PASS nincs.

Smoke scope: `coupon:offline-contract,tradetracker:bounded-page,deploy:checksum-verify`. Kiadás után pontos hash/lint/betöltés, TT implemented=true de OFF/not_checked, config/policy és Dognet friss cache/3ajánlat/0upstream megőrzése, anonim401. Rollback a feed saját változatlan utóhashéről a korábbi exact-file előállapotra; új releaseID csak valódi telepítés után. TT márOFF, most nem kell provider-disable/config-írás/DB-restore; a standalone olvasó külön telepítve maradhat. Kézi UI: Vásárlási Segéd kupon/másolás/inputváltás/partnermegerősítés, TT-hez nem jelenhet meg igazolatlan ajánlat vagy adomány-CTA; ellenőrzés még függő.


## 2026-11-18 – TT kuponfeed telepített, továbbra OFF

A PR #293 végleges `b1b890ac40bc1ce657649de4b9a19a5d307593b4` headjén mind a hat GitHub-check sikeres; merge `5b52787703a6555f00db3168ff77addb176d284a`, GitHub MERGED readback 2026-11-18T10:28:05Z. A külön jóváhagyott rövid ablakban kizárólag merge és egyszeri Air/Mini main-fetch/readback történt; mindkettő ugyanazt a commitot adta, az ablak azonnal felszabadult. A korábbi integráció megőrizte a main `7ec5f6a5` pénzügyi forrásait és digestjeit; ez nem pénzügyi funkcióteszt. A változatlan feed/teszt mellett a korábbi178TT+232Dognet/CJ ellenőrzést újra használtuk; integráció után a teljes protected inventory0drift+6negatív checksum-eset és a közvetlen saját diff-audit sikeres. A push-hook a már mainen lévő idegen történetet is sajátként kezelte; a közvetlen termékaudit után natív push történt, guard-/CI-módosítás nélkül.

A tiszta Mini release checkout detached a merge-elt commiton. A meglévő exact-file guard kizárólag `wp-content/mu-plugins/impactshop-coupon-feed.php` fájlt telepített staging, majd production célra, saját backup/CAS/lint/hash/readback mellett. Mindkét friss előhash `7ecbcc8f391168a08e72a7857a921d9489fb4f3260b1d4adaecf9be831e029c2`; a standalone TT-kliens változatlan `db93f31e05e4eb9eae3eeaaf61898d6c7adabcf678c4b90fb3312cd4fd4f3ff2`.

| Cél | Tényleges releaseID | Feed SHA256 | Fájl / parent mód |
| --- | --- | --- | --- |
| staging | `20261118T102940Z-5b52787703a6-0a954a5b` | `610658d55d0f4bde0874cdf0fb6debee2b764cc8b4b5a02d3628b311051cb77d` | `0444` / `0755` |
| production | `20261118T103030Z-5b52787703a6-aafba5a3` | `610658d55d0f4bde0874cdf0fb6debee2b764cc8b4b5a02d3628b311051cb77d` | `0444` / `0555` |

Mindkét célon TT implemented=true, enabled=false, refreshStatus=not_checked; a kliens és két metódusa betöltött. Staging minden providerOFF, nincs órás ütemező. Production Dognet ON/complete/errornull, checkedAt `2026-11-18T09:41:04Z`, validUntil `2026-11-18T11:41:04Z`, órás hook ütemezett; VisionExpress cache-only projekció available/complete/3ajánlat és0upstream. CJ OFF/not_checked, Admitad OFF/nincs feedadapter. A production config fingerprint `65d2f4907742401d76645a4b046b3e8bda83b8b0c41c621a5f5dad96d19ee2af` és policy `73de191668b3520121da2b25940571432ce0e4a5193af38aa4ff17b3e87ed128` előtte/utána azonos; staging fingerprintjei is változatlanok. Névtelen RESTGET staging és production401. WP-CLI meglévő stderr staging11/prod10sor, a redaktált readback a saját ignored `tmp/tt-feed-release-20261118/` alatt; titok vagy valódi kuponkód nincs a rekordban. Staging preflight1lassúsági figyelmeztetés (totals2090ms>2000ms); production preflight5/5OK. Az exact út az általános maintenance/smoke futást kihagyta; a fenti célzott readback külön történt. Ez nem teljesítmény-, teljesrendszer-, új SOAP-parser-live-, kéziUI- vagy vásárlási PASS.

### Feed rollback és fennmaradó aktiválási feltételek

Csak a saját változatlan feed-utóhashről az előző fájlra, a meglévő release mentéséből:

```sh
bin/impactshop-guard-rollback.sh --production --apply --release-id=20261118T103030Z-5b52787703a6-aafba5a3 --expected-deployed-sha=610658d55d0f4bde0874cdf0fb6debee2b764cc8b4b5a02d3628b311051cb77d
bin/impactshop-guard-rollback.sh --staging --apply --release-id=20261118T102940Z-5b52787703a6-0a954a5b --expected-deployed-sha=610658d55d0f4bde0874cdf0fb6debee2b764cc8b4b5a02d3628b311051cb77d
```

Rollback nem történt; TT márOFF, Dognet-disable/config-írás/DB-restore nem kell. Mentések megmaradtak. Nincs credentialpersist, TT policy/mapping-írás, enable, webbuild, alias-, DNS-, Legal- vagy pénzügyi adatváltozás. Az új környezeti/constant bemenetek forrásban definiáltak, nem már meglévő secretként igazoltak. A teljes TT aktiváláshoz új parserrel valódi bounded proof, minősített dátum/null/end-inclusive/resourcejelentés, saját program publication/adomány-kompatibilitás és jóváhagyott tartós szerveroldali hitelesítő-út kell; a korábbi natív6olvasás ezt nem helyettesíti. CJ kupon/handoff és Admitad programjóváhagyás/feedadapter külön nyitott. Kézi UI checklist változatlan: Vásárlási Segéd kupon/másolás/inputváltás/partnermegerősítés; TT-hez igazolatlan ajánlat/adomány-CTA nem jelenhet meg. Ez a kézi kör még függő.

A kiadási dokumentációs delta protected files touched: csak `docs/bastion-guard-status.md`, a jelen rekord, DOCSYNC-map és notes/status; runtime és manifest változatlan. Smoke scope: `docs:release-readback`; a fent rögzített telepítési readback tényeinek egyezését és a saját diff/titokellenőrzést vizsgáljuk, a változatlan teszteket nem ismételjük. Dokumentációs rollback: kizárólag saját új bejegyzések normál revertje, éles állapotmódosítás nélkül.

### 2026-11-18 — Saját felületi kvalifikáció; TT referenciaeltérés

Arnold befejezte a Mitgo- és TT-belépést. Az Admitad saját adspace2888572 tulajdonosi ellenőrzése külön engedéllyel egyszer beküldve; a frissített saját lista visszaolvasása **Under review**, nem Active. Nincs programcsatlakozás vagy aktiválás. A korábbi TT2FA URL a belépett fiók beállítási oldala volt, ezért az többé nem belépési blokkoló.

A TT saját site481183 / VidaXL18982 accepted; a kampány cashback- és kuponoldalt enged, a saját platformon lévő material2597886 kupon és a kampányhír november17–30. közötti Black Friday akciót jelez. A kupon csak kiválasztott termékekre szól; más marketingcsatornából származó kód nem helyettesítő. A kuponkód nincs ebben a rekordban. A saját kódgenerátor `a=481183` kötést adott, míg a lista előnézete `a=0`: előnézetből nem lesz saját tracking proof.

**Új, reprodukált outbound-kockázat:** a kódgenerátor a szintetikus `sat1_` +43 darab `A` bemenetből eltávolította az aláhúzást, és a generált `r` mezőben is47karakter maradt. A `sat1-` +43 darab `A` kontroll48karakterrel változatlan maradt. Ez nem terméktoken és nem elfogadott új tokenformátum. A [hivatalos TT útmutató](https://static.tradetracker.net/it/support_affiliate/Affiliate-Start-Guide-it_IT.pdf) 16. oldala255karakteres korlátot és aláhúzás nélküli karakterkészletet ad meg. A globális SAT1 vagy a tokenhash csendes átírása ezért tilos; veszteségmentes, providerhez kötött megoldás kell a meglévő korreláció megőrzésével.

A saját linkgenerátor a kampány által közölt `https://www.vidaxl.hu/black-friday` célhoz `c=18982,m=12,a=481183,r=<szintetikus referencia>,u=%2Fblack-friday` alakot adott; a kuponmaterialhoz `m=2597886,u=` alakot. A generált linkeket nem nyitottuk meg; ez csak formátum- és saját-site bizonyíték, nem redirect-, click/report-, konverziós vagy gazdasági elfogadás. A webes aktiválási policy és WP confirmed dispatch továbbra csak Dognet/CJ; a telepített kuponolvasó nem helyettesíti a TT vásárlási/attribúciós illesztést.

Következő: a jóváhagyott eredeti hálózati terv véges TT outbound-csomagja; külön feltétel marad a szerveroldali credentialút, a teljes új parserrel végzett próba, dátum/resource jelentés és a programablakhoz minősített retention. Új tartós kuponcél-kötés csak tényleges C3-szükség és külön egyeztetés után. Most runtime/config/policy/DB, telepítés és aktiválás nem változott; a korábbi410 offline ellenőrzés nem újrafuttatott PASS. A Qoder első tervéhez kész szűrt csomag kiküldési engedélye még függő.

### 2026-11-19 — Új élő olvasási bizonyíték; első draft még nem indult

Production readback: feedSHA `610658d55d0f4bde0874cdf0fb6debee2b764cc8b4b5a02d3628b311051cb77d`, TT-osztálySHA `db93f31e05e4eb9eae3eeaaf61898d6c7adabcf678c4b90fb3312cd4fd4f3ff2`, mód0444/szülő0555 és config/policy fingerprint változatlan. Dognet természetes frissítés `2026-11-19T05:41:07Z`, snapshot validUntil `07:41:07Z`, complete/errornull, órás hook ütemezett. WP cache-only VisionExpress available/complete/3ajánlat/0upstream. A meglévő ChatGPT `search_sharity_partners` friss válasza szintén három ajánlatot és teljes lefedettséget adott; authority fresh/complete, a kuponforrás checkedAt ugyanaz. A kliensnek visszaadott rövid projekció külön lejár, ebből nem lesz tartós beváltási garancia. A tényleges MCP-réteg célzottan elfogadott; kézi webes UI, vásárlás, attribúció és gazdasági elszámolás nem igazolt.

TT/CJ OFF/not_checked, Admitad implemented=false/OFF. A TT customer/passphrase és Admitad token env/constant bemenetek jelenlétpróbája a vizsgált WP-CLI környezetben false, értékkiolvasás nélkül; ez nem általános secretleltár. A readback első JSON-feldolgozása a JSON előtti egybájtos kimenet miatt sikertelen volt; a második, markerhez kötött feldolgozás sikeres. Nem volt refresh vagy konfigurációírás.

A szűrt Qoder004 tervcsomag kiküldése kifejezetten jóváhagyva, de a közös natív indító `pending_readback_required` választ adott a másik feladat uncertain foglalása miatt. Saját feladat nem ment ki, saját execution/checkpoint még nincs, új díj vagy retry nem történt. A foglalás érintetlen; másik gép/indító/provider nem kerülőút. A terméktények dokumentálása ettől független. Runtime/manifest/hashregiszter változatlan; a jelen dokumentáció saját bejegyzései normál reverttel visszavonhatók, telepítés nem kell.

### TT referencia-kódolás — szűk helyi implementáció

Arnold külön engedélyezte e munkacsomag első draftjának helyi Codex-kidolgozását. A Qoder004 nem ment ki, a másik feladat foglalása érintetlen. Cél: a ténylegesen megfigyelt aláhúzás-törlés miatt veszteségmentes TT wire-formátum az eredeti SAT1-hoz; a globális SAT1 és a tárolt tokenhash változatlan.

Koherencia/hatás: két tiszta statikus metódus a meglévő `ImpactShop_TradeTracker` osztályban, a meglévő offline osztályteszt bővítésével. Nincs új fájl, függőség, HTTP, constructor/auth, hook/route/cron/option/DB writer vagy linkkibocsátás. A már telepített kuponolvasó meglévő hívói változatlanok. A referencia nem jogosultság: későbbi fogyasztójának a visszafejtett eredeti tokenhez provider/site/campaign/owner/NGO/lejárat kötést is ellenőriznie kell; a dekódolás önmagában nem korrelál és nem aktivál.

Döntés: `tt1-` + a SAT1 eredeti32bájtjának64kisbetűs hex karaktere, összesen68karakter. Ez a dokumentált TT karakterkészlet részhalmaza,255alatt; minden bit megmarad. Mindkét irány szigorúan kanonikus: hibás típus/hossz/prefix/karakter, nagybetűs hex és nemkanonikus base64 padbit elutasítva, fix titokmentes hiba. Nincs csonkolás, karakterelhagyás vagy új tartós mapping/séma/retention. A formátum még nem élő provider-roundtrip bizonyíték.

Kockázat: hibás codec elvesztené az attribúciót; ezért ismert nulla/ff/szekvencia vektor, base64url mínusz/aláhúzás, roundtrip és elutasítási próbák,0HTTP és meglévő olvasó/feed regresszió kell. Célzott független kódreview a kanonikus határra; új pénzügyi binding vagy C3-cél nem készül, ezért a korábbi hálózati terv két auditját nem ismételjük. A mentett helyi előállapot a `35d7a8ba` commit; helyi rollback csak a saját codec/teszt/nyilvántartási diff normál revertje. Későbbi kiadás a meglévő exact-file OFF út mentés/CAS/rollback feltételeivel, külön merge-ablakban; enable nem része e lépésnek.

Elfogadás: tényleges lint, codec+meglévő osztály/feed tesztek és hashleltár; jövőbeli PASS nincs. Kézi UI későbbi telepítés után: Vásárlási Segéd VisionExpress kuponblokk megmarad, TT-hez nem jelenik meg új igazolatlan kupon vagy adomány-CTA; a referencia miatt nincs kattintás/vásárlás. Ez a UI-kör még függő.

Tényleges helyi eredmény: PHP8.4 lint,368osztályellenőrzés (ebből45új codec-állítás),178TT-feed és232baseline állítás sikeres. A meglévő teljes protected hashleltár0drift és6negatív eset sikeres; kizárólag a TT-osztály digestje lett `078b2b1381cae945aa36ff0fd0f3e1dcc17eebc9d0ca5070807669aa1c991caa`, a manifest checksum vele egyező, más hash változatlan. Külön `/root/tt_reference_review`, kért Sol/high, read-only review: konkrét P1/P2 lelet nincs; saját mindkét lint/diff-check/368osztálypróba sikeres. Ez a codec szűk forráskész állapota, nem új pénzügyi binding, telepítés vagy éles TT-roundtrip.


### 2026-11-19 — TT referencia-codec telepítve, továbbra OFF

PR #295 pontos headje `40c7a282b850422261f0db13077bf4a76012e6e8`, mind a hat GitHub-check sikeres; squash merge `db9a8712916904ebcbd7fff8f7a74332c188c5a8` (`2026-11-19T08:39:33Z`). Arnold külön merge-ablakában egy Air/Mini main-fetch ugyanazt a merge-et adta vissza; az ablak 6,83 másodperc után felszabadult. A kiadás ezen kívül futott, saját tiszta, detached Mini release worktree-ből.

A meglévő guard kizárólag az `impactshop-tradetracker.php` fájlt telepítette staging, majd production célra. Mindkét frissen visszaolvasott előhash `db93f31e05e4eb9eae3eeaaf61898d6c7adabcf678c4b90fb3312cd4fd4f3ff2`; saját exact-file mentés/CAS/lint/hash/readback történt. Más runtime, manifest, config, policy, DB, hitelesítő, retention, webbuild, alias vagy DNS nem változott.

| Cél | Tényleges releaseID | Utóhash (SHA256) | Fájl / parent mód |
| --- | --- | --- | --- |
| staging | `20261119T084146Z-db9a87129169-77ff0a63` | `078b2b1381cae945aa36ff0fd0f3e1dcc17eebc9d0ca5070807669aa1c991caa` | `0444` / `0755` |
| production | `20261119T084239Z-db9a87129169-1ee8c640` | `078b2b1381cae945aa36ff0fd0f3e1dcc17eebc9d0ca5070807669aa1c991caa` | `0444` / `0555` |

Mindkét cél PHP-lintje és codec-betöltése sikeres; a telepített kóddal végzett szintetikus roundtrip pontos, a referencia 68 karakter. A staging 4/4, a production 5/5 preflight végpontja sikeres, a távoli Bástya-manifest és production origin alignment egyezett. Az exact út az általános maintenance/post-deploy smoke-ot kihagyta; az itt felsorolt célzott readback külön futott. A forrás 368 osztály-, 178 TT-feed- és 232 baseline-ellenőrzését és független review-ját változatlan fájltartalom mellett újra használtuk; nem állítunk újrafuttatást.

Production Dognet ON/complete/errornull, természetes checkedAt `2026-11-19T08:41:05Z`, validUntil `2026-11-19T10:41:05Z`, órás hook ütemezett; VisionExpress cache-only projekció available/complete/3 ajánlat, 0 külső HTTP-hívás. TT implemented=true/OFF/not_checked; CJ OFF, Admitad implemented=false/OFF. Staging minden provider OFF, órás hook nélkül. A staging és production config/policy, feedhash és providerstátusz előtte/utána azonos; production config `65d2f4907742401d76645a4b046b3e8bda83b8b0c41c621a5f5dad96d19ee2af`, policy `73de191668b3520121da2b25940571432ce0e4a5193af38aa4ff17b3e87ed128`. A névtelen kupon-REST mindkét célon 401. A WP-CLI meglévő stderr-zaja staging 11, production 10 sor; az első stdin-alapú eval-file olvasás nem adott JSON-t, a bemenetátadás javítása után a natív eval readback sikeres. Ez nem provider- vagy termékhiba, és nem volt emiatt távoli írásismétlés.

Rollback kizárólag a saját változatlan utóhashről az exact-file mentett előállapotára:

```sh
bin/impactshop-guard-rollback.sh --production --apply --release-id=20261119T084239Z-db9a87129169-1ee8c640 --expected-deployed-sha=078b2b1381cae945aa36ff0fd0f3e1dcc17eebc9d0ca5070807669aa1c991caa
bin/impactshop-guard-rollback.sh --staging --apply --release-id=20261119T084146Z-db9a87129169-77ff0a63 --expected-deployed-sha=078b2b1381cae945aa36ff0fd0f3e1dcc17eebc9d0ca5070807669aa1c991caa
```

TT már OFF; nincs DB-restore vagy config/policy-írás. Tényleges rollback vagy mentéstörlés nem történt. A codec forráskész, merge-elt, telepített és betöltött, célzottan ellenőrzött; nincs még éles hívója, TT-roundtripje, vásárlási/elszámolási vagy teljesrendszer-elfogadása. A későbbi provider/site/program/owner/NGO/lejárat kötés, minősített retention, credentialút, új parser-live proof, coupon+donation kompatibilitás és az előző kézi UI-checklist nyitott marad. A Qoder004 csomag nem ment ki, a helyi Codex-kivétel érvényes, külső költés nincs.

Ez a kiadási dokumentáció csak a meglévő rekordot, Bástya-státuszt, DOCSYNC-mapet és notes/status fájlokat bővíti; runtime/hash/guard/CI delta nincs. Ellenőrzése saját diff és titokscan, a tényleges telepítési tények egyezése; visszavonása saját dokumentációs bejegyzések revertje. A központi DOCSYNC acceptedSource/lastVerifiedAt nem módosult; távoli acceptance nem következik a forrásjegyzetből.


### 2026-11-19 — Következő véges TT outbound-csomag, auditjelölt R2

Cél: a már telepített codec és kuponolvasó mellé egyetlen saját VidaXL-program megerősített vásárlási útja, alapból OFF. Ez az eredeti TT2/R3 hálózatbővítés; nincs C3 külön kuponcél, új tábla, séma, globális token/retention vagy pénzügyi writer. Arnold e TT-csomagra engedélyezett helyi Codex-kidolgozását használjuk; a már elkészült codec nem újragenerálandó. A fiók friss olvasásának DevTools-kapcsolata most 60 s után timeoutot adott, nincs retry vagy új élő qualification állítás.

**Meglévő bemenet:** saját site `481183`, accepted kampány `18982`, a saját generátorban igazolt merchant landing `https://www.vidaxl.hu/black-friday`, tracking origin `https://tc.tradetracker.net/`, merchant link `c=18982&m=12&a=481183&r=<referencia>&u=%2Fblack-friday`. A 68 karakteres codec pontosan megőrzi az eredeti SAT1-t és hashét. A kuponmaterial `2597886` eltérő linkje nem lesz e csomag külön célja. A kiválasztott termékekre korlátozott kedvezményt nem minősítjük merchant-wide ajánlattá. A material link szükségessége továbbra qualification: ha a kedvezmény csak azon jár, az ajánlat OFF és konkrét C3-egyeztetés következik.

**Caller-owned, jelenleg hiányzó élő tény:** szerver Customer ID/API-passphrase elhelyezése; friss saját site/program és promóciós/kupon+adomány jog; az új parserrel végzett bounded olvasás; a dátum/null/resource jelentés; a teljes reference saját click/report visszaérkezése; az első konverzió és elbírálás valós idejét lefedő megőrzési minimum. Ezek nem következnek a kódgenerátorból vagy a korábbi natív SOAP-próbából. A credentialt nem másoljuk ki, nem tároljuk WP-opcióban, Gitben vagy kliensadatban. Nincs fizetős hívás, vásárlás vagy új programcsatlakozás.

#### Minimális fájl- és szerződésdelta

| Fájl | Tervezett módosítás és határ |
| --- | --- |
| W `wp-content/mu-plugins/impactshop-tradetracker.php` | A meglévő codec használatával tiszta, fix saját kampány/site/landing HTTPS URL-builder; nincs HTTP/auth/constructor-változás. Csak a fenti igazolt merchant-link alak, `tt1-` referencia; raw NGO/owner/session/credential nincs URL-ben. |
| W `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php` | Név szerinti `tradetracker` branch a meglévő confirmed aktiválóban és új POST `/sharity/v1/shopping/tradetracker-activate`; külön GET/handoff nem kell. Egy alapból `0` TT outbound opció és egy meglévő opciórendszerben tárolt, titokmentes operator-review, nem új konfigurációs framework. A közös owner/NGO/UUID/payload-hash/tranzakció marad a writer; nincs második intent-writer. |
| W meglévő affiliate/TT fixture és `tests/shopping-purchase-mysql.integration.php` | Valódi új belépő tesztje, továbbá régi Dognet/CJ regresszió. A MySQL-harness csak meglévő privát, localhost izolált `impactshop_sandbox` konfigurációval és teszttáblákon fut. Hiányzó fixture vagy adatbázis nem PASS; éles DB-n nem tesztelünk. |
| W `docs/impactshop-guard-hashes.json` és `.sha256` | Kizárólag ténylegesen változott saját runtime digestjei, meglévő inventory megőrzésével; Bástya-státusz és e rekord/notes/status célzott frissítése. Guard/CI újraírás nincs. |
| A `apps/web/src/lib/sharity-shopping/affiliate-attribution-contract.ts` és teszt | Név szerinti `tradetracker` provider. Az eredeti belső SAT1-szerződés marad; a provider wire encoding kizárólag WP oldali. Nincs általános tokenregex lazítás. |
| A új `config/sharity-shopping-tradetracker-link-canary.v1.json`, meglévő `partner-activation-policy.ts` és teszt | Egy konkrét review-jelölt a fenti site/kampány/fix landinghez, kezdetben nem minősített/lejárt. Csak friss signed V2 authority pontos partner/provider/program/landing_only kötése és érvényes review ad policyt. Idegen termék-URL nem válthat csendben kampánylandingre. |
| A `activate/route.ts` és teszt | Zárt TT POST-dispatch, önálló alapból OFF web kapcsoló. Pontos fix origin/path/site/campaign/material/landing-paraméter és kanonikus 68 karakteres `r` validálása; duplikált/extra query, userinfo/port/fragment/encoded host/nested URL tiltása. Más provider URL-je elutasítva. |
| A meglévő `apps/web/src/app/api/sharity/shopping/mcp/contract.ts`, `apps/web/src/lib/sharity-shopping/shared-url-handoff.ts`, `apps/web/src/components/sharity-shopping/ShoppingAssistantExperience.tsx` és érintett teszt | Csak a meglévő policy hívóinak típus/TT-kapcsoló illesztése, ha ténylegesen szükséges. Ugyanaz a bejelentkezés, eredeti termék, választott NGO, explicit consent és confirmation. Kupon megjelenítés/másolás nem aktiválhat intentet vagy tracking kérést. |

W = saját `impactshop-notes` worktree; A = saját `ai-agent-impactshop-coupons-plan-20261116` worktree. Az első módosítandó web-fájlnál annak saját repo/branch/HEAD és szabályai újra ellenőrzendők. Más worktree-be nincs írás. Az új belső függvénynevek az implementációban választhatók; a jelenleg létező codec és confirmed függvények nem módosítható interfészként kitalált API-k.

#### Intent, review és megőrzés

A TT server-review rögzíti az exact Customer ID/site/kampány/program/merchant kötést, proof-refet, review lejáratot, jóváhagyott `retentionSeconds` és minősített `requiredRetentionSeconds` pozitív egész értékét; a tényleges reference/report és kupon+adomány kompatibilitás igazolását. Customer ID a szerver meglévő `IMPACTSHOP_TRADETRACKER_CUSTOMER_ID` env/constant értékével egyezzen; passphrase nem review-adat. A review nem származhat requestből. Hiányzó/nem igazolt/lejárt review vagy hiányzó outbound kapcsoló esetén nincs intent és URL. A webes review nem helyettesíti a WP független ellenőrzését.

E csomag nem enged 45 napnál hosszabb megőrzést: `requiredRetentionSeconds <= retentionSeconds <= IMPACTSHOP_SHARITY_AFFILIATE_RETENTION`. Ha az igazi TT ablak hosszabb, csak a függő outbound OFF; konkrét provideradat és külön tulajdonosi retention-döntés kell, nem globális emelés vagy elveszett adat visszaállítása. A sor a jóváhagyott expiry-t INSERT-kor kapja; retry, review-frissítés vagy új konfiguráció nem hosszabbíthatja meg. A meglévő cleanup/correlate már `delete_after` szerint működik.

A shared aktiváló explicit Dognet/CJ/TT allowlistet kap, ismeretlen provider nem eshet a Dognet ágba. TT-ben exact partner/program/merchant, friss szerver-review és migráció-readiness ellenőrzés történik. `provider_scope_ref` a server Customer ID/site pár, `provider_program_ref` a pontos új `tradetracker-18982` azonosító; az eredeti subject/NGO/snapshot/payload/token-hash kötés marad. A canonical binding és fixed merchant a payload hash része, a confirmation UUID az ownerhez kötött; másik provider/program/NGO/cél változása 409. Owner-grant/session/NGO lock és az aktív review recheck az INSERT előtt, ugyanazon connectionön; commit után csak tartós sor readbackjéből lehet URL. A TT eredményképző újra ellenőrzi a provider/site/program/subject/NGO/snapshot eredetileg tárolt kötését, a payload- és eredeti tokenhash egyezését, az intent és `delete_after` lejáratát, valamint az aktuális kapcsolót és változatlan, még érvényes review-t. Az első INSERT authority-snapshotja retry esetén sem íródik át; a BFF aktuális signed authority ellenőrzése ettől függetlenül minden kérésre kötelező. Nincs külön merchant-oszlop; a fixed review és a canonical payload-hash köti a célt. Bizonytalan COMMIT nem ismétel íróműveletet. A későbbi TT report-consumernek a visszafejtett SAT1 hash mellett a tárolt provider/site/program/NGO/subject és expiry egyezését is ellenőriznie kell; e csomag nem jogosítja fel capture-re vagy pontozásra.

#### Célzott elfogadás és kiadás

1. Offline: fix builder ismert vektorai, codec minden bitje, 0 HTTP/writer. Új route/gate/review és rossz scope/partner/program/cél/típus/dátum/retention/kulcs/lejárt review negatívumok; hiányzó review mellett 0 intent. Régi Dognet/CJ és kuponfeed változatlan eredményeinek regressziója csak az érintett úton.
2. Valódi lokális SQL: TT ugyanazon UUID-val egy sor/azonos URL, megváltozott NGO/provider/site/review/cél 409, lejárt confirmation/retention elutasítás; két owner-session konkurencia; grant revocation és NGO recheck; lost COMMIT readback/connection-csere; eredeti SAT1 hash és pontos frozen delete_after. Nincs éles vagy teljes történeti import.
3. Web: pontos signed authority + reviewed landing, OFF/review-expiry/wrong provider/idempotency és tracking-URL parser negatívumok; meglévő consent/owner/NGO/inputváltás tesztek. Célzott Vitest + szükséges typecheck, nem teljes build minden kis diffnél.
4. A tényleges pénzügyi kötés tervére két egymás utáni független audit, közben javítás; utána implementáció és szükséges célzott kódreview. Nincs az előző kuponterv vagy codec újragenerálása/auditja.
5. Saját scope commit → natív titok/product audit → push/PR → pontos head CI. Új szűk ablak csak main integráció/merge és egyszeri Air/Mini fetch/readback idejére; azonnali felszabadítás. A maint nem üldözzük. WP staging exact-file (csak két érintett runtime) → célzott OFF hash/lint/load/schema/config/policy/Dognet megőrzés → production ugyanez. Új migráció nincs. A valóban szükséges webváltozásokhoz egy meglévő, production beállításokat megőrző candidate és egy külön aliasablak; ne legyen helper/guard/deploy-rendszer projekt.
6. Aktiválás csak a fenti valós hiányok minősítése, friss saját signed authority és önálló TT web/WP kapcsoló után, egy programra. Előbb a kuponolvasó/publication kompatibilitása és friss cache; utána emberi, consenttel/NGO-val megerősített saját link és természetes click/report teljes reference; adományígéret csak bizonyítottan kompatibilis út mellett. A programjog/retention vagy tényleges report-kötés hiánya nem pótolható egy `true` állítással. Automatizált vásárlás/konverzió nem készül.

Rollback: a TT web/WP outbound célzott OFF, a working Dognet/CJ és reconciliation marad; már kiadott link nem visszahívható. Saját változatlan utóhashről existing exact-file backup/CAS rollback fordított sorrendben; web alias az előző pontos deploymentre, új review/config csak saját változatlan utóállapotból visszavonható. Intent/pénzügyi adatot, additív oszlopot, kulcsot nem törlünk; DB-restore nem éles rollback. Kézi UI a meglévő Vásárlási Segédben: OFF mellett nincs TT vásárlási/adomány-CTA, a Dognet kupon/másolás megmarad; későbbi ON mellett csak a reviewed landing, eredeti cél és választott NGO jelenik meg, input/account/NGO-váltás érvényteleníti a confirmationt, coupon copy 0 intent. Forráskész/merge/telepített/betöltött/aktivált/provider-live/pénzügyi elfogadás külön státusz. Most csak auditjelölt terv; új runtime vagy aktiválási PASS nincs.


Tervaudit #1: külön `tt_outbound_plan_audit_1`, kért Sol/high, read-only releváns WP/web forrás és az R1 delta. Konkrét P1/P2 nincs; nem termékteszt vagy live qualification. R2 köztes pontosítás: tényleges Customer-ID env/constant név, TT readback mező/lejárat/review recheck és immutable első snapshot; nem új tároló vagy scope. Tervaudit #2: külön `tt_outbound_plan_audit_2`, friss R2 és tényleges hívók read-only vizsgálata; konkrét P1/P2 nincs. Ez két egymást követő független tervreview, nem futási vagy providerbizonyíték.


#### R2 implementációs állapot — 2026-11-19, alapból OFF

Az új fix merchant URL-builder és TT POST-ág elkészült a fenti két meglévő runtime-ban. A provider allowlist explicit, az eredeti SAT1-hash megmarad; az owner/NGO/UUID tranzakció és a tartós COMMIT/readback közös. A TT authority-snapshot az eredeti payload hashhez kötött és immutable; a review jóváhagyott megőrzését az INSERT egyszer fagyasztja. Nincs migráció vagy új writer. Az operator-review nem került élesbe, a Customer ID/passphrase elhelyezése és az aktiválás nem történt meg.

A szükséges külön WP kódreview két P2-t talált, mindkettő javítva és ugyanazon reviewer által célzottan visszaolvasva/lezárva: (1) az izolált SQL COMMIT-hibainjektálás az intent INSERT darabszámára céloz, nem az előzetes account-binding COMMIT-jára; (2) a TT végső engedélyellenőrzés nem használhat kérésen belüli WordPress opciócache-t. Ehhez egyetlen helyi `impactshop_shopping_tt_current_review` a meglévő wpdb-kapcsolaton közvetlenül olvassa a négy kapcsoló/review opciót, új INSERT előtt `FOR UPDATE`, COMMIT után frissen. A max4096byte-os review kizárólag arrayként, `allowed_classes=false` mellett kerül feldolgozásra. Explicit hiányzó/hibás review nem fallbackel cache-re. Más provider változatlan. Új P1/P2 nincs; a reviewer valódi SQL-t nem futtatott.

Tényleges célzott ellenőrzés:

- PHP-lint: mindkét runtime és a bővített SQL-harness sikeres.
- `php tests/sharity-affiliate-runtime-test.php`: régi affiliate, confirmed Dognet és CJ PASS; 60 TT-assertion PASS, beleértve külön DB/cached állapot mellett pre-INSERT és post-COMMIT visszavonást, stabil UUID-t és immutable snapshot/expiry kötést.
- `php tests/impactshop-tradetracker-coupon-page.test.php`: 368 assertion PASS; `php tests/impactshop-coupon-tradetracker.test.php`: 178 TT +232 baseline PASS; `php tests/impactshop-coupon-feed.test.php`: 232 PASS. Ezek részben átfedő baseline-ok, nem összeadott egyedi lefedettség.
- Meglévő affiliate Bástya PASS; a guard tényleges teljes leltára 0 drift és 6 negatív checksum/hash eset PASS. Csak a két saját runtime digest és a manifest külső checksum változik, más pénzügyi digest megmarad.
- A valódi MySQL-harness új TT fixture-je kódolva/lintelt, **NOT RUN**. Az Air kb.19GB és Mini kb.20GB szabad helye mellett új DB-adatkönyvtár/full webbuild nem indult; Docker daemon nem elérhető. Ez nem SQL- vagy kiadási PASS. Nincs ismeretlen/megosztott DB használat vagy takarítás.

A webes pár ugyanazon nevű saját ágán fix reviewed landing policy, default-OFF env kapcsoló, zárt POST dispatch, közös raw canonical redirect-ellenőrző és MCP confirmation-handoff készült. Az URL-ellenőrző a már meglévő `shared-url-handoff.ts` modulban van, mert a tisztán fixture attribúciós szerződésbe való elhelyezést a változatlan Bástya helyesen elutasította. A web külön kódreview-jában nincs konkrét P1/P2; az utolsó változás csupán az ellenőrző áthelyezése és a hívók importja. 147 Vitest a policy/BFF/handoff/UI/MCP körben PASS, további 16 változatlan resolve/MCP-route regresszió korábbi PASS újrahasznált; a külön Node attribúciós szerződés 8 tesztje PASS. A UI-próba explicit NGO/consent nélkül 0 POST-ot és egy normalizálható, de nem kanonikus TT URL elutasítását igazolja. Typecheck a végső importáthelyezés után is sikeres. A teljes shopping Bástya a már telepített Node22.23.2/ABI127 alatt PASS; a kezdeti Node26/ABI147 SQLite-hiba környezeti eltérés volt, függőség/cache nem változott.

Jelen állapot: ellenőrzött helyi forrásjelölt, SQL és fullbuild nyitott; saját új commit/PR/merge/telepítés/aktiválás még nincs. A korábbi codec #295 és dokumentáció #296 merge/telepítési bizonyítéka változatlanul újrahasznált. A központi DOCSYNC acceptedSource/lastVerifiedAt nem változott; e bejegyzés forrásjegyzet, távoli acceptance nincs. Az alábbi kiadási út és rollback változatlan; a valódi SQL, biztonságos buildkapacitás és valós TT qualification csak a függő lépéseket tartja vissza.


## 2026-11-23 — TT vásárlási út telepítve, továbbra OFF

A #306 (`70a139e32f9144b11c42c9a878ea047642d42ed6`) két meglévő PHP-fájlja a Mini tiszta, detached kiadási példányából ment stagingre, majd sikeres ellenőrzés után élesre. A korábbi forráseredmények, 117 valódi SQL-ellenőrzés és 133 oldalas Next-build, érvényesek a változatlan TT-kódra. Az előző forrásjelölt bejegyzés az akkori állapotot rögzíti.

| Cél | TT-osztály release ID | Affiliate runtime release ID |
| --- | --- | --- |
| Staging | `20261123T081539Z-70a139e32f91-c1cc3b51` | `20261123T081553Z-70a139e32f91-c76a91b1` |
| Éles | `20261123T081722Z-70a139e32f91-71351b13` | `20261123T081739Z-70a139e32f91-858bd997` |

Mind a négy manifest, backup és élő fájl hashét visszaolvastuk. A PHP-fájlok módja 0444, a szülő stagingen 0755, élesben 0555. PHP-lint és szintetikus codec/URL-próba sikeres. Mindkét célon a TT outbound OFF; élesben a TT útvonal 403 `provider_disabled`, a névtelen kuponkérés 401 és a totals 200. A preflight lassú válaszokat jelzett. Az első staging HTTP-próbát a célzott curl-próba tisztázta.

A web #2185 (`6159d6607a94bf4fdbc84c7a92e62bc51c70abd0`) nyolc futásidejű fájlja az akkori éles forrásba került. A `dpl_GpV7LMJXkDiDWkLA3hsJfyXNeGPs` kiadás 5911 forrásfájlját és 61 megőrzött projektbeállítását ellenőriztük. A sharity.hu és www.sharity.hu alias erre a kiadásra mutatott, a Legal változatlan maradt. A webes TT flag hiányzik. A közös éles smoke 10/10 sikeres. A Vision Express a webes resolve és MCP útvonalon három ajánlatot ad; a TT-t a web nem minősíti jogosultnak, az MCP nem ad TT továbbvezető linket. Az eseti smoke szkript MCP fejléc- és státuszelvárásait a tényleges protokoll és válasz alapján tisztáztuk. A próba nem indított valódi vásárlást.

**Rollback.** Kizárólag a saját változatlan kiadási állapotból, a meglévő `bin/impactshop-guard-rollback.sh` úton, célonként előbb az affiliate runtime, majd a TT-osztály, a fenti release ID-val és az elvárt telepített SHA-val. Runtime: `8e34515c7119ec1a310daa4ea4603e7dea32b670821bc2f239b25ff8f83f8569`. Osztály: `df40a0b0b390abd98752226579baf05e11acd71a5a7d6cc7680006180098e11b`. A két Sharity alias visszaállási kiadása `dpl_HrcffEm8SDP5cQz1UzjuqfXejFYH`, külön rövid aliasablakban.

Forrás merge-elve, WordPress és web telepítve és betöltve, a célzott OFF-működés élesben ellenőrizve. TT-aktiválás, kereskedelmi jogosultság, teljes reference/report próba, valódi vásárlás és pénzügyi elfogadás függő. Secretary ACK `chk-ed216063-c524-4c00-be06-4f8e6b667b05` és `chk-f279460e-9ae4-4d37-8329-4ba0ff968402`. A központi DOCSYNC `acceptedSource` és `lastVerifiedAt` értékét ez a forrásjegyzet nem lépteti előre.

## 2026-11-25 — VidaXL egyetlen jóváhagyott kuponanyaga

Protected files touched: `wp-content/mu-plugins/impactshop-coupon-feed.php`, `docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`. A VidaXL-pilot kizárólag a 2597886 voucher megjelenítését engedi meg. A meglévő TT-frissítés a teljes lapozás és a sorok azonosításának ellenőrzése után szűr, a kuponsor normalizálása előtt. A pillanatkép és a REST-projekció a meglévő szerződést követi. A jóváhagyott voucher hiányakor egy másik ajánlat sem jelenhet meg helyette. Az érintett háttérfolyamat a TT kuponfrissítés és a kuponmegjelenítés. A változás futó kapcsolót, titkot, követőlinket és pénzügyi műveletet nem érint.

Smoke scope: PHP-lint, 185 TT- és 249 alap-assertion, teljes védett hashleltár és hat izolált checksum-eset sikeres helyben. Telepítés után ellenőrzendő a mentett fájlhash és a hitelesített REST-válasz, először stagingen, majd élesben. Kézi UI-ellenőrzés a Vásárlási Segéden: Vision Express esetén WS2026, VidaXL esetén csak a jóváhagyott kupon látható a pilot élesítése után az ellenőrzött kampányoldalhoz. Vásárlás nem szükséges.

Rollback: az egyfájlos guardolt telepítés saját mentése, a deploy által visszaadott release ID és elvárt telepített SHA szerint. A Dognetet érintő konfigurációváltás után célzott frissítés és WS2026-visszaolvasás. A staging-, éles- és aktiválási eredményt a végrehajtás során kell rögzíteni.
