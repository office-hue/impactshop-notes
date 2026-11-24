# Shopping kuponfeed – additív C1

Scope: új `impactshop-coupon-feed.php`, offline fixture-teszt és a meglévő inventory/hash nyilvántartás kiegészítése. A meglévő affiliate, reward, pont, JYSK, guide, autobanner, tulajdonosi és pénzügyi runtime kódja nem változik.

Kapcsolatok: meglévő service-auth checker → privát cache-only GET; meglévő Dognet token-cache → explicit bounded refresh; saját nem autoloadolt policy/config/snapshot/state/lock optionok; `impactshop_coupon_refresh_v1` órás hook kizárólag explicit CLI-enable után. Betöltéskor nincs frissítés, ütemezés vagy provideraktiválás. A többi hálózat adaptere inaktív.

Konkrét kockázat: hibás program/site kötés vagy kuponfeltétel jutalék elvesztését okozhatja; stale/partial adat érvényesnek látszhat; egy késői refresh visszavonás után publikálhatna. Exact binding, kvalifikációs policy, fail-closed dátum/compatibility, cache-state-owner ellenőrzés és saját config/policy/lock fence védi ezeket. GET nem hív providert, személyes kód és tracking URL nem kerül a publikus projekcióba.

Valós Q 2026-11-17: a saját Dognet token-cache-ből két korlátos olvasó POST (limit1, majd teljes limit100), mindkettő200;21 rekord, végleges lap1/1. A21 rekord kezdő/végdátuma null, `form=1`,20×`type=1`,1×`type=2`, `url_type=3`. Ez nem bizonyít ongoing szemantikát vagy publikálási/attribúciós kompatibilitást. Éles policy és enable nem történik e bizonyíték alapján. Token/kód/raw válasz nem tárolt. `DISABLE_WP_CRON=true`; a meglévő külső cron-wake elfogadása külön függő.

Teszt: `php -l` és saját standalone mockfixture; a végleges eredményt a kiadási jegyzetben rögzítjük. Mockolt HTTP és option-CAS vizsgálat nem valódi DB/cron vagy provideraktiválás.

Release: merged saját checkout, existing exact-file guard staging→production; új plugin expectedSHA=absent, célhash/mode/lint és anonim401 readback, valamennyi új kapcsoló OFF. Policy-cache pilot csak igazolt saját programjog és szemantika után. WP rollback a saját exact-file release backupból, csak a saját változatlan célhashről; előtte provider disable és saját hook leállítása. Pénzügyi táblákhoz és teljes DB-restorehoz nem nyúl.

UI checklist: a Sharity Vásárlási Segédben a kód feltétellel/lejárattal látszik; másolás nem redirect; inputváltás/lejárat után eltűnik; elérhetetlen kupon mellett partner és vásárlási megerősítés működik. A pozitív élő kupon és természetes órás frissítés még nem elfogadott.

## Érintett fájlok / smoke scope

A termékfájl mellett docs/impactshop-protected-files.json, docs/impactshop-guard-config.json és .sha256, docs/impactshop-guard-hashes.json és .sha256, docs/bastion-guard-status.md a meglévő védelmi inventoryt bővíti. Új guardkód vagy általános release rendszer nincs. Smoke tagek: deploy:guard-preflight, deploy:checksum-verify, coupon:cache-only, coupon:offline-contract. A preflight/chksum izolált fixture és readback; éles preflight nem futott.

### Valós HTTP router-kompatibilitás javítása

Az első telepítés valódi HTTP-próbája400-at adott: a WP core a pretty REST útvonalnál is hozzáadja a `rest_route` queryparamétert. Csak az e végponthoz tartozó pontos útvonalat engedjük meg; ismeretlen/dupla paraméter, idegen routerút továbbra is400. Service-auth, binding, provider, cache/cron és pénzügyi út változatlan.153 offline assertion PASS; új pozitív/negatív routeresetek és0upstream. Élő újratelepítés/readback még függő; az eredeti fájlhash rollback-cél.


## 2026-11-17 – tényleges telepítés és célzott éles elfogadás

WP #285 merge `8c8ca0a1f7643932a7dac3d06a3e12140c2bb3f9`; a routerjavítás staging és production exact-file telepített. Production release `20261117T130409Z-8c8ca0a1f764-2deea56f`, SHA256 `c94921bfce38cbc1ae104e42bb5ec2ca8f03459ca0510650368f373b9be237b7`, mód0444. Rollback: `bin/impactshop-guard-rollback.sh --production --apply --release-id=20261117T130409Z-8c8ca0a1f764-2deea56f --expected-deployed-sha=c94921bfce38cbc1ae104e42bb5ec2ca8f03459ca0510650368f373b9be237b7`, kizárólag saját változatlan utóállapotból. Előtte provider disable; pénzügyi táblához nem nyúl.

