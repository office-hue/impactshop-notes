# Dognet: igazolt vásárlások pontozásának befejezése

Állapot: két egymást követő független tervaudit után javított terv; a megjelölt időszabályt Arnold kifejezetten jóváhagyta. Ez tervezési dokumentum, nem implementációs vagy élő PASS.

## Cél és kiindulás

A normál, végleg jóváhagyott új Dognet-vásárlás tényleges jutalékának 50%-ából egyszeri pontjóváírás készüljön. EUR → HUF a jóváhagyás magyar naptári napján elérhető legutóbbi hivatalos MNB-jegyzéssel, egyetlen half-up kerekítéssel. Részleges, ismételt, ellentmondó, történeti vagy nem egyértelmű korrekció kézi esemény. Nem kell szolgáltatói levél a normál jóváhagyott események miatt; levél nem ment ki és nem küldendő.

Saját meglévő worktree: `/Users/bujdosoarnold/.worktrees/impactshop-points-simple-20261029`, ellenőrzött induló HEAD `fb8cbaadfed0e167d8c0e13369b2ec57d3255e40` (jelenleg saját terv- és notes-módosítással), branch `fix/impactshop-points-handoff-20261029`, remote `office-hue/impactshop-notes`. Ennek fájlfája a #239 merge `3cdcf4e2` fájlfája. Megvalósításkor ugyanitt új saját `feat/impactshop-dognet-confirmed-20261029` ág indulhat a helyi HEAD-ről; nincs induló main-fetch. A primary checkout és más munkák érintetlenek.

A meglévő privát release-átadás szerint a core és #239 staging/prod telepítése kész, CJ aktív, Dognet és közvetlen partner automatikus pontozása OFF. Ezek korábbi, időbélyegzett megfigyelések, nem ebben a tervezési körben frissített élő állítások. A cutover rögzített: **2026-10-29 15:37:39 UTC**. Éles pénzügyi elfogadás még nincs. A Secretary repo-keresés üres; ez nem cáfolja és nem igazolja az élő állapotot.

Bizonyított és újrahasznált adatok: Dognet csatorna26081, EUR, Europe/Bratislava; dokumentált A/P/D státusz, A rendes úton végleges; REST last_id és explicit sort; meglévő SAT1 attribúció; money/MNB, két eseménytábla, tranzakciós manager, adminrendezés, 15 perces cron és exact-file release. Decathlon kifutott Dognet-partner. Árukereső útpróba sikeres, de CPC-jellege miatt nem pénzügyi vásárlási teszt.

## Fájlszintű változás

| Fájl | Szükséges módosítás |
| --- | --- |
| `wp-content/mu-plugins/impactshop-dognet-points.php` | A/P és kézi kivétel normalizálása; stabil vásárlás/eseményazonosság; pontos decimális Dognet-válasz; befagyasztott pénzügyi alap és pending-kötés újrahasználata; hiányos/ellentmondó sor megőrzése; helyes, korlátos és folytatható lapozás; csak Dognetre vonatkozó konfiguráció és kapcsoló. |
| `wp-content/mu-plugins/impactshop-netflix-shortcodes.php` | A meglévő `dognet_api_request` utolsó, opcionális paramétere sikeres HTTP-válasz nyers JSON-törzsét adhatja vissza a pontadapternek. Régi hívóknál minden marad; nem új transzport. |
| `wp-content/mu-plugins/impactshop-rest-coupons.php` | Ugyanaz az opcionális transzportparaméter a másik `function_exists`-védett definícióban; külön teszt mindkettőre. |
| `tests/impactshop-points-provider-adapters.test.php` | A jelenlegi Dognet manual-only elvárások cseréje normál A/P/kivétel, pénz, attribúció, ismétlés, lapozás és hibák eseteire; CJ/partner regresszió ugyanitt. |
| `tests/impactshop-dognet-transport.test.php` | Régi dekódolt és új nyers válasz; 401/429 egyetlen kérés; hiányzó token; mindkét tényleges definíció. |
| `tests/impactshop-points-mysql.integration.php` | Meglévő izolált SQL-tesztbe kevés valódi adapter → capture → consumer eset: P→A, első A, lejárt token/pending-kötés, duplikátum és megváltozott A, kézi korrekció; nem új SQL-harness. |
| `tests/fixtures/impactshop-dognet-*.json` | Kis szintetikus, személytelen nyers JSON-minták; EUR MNB-árfolyam fixture a meglévő MNB-fixture mellett. Nem éles tranzakciódump. |
| `docs/dev-plans/impactshop-donation-proportional-points-source-r1-2026-10-13.md`, `docs/rewarding-system.md` | A Dognet manual-only leírás helyére a pontos normál/kivétel működés, pénzügyi szabály és státusz. Ez a terv adja a végrehajtási részleteket. |
| `notes.md`, `system-status-snapshot.md`, `docs/protected-change-records/2026-10-29-impactshop-simple-points.md` | Rövid DOCSYNC, koherencia, érintett háttérfolyamat/transzport, teszt, rollback, valós állapot. |
| `docs/impactshop-guard-hashes.json`, `.sha256` | Csak a ténylegesen módosult védett fájlok digestje. Guard-config csak akkor, ha valóban szükséges; új runtime-modul nincs. |

