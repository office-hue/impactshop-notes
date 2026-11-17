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