A saját Dognet adminban ellenőrzött „Unlimited validity”, saját program223/channel26081 és nyilvános kuponjog alapján kizárólag VisionExpress minősített. Policy `dognet-visionexpress-public-20261117-01`, kvalifikáció Secretary ACK `chk-a1efc081-2392-4d82-831a-a9e9eca0a6c7`. OFF pilot teljes/withheld0, majd explicit enable és új teljes refresh történt. Természetes órás futás is igazolt: checkedAt `2026-11-17T14:41:04Z`, complete/errornull. A korábbi dátumszemantikai és cron-wake hiány e pilotnál rendezett. A többi Dognet programra ez nem általános engedély; CJ/Admitad/TradeTracker kuponadapter továbbra sincs implementálva/aktiválva.

Web #2112 merge `c833511daf8549b56768b3201e56938e5d1ec97c`, #2123 letöltési korrekció merge `d4be8b2469b3ebad15c2e4982762e9f3354c4090`. Tényleges Vercel artifact `dpl_Hx1CLoFTyEvNyAeWnGzvc1ULyH3f`:5890 forrás SHA/mód egyezik a18 saját runtime-eltérést tartalmazó kiadási listával,5872 idegen/érintetlen fájl megőrzött; az időközben kiadott SharityAdProvider változása is megmaradt. A middleware az éles alapra illesztett három pontos1.2.0 letöltési útvonal; más chat még ki nem adott ads.txt-eltérését nem élesíti. Mind54 eredeti production env változatlan; csak az új couponflag1. Külön jóváhagyott aliasablakban kizárólag sharity.hu/www kapta az artifactot, visszaolvasva; Legal változatlan, ablak felszabadítva. Webrollback közvetlen előző célja `dpl_CaVEwJd2B6BQghm9r3tKvHN8pUzz`, csak saját változatlan alias-utóállapotból.

Célzott élő eredmények: meglévő public post-cutover smoke PASS; privát WP feed jogosult HTTP200/3kupon, névtelen/hibás auth401. Canonical web resolve és mindkét MCP tool HTTP200/no-store, VisionExpress `available/complete`,3kupon és kb58s maradék kijelzési idő. MCP1.2.0 pontosan a meglévő két olvasóeszközzel. Böngészőben három kód/feltétel/Dognet/ongoing/check idő látszott, inputváltáskor eltűnt; másolásgomb után0 további aktiválási kérés. Vásárlás, kereskedői továbblépés és pénzügyi tesztírás nem történt. A korábbi ENOSPC miatt leállt külön próba akkor nem volt PASS; tárhely helyreállása után a pontos letöltési artifact célzott tesztje1PASS/44skipped, nem új45-ös futás.

Az éles1.2.0 ZIP HTTP200/hash/9allowlist-fájl ellenőrzött, SHA256 `b242d69d992ff7fa53c99eb8ee443777318c40e7f45a88981dd58656065b77c6`. Natív CLI marketplace-forráscsere és pluginfrissítés1.2.0 sikeres; enabled/list és8 telepített forrás/cache fájl egyezése visszaolvasva. Régi1.1.2 forrás megőrzött. Új Work-beszélgetés automatikus skill-betöltése még külön függő; CLI-telepítés nem új chat elfogadása. Secretary élő checkpoint `chk-02c8475c-ba4f-448f-aa39-94f2f3e27324`; a helyi DOCSYNC-bejegyzés nem központi registry acceptance vagy távoli sync ACK.

Utólagos valódi Work-klienspróba: a hostkatalógus már az1.2.0 skillt kínálta, annak tényleges betöltése után a csatlakoztatott `search_sharity_partners` és `classify_shopping_offers` egyaránt VisionExpress `available/complete` és3kupon választ adott. Ez a jelenlegi Work-chatben igazolt működés; külön új beszélgetést nem indítottunk. A rebase utáni egyik push-audit hiányzó előző SHA miatt unavailable/exit2 volt, miközben a PR teljes tartományának auditja sikeres; ez nem forráshiba és nem PASS-ként jelentett kihagyott vizsgálat. A mostani lineáris commit a valós klienspróba dokumentálása, a meglévő CI változatlan.


## CJ kuponolvasó – következő additív csomag