A `sharity-points-money.php`, `sharity-points-ledger-sync.php`, `sharity-points-impactshop-manager.php`, `sharity-points-cron.php`, attribúciós runtime és közös Offerwall/JYSK manager alapból változatlan. Az adapter már a meglévő cronba van kötve. Nincs séma-, wp-config-, globális DOGNET_AD_CHANNEL_ID-, frontend-, új ütemező- vagy release-rendszer-változás. Ha e határon túl konkrét szükség derül ki, a diff és kockázat alapján külön döntés kell.

## Normalizálás és pénzügyi szerződés

1. A provider olvasott sorának csatornája26081 legyen. A vásárlás kulcsa csatorna+kampány+igazolt rendelési/tranzakcióazonosság; raw `id` az eseményazonosság. A dokumentált raw `id` egy szolgáltatói tranzakció rekordazonosságát bizonyítja. A `transaction_id`/`original_id` rendelési szemantikáját egy minta önmagában nem bizonyítja: dokumentált vagy meglévő hiteles integrációs mapping kell, a minta csak mezőjelenlétet/összhangot ellenőriz. Ezt az implementáció első, szűk olvasásos lépésében kell lezárni; nincs hallgatólagos id-fallback vagy rendelésdedupe-állítás. Ugyanazon vásárlás több jutaléksora/azonosságváltása kézi eset; nincs összeadó motor. Credentialváltás nem változtat kulcsot. Ha a provider csak egyedi raw-tranzakciót igazol, az adott rekordot nem nevezzük bizonyítottan rendelésnek.
2. SAT1 kizárólag azonosítás: provider/merchant/campaign összhang, hiteles subject-kötés, redirected állapot. Nem igazol vásárlást vagy jutalékot. A `P` esemény tartósan menti a kötést és az azonosításhoz szükséges kis alapadatot a meglévő event_json/donation_basis mezőbe. Későbbi A a tárolt kötést használja, nem a token újraérvényesítését. Elsőként A is feldolgozható, ha akkor még igazolható a kötés.
3. Vásárlásként igazolt sale-típus és pozitív tényleges vásárlási összeg kell; CPC/lead/zero-sale nem automatikus vásárlás. Ismeretlen típus csak saját eseményét állítja kézibe. A státusz A a jutalék jóváhagyását jelenti, nem feltétlen készpénzes kifizetését: payout_status nem új kapu.
4. Provideridők Europe/Bratislava → UTC szigorú dátumellenőrzéssel, DST-kétértelműség kivétel. A created_at dokumentáltan rekordidő; pontos vásárlási időként nem állítjuk be. **Arnold által jóváhagyott szűk szabálypontosítás:** az új-vásárlás jogosultságát a hiteles, cutover utáni SAT1-redirect és az ezt ugyanazzal a tokennel visszaadó normál sale együtt bizonyítsa, `cutover ≤ redirected_at ≤ created_at ≤ date_approved` időrenddel. A változatlan core `original_purchase_at` mezőjében Dognet esetén a befagyasztott `redirected_at` konzervatív alsó időkorlátként szerepel; a donation_basis kifejezetten `time_source=sat1_redirect_lower_bound`, `actual_purchase_at=unknown` jelentést rögzít. A tényleges vásárlási időt továbbra sem állítjuk ismertnek. Ez az eredeti „ismeretlen idejű vásárlás nem automatikus” követelmény kifejezetten jóváhagyott, szűk pontosítása. Arnold válasza: „Igen, a bizonyított átállás utáni vásárlás így pontozható.” Emiatt újabb időszabály-jóváhagyás vagy providerlevél nem szükséges. A hiteles időkorlátot sem teljesítő esemény kézi marad. Hiányos/ellentmondó P már capture-kor kézi rootot hoz létre; nem készítünk belőle később automatikus A-t, és nem javítjuk csendben a kötését.
5. A commission és total_cost eredeti JSON-számalakját megőrizzük. A nyers sikeres törzs csak memóriában él. Az adapter egy kicsi, JSON-stringeket tiszteletben tartó lexikai átalakítással számtokenből stringet készít, majd natív json_decode és a meglévő decimális validátor dolgozik. Nincs floatból visszaformázás, általános parserkönyvtár vagy új függőség. Túl nagy/túl pontos/nem támogatott szám vagy hibás JSON explicit eredmény; a nyers törzs nem kerül logba.
6. A normál A `date_approved` alapján egyszer meghívja a meglévő EUR MNB-utat. Adomány = jutalék × 0,5 × HUF/EUR, egy kerekítés. A jutalék, sale, providerazonosság, eredeti idő forrása, jóváhagyási idő, jegyzésnap/árfolyam/egység és szabályverzió a meglévő, legfeljebb512byte-os donation_basis-ben marad; mérettúllépés nem csonkolás. Első capture befagyasztja, ismétlés onnan olvas. MNB-hálózati hiba nem végleges pénzügyi kivétel: az érintett lap kurzora nem lép, a lap többi független sora még capture-ölhető, későbbi rendes cron próbálhatja újra; nincs azonnali retry.
7. A normalizált esemény tartalmi hashében csak gazdasági/azonossági adatok szerepelnek. Provider updated_at vagy ártalmatlan metaadat változása nem új jóváírás és nem hamis konfliktus. Változó jutalék, sale, subject, purchase-id vagy jóváhagyási alap kézi konfliktus. P után A külön revision; ugyanazon A azonos tartalommal hatástalan. Már kézi root nem tér vissza automatába.
8. A D önmagában nem bizonyítja egy korábbi jóváírás teljes visszavonását. A→D vagy jutalékmódosítás a meglévő kézi rendezési ágba kerül, amíg egy konkrét szolgáltatói esemény teljes, az eredetire mutató visszavonást nem igazol. Ez Dognetre korlátozott automatizálási korlát, nem a normál A feldolgozásának feltétele. A már létező teljes refund művelet és cap/elengedés változatlan; automatikus refundot nem jelentünk késznek puszta D alapján.

