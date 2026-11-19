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
