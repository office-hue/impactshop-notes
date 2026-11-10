# JYSK NGO-k szűk katalógusfelvétele — 2026-11-10

## Felhatalmazás és határ
Arnold a nyolc már közzétett JYSK NGO szűk felvételi megoldásának kidolgozását és izolált tesztjét engedélyezte. Saját Mini-ág: feat/jysk-ngo-catalog-enrollment-20261110, alap 8067f57b698dfcb85a676852f33a5fdc2a028d25. Az éles ID-kiosztás, DB-írás és telepítés csak a kész konkrét csomag új jóváhagyásával történhet. Nincs törzsátalakítás, CSV-írás, webbuild, JYSK-pont/szavazat/kampány vagy pénzügyi művelet.

A két előzetes független tervaudit az ai-agent saját Card-tervének §13.5–13.6 pontjában található: docs/dev-plans/jysk-ngo-card-common-fund-2026-11-10.md. A második audit a korrigált bootstrap/recovery megoldáson nem talált új P1/P2 hibát.

## Érintett fájlok / protected files touched
- wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php: a meglévő projekció megtartása; teljes feldolgozott batch ellenőrzése, DB/prefix-szintű közös lock, native mysqli tranzakció, foglalási konfliktus, recovery.
- scripts/jysk-ngo-catalog-reservation-schema.sql: külön jóváhagyandó, explicit CREATE; nincs automatikus futtatás vagy meglévő táblamódosítás.
- scripts/jysk-ngo-catalog-enroll.php: pontosan nyolc NGO plan/apply/status; WP-bootstrap nélküli olvasás; kizárólag explicit apply írhat.
- tests/impactshop-vb2026-ngo-catalog-sync-test.php: valódi wpdb és MariaDB, saját eldobható loopback/tmpfs adatbázis; kizárólag szintetikus rekordok.
- A meglévő Bástya config/hash/model és checksum: új operátori fájl inventoryja, az ellenőrzött katalógusforrás pontos hash-e. Nincs guardlogika-, engedély-, emergency- vagy külső deployszabály-változás.

Koherencia: CSV/dataset/featured olvasás a lock előtt marad. Katalógus és vb2026 flag egy tranzakció. A publikus route, mezők és a régi adatok nem változnak. A megőrzött régi importfüggvény két szintetikus CSV-sorának teljes catalog/flag kimenete kétszeri futtatás után pontosan egyezik az újéval. Az importer írásai nem használják a wpdb automatikus reconnectjét.

Konkrét kockázat: új canonical ID más olvasókon is megjelenhet; árva referenciához nem köthető új szervezet; a későbbi CSV nem veheti át ugyanazt az identitást. A VB2026 választás/kiemelés/verseny nem bővül: új flagsor allow_user_selection=0, is_featured=0, is_race_visible=0. Aktív katalógust olvasó további listák megjelenését az éles csomagban tételesen fel kell sorolni. A meglévő JYSK payload és jogosultság nem módosul.

## Natív operátori felület
PHP >=8.2, mysqli/mysqlnd, mbstring és iconv. Mini: PHP8.4.14; hosting CLI csak olvasott képességellenőrzése: PHP8.4.26, mysqli::execute_query rendelkezésre áll. Nem történt hosting DB-művelet.

A kapcsolat csak védett folyamatkörnyezetből:
JYSK_NGO_DB_HOST, JYSK_NGO_DB_PORT, JYSK_NGO_DB_NAME, JYSK_NGO_DB_PREFIX, JYSK_NGO_DB_USER, JYSK_NGO_DB_PASSWORD.
Jelszó nem kerül parancssorba, tervbe, naplóba vagy repóba.

Parancsok:
    php scripts/jysk-ngo-catalog-enroll.php plan --input=/private/path/input.json
    php scripts/jysk-ngo-catalog-enroll.php apply --plan=/private/path/plan.json --approve=EXACT_DIGEST
    php scripts/jysk-ngo-catalog-enroll.php status --plan=/private/path/plan.json

