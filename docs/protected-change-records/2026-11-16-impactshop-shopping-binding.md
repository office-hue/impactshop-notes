# Shopping R3 – első gazdasági kötés és additív migráció

## Határ és felhatalmazás
Kijelölt worktree: `impactshop-notes-shopping-binding-20261116`, ág: `feat/impactshop-shopping-binding-20261116`, induló HEAD: `f17687d60a7d187a2c4666fe40b732704f332a7d`. Arnold jóváhagyott R3 tervének szűk része: az első hitelesített capture NGO/program/snapshot/subject kötése és tényleges idempotens migráció. Jóváhagyott legacy touch szükséges a már létező capture és CLI migráció belépőin. Helyi forrásmunka; nincs remote, provider, adatbázis, publikálás vagy telepítés. Privát forrás külső Hermes-exportjára nincs adatkör/címzett felhatalmazás, ezért helyi kidolgozás.

## Előzetes koherenciavizsgálat és döntés
Az affiliate intent tartalmaz provider/partner/program/NGO/subject/snapshot adatot, de törölhető. A settlement gyökér csak subject/time/merchant adatot fagyaszt; az adapter donation_basis gazdasági bizonyíték, nem teljes NGO-kötés. Nincs egyenértékű tartós receipt. Meglévő intentbe nullable `provider_scope_ref VARCHAR(96)`, meglévő settlementbe nullable `attribution_binding_json TEXT` kerül; nincs új tábla. A v1 JSON felső határa 2048 bájt. A gyökér első INSERT-je ugyanabban a meglévő, reconnect-tiltott tranzakcióban rögzít, mint az esemény. Ütközés a meglévő manual út; profil nem írhatja át. Régi null gyökér nincs visszatöltve.

Érintett funkciók: `sharity_points_impactshop_prepare/capture`, SAT1 subject feloldás; `sharity points migrate`, `sharity affiliate migrate-shopping`; confirmed Dognet intent belső adatai. Upstream: autentikált meglévő adapterek és intent. Downstream: változatlan pontkezelő, dedup, refund/manual utak. Külső URL/API és gazdasági formula nem változik. Challenge/JYSK/guide nincs érintve. Per-row delete_after és globális 45 nap változatlan. Az új binding gyökér-metaadat: nem része payload_digest/revision azonosságnak.

## Kockázatok és védelem
- Meglévő táblán CREATE IF NOT EXISTS nem migrál: SHOW COLUMNS utáni célzott ALTER, utólagos típus/null ellenőrzés szükséges.
- Részleges migráció: régi readiness marker megmarad; külön binding readiness csak igazolt oszlopokra. Régi lane opcionális binding nélkül tovább működik.
- Cleanup utáni újraimport: feloldott metaadat eltűnése nem hozhat létre új gazdasági eseményt. A root tokenDigest megőrzi a konfliktusjelzést.
- Scope/program/NGO/subject/snapshot csere: eredeti kötés változatlan; új ellentmondó bizonyíték manual.
- Téves backfill/keresztprofil: null régi gyökérhez nem rendelünk mai profilt vagy NGO-t.
- Őrzött hash-inventory nem frissül vakon; review és az érintett inventory/guard eredmény továbbra is szükséges a publikálás előtt.

## Backup és rollback
A változatlan induló Git HEAD a helyi forrás-visszaállítás alapja; nincs élő adatírás. Release előtt külön célpont-backup kell. Forrás rollback: csak e csomag fájljainak visszaállítása az induló revízióra, más WIP megtartásával. Additív nullable DB oszlopot rollbackkor meg kell tartani; adatok törlése/drop nincs engedélyezve. Readiness kikapcsolható a release műveleti tervében; régi schema marker nem változik.

## Ellenőrzési terv és kézi UI checklist
Helyben: kapcsolódó PHP fixture, PHP lint, diff-check; első kötés, mezőütközés, cleanup/reimport dedup, régi null gyökér és régi/új/részleges/hibás séma. Valódi MySQL csak már meglévő, igazoltan helyi privát harness esetén; fixture nem SQL-integráció.

Kiadás után ellenőrizendő: mindkét CLI migráció kétszeri futása és típus/readiness visszaolvasása; confirmed intent → első hitelesített pending/award capture; azonos újraimport 0 pont; NGO/scope/program/subject csere manual; cleanup után eredeti root/refund; régi lane folytonosság; route és admin ponttörténet. Ezek most nem futnak.

Arnold kézi UI ellenőrzése stagingen: (1) Vásárlási segédben partner és NGO kiválasztás, megerősítés és megfelelő kereskedőre irányítás; (2) profil ponttörténetében a hitelesített vásárlás egyszer jelenik meg; (3) ismételt betöltés/import nem növeli újra; (4) admin manual ügyben az eredeti kötés megmarad. Regressziójel: másik NGO/profil, duplapont, hibás redirect vagy eltűnt régi vásárlás. UI/deploy most nem ellenőrzött.

## Tényleges eredmény – 2026-11-16
A négy célzott termékfájl módosult: affiliate runtime, points attribution, ledger-sync és points.php. A receipt v1 kulcssorrendje `v/provider/scopeRef/programRef/merchant/ngoRef/subjectDigest/tokenDigest/snapshotRef`; nyers bearer nincs benne. A scope régi intentnél null maradhat; új confirmed Dognet intentnél a szerver által feloldott `chid`, és capturekor az autentikált meglévő namespace ellenőrződik. Scope/program/NGO/subject/snapshot vagy token ütközés az eredeti root változtatása nélkül manual. A gazdasági payload_digest bitre azonos marad az intent cleanup előtt/után; a receipt nem kerül az event JSON-ba. A meglévő null binding gyökér nincs backfill-elve.

