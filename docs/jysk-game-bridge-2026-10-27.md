# JYSK game WordPress bridge — elkülönített forrás

## 2026-10-28 — eredmény, sorsolás és privát nyeremény, implementáció

Saját ág feat/jysk-game-completion-20261028, alap368e466a; ez az új fejezet
a következő forráskiadást írja le, nem már megtörtént deploymentet.
Protected files touched: wp-content/mu-plugins/impactshop-jysk-game.php,
docs/impactshop-guard-hashes.json, docs/impactshop-guard-hashes.sha256,
docs/bastion-guard-status.md. Arnold a teljes fájlszintű terv implementációját
jóváhagyta; a BASTION_OVERRIDE e konkrét reviewed változás dokumentált
feloldása, nem guardkód- vagy idegen scope-kivétel. Rollback és smoke scope
az alábbi ellenőrzési fejezetben, kizárólag JYSK és deploy-integritás.
Egyetlen saját bridge bővül. A közös pont/identity/legacy sorsoló és TLS
változatlan; új runtime konfigurációs kulcs vagy scheduler nincs.

### Adatút és ellenőrzött műveletek

Az app game-results-export konzisztens, zárt, időkapuzott Turso-olvasásából
származó privát jysk-game-results-v1 fájl érkezik. Tartalmazza a forrás/policy/
roster/döntés/eseményhash és ellenőrzött NGO-szavazat, illetve külön személy-
sorsolási súly mezőket. Privát szervezői többprofilos ellenőrzés történik;
a profil nem bizonyít természetes személyazonosságot. A notificationSubject
egy ellenőrzött csoport profilja; a mapping HMAC-azonossága ellenőrzött,
régi grant lejárata nem törli a megszerzett szavazatot.

Saját új results/prizes/prize_events táblák, explicit WP-CLI DDL-lel.
Minden eredményírás előtt: valódi wpdb, dropin hiánya, writer DB, érintett
saját és közös üzenettáblák InnoDB-je és saját elsődleges indexek. Nincs
közös tábla-konverzió. A valódi escaped INSERT mérete max_allowed_packethez
kötött. Unknown schema/engine csak az eredményírást állítja meg.

```text
wp jysk-game results status
wp jysk-game results install --expected-version 0 --intent-file /private/install-intent.json
wp jysk-game results prepare --input /private/results.json --expected-hash H --expected-version V --intent-file /private/prepare-intent.json
wp jysk-game results approve --expected-hash H --expected-version V --verification-file /private/verification.json --intent-file /private/approve-intent.json
wp jysk-game results resume --expected-hash H --expected-version V --intent-file /private/resume-intent.json
wp jysk-game prize status
wp jysk-game prize status --input /private/claim-lookup.json
wp jysk-game prize record --input /private/event.json --expected-version V --intent-file /private/record-intent.json
wp jysk-game prize redraw --input /private/redraw.json --expected-hash H --expected-version V --intent-file /private/redraw-intent.json
```

Az inputok repón kívüli saját0700 könyvtár/0600 fájlok, max8MiB, symlink
nélkül. Status olvasó, más művelet saját privát intenthez kötött:
kind=jysk-game-operator-intent-v1, operation=results-prepare/results-approve/
results-resume/results-install/prize-record/prize-redraw, expectedVersion egész,
parameters a pontos CLI-opciók az intent-file nélkül, input/verification-file
esetén a fájlok input-sha256/verification-file-sha256 értékével is.
Folyamat-env megerősítés: JYSK-GAME:<operation>:<version>; titok nem argumentum.
Az install version0, prepare kezdetben0→1, approve1→2→published3.
Status az aktuális verziót/hash-t mutatja; nem ad seedet vagy játékoslistát.
Minden mutáció után friss status szükséges.

A verify outputját az app készíti friss tartalomellenőrzéssel; a WP approve
ötperces időablakban a pontos manifest/dataset/decisions hashhez köti.
A seed egyszer, jóváhagyó tranzakcióban rögzül. Utána a visszaállítható
feldolgozás atomikusan rögzíti a sorsolást, saját nyereményt, közös semleges
értesítést/célzást és saját mappinget. Ugyanaz a globális kampánylock és
kapcsolat/reconnect ellenőrzés védi az approve/resume/ACK/ügyírást.
Lockütközés busy, nem siker; bizonytalan commit után readback, nem új seed.
Jóváhagyott input nem írható át. Nincs automatikus reset/újrasorsolás.

### Véletlen és privát értesítés

V1 sorsolás: 32 bájtos seed; HMAC-SHA256 domain a manifestHash/round/kind/
slot/counter mezőkből; az első6bájt uint48, intdiv(2^48,bound)*bound
visszautasításos küszöbbel. PHP64bites egész; explicit bájtrendezés.
Három külön személy súlyozottan, visszatevés nélkül; 0/1/2 jogosult külön
eset, nincs későbbi helyfeltöltés. NGO-holtverseny külön véletlen domain.
A PHP és TypeScript ugyanazt a rögzített v1 vektort ellenőrzi.