## Lekérdezés, erőforrás, hiba

- Az adapter saját `impactshop_dognet_points_channel_id=26081` beállítást használ; a globális0 nem íródik át. `impactshop_dognet_points_scan_from` marad az indító/leállító kapcsoló, csak a már rögzített cutover napjától. A producer és a namespace-allowlista is megmarad.
- Explicit `sort=id`, dokumentált `meta.last_id`, szigorúan előrehaladó azonosítók; sorok saját csatornaellenőrzése. Legfeljebb4×200 sor/kör, a meglévő15perces cronban. A végigjárt időablak felső határa egy sweep elején rögzül; provider-időzónában küldött created_at szűrés, a pontos UTC cutover az eseményen is érvényes.
- Konkrét jelenlegi hiba: 800+ sor esetén minden futás ugyanott elölről indulna, a többi kimaradna. Egyetlen kis WP-option (`impactshop_dognet_points_scan`) tárolja a sweep felső határát és last_id-t; csak az egész lap tartós capture-je után lép. Szakadáskor lapismétlés idempotens. Üres data zárja a sweepet; következő sweep ismét a cutover napjától indul, így régi P/A módosulás sem csak új ID alapján kerül elő. 4kérés után `in_progress`, nem teljes-scan PASS. Nem új tábla/queue/worker.
- Párhuzamos poll ellen adapteren belüli meglévő MySQL GET_LOCK/RELEASE_LOCK minta, nem lease-rendszer. Nem fogjuk a közös consumerzárat hálózati várakozás alatt. Hálózati várakozás után a meglévő CONNECTION_ID + IS_USED_LOCK mintával ellenőrizzük a kapcsolatot és a zártulajdont capture és kurzorírás előtt. Elveszített zár vagy kurzor/readback hiba esetén nem lépünk tovább; finally felszabadítás.
- Cache-elt token, auth-refresh=false; nincs401/429retry vagy másikaccount/fallback. Tokenhiány világos üzemeltetési hiba; meglévő szabályos tokenfrissítési út képességét aktiválás előtt ellenőrizzük, új authrendszert nem építünk. Provider- és MNB-hiba nem akadályozhatja a közös cronban a CJ/consumer futását: adapter visszatér explicit hibával, kivételt nem enged ki.
- Egy poll legfeljebb **4 új jóváhagyási naphoz** kér MNB-árfolyamot. A már befagyasztott eredmény nem fogyaszt új quote-keretet. Keretelfogyáskor lapkurzor marad, `in_progress`; az önállóan feldolgozható sorok capture-je folytatható, a többi a következő rendes cronban kerül sorra. Az eltérő napok számát is fixture teszteli. A jelenlegi timeoutokkal a hálózati felső becslés4×30s Dognet+4×10s MNB=160s, ehhez jön a korlátos DB-munka. Aktiválás előtt az aktuális host teljes cron-futáskeretét és a CJ/consumer számára maradó időt ellenőrizni kell. Ha ez nem fér el, a már érintett transzportban kizárólag a points-hívás timeoutját és az adapter kéréskeretét csökkentjük; nem növeljük a globális futásidőt és nem építünk új workert.
- A globális keretek változatlanok:100000pont/esemény,1000000/felhasználó/24h és meglévőfutáskeretek. Sweepkurzor nem új gazdasági run_id; held eseményt nem nyitunk új keretre. Külön napi teljes cutover-scan nem szükséges: a rendes ciklus bejárja. A körök tényleges futásidejét és sweepkorát mérjük, új monitor nélkül. Ha a4kéréses út nem tud érdemi késleltetéssel végigérni, ez tényleges kapacitáshiány, külön szűk döntés; nem emeljük vakon a limitet.