A privát input alakja:
- version:1
- target: host, port (egész), database, prefix; pontosan az operátori környezettel egyező sorrend/alak.
- authorityEvidenceSha256: a külön felülvizsgált kiosztási jogosultság/hivatkozási határ bizonyítékának hash-e. Ez önmagában NEM hitelesített engedély; az emberileg jóváhagyott végrehajtási csomag része.
- verifiedAt: UTC ISO időpont; a plan és első apply legfeljebb 10 perces forrás-visszaolvasást fogad. Ez nem kampányablak és nem előre készítendő aláírás.
- directory, csv, dataset, runtime: mindegyik {path,sha256}; kizárólag ellenőrzött helyi másolat/telepített fájl, nincs WP-init vagy hálózati sync.
- directory: a config/ngo-card-directory-jysk-20261109.json pontos bájtjai, SHA256 db4db03c3802d21034877b2375ad1d5198af19d88c8cdb4427014626aaee7d1a.
- csv: frissen, külön read-only úton beszerzett valódi forrás; Azonosító/Név/Honlap olvasása.
- dataset: a meglévő Card-projekció név/slug/website/ngo_id rekordjai, slug szerint kulcsolva. A már látható saját nyolcas pontos név/slug, canonical ID nélküli kiegészítő metadata nem foglalás; eltérő identitás konfliktus.
- runtime: a tényleges normál telepített katalógusmodul, a jóváhagyott hashhez kötve.
- references: a cél DB minden NGO-ID/beneficiary nevű oszlopának táblanév.oszlopnév szerinti, forrásból ellenőrzött canonical/different-namespace besorolása. A séma felfedezett listája pontosan egyezzen. Új/missing oszlop blokkol; canonical oszlopok minden létező ID-ja kizárt. A névminta nem bizonyítja az alkalmazás- és külső hivatkozások teljességét: az inventory, más néven tárolt azonosítók és külső írók authority-visszaigazolása az éles csomag nyitott előfeltétele. Nincs automatikus „nincs külső hivatkozás” állítás.

A plan új véletlen ID-jelölteket ad a pozitív 1..2147482647 tartományban. Ezek nem kiosztott ID-k. Apply nem sorsol újra, nem frissít régi sorokat. A jóváhagyás a teljes tervdigestről szól. A DB-prestate minden catalog/flag mezőt köt, kivéve az importer pusztán periodikus source_last_synced_at/updated_at időbélyegeit; a canonical referenciasorok külön kötöttek.

Sikeres commit után a kimenet committed-readback-required. Új kapcsolatból külön status kötelező; bizonytalan commit nem újraapply. Ugyanaz az alkalmazott terv később readback-only. Status recovery vagy időközben eltűnt CSV mellett is olvas; új apply normál hash nélkül nem indul. A reservation tartós raw JSON a külön InnoDB foglalási táblában; nincs törlés/ID-újrafelhasználás.

## Teszt és független review
2026-11-10 helyi eredmény:
- 124 valódi MariaDB/real-wpdb assertion sikeres: teljes CLI plan/status, hiányzó séma, nem InnoDB, pontos nyolcas/digest/metadata, mind17 íráspont hibája, két külön kapcsolat lockütközése, natív kapcsolatvesztés, sikeres COMMIT utáni válaszvesztés, külön readback, idempotencia, teljes batch ütközés, stale cache, régi projekció, recovery.
- A valódi catalog callable és a rögzített Card-forrás fund-eligible callable-je látja mind a nyolc identitást. A fund-olvasó tesztmásolata b290a7d069ee31604f21a85490df04b82bc0830d0f2537260b96ac4d2eaabcd4; nem másoljuk a termékcsomagba és nem változtatjuk.
- Régi votes/preferences/ledger/JYSK payload izolált rekordjai változatlanok. Nem történt éles szavazat vagy pénzügyi próba.
- Meglévő directory fixture:47 assertion; VB principal-binding teszt sikeres.
- Független patchreview: /root/jysk_enrollment_patch_review két P2-t talált (recovery-status és HTTP/CSV honlapütközés), mindkettő korrigálva és külön visszaolvasott review-val lezárva. Nincs nyitott P1/P2. Reviewer nem futtatott tesztet.
- Hermes első draft technikailag completed, tartalmilag elvetett (hibás tranzakcióhatár/hiányzó teszt); nem alkalmaztuk, nem ismételtük. Becsült költség0.00135499USD, nem számla. A végleges patch Codex natív javítása.

Reprodukció a meglévő privát saját fixture-ekkel:
    JYSK_ENROLLMENT_SANDBOX=LOCAL_ONLY JYSK_FUND_READER_SHA256=b290a7d069ee31604f21a85490df04b82bc0830d0f2537260b96ac4d2eaabcd4 php tests/impactshop-vb2026-ngo-catalog-sync-test.php
A teszt csak saját jysk_sandbox / jysk_test / loopback célpontot fogad, nincs éles konfig fallback. Az eredeti wpdb hash:0fd1ffb9a24e9db8cb7370965db379a2fecd985dbeb73d319521cc1bca1bd04c. Saját konténer már meglévő imageből, tmpfs adatkönyvtárral; nincs pull, tartós volume vagy idegen konténer használata.

## Rollback és kiadás
Forráspublikálás nem telepítés. Merge és main-integráció külön Git-csendablakban. Kódtelepítés csak merge-elt exact-file guarded út + pontos előkép + normál/recovery fájlhash + külön jóváhagyás után. Az operátori script nem autoloadolt plugin.