Kiindulás: saját `feat/impactshop-cj-coupon-reader-20261117` ág, HEAD `1c1151bb`; a korábbi Dognet-pilot változatlan. A tulajdonos újraaktiválása után friss CJ Dashboard és saját publisher7318997/website101302202 API200 igazolt. A joined/coupon lekérés45találatot jelez; a pontos UNice link15487360 továbbra is joined, de a program kuponkeresése0találat. A régi link Product promóció/Banner, nem minősül kuponnak; friss clickOrigin `www.jdoqocy.com`, a régi canary allowlisttől eltér. Nincs automatikus review-hosszabbítás vagy domainbővítés.

Előzetes koherencia/hatás: `impactshop-cj.php` kizárólag új, írásmentes, egyszeri bounded coupon-page seam; a régi sync/ledger/CLI alapértelmezései maradnak. `impactshop-coupon-feed.php` a meglévő privát service-auth/cache/CLI/órás-hook láncban kap CJ-adaptert, alapból OFF; ugyanazok a providerhez kötött optionok, config/policy/lock fence és nyilvános mezők. CJ HTTP csak explicit refreshből, nem GET-ből. Nincs új endpoint, pénzügyi tábla, reward-képlet, redirect vagy új daemon.

Konkrét kockázat/megoldás: a CJ hibaszöveg visszaechozhat credentialt, ezért fix titokmentes hibakód; XML DTD/XXE és hibás lapozás elutasítás; joined advertiser/site és friss public/compatibility policy metszete;20call/60s és a25/min providerlimit alatti saját futások, meglévő state alapján cooldown; hiányos eredmény nem none és nem régi cache fallback. A csak hálózati Banner/Product kedvezményszöveg nem válik automatikusan kuponná.

Ellenőrzés/kiadási terv: valódi CJ seam offline HTTP/XML negatív esetek; feed CJ binding, dátum, részlegesség, cooldown, publikus0upstream és Dognet-regresszió; érintett protected hash/guard; saját diff/commit/push/PR, friss szűk mainablak csak kész CI után; existing exact-file staging→production, csak merge-elt saját két PHP-fájl. Aktiválás kizárólag friss programonkénti public coupon+adomány kompatibilitás és valódi teljes OFF-pilot után. Enélkül csak telepített/OFF állapot. Jelenleg új kód/teszt/merge/deploy/activation még nem történt.

Rollback: provider CJ disable az existing CLI-n, majd csak saját változatlan utóhashről existing guard-release backup, Dognet option/policy megőrzésével. Pénzügyi/full DB restore nincs. UI checklist későbbi éles pilotnál: Sharity Vásárlási Segéd, CJ hálózatnév/feltétel/lejárat, másolás0redirect, inputváltás után elrejtés;0kupon mellett partnermegerősítés működik. A még nem futott vizsgálatok nem PASS.


## 2026-11-17 – CJ kuponolvasó, saját ág / aktiválás nélkül

A tulajdonos CJ-újraaktiválását friss Dashboard és saját publisher7318997/PID101302202 Link Search HTTP200 igazolja. Joined/coupon:45rekord; az új, változatlan módszertörzzsel futtatott, írásmentes szerveroldali kvalifikáció45/45rekordot/103327byte-ot sikeresen feldolgozott. A kvalifikáció egyszeri memóriabeli osztályt és a telepített credential/normalizer metódusokat használta, nem telepített új fájlt és nem írt optiont. UNice5824323/link15487360 joined/aktív, kuponkeresés0; Product/Banner nem kupon. A friss clickOrigin `www.jdoqocy.com`; a régi webes allowlist és a hiányzó új purchase-review nem hosszabbodott automatikusan.

Saját forrás: `feat/impactshop-cj-coupon-reader-20261117`, additív bounded CJ-page metódus + meglévő kuponfeed CJ-dispatch/UTC-normalizálás. Alapból OFF, kizárólag friss program/site/publication/compatibility kvalifikáció után engedhető. Authhiba ugyanazon config/policy mellett felfüggeszt; javítás után explicit CLI-disable új configrevisiont ad, majd OFF-pilot/enable/refresh. A120s startköz korlát betartja a25/min limitet; enable után a következő CJ refreshhez a120s letelte kell.

Célzott eredmények: CJ valós metódus116 offline assertion PASS; kuponfeed232 offline assertion PASS, benne az eredeti153 és CJ esetek. Mindkét PHP lint PASS. A legacy CJ metódusok bájtra változatlanok; teljes saját protected inventory0drift és6 izolált negatív hash/checksum eset PASS. Csak a két érintett PHP hash frissült, config/inventory/CI/guardkód változatlan. Nincs új pénzügyi/redirect/authority/webbuild-módosítás. A szintetikus feltételjog nem élő elfogadás.