GET /api/jysk/game/results az appból a rögzített signed bridge results
POST-ot hívja; a bridge nem publikus író. NGO-végeredmény játékosazonosító
nélkül, session és Turso megnyitása előtt is olvasható az appon.
Közös profilüzenet csak semleges link. A saját messages/ACK a tulajdonosi
sessionből olvassa a teljes nyereményt, az első igazolt megjelenés idejét
és a 7napos határidőt; közös üzenet lejárata/read flagje nem jogosultság.
ACK után újraolvasható, nincs határidőhosszabbítás. A reference nem
személyazonosság-igazolás. Lezárt játék után auth/read marad, reward külön off.

### Szervezői ügykezelés

Claim lookup fájl: {"claimReference":"<32 hex>"}; csak a pontos round/slot/
version/state/deadline kerül vissza, nincs teljes személylista-nyomtatás.
Event JSON: round(0/1), slot(1..3), kind, occurredAt(UTC másodperces ISO),
reference(mentett bizonyíték SHA256), details(rövid technikai ügyhivatkozás,
személyadat nélkül). Támogatott kind: received, deficiency-sent,
deficiency-delivered, response, disputed, decision, approved, fulfilled,
forfeit-decision. Minden esemény append-only; verzióütközésnél nincs írás.
Határidőben érkező hiányos igény/hiánypótlás/vita szervezői hold; nincs
automatikus jogvesztés. Fulfilled csak approved, forfeit-decision csak
dokumentált decision után írható. Személyes/postai átadás operátori feladat.

Redraw JSON: slots([ténylegesen megüresedett eredeti slotok]), reference(SHA),
deadlinesReviewed=true. Csak végleges forfeit-decision után; a fennmaradó
igénylési/hiánypótlási/átadási időt a szervező ellenőrzi. Legfeljebb egy
pótkör; minden korábbi nyertes kizárt, kezdetben üres hely nem tölthető fel.

### Ellenőrzés és visszaállítás

Független forrásreview: két P2 (receipt-egyediség, busy ACK) célzottan javítva;
delta-review lezárva, a javított határokon nyitott P0/P1/P2 nincs.
200 meglévő bridge,14 privát-profil,19 pure eredmény és172 valódi SQL
ellenőrzés PASS. Célzott PHP/protokoll és valódi izolált MariaDB
hibatesztek; nincs éles vagy közös staging pont/sorsolási próba.
Guard-hash csak az utolsó reviewed bridge után zárható. Deploy az existing
exact-file guarded úton, tiszta merge-forrásból, backup/CAS/0444/readbackkel.
Additív táblák és változatlan profile-keyek megtartják a korábbi auth rollbacket.
Publikált nyeremény után az olvasást megőrző javítás kell. Nincs adat/pont/
seed törlés vagy teljes production restore.

Kézi UI-checklist: /jatek belépés/reconnect; eredmény „ellenőrzés alatt” majd
NGO-végeredmény; csak saját privát üzenet nyitható; első látható nyitás után
változatlan hétnapos határidő; hiba után őszinte unknown és readback.
Kontroll: intake, profil, Ads Watch/Offerwall és meglévő JYSK-riport működése.
Forrás/merge/deploy/aktiválás/élő pontelfogadás/valós sorsolás külön státusz.

## 2026-10-28 — prefixjavítás telepítve, valódi profilkapcsolás igazolt

#232 merge `77c4abbf1b7d45ce9e01eadb102e44554dbdcd15`, tree
`f8c688d1b68c7697440013d1b7bc0a7651c1e037`; exact saját checkout és source/hash
readback után a rövid main-ablak azonnal felszabadult. Az egyfájlos guarded
dry-run sikeres (két nem blokkoló latency warning), majd az öt production
preflight endpoint warning nélkül válaszolt. Release
`jysk-wire-prefix-20261028-77c4abbf`: privát elődmentés, CAS/atomi csere,
remote SHA256 `53f972cd70950730e343b9b220b04afcad4af69bf653e7b5e44b681d30846cbb`,
mód0444 és valódi WP-boot visszaolvasva. Más fájl/TLS/config nem változott.

09:27:10Z-kor a valós signed context hibaválasza pontosan 27 JSON-bájt,
HTTP403/invalid_session, az exact-wire HMAC helyes. Ezután a meglévő valódi
tulajdonosi böngészőprofil → WP handoff → JYSK callback → session sikeres;
„Sharity-profil kapcsolódva” látható. A kampány zárt, a részvételi jelölők
nem lettek elfogadva, nincs jutalomkérés. WP-profil ready/auth-enabled,
rewards=false, transaction=false. A 09:29Z natív DB-readback: game inactive/V1,
kapuk000, roster0/participants0; intake open/V20/111/hold1, nincs backlog/lease.
Az intake korábbi egy kizárt tesztje és két sent levele változatlan.

Rollback: a meglévő `bin/impactshop-guard-rollback.sh --production --apply
--release-id=jysk-wire-prefix-20261028-77c4abbf
--expected-deployed-sha=53f972cd70950730e343b9b220b04afcad4af69bf653e7b5e44b681d30846cbb`;
előbb inspect, idegen drift esetén nincs írás. Nincs DB-restore/törlés.
Bizonyíték: saját ignorált final-dry/final-release napló, kapcsolódott képernyőkép;
privát exact-wire és DB-readback JSON. Teljes játékaktiválás/35 pontos élő
elfogadás nincs: jóváhagyott roster a jelentkezészárás után, pontszerzés
november9.12:00 Budapest előtt tiltott. E záró dokumentáció helyi checkpoint,
nem új runtime vagy újabb telepítést igénylő változás.