Felvétel előtt az eredeti fájl visszaállítható, ha nincs reservation és közben idegen változás. Felvétel után a régi, foglalást nem ismerő importer nem állítható vissza. Ugyanebből a forrásból készült exact-file recovery a modul elején IMPACTSHOP_NGO_CATALOG_RECOVERY=true értéket definiál; az ensure_sync minden IO előtt visszatér, a meglévő olvasók és a tartós foglalás megmaradnak. Az operator apply recoveryhash mellett tiltott, status működik. Nincs teljes DB-restore, automatikus törlés vagy régi votes/ledger javítás. Telepítéshez külön backup/CAS/rollback- és éles smoke-csomag kell.

A végleges live input/ID mapping még NINCS elkészítve. Külső canonical referencia/authority ellenőrzés, friss runtime/forrás/DB-readback, közölt mellékhatások, külön apply-jóváhagyás és természetes M10-futás utáni public readback hátravan. A teszt nem igazolja az éles bekötést.

## Smoke és kézi UI checklist
- Ngo-catalog és fund/eligible:8/8 exact ID–név–slug, régi identitások/flagsorok megmaradnak.
- Meglévő JYSK kiválasztás, policy, pont/szavazat/dedupe érintetlen.
- A természetes M10 után a nyolc NGO részletes és katalóguskártyája egyszer szerepel, régi URL-en.
- Közösalap-adatoknál csak igazolt ID és valódi szavazathiány esetén 0; hiányzó adatból nem lesz nullázott pénz.
- VB kiemelt/verseny és user-selection nem bővül.
- Recoveryben az eltárolt lista olvasható, frissítés szünetel; elöregedést nem jelölünk frissnek.
Ezek az éles/UI lépések még nem futottak. Ez a forráscsomag nem kezeli a top-two táblát vagy az általános törzsátalakítást.

### Végső helyi forrásellenőrzés

Mindhárom PHP-fájl lintje, a teljes tényleges Bástya hashinventory és hat negatív/checksum fixture, a meglévő deploy-wpcontent-map-bastion izolált szerződésteszt és a diff-check sikeres. A protected-touch ellenőrzés a jóváhagyott saját változást, a rollbacket és a következő élő smoke határát fogadta el. A smoke tag-ek tervezett érintettséget jelölnek, nem lefutott élő próbát. Guard-/CI-logika nem változott. Saját, nem titkos eredmény: tmp/jysk-ngo-card-directory-20261109/enrollment-test/source-validation.json.

### Rögzített main-integráció, PR #270

A 3267e172792cbcc62fb8e0c9a1cac57b336eaa7d main saját ágba integrálása Arnold külön Git-ablakában megkezdődött; az ablak 1.54 másodperces readback után felszabadult. Az egyetlen konfliktus a docs/impactshop-guard-hashes.sha256 volt: a mindkét fél pontos hashmódosítását megőrző JSON alapján újraszámítva. Az incoming impactshop-identity-panel.php/.js és profiltesztek byte-ra a rögzített mainnel egyeznek; az operátor, a katalógus és a saját DB-teszt byte-ra a 5828778157e9cdd837f0a0eb3fd580f90f19823c headen ellenőrzött változat. Az idegen profiljavítás változatlan átvétel, nem új saját javítás. A teljes egyesített Bástya hashinventory/hat negatív checksum-eset és staged diff-check sikeres. A változatlan termékkód 124+47 tesztbizonyítékát újrahasználtuk; új élő teszt, telepítés és ID-kiosztás nincs.

## MyISAM éles eltérés javítása — a #270 utáni aktuális kiadás

A #270 merge-je 7e6ffbe2221b882f7c8826d446b844b954700578, tree 64033278edf64eca039384b32cf6ec685e3ce7c3. Nem telepítettük: két külön WP-bootstrap nélküli SELECT igazolta, hogy a közös wp_options MyISAM, a catalog és campaign_flags InnoDB, a korábbi reservation opció nincs jelen. A #270 mindhárom táblára InnoDB-t követelt, így éles szinkronra alkalmatlan. Az eredeti 124 állítás az InnoDB-fixture-re volt érvényes, nem erre a hostra.

Arnold engedélyezte a kizárólag nyolc szervezet foglalására szolgáló külön InnoDB-táblás javítás kidolgozását. Saját ág fix/jysk-enrollment-myisam-options-20261110, alap a fenti merge. A közös wp_options és a meglévő üzleti táblák átalakítása tiltott; nincs új ID vagy élő adatírás engedélyezve.

### Tárolás és olvasási határ