## Célzott ellenőrzés és review

Előbb offline fixture, utána egy szükséges, minimális hiteles read-only provideroldal a dokumentált sort/meta/mezők ellenőrzésére. Sem vásárlást, sem külső írást, levelet vagy kampányjelentkezést nem indítunk. Decathlon nem tesztpartner. A meglévő történeti Árukereső CPC-minta negatív eset.

Teszt: pontos EUR, half-up/0pont/túlcsordulás; first-A és P→A; tokenlejárat utáni kötés; kampány/subject mismatch; cutover és DST; változatlan ismétlés; gazdasági módosítás és ártalmatlan metaadat; D/partial/repeated kézi; 801+ rekord, megszakadt lap, változás a következő sweepben, üres/hibás válasz és auth/rate-limit; nem blokkolt CJ/consumer. A korábbi átadás118 SQL-checket rögzít, ez újrahasznált bizonyíték; az új adapter gazdasági kapcsolata külön tényleges SQL-futtatást kap.

Natív parancsok: `php tests/impactshop-points-provider-adapters.test.php`, `php tests/impactshop-dognet-transport.test.php`, money/MNB fixture az új EUR-minta miatt; érintett PHP-lint és `git diff --check`. SQL: meglévő teszt privát IMPACTSHOP_MYSQL_CONFIG/WPDB_FILE és LOCAL_IMPACTSHOP_ONLY feltételeivel, saját eldobható adatbázisban; más projekt DB/cache érintetlen, nincs dependency-install. Nem futtatjuk újra a teljes böngésző/JYSK/restore-csomagot változatlan core miatt.

Pénzügyi adapter miatt két egymást követő független tervaudit szükséges, az első javításai a második előtt. Az implementáció végén egy célzott független diffreview, azonosság/összeg/dedupe/hibaizolálás fókusz. Nem új általános auditciklus.

## Commit → PR → merge → telepítés

1. Egy koherens implementációs commit, legfeljebb még egy review-javítás. Csak saját felsorolt fájlok stage. Valós teszteredmény, staged diff, natív titokscan, érintett Bástya, `scripts/git-health-check.sh` és `scripts/safe-repo-audit.sh --strict --mode push`. Helperhiba esetén natív megfelelő, nem hamis PASS.
2. Native feature-push, távoliSHA/readback; egy PR a pontos csökkentett működéssel, Dognet-korrekciók kézi korlátjával, teszttel, DDL-mentes telepítéssel és rollbackkel. Kötelező checkek aktuális védelme visszaolvasva: validate, safe-audit, Guard, canonical-source-guard. Nem módosítunk CI/release-workflowt.
3. Friss rövid csendablak csak szükséges main-integrációhoz, majd külön merge-hez, ha időben elkülönül. Integráció után csak érintett ellenőrzések újra; CI-várakozás nem fogja az ablakot. Squashmerge/readback; külön Git-adminisztrációjú release-klón a tényleges merge-SHA-ra rögzítve. Ablak azonnal felszabadítva. Telepítés közben nincs fetch.
4. Air→`mac-primary`→Mini meglévő `s59` kulcsválasztással, tényleges `we040.tarhely.com`, ismert hostkey. Staging `/home/sharityh/app-staging`, prod `/home/sharityh/app`, eltérő táblaprefix ugyanabban aDB-ben. Jogosultság/host/fileSHA/mode friss readback; titokkiírás nincs. A korábbi működő hozzáférés újrahasználandó, nem signer/VPS-infrastruktúra-feladat.
5. Először staging, Dognet OFF. Egyszeri retrymentes preflight, érintett fájlok exact-release mentése/CAS. Transportdefiníciók előbb, adapter utoljára; újrawparaméter régi hívóknál kompatibilis. `bin/impactshop-guard-deploy.sh`, IMPACTSHOP_EXACT_RELEASE=1, pontos fájl/előhash/releaseID; nincs rsync. Második fájltól friss közös preflight újrahasználható SKIP_PREFLIGHT=1-gyel, Bástya/hash/backup megmarad.
6. Nincs DDL, nincs élő pontíró-core csere: teljes HTTP-leállítás/DBmentés-visszaállítás nem indokolt. Dognet polling OFF és függőségi sorrend elég; ha már aktív vagy más munkából futó Dognetíró van, csak azt állítjuk meg és kifuttatjuk. Érintett beállítások privát előállapotát mentjük; a meglévő adatmentés visszaállíthatósági bizonyítékát használjuk újra. Ugyanazon közösDB teljes restore tilos.
7. Staging: fájl/mód/PHP-bootstrap readback, régi kupontranszport működik, offline adapter/SQL igazolás megvan, providerolvasás legfeljebb a szükséges egyszeri oldal. Staging pénzügyi fogyasztó OFF; nincs élő soron mesterséges jóváírás. Ez telepítési smoke, nem staging pénzügyi E2E. Ezután ugyanaz a mergeartifact productionre, Dognet OFF mellett.

