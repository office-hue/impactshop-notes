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
