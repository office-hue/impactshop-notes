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