A scripts/jysk-ngo-catalog-reservation-schema.sql az egyetlen új táblát definiálja: wp_sharity_ngo_enrollment_reservations. reservation_key VARCHAR(64) ASCII/binary elsődleges kulcs, payload LONGTEXT utf8mb4, ENGINE=InnoDB. A fix kulcs impactshop_jysk_ngo_enrollment_v1; az egyetlen JSON rekord továbbra a tervdigestet és a pontos nyolc kötést tartalmazza. Az operátor és az importer ezt használja közös tranzakció/zár alatt. Hiányzó vagy nem InnoDB tábla megállít; ismeretlen rekordkulcs vagy több reservation nem fogadható el. A legacy reservation opció jelenléte külön hibát ad, nincs csendes migráció. A runtime-ellenőrzés már az új tárolási markert is megköveteli.

Az enrollment teljes CLI és az importer tranzakciós írója nem módosítja a MyISAM options táblát. A teljes ensure_catalog_sync előkészítő get_dataset_items(true) hívása továbbra meglévő dataset-cache írást végezhet; azt a későbbi tranzakció rollbackje nem vonja vissza. Ez nem authority-adat. A saját sync-transiens frissítése csak igazolt commit után következhet. Hamis/exception cacheeredmény külön catalog_sync_cache_refresh_failed diagnózis; a már commitolt catalog+flags+reservation megmarad. Nincs új dataset-olvasó vagy cache-rendszer.

### Elkülönített kiadás és rollback

1. A végleges SQL és célpont külön jóváhagyása után az üres új tábla létrehozható; csak a pontos wp_ prefixű igazolt adatbázison. Nincs IF NOT EXISTS, ALTER vagy automatikus WP-init migráció. Meglévő azonos nevű táblánál megállás és readback. A CREATE implicit commitú művelet, nem az enrollment tranzakció része; külön schema-readback szükséges. Bizonytalan CREATE után nincs vak ismétlés.
2. Új runtime telepítése csak külön exact-file csomaggal, mentéssel/CAS-szal/guarded deployjal. Régi importer az üres új táblát nem olvassa. Új runtime hiányában az operator nem fogadja el a régi markerű fájlt. A #270 hibás runtime nem köztes kiadási lépés.
3. Friss forrás, reference inventory és kiosztási felhatalmazás után új plan; annak exact digest-jére külön live apply-engedély. Nyolc catalog+nyolc flags+egy reservation közös tranzakció, új kapcsolatból status. Az új tábla puszta létezése sem ID-kiosztási engedély, sem aktív felvétel.
4. Felvétel előtt a runtime visszaállhat az igazolt eredeti fájlra, ha nincs reservation vagy saját jysk_enrollment sor és idegen drift. Az üres új táblát nem kell visszaállításkor törölni. Felvétel után csak a foglalást és olvasókat megőrző sync-disabled recovery; nincs régi importer, reservation-törlés vagy teljes DB-restore.

### Audit és célzott teszt

Két egymást követő független deltatervaudit (jysk_enrollment_plan_audit1/2) lezárult. Az első cache-commit pontosítását, majd a második előkészítő dataset-cache határát korrigáltuk; második visszaellenőrzés után nincs nyitott P1/P2 tervlelet. A review nem tesztfutás.

149 izolált MariaDB/valódi-wpdb állítás sikeres, MyISAM options mellett. Mind17 enrollment-íráspont rollbackje, kapcsolatvesztés, COMMIT-válaszvesztés/külön status, idempotencia, régi importer projekció, reservation ütközés, nem InnoDB/hiányzó tábla, legacy opció, régi runtime tiltása, teljes CLI, két saját cachehibamód és commit utáni frissítés vizsgált. Az előkészítő dataset-cache mellékhatást szintetikus stub szemlélteti; ez nem a teljes dataset-implementáció teszt-PASS-a. Három PHP lint sikeres. Független implementációs review (jysk_enrollment_patch_review) nyitott P1/P2 nélkül; a reviewer forrást olvasott, nem futtatott tesztet. Teljes Bástya hashinventory és hat negatív/checksum-eset, protected-touch és diff-check sikeres. Az inventory első futása a saját új config két még nem frissített pinjét jelezte; csak e két pontos hash korrigálása után lett sikeres, idegen hash nem változott. A protected-touch első hívásában csoportnevek helyett a tényleges smoke-tageket kellett megadni; a korrigált hívás sikeres. A tagek tervezett éles érintettséget jelölnek, nem lefutott UI-próbát.

Állapot: helyi javítás, nem commitolt/pusholt/merge-elt, nem telepített, nincs éles schema vagy ID. Az eredeti authority/reference és természetes M10 utáni UI/readback feltételek megmaradnak. A top-two tábla és törzsátalakítás továbbra kívül esik ezen a csomagon.