## Aktiválás és rollback

Aktiválás előtt friss saját lane/opció/readback és mentés. Az előre rögzített, dokumentált azonosító-mapping, a jóváhagyott időszabály és a célzott read-only ellenőrzés legyen meg; a minta csak az elvárt mezők jelenlétét/összhangját, sort/last_id-t és tokenellátást ellenőrzi, nem bizonyít általános mezőszemantikát; az ismeretlen refund változat nem kap normál-A-ra vonatkozó blokkoló szerepet. A Dognet-csatorna opció26081; meglévő CJ-allowlista változatlan elemei mellé **impactshop:dognet:26081** kerül, majd `impactshop_dognet_points_scan_from=2026-10-29`. A lane előbb legyen engedett, mint az első capture: különben a jelenlegi consumer visszafordíthatatlan manual állapotot adna lane_not_ready indokkal. CAS/readback, cutover változatlan, legacy0; más lane-t nem aktiválunk hallgatólagosan.

Első rendes cronig várunk, nem indítunk duplán manuális pollt. Readback: scanhaladás, valóban rögzített esemény/eredmény, helyes user/összeg, hibák/kivételek és CJ folytonossága. Üres vásárlási ablak nem hiba, de nem pénzügyi elfogadás. Aktiválás technikai állapota külön az első természetes jóváírástól.

Rollback: scan_from üresre, futó Dognetpoll kifut; szükség esetén saját namespace eltávolítása úgy, hogy CJ megmarad. A jelenlegi consumer lane-hiánykor queued Dogneteseményt manualra tesz: ez tudatos megőrzött karantén, nem törlés; visszaengedéskor nincs automatikus visszanyitás. Súlyos közös consumerhiba esetén csak igazoltan szükséges ideig annak külön kapcsolója0. Pont/történet/séma/events marad, már jóváírt egyenleget nem állítunk vissza régiDB-ből. Exact-file visszaállítás saját release backupból és ismert utóhashből; részleges/timeout kimenet előbb readback. Régi kattintási jutalom/legacyconsumer nem kapcsolható vissza. Két hibás javítás után diagnózis.

## Elfogadás és a teljes termék hátralévő része

- Forráskész: célzott tesztek/review kész; merge-elt: visszaolvasott GitHubmerge; telepített: mindkét célon pontosSHA/bootstrap; aktivált: Dognetlane és rendes cron igazolt; élőben elfogadott: természetes jogosult esemény alapján helyes és egyszeri pont.
- Az eredeti vállalás legalább20 természetes jogosult esemény, minden aktív provider lefedésével megmarad. Nem20tesztvásárlás, nem külön elfogadási infrastruktúra. Refund izolált hiteles mintán, élőben csak ha természetesen előfordul. Dognet automatikus full-refund nem állítható késznek, amíg a konkrét adat nem támasztja alá; kézi megoldása a működőtermék része.
- Első két hétben heti kivételáttekintés a meglévő `wp sharity points exceptions list/show` úttal: darabszám, tényleges rendezési idő. Következő automatizálást a valós esetek indokolhatnak.
- Rövid UIlista: ImpactShop egy aktuális partnerrel→NGO→merchant; kattintás0pont; profil/pont/szint/történet változása csak igazolt vásárlás után; kuponlista render/redirect. Decathlon nem használható. A korábbi útpróba nem új pénzügyi bizonyíték.
- CJ-hez nem tervezünk új implementációt: természetes élő elfogadás maradt. A közvetlen partnerlane külön: meglévő aláírt fogadás `impactshop-partner-api.php`, hiteles végleges HUF-adomány/originaltime/policy minta ellenőrzése, célzott fixture, majd csak saját lane aktiválása. Hiányában az adott partner OFF marad; nincs kitalált adományszabály vagy új partnerhitelesítés. Az egész termék minden-provider elfogadása addig nincs kész.
- Átadásban megőrzendő, nem végrehajtandó Substack-pointer: `/Users/bujdosoarnold/ai-agent/SUBSTACK-HANDOVER.md`; kézikönyv és stílusjegyzet hivatkozása az eddigi átadás szerint. Közös memória átírása nincs.

## Független tervaudit 1 — tényleges eredmény

Külön olvasásos reviewer két P2 pontosítást talált: (1) jogosultsági időkorlát nem azonos a pontos vásárlási idővel; (2) egy sale-minta nem bizonyít rendelésazonosító-szemantikát. Mindkettő fent javítva; az időszabály az audit idején felhasználói döntést igénylő változásként szerepelt; Arnold azóta kifejezetten jóváhagyta. P1-et nem jelzett. A review nem teszt vagy szolgáltatói elfogadás. A második független reviewer ezt a javított változatot kapta.