A globális coupon config/policy hash miatt későbbi CJ-policy/enable a Dognet snapshotot is invalidálja: az aktiválási menetben az új policy/config után a meglévő Dognetet azonnal frissíteni és visszaellenőrizni kell, a CJ-t a cooldown leteltével. A Dognet policy kötéseit meg kell őrizni; teljes policycsere vagy pénzügyi restore nincs.

Hermes egy engedélyezett követelmény+szintetikus csomag: completed, becsült0.00174978USD, nem számla. Transport/fixture-váz részben felhasznált; a hibás scalar-count, DTD/UTF16, alias/scope elgépelés, nem finite timeout és kikapcsolható assert-tesztek helyben javítva/lecserélve; nincs új Hermes-hívás vagy privátforrás-export. Secretary delegálás ACK; termékforrás/merge/deploy/activation külön. Kód saját ágon; commit/PR/CI állapot a kiadási checkpointban. Új telepítés és éles CJ policy/enable nem történt. TradeTracker saját profilból belépett/accepted site481183, API credential jelen van, tényleges SOAP auth/session/kampányjog még külön Q; Admitad site2888572 Inactive/tulajdonellenőrzés szükséges.

Manuális UI checklist az esetleges későbbi CJ-pilothoz: Sharity Vásárlási Segéd → CJ kód/feltételek/lejárat/hálózatnév; másolás nem navigál; inputváltás elrejti a kódot; kuponhiba mellett a partnermegerősítés marad. Jelenleg ezt új CJ-éles kóddal nem futtattuk; a korábbi Dognet-elfogadás újrahasznált előzmény.


## 2026-11-17 – CJ default-OFF tényleges kiadás és visszaállítás

Forrás: PR #288, jóváhagyott pontos head `f182a173e91c0e267b18d3a45cf76e83bc39faf9`, merge `a9b1d3e8812dec2c676aa0f5b1876651dced879f`, hét kijelzett CI SUCCESS. Az Air és Mini egyszeri main-fetch visszaolvasva; szűk merge-ablak felszabadítva. A saját tiszta Mini release-worktree detached a merge-commiton. A kiadás a meglévő `bin/impactshop-guard-deploy.sh` útján, fájlonként staging→production történt. Sem új guard, sem általános rsync/deploy/DB-művelet nem készült.

| Cél | Fájl | Release ID | Végső SHA256 / mód |
| --- | --- | --- | --- |
| staging | impactshop-cj.php | 20261117T211232Z-a9b1d3e8812d-844167d4 | 9943cf83d1220f1bbe528e75fc801cc6871dfd547e82c5cd119529a96a748b0d /0444 |
| staging | impactshop-coupon-feed.php | 20261117T211251Z-a9b1d3e8812d-60d0372e | 7ecbcc8f391168a08e72a7857a921d9489fb4f3260b1d4adaecf9be831e029c2 /0444 |
| production | impactshop-cj.php | 20261117T211325Z-a9b1d3e8812d-608516c3 | 9943cf83d1220f1bbe528e75fc801cc6871dfd547e82c5cd119529a96a748b0d /0444 |
| production | impactshop-coupon-feed.php | 20261117T211356Z-a9b1d3e8812d-0dc5e8c7 | 7ecbcc8f391168a08e72a7857a921d9489fb4f3260b1d4adaecf9be831e029c2 /0444 |

A guard által ellenőrzött előállapot mindkét célon: CJ `96a6cefe64a958e9d53c7490d7dd733e611ed8f0620d3aab8a59d7d29c3875a2`, feed `c94921bfce38cbc1ae104e42bb5ec2ca8f03459ca0510650368f373b9be237b7`; célfájlonként privát mentés és CAS. Mind a négy release deployed; a staging parent0755, a production parent0555 maradt. A kiadási logok a saját Mini worktree `tmp/cj-release-20261117/` mappájában,0600 fájlokkal vannak; nem secret- vagy provider-response exportok.