## 2026-10-28 — a #231 review-zott bridge-hash célzott felvétele

Protected files touched: `docs/impactshop-guard-hashes.json`,
`docs/impactshop-guard-hashes.sha256`; smoke scope: deploy_guard,
`deploy:checksum-verify`, `deploy:guard-preflight`.
Arnold kifejezetten jóváhagyta az egyetlen JYSK-hash és a külső checksum
frissítését. Alap: #231 merge `f61e60c86f39a0d4a481a127ec4ab4185cbb325d`.
Az új érték a már függetlenül review-zott, 200+14 célzott teszttel ellenőrzött
`impactshop-jysk-game.php` SHA256-ja:
`53f972cd70950730e343b9b220b04afcad4af69bf653e7b5e44b681d30846cbb`.

Koherencia/kockázat/érintettség: a guard változatlan programja pontosan ezt a
merge-elt fájlt ismeri fel; a régi baseline `bce8b1ad…` csak a kiadási próbát
állította meg, élő fájl nem változott. Más hash, időbélyeg, inventory, config,
engedélyezési logika vagy runtime-forrás nem változhat. Fő kockázat téves
forrás legitimálása: teljes strukturális one-key diff, exact forrásdigest,
külső checksum és a valódi guard teljes inventory-ellenőrzése szükséges.
A meglévő hat pozitív/negatív hash-regresszió fut; nincs új helper vagy tesztkód.

Rollback: csak e baseline-commit célzott revertje új PR-ben; ettől a régi
ismert blokkolás tér vissza. Ez nem production rollback vagy adat-visszaállítás.
Kézi UI/smoke: e forrás-only hash-adoptálásnak nincs új UI-ja; merge után a
meglévő egyfájlos guarded dry-run, majd backup/CAS/0444 kiadás és exact-wire
HMAC/profilkapcsolás továbbra is kötelező. Nincs auto-approve bővítés,
guard-kikapcsolás, Vercel-build vagy pontaktiválás.

## 2026-10-28 — JYSK aláírt válasz előtagjának célzott javítása (forrásmunka)

Protected files touched: `wp-content/mu-plugins/impactshop-jysk-game.php`.
Smoke scope: identity_and_points; ez a bejegyzés a célzott change record.

Reprodukció a karbantartás megszűnése után: a hitelesített, nem létező sessionre
irányuló context-kérés HTTP403/`invalid_session`; a wire 28 bájt, kezdete LF,
utána 27 bájt JSON. HMAC az exact 28 bájtra hibás, a 27 bájtra helyes.
A JYSK fogyasztó jogosan zár (`game_bridge_unverified`); a kliens nem trimelhet
és nem gyengítheti az exact-wire ellenőrzést. A teljes válasz privátan megőrzött.

Módosítás előtti koherencia/kockázat/funkciólista: kizárólag a saját
`impactshop-jysk-game.php` válaszkezelése és hermetikus bridge-tesztje érintett.
Minden saját aláírt service-ág (exchange/context/revoke/reward-status/award/
messages) ugyanazt a response/serve utat használja; a böngészős handoff marad.
Globális identity, pont-/level-manager, TLS/WAF, más REST-válasz vagy plugin
nem változhat. A saját service belépésénél, DB-művelet előtt és a saját wire
kiküldésekor csak ismert, tisztítható alap PHP-puffer ASCII whitespace-e
üríthető. Nem-whitespace, nagy előtag, már elküldött fejléc, ismeretlen handler
vagy nem üres külső puffer: fail-closed, nem aláírt siker. A puffer és handler
nem szüntethető meg, más útvonal outputja nem tisztítható.

Kockázat: más kimeneti pufferprofil zárhatja a saját bridge-et; ezért negatív
puffertesztek, exact-wire HMAC-teszt és független diffreview szükséges. Late
output-hiba után már commitált eseményt nem ismétlünk: a meglévő claim/receipt
readback kezeli, a jutalmazás most végig tiltva marad. Nincs új release-rendszer.
Már kiküldött fejléceknél a státusz/aláírásfejléc nem vonható vissza; ilyenkor
nincs fejlécátírási kísérlet. A hibás wire-t az exact-byte fogyasztó utasítja el.
Ezt külön, output_buffering=0 alfolyamat igazolja; a DB előtti zárás is ellenőrzött.
Kiadás csak egyfájlos guardolt merge-elt forrásból, friss SHA/backup/CAS/0444
readback mellett; visszaút az exact saját release guardolt rollbackje.
Ellenőrzés: lint, bridge/private-profile célzott teszt, érintett Bástya; live
aláírt hibaválasz és valódi owner→callback→session próba, jutalom nélkül.
Kézi UI: `/jatek` → „Kapcsolódás a Sharity-profilommal” → kapcsolódott állapot;
ne legyen raw JSON hiba, a kampány maradjon zárt, a civil jelentkezés működjön.
Forrás/merge/telepítés/profilpróba állapotát külön kell rögzíteni; jelenleg nincs
új kiadás vagy sikeres profilkapcsolási bizonyíték.