## Független tervaudit 2 — tényleges eredmény

Az első audit javításai után másik reviewer vizsgálta a tervet. P1-et nem talált; egy P2-t jelzett: az MNB-kérések száma külön korlát nélkül túlnőhet a négy Dognet-oldalon és késleltetheti a közös consumert. Javítás fent: legfeljebb4 új FX-nap/poll, már befagyasztott eredmény keretmentes, lapkurzor megőrzése és rendes cronfolytatás. A runtime-futáskeret külön ellenőrzendő a telepítési célon. Ez dokumentált tervjavítás, nem újabb független recheck vagy runtime PASS. A reviewer a további kötelező tényeket külön jelölte: hiteles ID-mapping, felhasználói időszabály-döntés, tényleges implementációs teszt és aktiválási readback.

## Felhasználói döntés — időszabály jóváhagyva

Arnold a tervhez feltett konkrét kérdésre: „Igen, a bizonyított átállás utáni vásárlás így pontozható.” A 4. pont ennek megfelelően véglegesítve. Ez a szabály jóváhagyása, nem implementációs teszt, telepítés vagy Dognet-aktiválás bizonyítéka.

## Végrehajtási pontosítás — a jóváhagyott időszabállyal

Ez az előző két auditot használja újra. Az alábbi függvény-, adat- és sorrendpontosítások nem új független audit vagy implementációs bizonyítékok. A runtime-fájllista továbbra is három fájl; nincs új szolgáltatás vagy séma.

### 1. A meglévő függvények pontos szerződése

**Mindkét transzportdefiníció:** `dognet_api_request($method, $path, $body = null, bool $allow_auth_refresh = true, bool $raw_response = false)`. A pontadapter az utolsó két argumentumot `false, true` értékkel használja. Sikeres HTTP esetén az opcionális mód az eredeti törzset adja; az alapértelmezett négyargumentumos hívások dekódolt válasza változatlan. Az új mód hibájában csak HTTP-kód és biztonságos hibakód kell, nem nyers rendelési payload. Ha a régi refresh-ág rekurzívan újrahívja a függvényt, az ötödik argumentumot is megőrzi. A pontadapter ezt az ágat nem használja. A két fájlt külön processzben teszteljük, mert a function_exists miatt egy bootstrapban csak az egyik definíció él.

**`impactshop_dognet_points_event`:** a már pontos stringekre dekódolt sorból a meglévő capture bemenetét állítja elő. A normálizálás sorrendje:

1. hiteles namespace/channel, mezőméret, típus és szolgáltatói azonosítás;
2. meglévő root/pending/award snapshot olvasása vagy első SAT1-korreláció;
3. hiteles merchant/campaign, subject és időkorlát ellenőrzése;
4. P/A/D szerinti minősítés, és csak alkalmas első A esetén MNB+összeg;
5. stabil, minimális normalizált esemény és egyszeri capture.

Az adapteren belüli kis pontos-JSON dekódolás és snapshot-lekérdezés indokolt: a meglévő transzport floatot ad, a CJ snapshot-függvénye pedig privát és CJ-specifikus. Ezekhez nem készül általános helpercsomag vagy CJ-refaktor. A végső pontmódosítást kizárólag a meglévő consumer/manager végzi.

**`impactshop_dognet_points_poll`:** ellenőrzi az OFF állapotot még hálózat előtt; megszerzi saját röviden ellenőrizhető DB-zárát; betölti a rögzített sweepet; lekéri és pontosan dekódolja az oldalt; feldolgozza a sorokat; csak igazolt teljes lap után menti a kurzort. Minden eredmény a meglévő cron számára értelmezhető tömb: `success`, `status`, `captured`, hiba esetén `error`. `disabled`, `busy`, `in_progress`, `completed` külön állapot; az `in_progress` nem sikertelen szolgáltatói kérés és nem teljes bejárás. Végül zárfelszabadítás, kivétel nem juthat a CJ/consumer elé.

### 2. Események és befagyasztás