A két külön readiness opció: `sharity_points_impactshop_binding_schema` és `impactshop_sharity_affiliate_binding_schema`. Mindkettő csak az érintett valódi oszlopok név/típus/nullability ellenőrzése után lesz `1`. Az eredeti schema marker megmarad. A confirmed shopping kibocsátás affiliate marker és ellenőrzött oszlopok nélkül zárva marad; scoped capture pontoldali binding-migráció nélkül hibával visszatér, nem veszti el csendben a receiptet. A régi null-scope lane tovább működik. A már létező explicit CLI migrációk működnek, nem új provider/hálózati adapterek.

Lefuttatva, PASS:
- `php tests/impactshop-shopping-binding.test.php`: 50 szintetikus SQL-határ fixture. Első receipt, scope/NGO/program/subject/snapshot/token ütközés, változatlan digest cleanup után, valódi manager duplicate út, cleanup utáni capped refund döntés, régi null root, old/new/partial/bad-type/nullability/failed ALTER és újrafuttatás.
- `php tests/sharity-affiliate-runtime-test.php`: legacy affiliate és confirmed shopping vásárlási fixture, szerveroldali scope ellenőrzéssel.
- `php tests/impactshop-points-state.test.php`, `php tests/impactshop-points-provider-adapters.test.php`, `php tests/impactshop-points-attribution.test.php`, `php tests/impactshop-click-no-reward.test.php`.
- `bash tests/sharity-affiliate-runtime-bastion.test.sh`: meglévő negatív/tamper guard esetek.
- `php -l`: mind a négy termékfájl, a két módosított és az új PHP fixture.
- `git diff --check`.

Nem futott: valódi MySQL/WordPress/staging/live/CLI telepítési migráció, UI, provider capture, merge/deploy. Az `IMPACTSHOP_MYSQL_CONFIG`, `IMPACTSHOP_WPDB_FILE`, `IMPACTSHOP_MYSQL_SANDBOX_CONFIRM` konfiguráció nincs megadva; nem kerestünk vagy használtunk production hitelesítést. A meglévő `shopping-purchase-mysql.integration.php` a tényleges explicit migráció hívását kapta meg, de nem állítunk futási PASS-t.

Protected inventory: mind a négy termékfájl egyedi hash-elt bejegyzés, és a várt saját driftet megmértük. Manifest/checksum nincs átírva. Független review után a szülő végzi a saját pontos baseline-frissítést és product guardot; addig nincs teljes inventory-PASS vagy publikálhatósági állítás. Új termékmodul nincs, guard/policy nem módosult.

DOCSYNC pointer: ez a change record, a `notes.md` és a `system-status-snapshot.md` 2026-11-16 shopping-binding szakasza ugyanazt a helyi tesztelt csomagot jelöli. Központi registry vagy távoli memory ACK nem készült. A teljes új hálózati adapter/retention/outbound csomag külön feladat; ez a csomag csak első kötés és migráció.

## Célzott review-javítás
A független reviewer reprodukálta: az affiliate marker átmeneti 0 értéke korábban elrejthette egy már kiadott scoped intent adatait, és binding nélküli első gyökér keletkezhetett. A feloldó most a tényleges intent sort olvassa (régi oszlop nélküli sémán is kompatibilis SELECT *); tényleges non-null scope és affiliate marker0 esetén `binding_schema_unavailable` érkezik még a capture-tranzakció előtt. A valóban null/hiányzó scope legacy útja továbbra is működik. A fixture SQL NULLIF üres-string viselkedése javítva; mindkét marker0 és affiliate0/points1 esetben nulla új root/esemény írása ellenőrzött. Ezzel 50 a tényleges szintetikus ellenőrzések száma. A javított két PHP fájl lintje, affiliate/confirmed/attribution fixture és diff-check ismét PASS. A független reviewer a javítást és az 50 fixture-t visszaellenőrizte; nincs nyitott érdemi lelet. Valódi MySQL/staging nem futott.

## Felülvizsgált source-inventory és publikálási scope
Protected files touched: a négy fenti PHP fájl; `docs/impactshop-guard-config.json`, `docs/impactshop-guard-config.sha256`, `docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`, `docs/impactshop-protected-files.json` és `docs/bastion-guard-status.md`. Az utóbbi inventory-kiegészítés a külön [Admitad ownership record](2026-11-16-admitad-ownership.md) egy új, passzív MU-pluginját is nyilvántartja. Meglévő guard/CI-program nem változik; nincs általános hash-refresh vagy új védelmi mechanizmus. Csak a négy review-zott termékhash, a config/checksum, a protected modell és az egy új fájl hash-e változik; minden idegen digest és metadata megmarad.

Smoke scope/tag (érintettség, nem mai élő PASS): `route:factlens-vb-prod`, `route:impact-challenge`, `route:profil`, `flow:message-popup`, `flow:points-jump`, `flow:legacy-pool-visibility`, `flow:profile-return-account`, `flow:profile-return-restore`, `route:impactshop`, `flow:saved-offers-open`, `flow:go-deal`, `browser:webkit`, `browser:chrome`, `deploy:guard-preflight`, `deploy:checksum-verify`. Mai tényleges ellenőrzés az itt felsorolt helyi fixture/lint/guard; az összes élő/UI elem külön függő release-ellenőrzés. Rollback a saját commit/file és új schema-adatok megtartása az előző szakasz szerint.