Helyi eredmény: PHP-lint, 200 determinisztikus bridge assertion és 14 virtuális
private-profile eset sikeres. Az első célzott fixture az objektumhash-újrahasználat
miatt más route-ra is elérő serve ágat talált; explicit saját routeprefix rendezi.
Független 6.1 Sol/high review: P0/P1 nincs; a már kiküldött fejlécek utólagos
átírására vonatkozó P2 guarddal és külön alfolyamatteszttel javítva. A javított
delta második review-ja nem talált fennmaradó P0/P1/P2-t; nem FPM/live-PASS.
Protected-touch és öt saját fájl natív tartalom/titok-audit sikeres. A gépi
identity_and_points smoke-tagek érintettségi scope-ot jelölnek, nem nyolc most
lefuttatott UI-folyamatot; a változatlan ledger SQL-bizonyítékot újrahasználjuk.

## 2026-10-28 — háromfájlos production kiadás visszaolvasva, jutalmazás zárt

A #229 merge `f8f3c4568ee437fe6ab0c4ba871f2bd6c9b135e3` saját tiszta detached
worktree-jéből, külön jóváhagyott scope-ban mindhárom fájl a canonical
`bin/impactshop-guard-deploy.sh` → exact-file engine útvonalon kiadva:

| Fájl | Release-ID | Távoli, visszaolvasott SHA256 |
| --- | --- | --- |
| `sharity-level-manager.php` | `jysk-level-20261028-f8f3c456` | `136e2ea9bab04a7265af7239c7bdb27b340bec645430455367d84d2a3de545ad` |
| `sharity-points-manager.php` | `jysk-points-20261028-f8f3c456` | `5f0be67fe56a09c087f0e62b2db617ca9ef385e21db671cb5d0d96251ed5d3bc` |
| `impactshop-jysk-game.php` | `jysk-bridge-20261028-f8f3c456` | `bce8b1adfc65a9da17f5632700c6d4ce6c14570d7a0e51740801574cd8d9c8b5` |

Mindegyik külön írásmentes próba, elődhash-CAS, távoli privát mentés, atomi
csere/PHP-lint és 0444 mód mellett. Az eredeti production profil változatlan;
az Air → Mini → S59 kapcsolat folyamaton belüli transport, gépkonfig-/kulcsírás
nélkül. Bastion manifest és public_html→app origin ellenőrzés sikeres. Öt
preflight endpoint válaszolt; a bridge-kiadásnál ticker4166ms latency warning,
nem blokkoló hiba. A WP-boot valóban betölti a bridge-et és a pontkezelőt.
Más meglévő pluginok nullable/deprecation jelzései látszanak; nem JYSK-javítások.
`app.sharity.hu/wp-json/` frissen HTTP200; TLS-beállítást nem módosítottunk.

Explicit `Impactshop_Jysk_Game::install()` létrehozta a hat saját üres táblát;
mind InnoDB. A három érintett közös ponttábla is InnoDB; valódi `wpdb`,
read_only=0, routing db.php drop-in nincs. A privát profil 0700/0600, azonos
tulajdonosú, symlink nélkül; enabled/rewards/transaction=false, külön kulcsokkal.
Sem pontírás, sem profil-belépési/üzenet-/sorsolási acceptance nem történt.
Az izolált 141 SQL-ellenőrzés a változatlan kódhoz továbbra is használható,
de nem helyettesíti a promóció utáni valódi owner-grant/callback próbát.

Rollback: előbb JYSK reward/acceptance off; saját release-ID ellenőrzése
`bin/impactshop-guard-rollback.sh --production --release-id=<ID>`, majd szükség
esetén `--apply --expected-deployed-sha=<fenti SHA>`, fordított fájlsorrendben.
Idegen driftet nem írunk felül, pontadatot nem törlünk. A remote engine őrzi
az elődbyte-okat és az eredeti módot; bridge előállapota absent volt.

Kézi UI-checklist: a Sharity profil normál pontösszeg/szint/üzenet megjelenése;
JYSK `/jatek` elérhető, kampánykezdet előtt nincs pontszerzés; későbbi saját
tesztprofilnál az ismételt aktivitás nem ad új pontot. Jelenleg csak WP-boot,
forrásparity, séma és a fenti olvasó endpointok igazoltak; valódi jutalom,
szintküszöb és downstream értesítés élő tesztje nem állítható PASS-nak.

A kiadás után saját dokumentációs ág készült; új main-fetch vagy csendablak
nem kellett. A JYSK appkiadás és végleges roster/aktiválás külön állapot.

## Valódi helyi MariaDB-próba — 2026-10-27, irányadó új checkpoint

WP #227 merge-elt: `2823f4a0aa7b04e328e71739f8e3f969b971c08b`, tree
`daf06c37a0897ce0a6a51fd3ab1d14c8838f79bf`. A lentebbi nem-merge-elt státuszok
történetiek. Nincs új main-fetch vagy csendablak, WP-production telepítés sem.

Arnold külön engedélyével a meglévő helyi Dockerben ideiglenes, kizárólag
localhoston elérhető adatbázis futott, 512 MiB / 1 CPU kerettel, tmpfs adatokkal.
Az éles szerver olvasással igazolt verziójához illesztett MariaDB10.11.19 kép:
`sha256:d769e37960821dfdcf096954f6e7ac89f747e116c1bba98e48edd265dfc7deae`.
Nincs éles adat/konfiguráció másolva; csak szintetikus rekordok és saját tesztkulcsok.
A valódi target wpdb osztály olvasott, hash-ellenőrzött másolatának SHA256-ja
`0fd1ffb9a24e9db8cb7370965db379a2fecd985dbeb73d319521cc1bca1bd04c`.