| Beérkező helyzet | Adaptereredmény és változatlan core-viselkedés |
| --- | --- |
| P, teljes hiteles kötés | `pending`, stabil revision; időrend ekkor csak `cutover ≤ redirected_at ≤ created_at`. Nincs date_approved-, MNB- vagy végleges jutalékkövetelmény. Nulla pont. |
| Ugyanaz a P más becsült jutalékkal | Ugyanaz a normalizált pending esemény. A még nem végleges commission/total_cost és updated_at nem kerül a pending gazdasági hashébe. Nem keletkezhet pusztán ettől végleges manual állapot. |
| Első A, teljes hiteles kötés | Pozitív sale/jutalék, jóváhagyási idő és teljes időrend után `award`; végleges pénzügyi alap egyszer befagyasztva. |
| P→A lejárt attribúciós token mellett | A korábban tárolt kötés, merchant és SAT1 alsó időkorlát használata; sem új subject, sem új idő nem írja felül. A szolgáltatónál megőrzött SAT1 token hashének is egyeznie kell. |
| A változatlan ismétlése | Ugyanaz a revision és gazdasági tartalom; tárolt FX/adomány újrahasználata, új pont és új FX-kérés nélkül. |
| Azonos A eltérő végleges összeggel vagy kötési adattal | Megőrzött konfliktus/kézi eset; második jóváírás nincs. A basis eltérését nem nyomjuk el az első snapshot visszaadásával. |
| D, összetett vagy nem vásárlási esemény | Megőrzött kézi eset, automatikus pénzmozgás nélkül. P→D sem bizonyít visszafizetett korábbi pontot. |
| Érvényes jutalékból 0 egész HUF adomány | Nem pozitív award; konkrét nullás eredményű kézi lezárási eset a meglévő adminúton. Nem adunk minimum1pontot és nem bővítjük a core állapotgépét. |
| MNB átmeneti hiba vagy futási keret | Nincs hamis adjustment-capture, amely végleg kézire zárná a rootot; az oldal folytatásra marad. A már sikeresen tárolt többi esemény megmarad. |

Az `event_id` a szolgáltatói rekordazonosság; a `purchase_id` a rögzített, hiteles mapping eredménye. A `revision` legfeljebb64byte és státuszhoz kötött, nem poll-időbélyeg. `authority_ref` legfeljebb160byte; `donation_basis` legfeljebb512byte, verziózott és tömör, a nyers válasz nélkül. A core ezeket csonkolhatná/elhagyhatná: az adapter ezért a capture ELŐTT ellenőrzi a határt és kézi indokot ad túllépéskor. A teljes JSON-válasz hashét nem használjuk gazdasági konfliktusjelként.

A befagyasztott első pending alap tartalmazza a stabil azonosságot, campaign-t és a `sat1_redirect_lower_bound` időbizonyítékot. A végleges alap ehhez adja a pontos commission/sale összeget, finalizálási időt, EUR jegyzésnap/rate/unit adatot és szabályverziót. A meglévő normalizált esemény már külön tárolja a merchantet, tokendigestet és adományt: ezeket nem duplikáljuk szükségtelenül az512byte-os mezőben. A nyers SAT1-token nem kerül a basisbe.

### 3. Beállítások, bejárás és bekapcsolás pontos állapota

| Beállítás | Telepítés közben | Aktiválás után |
| --- | --- | --- |
| `impactshop_dognet_points_channel_id` | Előállapot mentve; új adapterhez26081 | 26081 |
| `impactshop_dognet_points_scan_from` | Üres, Dognet hálózati poll OFF | 2026-10-29 |
| `impactshop_dognet_points_scan` | Előállapot megőrizve; nem vakon törölve | Egy kis objektum: verzió, channel, cutover, felső időhatár, last_id, bejárási állapot |
| `sharity_points_impactshop_lanes` | Meglévő lista megőrizve | Meglévő lista + pontosan `impactshop:dognet:26081` |
| cutover / legacy / CJ kapcsolók | Előállapot ellenőrizve | Cutover15:37:39UTC és legacy0 változatlan; CJ folytatódik |

A sweep opció a channel/cutover értékét is tartalmazza, hogy más beállításhoz tartozó kurzort ne használjunk észrevétlenül. Leállítás nem törli ezt az opciót. Új sweep csak az előző dokumentált befejezése után kezdődik; a közös gazdasági run_id-hoz nem nyúl. Hiányzó vagy ellentmondó pagination-meta explicit hiba, nincs id/transaction_id/tid fallback. A régi totals ilyen fallbackje nem pénzügyi mapping és nem másolandó át.

Aktiválási sorrend: (a) friss prefix/kapcsolók/allowlista és privát előállapot; (b) saját csatornaérték; (c) a változatlanul visszaolvasott allowlistához a Dognet elem hozzáadása; (d) readback; (e) scan_from; (f) readback. Eltérő friss előállapotnál nincs egész listát visszaíró felülírás. A natív WP-CLI/WordPress beállítási út és szükség esetén rövid célzott DB-zár használható; nincs új konfigurációkezelő. Aktiválás előtt egyértelmű legyen, hogy saját új beállításról vagy már létező, más munkában módosított értékről van-e szó.

Rollbackkor a scan_from kikapcsolása csak az új beolvasást állítja meg: a már queued események még mozgatnának pontot. Ha éppen a gazdasági helyesség a hibás, a Dognet namespace-t is el kell távolítani, majd a már futó consumer-tranzakció eredményét visszaolvasni. A már commitolt pontot nem tekintjük visszavontnak ettől; a megőrzött esemény alapján kézi rendezés következhet. Pusztán transporthiba esetén nem kell a jó, már tárolt eseményeket kézire karanténozni. A CJ-allowlista és a többi jogosultság megmarad.

