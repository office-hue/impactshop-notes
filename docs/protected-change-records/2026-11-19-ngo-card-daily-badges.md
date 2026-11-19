# NGO Card napi besorolásátvétel — 2026-11-19

Protected files touched: `wp-content/mu-plugins/impactshop-ngo-card-directory.php`.
A meglévő Card-addon opcionális read-only mezőbővítése a napi frissítés Arnold
által jóváhagyott szűk scope-jában; a régi szintszámító, normál katalógus,
pont/szavazat, jogosultsági és pénzügyi kód nem módosul.

Koherencia, konkrét kockázat, funkciólista és kézi UI-checklist:
`docs/ngo-card-directory-jysk-2026-11-09.md` 2026-11-19-i saját szakasza.
Smoke scope: a directory CLI-fixture és PHP-lint, adatbázis/hálózat nélkül;
a régi ordinary-route változatlansága és forráshiba miatti hiány külön tesztelt.
Publikáláskor natív audit és GitHub-check-readback; éles PASS nincs.
Backup/rollback: pontos saját fájlelőképből, változatlan saját utóállapotból,
csak külön jóváhagyott guarded telepítési csomagban. Most forrásmunka történik.

## Jóváhagyott main-integráció

Arnold külön ablakában a már letöltött `325163a70f9953a0bbb05ec94c1caea15f55d020`
main került beemelésre. A `notes.md` mindkét oldalának teljes szövege megmaradt;
a status automatikusan egyesült. A mainhez képest kizárólag a hat saját Card-fájl
eltérése maradt; az incoming `impactshop-tradetracker.php`,
`impactshop-guard-hashes.json`, `impactshop-guard-hashes.sha256` és
`bastion-guard-status.md` byteazonos a jóváhagyott mainnel. Ezeket nem javítjuk
és nem telepítjük újra. A natív staged commit-lane ezt egyetlen protected
main-integrációként elfogadta; szabálymódosítás nincs. A meglévő protected-touch
folyamat e rekorddal, pontos rollback- és smoke-scope-pal kerül meghívásra.
Smoke scope: Card64-fixture/PHP-lint és incoming-main blobparity; TT új élő
próbát nem állítunk. Rollback: kizárólag a saját integráció visszavétele saját
ágból, a közös mainhez és a már telepített idegen működéshez nem nyúlva.

## Második rögzített main-integráció

Arnold új, külön Git-ablakában a rögzített
e5bcd9f5a4272c18027af385e7f76c979ea56a71 main integrációja indult.
A readback után az ablak 0,79 másodpercnél felszabadult. A két dokumentumütközés
feloldása az ablakon kívül, mindkét oldal teljes bejegyzéseinek megőrzésével
történik. Az incoming Amplifier pénzügyi forrás, teszt, governance és hashleltár
változatlan főági blob; a napi badge addon és fixture az előző saját headhez
képest változatlan. A saját hatfájlos delta marad az egyetlen mainhez viszonyított
eltérés. A meglévő protected-touch folyamat csak ezt az engedélyezett integrációt
rögzíti; guard-, pénzügyi logika- vagy jogosultságmódosítás nincs.
Ellenőrzés: incoming blobparity, PHP-lint és a meglévő 64-assertion Card-fixture.
Rollback: csak saját integráció a saját ágban; közös main és idegen telepítés
nem módosítható. Telepítés vagy adatírás ebben a csomagban nincs.

## Natív lane-elutasítás és jóváhagyott ágcsere

A második integráció staged auditja a több elsődleges lane miatt FAIL lett:
az incoming finance-ledger alkalmazáskód és a protected hashleltár nem tehető
egyetlen merge-commitba. A titokscan, diff-check és protected-touch sikeres volt;
ebből összesített PASS nem következik. Commit vagy push nem történt, nincs bypass.

Arnold #11 ablakában a saját függő merge megszakadt, és a már letöltött
e5bcd9f5a4272c18027af385e7f76c979ea56a71 alapból létrejött a
fix/ngo-card-daily-badges-current-20261119 ág. A readback után 0,42 másodpercnél
az ablak felszabadult. A régi ág/PR és a 186cae4b…446bc3 SHA-256 hashű mentett
hatfájlos delta megmaradt. A javítást csak ezután, ablakon kívül alkalmaztuk.
A termék- és tesztfájl az előző saját f5d9986d head blobjaival egyezik.
A pénzügyi és más bejövő fájlok a rögzített alaphoz képest változatlanok.
Kizárólag a saját hatfájlos különbség kerül normál commit/push és új PR útjára;
a korábbi merge elutasítása dokumentált marad. Új fetch, force-push,
guardmódosítás, helyi mainírás, telepítés vagy adatírás nincs.