Új, célzott `tests/jysk-mysql.integration.php`: **141 ellenőrzés PASS**,
PHP lint sikeres. A tényleges merge-elt canonical manager/level/bridge kód futott.
Igazolt: 11 SQL-hibahatár rollbackje, valós KILL CONNECTION, COMMIT után
modellezett hibás visszaigazolás, hat külön kapcsolatos párhuzamos kérés;
azonos dedupe-kulcs egyszer ír, külön kulcsok nem veszítenek növekményt.
A manager-kör aktív SQL-tranzakcióinak átfedése ténylegesen mért; a bridge
busy kérése a valódi event-lockot tartó művelettel fed át (a lock a claim
tranzakciója előtt kezdődik). Tartós claim, unknown→absent→azonos retry,
receipt-readback és a 35 pontos technikai plafon napváltás után is igazolt.
Nem tranzakciós engine és hiányzó gazdasági unique index elutasítása vizsgált.

Korlát: a WordPress boot/auth és downstream hookok nem futnak, a bridge órája
csak a helyi fixture namespace-ben helyettesített. A COMMIT-hiba nem tényleges
hálózati csomagvesztés. A technikai plafon napváltási esete soros; közös subject
lockja forrásreview-val ellenőrzött. Nincs production-időeltolás, pontírás vagy
aktiválási bizonyíték. A 6.1 Sol/high független review blokkoló forráshibát nem
talált; a mért párhuzamosság állítását pontosító tesztjavítás bekerült, újrafutott.

Reprodukció saját, új lokális konténerrel (titkot ne adj CLI-argumentumban):
`JYSK_MYSQL_SANDBOX_CONFIRM=LOCAL_JYSK_ONLY JYSK_MYSQL_CONFIG=<privát-0600-json> JYSK_WPDB_FILE=<ellenőrzött-wpdb-fájl> php tests/jysk-mysql.integration.php`.
Az eredmény és futási metaadat a saját ignorált `tmp/jysk-mysql-20261027/`
alatt megőrzött. Ez a SQL-réteg bizonyítéka, nem teljes WordPress staging-PASS.
Az exact saját `22af2647c2f7…` tesztkonténer a végső jelentés mentése után
leállítva és `--rm` útján visszaolvasottan eltávolítva; a tmpfs szintetikus DB
megszűnt. Más konténer, Docker-beállítás vagy megosztott image/cache nem törlődött.

Friss config-összevetés: `app-staging` és `app` ugyanazt a DB_NAME/DB_HOST
célpontot használja. A közös stagingen nem futott pontíró teszt/deploy.
A production readiness flag false marad; guarded kiadás, saját profil/séma,
backup/restore, valódi belépés/HTTP és Card-owned TLS javítás még szükséges.

## Publikált forráscheckpoint — 2026-10-27

Az alábbi korábbi helyi állapot történeti. A forrás PR #227-ben publikált:
`26365a9b759524baf89f8cf1d3e0207bd538c124`, rögzített main-alap
`68a6c3241c8be74ff9998d57ca0a3dac24c83904`;
tree `69432e0ad4de6423e831086d7310bae255b63d05`.
Arnold külön jóváhagyásával az egyetlen saját commit erre az alapra került;
a végső tartalom byte-azonos a korábbi helyi, feloldott integrációval.
Mentési ref: `backup/jysk-pre-replay-20261027-829cc9c5`.

A közvetlen, exact main→HEAD audit ténylegesen sikeres: 17 fájl titokscan,
diff-check, protected-touch és commit-lane. A változatlan PHP-fára a korábbi
178 bridge / 124 canonical-manager assertion és 14 privátprofil-szcenárió
bizonyítékát használjuk újra. Valódi MySQL staging és éles acceptance nincs.

A történetfrissítéshez külön jóváhagyott native recovery tartozott:
egyetlen, pontos régi remote SHA-val védett force-with-lease; a non-fast-forwardot
nem támogató opcionális wrapper egyparancsos hooksPath felülbírálással nem
futott. Tartós hook/config/guard módosítás nincs, wrapper-PASS nincs; a valódi
termékguardok külön, változatlanul lefutottak. Remote OID readback sikeres.

A PR-es safe-audit sikeres. A push-es run40539571569 exit2 oka: a CI a
történetátírás előtti `ddd14318...` commitot nem találta (`rev-parse --verify`),
nem tartalom- vagy PHP-hiba. Nincs vak rerun vagy workflow-módosítás: a jelen
valódi DOCSYNC-státuszfrissítés normál fast-forward pushja már elérhető
elődcommitot ad. Új checkek eredményét külön kell visszaolvasni.

Az app PR #1768 merge-commitja `e9a6f92cfe3001f7e99cd8cd0a1bc6bb8f4e534f`.
A WP PR main-merge-je, WP/app-telepítés és pontaktiválás még nem történt meg.
A TLS-javítás a Card-szálban marad; ebben a szálban nincs infrastruktúraírás.
A jóváhagyott main-ablak a műveletek/readback után felszabadult.

