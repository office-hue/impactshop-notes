# JYSK — tartós, többeszközös profilkapcsolat

Jóváhagyás: a 2026-11-13-i terv implementálása, egyszerre több eszközön
megőrzött belépéssel. Saját alap: 72c12d448e15b2a171f0753247c86023bffd0e5d.

Koherencia/érintettség: impactshop-jysk-game.php protected runtime;
handoff, exchange, context, disconnect/revoke, meglévő CLI. Két meglévő
InnoDB authtábla hozzáadó mezői; HTTP nem migrál. Subject generation globális
revoke, session saját owner-grantje eszközszintű jogosultság. Normál disconnect
megtartja a jutalmazási mappinget és a többi eszközt. Reward payload, ledger,
outbox, NGO-roster, napi keret és pontmotor változatlan.

Kockázat: hibás legacy backfill, mappingváltási verseny, párhuzamos exchange,
részleges DDL, bizonytalan COMMIT, eredeti PHP-ra visszaállított v2 token.
Védelem: pontos grantkötés, subject zár/kapcsolatellenőrzés, explicit offline
upgrade, struktúra/hash readback. TTL maximum365 nap és saját grant lejárata;
legacy alap900 s. Jutalmazási grant továbbra is külön követelmény.
Megoldási döntés: jóváhagyott meglévő authút módosul; második authréteg nem
őrizné meg az egyszeri kód/globális revoke szerződését. Additív séma,
meglévő CLI és release-út; nincs új szolgáltatás vagy közös domain-cookie.

Kiadás: teszt/review/merge után READY külön JYSK artifact; majd hostingablak,
JYSK bridge lezárás és igazolt PHP-drain, privát fájl/authtábla mentés, izolált
restore, guarded exact-file csere és explicit upgrade. Mentési kivétel:
0700/0600, webrooton kívül, legalább7 nap. App-repo részletes terv szerinti CAS.
Rollback: kompatibilis v2 PHP/web, hosszú kibocsátás leállítása; szükség esetén
pontos session/kód-készlet visszavonása. Eredeti PHP csak minden JYSK-session
és kód érvénytelenítése/readbackje után. Grant/pont/függő jutalom megmarad.

Ellenőrzés: PHP lint/bridge/private-config, izolált session lifecycle,
app szerződés/UI-életciklus, protected hash/Bástya. Kézi UI:
jysk.sharity.hu/jatek két eszközön; újratöltés/bezárás/visszatérés15 perc után;
egyik bontása mellett másik megmarad; profiljelzés/teljesítettség helyes.
Kontrollált olvasás, éles pont-/szavazatírás nélkül.

Protected files touched: impactshop-jysk-game.php, impactshop-guard-hashes.json,
impactshop-guard-hashes.sha256 és bastion-guard-status.md. Smoke: identity_and_points,
deploy_guard; meglévő közös profile-return-account/profile-return-restore,
points-jump, message-popup és legacy-pool-visibility release-scope megmarad.

Ellenőrzés: PHP-lint;301 determinisztikus bridge-állítás;19 virtuális privát
konfiguráció;19 eredményprotokoll-állítás. Izolált localhost MariaDB10.11.19,
512MiB/1CPU/tmpfs, valódi target wpdb0fd1ffb9…1bd04c:61 lifecycle-állítás,
köztük részleges DDL/resume/backfill, saját grant, rövidebb expiry, két
ténylegesen átfedő exchange egyetlen sessionnel, disconnect/revoke/rollback.
Három authtábla saját dump/restore checksumja egyezik. A WP boot/HTTP/iPhone
és a teljes régi gazdasági hibainjektáló suite nem kapott új PASS-t.
Független runtime-review nem talált P1/P2 hibát. Webes review leletei az appban
javítva, célzott visszaellenőrzés lezárva. Élő adat csak a target wpdb-forrás
olvasása volt; éles adatírás nem történt.

Állapot: helyi implementáció/teszt; PR/merge/telepítés/aktiválás még külön lépés.
Idegen dokumentáció-WIP megőrizve. A session CLI upgrade `expected-version=2`;
intent paraméterei target-hash/structure-hash/source-hash, kikapcsolt bridge,
külön igazolt PHP-drain szükséges. Részleges DDL után friss structure-hash kell.