Production config/policy serialize-SHA256 előtte/utána azonos: `65d2f4907742401d76645a4b046b3e8bda83b8b0c41c621a5f5dad96d19ee2af` / `73de191668b3520121da2b25940571432ce0e4a5193af38aa4ff17b3e87ed128`. Dognet ON, complete/errornull, checkedAt2026-11-17T20:41:03Z, validUntil2026-11-17T22:41:03Z, órás hook scheduled; valódi telepített projection available/3kupon. CJ callable/implemented, OFF/not_checked; nincs CJ refresh/snapshot. A célzott projection próba HTTP-szűrővel0upstream, staging és production névtelen coupon REST401. A régi116+232 assertion azonos forrástestre újrahasznált bizonyíték. A kiadás WP-bootstrapja lefutott; a meglévő10/11 PHP deprecation stderr sort nem tekintjük új tesztnek vagy hiba nélküli teljesrendszer-elfogadásnak. Production preflight két futásban1, illetve4 lassúválasz-figyelmeztetés (kb2–6.4s), ezért nincs teljesítmény-PASS. Új webbuild/alias/env/authority/pénzügyi írás nem történt.

Kizárólag saját változatlan célhashről végrehajtható production rollback, fordított sorrendben:

```bash
bin/impactshop-guard-rollback.sh --production --apply --release-id=20261117T211356Z-a9b1d3e8812d-0dc5e8c7 --expected-deployed-sha=7ecbcc8f391168a08e72a7857a921d9489fb4f3260b1d4adaecf9be831e029c2
bin/impactshop-guard-rollback.sh --production --apply --release-id=20261117T211325Z-a9b1d3e8812d-608516c3 --expected-deployed-sha=9943cf83d1220f1bbe528e75fc801cc6871dfd547e82c5cd119529a96a748b0d
```

A staging rollback ugyanígy `--staging` és a fenti staging release ID-k használatával. Jelenleg CJ OFF, nem kell a működő Dognetet kikapcsolni vagy policy/configot módosítani e két forrásfájl visszaállításához. Ha később CJ-t aktiváljuk, előbb explicit CJ disable és a globális hashváltozás miatt Dognet refresh/readback szükséges; ezután csak az akkor még egyező saját célhashről szabad rollbackelni. Pénzügyi/full DB restore nincs. Új CJ-UI kuponpilot nem futott: friss programonkénti public+adomány kompatibilitás, purchase-handoff review és teljes OFF-pilot hiányában a hálózat OFF marad. A korábbi Dognet UI-elfogadás előzmény, az új telepítésnek célzott regressziós readbackje van.

### 2026-11-24 — Vision Express WS2026 kódjavítás

A Dognet magyarországi kapcsolattartója a WS2026 használatára adott visszaigazolást. A Vision Express a saját akciós oldalán ugyanezt a kódot közli. Az éles Sharity-feloldó még három WS2025 sort adott vissza. A kereskedői feltételek forrása: https://www.visionexpress.hu/hu/napszemuvegek/kollekcio/napszemuveg-kupon-akcio . A belső partneri bizonyíték a kupon-munkajegyben található.

Érintett fájl: `wp-content/mu-plugins/impactshop-coupon-feed.php`. A cache-olvasó az érvényes Dognet 223 / 26081 / `dognet:visionexpress` kötésű WS2025 és WS2026 kupont egyetlen igazolt WS2026 ajánlatként vetíti ki. Más partner, csatorna, kód és ajánlattípus változatlan. A már meglévő jogosultsági, szolgáltatás-hitelesítési, frissességi és lejárati feltételek előbb érvényesülnek. Az ajánlat csak akkor jelenik meg, ha a forrásban aktív megfelelő sor van. A feltételek a teljes árú, akcióban részt vevő új kollekciós napszemüvegekre vonatkoznak. A javítás nem ír partneri jogosultságot, jutalékot, affiliate linket, provider beállítást vagy cache-snapshotot. A közvetlen hibakockázat a téves kód vagy a túl széles feltételleírás. A közvetett hibakockázat az idegen kampány vagy lejárt találat megjelenése.

Smoke és UI ellenőrzőlista: a hitelesített coupon REST-válaszban pontosan egy WS2026, WS2025 nélkül. Az anonim REST továbbra is 401. A Vásárlási Segéd mai akciói között jelenjen meg a WS2026 és a kivételek. Az „Ajánlatok ellenőrzése”, majd a „Másolás ChatGPT-be” adja át a kódot és feltételeit, technikai lejárat nélkül. Egyik művelet sem indít vásárlást. A staging és az éles visszaolvasás külön státusz. Smoke tagek: `coupon:cache-only`, `coupon:offline-contract`, `deploy:guard-preflight`, `deploy:checksum-verify`.

Rollback: a merge-elt forrásból a meglévő exact-file guard telepít stagingre, majd productionre, célonként előhashsel és mentéssel. Hibánál csak az adott release ID-jára és a még egyező aktuális fájlhashre fut a guarded rollback. Egyetlen fájl áll vissza. Nincs teljes adatbázis-rollback vagy Vercel-aliasművelet.