Állapot: helyi, nem commitolt forrás; nincs push, merge, telepítés, aktiválás
vagy éles elfogadás. Saját worktree `impactshop-notes-jysk-game-bridge-20261027`,
ág `feat/jysk-game-bridge-20261027`, alap `73beb6f4598acdca22e7910d39a0088cd5f38b14`.
Az új modul `wp-content/mu-plugins/impactshop-jysk-game.php`; meglévő védett
identity-, vote-, Offerwall- és guide-fájl változtatása nincs. A később igazolt
live parity alapján külön jóváhagyott minimális pseudo writer/level tranzakciós
javítás is készült; lásd a kapcsolódó protected change recordot.

## Koherencia, kockázat, érintett funkciók

A jóváhagyott additív bridge kizárólag a `jysk-game/v1` REST névteret használja.
A böngészős handoff valódi `impactshop_identity_owner_authorized(pseudo)`
tulajdonosi ellenőrzést végez. A szolgáltatás az opaque subjectet a saját,
WordPressben maradó pseudo/grant leképezésen keresztül oldja fel. A grantet
minden munkamenet-, üzenet- és jutalomkérésnél újra ellenőrzi az eredeti owner
grant táblában. A saját revoke nem von vissza más termék owner grantjét.

Az új állapottáblák: `jysk_game_grants`, `codes`, `sessions`, `nonces`,
`rewards`, `message_map` a WordPress prefixszel. Létrehozás kizárólag explicit
operátori WP-CLI-hívással: `Impactshop_Jysk_Game::install()`; böngészős vagy
REST-kérés nem készít sémát. Új cron vagy watchdog nincs. A séma InnoDB.
A bearer kódot/munkamenetet és a kérésnonce-t csak SHA-256 hash tárolja.
A külön subject-kulcs és service-kulcs eltérése kötelező.

Fő kockázatok: hibás identity-bizalom, replay, árazás/schedule manipuláció,
párhuzamos jóváírás, kapcsolatvesztés, bizonytalan COMMIT, hamis nyugta,
meglévő üzenetek téves publikálása. A bridge fix 5/10 pontos policyt, nap/slot
ellenőrzést, gazdasági dedupe kulcsot, tartós előzetes claimet, kapcsolathoz
kötött eseménylockot és explicit üzenetleképezést használ. A shared manager
saját tranzakciója előtt a saját claim COMMIT-je befejeződik. Kétértelmű
korábbi managerhívás nem indul újra automatikusan.

Érintett funkciók: JYSK-profilkapcsolás és visszavonás, quiz-/videójutalom,
juttatás státusza, külön JYSK-profilüzenet és első láthatósági ACK. A meglévő
`read_at` nem kézbesítési bizonyíték; csak a saját `delivered_at` az. Az ACK
nem írja át a legacy olvasottsági állapotot. Üzenetpublikáló API nincs:
operátor írhat explicit meglévő targeted üzenet/pseudo + saját subject mappinget.

## Wire protokoll

A browser GET `/wp-json/jysk-game/v1/handoff` paraméterei:
`client_id=jysk_2026`, `redirect_uri=https://jysk.sharity.hu/api/jysk/game/session/callback`,
`state` (43–128 base64url karakter), `code_challenge` (43 base64url),
`code_challenge_method=S256`. A fix callback 302 választ kap `code` (64 hex)
és eredeti `state` paraméterrel. A code 120 másodperces és egyszer használható.
Új handoff generációváltása érvényteleníti az előző munkameneteket/kódokat.

A többi endpoint POST és service-HMAC köteles:

| Útvonal | Bemenet | Válasz |
| --- | --- | --- |
| `/exchange` | `clientId,redirectUri,code,codeVerifier` | `subject,sessionToken,expiresAt,earningAllowed` |
| `/context` | `subject,sessionToken` | ugyanaz az abszolút lejáratú context |
| `/revoke` | `subject,sessionToken` | `revoked:true` |
| `/award` | teljes eredeti jutalompayload | `state,eventId,payloadHash,points` |
| `/reward-status` | ugyanaz a teljes eredeti jutalompayload | ugyanaz a státuszséma |
| `/messages` | `subject,sessionToken` | `messages:[{id,title,body,type}]` |
| `/messages/delivered` | `subject,sessionToken,messageId` | `delivered:true,messageId` |

A subject és sessionToken 64 kisbetűs hex karakter; session 900 másodperc,
nem csúszó lejárat. A grant visszavonása minden contextet lezár. A korábban
elfogadott outbox jutalom sessionToken nélkül, még aktív grant mellett
sessionlejárat után is feldolgozható.

Fejlécek: `x-jysk-timestamp` (Unix másodperc, legfeljebb ±60 s),
`x-jysk-nonce` (32 kisbetűs hex), `x-jysk-signature` (64 kisbetűs hex).
A kulcs `hex2bin(JYSK_GAME_SERVICE_KEY)`. Az aláírt bemenet pontosan:

```text
timestamp\nnonce\nPOST\n/wp-json/jysk-game/v1/<path>\nsha256(rawBody)
```

A válasz signature ugyanazzal a kulccsal:
`response\nrequestNonce\nHTTPstatus\nsha256(rawResponseBytes)`. Az aláírt és
kiküldött JSON bájtok azonosak; minden bridge-válasz `no-store, private`.
Maximális request/response 65536 bájt. Hibás HMAC elutasításához nincs
adatbázisírás; hitelesített replay/storage hibák is aláírtak. Titkot nem naplóz.

