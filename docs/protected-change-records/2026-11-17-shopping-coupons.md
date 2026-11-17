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