### 4. Lezárandó technikai tények — korlátozott vizsgálattal

- **Azonosítás:** a most olvasott totals-kód csak cursor-fallbackként használja az id/transaction_id/tid mezőket. Ez nem igazolja a rendelési mappinget. A meglévő hivatalos leírás külön order_id-t és raw-id-t nevez meg. Első implementációs lépésként csak a normál sale egy dokumentált detail-válaszát és a hozzá tartozó mappingleírást kell ellenőrizni; az aktuális CPC-minta nem elegendő. Amíg ez nincs meg, a normalizáló gazdasági bekötése nem nevezhető késznek. A pontos transport, lapozás és offline tesztek ettől elkészíthetők. Nem indul ismételt általános kutatás vagy szolgáltatói levél; ténylegesen hiányzó adat esetén pontosan azt az egy hiányt kell jelenteni.
- **Tokenellátás:** a pontadapter helyesen nem frissít automatikusan401-re. Aktiválás előtt a már meglévő token-cache lejárat utáni ellátását kell célzottan ellenőrizni. Egy jelenleg működő token nem bizonyít tartós üzemet. Ha nincs használható meglévő út, a hiány külön, konkrét authfeladat; nem rejtett fallback és nem egész DEV-javítás.
- **Futásidő:** a160s hálózati felső becslés nem mért cronidő. A tényleges hostkerethez igazított kérésszám/timeout és a fogyasztónak maradó idő kerül a releasejegyzetbe. Nem emelünk globális PHP-limitet egy adapter kedvéért.

A pénzügyi és felhasználói időszabály eldöntött. A fenti három technikai tényt nem állítjuk ellenőrzöttnek pusztán ettől a tervtől; mindegyik kizárólag a tőle függő műveletet korlátozza.

### 5. Munkaegységek és kilépési feltételek

1. **Adapterforrás:** három runtime-fájl + közvetlen fixturek. Kész, ha a fenti eseménymátrix és a hibák valódi teszttel teljesülnek. Előbb kis offline adatok; csak szükséges provideroldal, nincs fizetett próba.
2. **Gazdasági illesztés:** meglévő saját SQL-harness néhány Dognet-folyamata. Az aktuális törölt/lejárt intentet is modellezi, nem csak a callback stubját. Kész, ha a tényleges tárolás, befagyasztás, ismétlés és kézi állapot igazolt. A meglévő bonus-dedupe és limitkeretek nem változnak.
3. **Review és publikálás:** egy célzott független implementációreview; DOCSYNC/digest; natív commit/push/PR és négy valós CI-check. Új általános tervaudit csak lényeges gazdasági/hatókör-változásnál. Újrafuttatás kizárólag módosult kód vagy tényleges hiba miatt.
4. **Merge és kiadás:** friss rövid mainablak, merge-SHA readback, külön release-klón; staging három exact-file, majd ugyanaz productionre. Új fájldigest + tényleges bootstrap + meglévő kuponhívó smoke. Nincs régi live-adat visszatöltése vagy teljes új SQL-restore-próba.
5. **Aktiválás:** fenti sorrend és rendes cron readback. Kész, ha az új lane fut és CJ is továbbműködik; a természetes vásárlási minta hiánya még nem élő pénzügyi elfogadás.
6. **Élő elfogadás:** első természetes helyes jóváírás, ugyanazon esemény normál újraolvasásakor nulla többlet, összesen legalább20 jogosult természetes esemény és minden aktív provider lefedése. A várakozás alatt nincs állandó polling vagy mesterséges vásárlás. Heti kivételáttekintés a meglévő list/show útból.

A mostani tervezési folytatás csak ezt a dokumentumot és a notes-bejegyzést módosítja. Commit/push/PR/merge/telepítés/aktiválás ebben a körben nem történt, csendablak nincs tartva.


## 2026-10-29 — Dognet normál jóváírás forráskész

Három runtime-fájl; channel+campaign:order_id vásárlás, rawid esemény;
jóváhagyott SAT1 alsó időkorlát, EUR jutalék50% × MNB, egyszeri kerekítés.
Négyoldalas folytatható poll, négy új FX-nap, meglévő consumer/schema.
Provider/transport/money/MNB fixturek, PHP-lintek és139 saját SQL-check sikeres.
Független review javításai célzottan visszaellenőrizve. Merge/deploy/aktiválás
még nyitott; Dognet korábbi OFF állapota ebben a körben nem változott.
Részletes működés/teszt/rollback/UI: `docs/protected-change-records/2026-10-29-impactshop-simple-points.md`;
végrehajtási terv: `docs/dev-plans/impactshop-dognet-confirmed-purchases-2026-10-29.md`.
