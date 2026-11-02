# NGO Card központi hírolvasó — 2026-11-02

## Előzetes hatás és felhatalmazás

Arnold jóváhagyott Card-display implementációja; saját ág
`feat/ngo-card-news-20261102`, base `d5ff78fe966065bb5e478c2105a6e493f7528f31`,
tree `cf938b9e16b5bed8758d05930d2dfb4f9e2e07f1`. A rövid Git-ablak lezárult;
a donor JYSK checkout ága és munkafája változatlan maradt.

Additív `wp-content/mu-plugins/impactshop-ngo-card-news.php` adja a publikus
GET `/wp-json/impact/v1/ngo-card-news` választ. A meglévő
`impactshop_ngo_card_global_announcement` opció és
`impactshop_ngo_card_share_announcement` filter marad a forrás; a régi
`impactshop-ngo-card.php` és adminírója nem módosul. A `no-store` válasz
`schema_version=sharity-ngo-card-news-v1`, `announcement={text,url}|null`.
Üres opció a valódi eltávolítás; nincs dataset-cache vagy NGO-részletkérés.

Közvetlen kockázat: publikus hírben HTML vagy hibás link jelenhetne meg.
Tisztítás filter után is, 200 Unicode karakteres határ, opcionális HTTPS-link
hitelesítő adatok nélkül. Nincs személyes adat, token vagy privát opcióolvasás.
Közvetett kockázat: régi Card hírével eltérés; ugyanaz az opció/filter olvasott,
az új web BFF legfeljebb 60 s újrahasználással és 5 perces végső lease-szel dolgozik.
Nem érintett pont/szavazat/affiliate/pénz/identity/cron/redirect/kampány/guide.

## Érintett fájlok és Bástya

Az új plugin és `tests/impactshop-ngo-card-news-test.php` mellett csak saját
dokumentáció és a meglévő inventory pontos egyfájlos bővítése változik:
`docs/impactshop-guard-config.json`, config SHA-256, guard-hashes és checksum,
`docs/impactshop-protected-files.json`. A hashjegyzékben kizárólag az új plugin
és a módosított config hash-e frissül; más baseline nem kap új lenyomatot.
A canonical repo/root/main és deploy-modell nem változik. Új guard nincs.
A lokális és CI protected-touch ugyanazt a bővített modellt olvassa.

## Ellenőrzés, kiadás és rollback

Forrásellenőrzés: PHP lint, izolált REST/opció/filter/Unicode/link fixture,
exact inventory/hash/checksum ellenőrzés, meglévő protected-touch és commit-lane,
git-health és strict push-audit. Tényleges eredmények a következő checkpointban.
Ez nem éles WordPress vagy webes PASS. Webes pár: ai-agent PR #1889.

Csak merge után, meglévő `bin/impactshop-guard-deploy.sh` exact egyfájlos úton,
friss host/célút/hash/readback mellett. Előállapot: meglévő fájl esetén privát
mentés és hash; hiány esetén az igazolt hiány. Rollback csak ezt az új plugint
állítja vissza vagy távolítja el; opciót és régi szerkesztőt nem ír/töröl.
Védett mentés legfeljebb 2 nap, sikeres működés+UI ellenőrzés után törölhető;
teljes backuphoz nem nyúlunk. Bizonytalan írás után readback, nem retry.

Éles ellenőrzés: GET 200/no-store és tisztított meglévő hír vagy null;
POST ne írjon; korábbi Card/admin és más WP-funkciók ellenőrzése az érintett
listán. A közös webkiadás előtt a network reader átadása külön függőség.
Új live hír közzététele nem része e forrásmunkának.

Kézi UI-checklist a közös webkiadás után:

- NGO Cardon a jelenlegi központi „Sharity hírek” látható, nincs HTML-kód.
- A „Részletek” HTTPS-link új lapon nyílik; üres hírnél nincs üres hírpanel.
- A meglévő admin hírszerkesztő továbbra elérhető; új mentés és törlés csak
  külön jóváhagyott teszttartalommal, majd az eredeti tartalom visszaállításával.
- Shopping, feladatok, JYSK, guide és belépés nem változik e plugin miatt.

## Tényleges helyi smoke

`php -l wp-content/mu-plugins/impactshop-ngo-card-news.php` sikeres;
`php tests/impactshop-ngo-card-news-test.php`: 13 assertion sikeres, élő írás 0.
Ez a GET-only, null/eltávolítás, no-store, opció/filter, Unicode-hossz és veszélyes
link fixture-je; nem éles GET vagy admin-tartalomváltoztatás.

A meglévő PHP CS Fixer csak az új plugint formázta; végső SHA-256
`2e0326e4e84cf69d0504b346fc88abe49dd5b9bf567770fdf1eed26a7e83052e`.
Az exact inventory/config/checksum ellenőrzés szerint egyetlen új modul van,
minden más runtime-hash változatlan. Meglévő izolált
`impactshop-guard-preflight-detached.test.sh` és
`deploy-wpcontent-map-bastion.test.sh` sikeres. Protected-touch, staged commit-lane
és strict natív staged audit sikeres (14 fájl, 0 titokfinding). A smoke tagek
helyi fixture-t jelölnek; valódi host preflight/telepítés nem történt.

Státusz: helyi forrásjelölt; merge/telepítés/aktiválás nincs.