A jutalompayload mezősorrendje a hashhez:
`eventId,subject,kind,dayIndex,slot,ngoId,points,occurredAt,policyHash,producerSha,isTest`.
Pontosan ezek a mezők engedettek, típusuk szigorú. A request kulcssorrendje
lehet más; a hash a fenti sorrendben képzett, unescaped JSON SHA-256 hash-e.
A `kind` `quiz_answered` (5 pont, slot 0–4) vagy `video_completed` (10 pont,
slot 0). `dayIndex` 0–20, a Budapest szerinti naptári naphoz kötve.
Időablak: 2026-11-09 12:00 Budapesttől 2026-11-30 00:00 Budapestig,
félig nyílt; tehát november 29. a zárónap. `occurredAt` UTC ISO milliszekundum
(`...000Z`), jövőbeli esemény legfeljebb 60 s toleranciával.
Új jóváírásnál a tényleges szerveróra is legalább a kezdő időpont kell legyen;
a 60 s occurrence-tolerancia nem ad korai írási jogot. A lezárás után már
elfogadott outbox továbbra is üríthető. A 105 quiz-ID
alkalmazásoldali kötése `jysk-(dayIndex+1+slot*21)`.

Az `eventId` a `jysk_2026:<subject>:<kind>:<dayIndex>:<slot>` UTF-8 string
SHA-256 hash-e. A globális dedupe `jysk_2026:<eventId>`; producer SHA vagy
policy hash nem kerül a gazdasági kulcsba. Policy/producer/NGO eltérés csak
explicit konfigurációval fogadható el, kliens nem szabhat árat. `isTest=true`
csak az operátor által előre besorolt, legfeljebb egyetlen exact
`JYSK_GAME_TEST_SUBJECTS` subject számára adhat a kampány kezdete után valódi
globális pontot. Üres/default allowlist tiltja. Nincs korai bypass. A hat
napi aktivitás legfeljebb 35 valódi pontot adhat a teljes kampányra,
`is_test=true` metadatával; következő napon sincs új keret. A technical
subject minden eseménye ugyanazon subject-derived GET_LOCK alá kerül;
kanonikus JYSK ledger pozitív pontösszeg + új pont legfeljebb 35 lehet.
SQL-olvasási hiba fail-closed; korábbi nyugtát a limit után is visszaolvas.
NGO-rangsorból és
sorsolásból az alkalmazás zárja ki. A besorolt subject nem küldhet isTest=false-t.

Státuszok: `confirmed`, `absent`, `busy`, `unknown`, `policy_blocked`,
`conflict`. `absent` csak igazolt globális nyugtahiány esetén adható.
Már létező claim teljes eredeti payloadeltérése `conflict`. Accepted claim
nyugta nélkül kezdetben `unknown`. Új POST előtt a státuszolvasó ugyanazon
writer kapcsolaton, GET_LOCK alatt és ellenőrzött tranzakciós séma mellett
igazolhatja az absence-t; a saját claim `absent` lesz. Csak ezután indulhat
ugyanazon immutable kulcs/body újraküldése, újabb tartós claim COMMIT után.
SQL-hiba vagy aktív lock esetén ez nem megengedett. A `confirmed`
globális nyugta pseudo/pont/type/source/metadata hash egyezést és előzőleg
ellenőrzött tranzakciós készültséget kíván.

## Konfiguráció és készültségi korlát

Alapból minden route zárt: `JYSK_GAME_ENABLED` csak boolean `true` nyitja.
Éles saját konfiguráció: `/home/sharityh/jysk-game/config.json`, kizárólag
ennek az új modulnak. Könyvtár `0700`, fájl `0600`, azonos owner, symlink
tiltott, méret legfeljebb 16 KiB. Hiányzó/hibás profil zárt alapállapotot ad,
nem WordPress fatalt. Exact engedélyezett uppercase JSON kulcsok: `ENABLED`,
`SERVICE_KEY`, `SUBJECT_KEY`, `REWARDS_ENABLED`, `TRANSACTION_READY`,
`POLICY_HASH`, `PRODUCER_SHAS`, `NGO_IDS`, `TEST_SUBJECTS`. Típusellenőrzés
kötelező, ismeretlen kulcs tiltott. Konstansok fixture-kompatibilitásként
megmaradnak. Nincs shared wp-config írás vagy másik profil/titok másolás.
Titokmentes operátori CLI diagnosztika: `Impactshop_Jysk_Game::configurationStatus()`.
Secret nélkül nem használható: `JYSK_GAME_SERVICE_KEY`, `JYSK_GAME_SUBJECT_KEY`
két eltérő, 64 kisbetűs hex kulcs, konfigurációból; nincs repo-secret.
Jutalomhoz külön `JYSK_GAME_REWARDS_ENABLED=true` és
`JYSK_GAME_TRANSACTION_READY=true` kell; mindkettő alapból false.
További explicit értékek: `JYSK_GAME_POLICY_HASH` (SHA-256),
`JYSK_GAME_PRODUCER_SHAS` (review-zott SHA-1 commitok listája, a függő
nyugtákhoz szükséges korábbi producerekkel), `JYSK_GAME_NGO_IDS` (pontos
jóváhagyott string allowlist). Üres allowlist nem nyit jutalmat.

Az eredeti live/base `Sharity_Points_Manager::award_points_for_pseudo()`
figyelmen kívül hagy SQL-false és COMMIT hibákat, a level-manager is ír.
A tényleges live/base parityt a Mini saját S59-kulcsával, a jelenlegi we040
hoston és a megőrzött szigorú hostkey-alias ellenőrzéssel igazoltuk. Ezután
minimális helyi pseudo writer/level SQL-/COMMIT-hibakezelési javítás készült:
`docs/protected-change-records/2026-10-27-jysk-canonical-points-transaction.md`.
Mindkét eredeti live hash ott szerepel. Távoli módosítás nem történt.
A readiness **nem igazolt**, értéke false marad: a helyi javítás nem staging proof.

Élesítés előtt kell: a pontos javított manager/level-manager kiadási parity; minden
érintett SQL-, level-write-, COMMIT-hiba és connection-loss fail-closed
kezelése a kanonikus writerben; saját és globális táblák InnoDB/schema
ellenőrzése; valódi DB-párhuzamossági és fault teszt; független security review;
titok/producer/policy/NGO-konfiguráció és operatori kiadási felhatalmazás.
Konfigurációs boolean nem helyettesíti ezt a bizonyítékot. Futáskor ezenfelül
exact alap wpdb osztály, DB-dropin hiánya, `@@read_only=0`, a négy jutalomhoz
érintett tábla InnoDB engine-je és a teljes gazdasági dedupe/pseudo/claim
unique indexei ellenőrzöttek: own claim `event_id`, user_points `pseudo_id`,
kanonikus point_transactions `(pseudo_id,dedupe_key)` összetett index,
teljes oszlopokkal. Routeres DB-dropin külön igazolt
writer adapter nélkül fail-closed. Ez nem valódi staging működéspróba.

Élő, kizárólag SELECT/SHOW readback 2026-10-27: `@@read_only=0`, három
globális tábla InnoDB, `uniq_dedupe_pseudo(pseudo_id,dedupe_key)` és
`uniq_pseudo(pseudo_id)` teljes unique index; `wp-content/db.php` absent.
Saját bridge-tábla még nincs telepítve. Egy kezdeti metadata SELECT szintaktikai
hibát adott, a javított pontos lekérdezés sikeres; adatírás és staging fault-
vagy párhuzamossági teszt nem történt.

A kritikus szakasz végig `reconnect_retries=0`, GET_LOCK alatt fut.
CONNECTION_ID és IS_USED_LOCK ellenőrzés van minden veszélyes határnál;
finally visszaállítja a korábbi retry értéket release-hiba esetén is.
WordPress wpdb magic getter/setter támogatását tényleges célkörnyezeten
ellenőrizni kell; nincs reflection vagy alternatív DB writer.

## Ellenőrzés, backup és rollback

Futtatva: `php -l wp-content/mu-plugins/impactshop-jysk-game.php`,
`php tests/jysk-game-bridge.test.php` (178 determinisztikus assertion),
`php tests/jysk-canonical-points-failures.test.php` (124 tényleges manager
fixture assertion), mindhárom érintett production PHP lint és diff-check.
`php tests/jysk-game-private-config.test.php`: 14 virtuális privát-profil
pozitív/negatív szcenárió; valós hostfájl létrehozása nélkül.
Nyilvános, szintetikus kereszt-nyelvi request/response fixture:
`tests/fixtures/jysk-game-protocol.json` (pontos JSON bytes és HMAC).
A fixture PHP órát névtérrel helyettesít, a production forrást használja;
nincs hálózat vagy valódi adatbázis. Bizonyítja a claim COMMIT előtti/utáni
hibák, manager-failure, nonce, payload, lock, reconnect, owner, PKCE,
session-generation/revoke és message-mapping határokat. Nem bizonyít MySQL
atomicitást, valódi WP-hookrendet, production parityt vagy böngészős működést.

A source rollback az új modul és teszt saját diffjének visszavonása.
Deploy csak merge-elt exact source-ból, guardolt pontos fájllal, előzetes
absent/file readback és távoli backup/CAS/rollback mellett. Aktiválás előtt
adatbázisbackup és restore-próba kell. Runtime rollback először mindkét
játék/reward flag lezárása; a saját claims/sessions/messages táblák maradnak
egyeztetéshez. Pontjóváírás automatikus visszatörlése vagy újrajátszása tiltott.
Eltávolítás nem töröl shared pontot, usert vagy meglévő üzenetet.

Kézi UI checklist az engedélyezett staging/éles kiadás után:

- Tulajdonosként kapcsolás a JYSK felületről és fix callback; idegen/raw-cookie
  profil elutasítás; rossz callback/state/PKCE elutasítás.
- Öt napi quiz és egy videó 5/10 ponttal; ismételt kattintás, refresh és párhuzamos
  ablak ne duplázzon; kliensárazás és másik nap/slot ne adjon pontot.
- Sessionlejárat/revoke után a context zárjon; már accepted outbox státusza
  legyen őszinte, bizonytalan esetben ne jelenjen meg sikeres pont.
- Csak explicit JYSK mappingű saját üzenet jelenjen meg, láthatósági ACK után
  tűnjön el; más profil és legacy olvasottság ne kapjon mellékhatást.
- Kontroll: meglévő profil/identity, Ads Watch, Offerwall, szavazás és
  `/jysk-riport/` route/print/JSON maradjon a kanonikus baseline szerint.
