# JYSK – saját kapcsolódott fiók jelzése

Felhasználói kérés: a JYSK oldalon látszódjon az azonosító első 4–5 karaktere.
Protected files touched: wp-content/mu-plugins/impactshop-jysk-game.php,
wp-content/mu-plugins/impactshop-identity-panel.php,
docs/impactshop-guard-hashes.json, docs/impactshop-guard-hashes.sha256,
docs/bastion-guard-status.md.

Koherencia/kockázat: a már aláírt context olvasás opt-in mezőt kap. Csak a
hitelesített session aktív tulajdonosi grantjának első öt karaktere adható ki.
A régi kliens pontos válaszformája megmarad; a teljes pseudo, belépési kód és
token nem kerül a publikus bootstrapba. Nincs cookie-, callback-, visszatérési,
pont-, szavazat-, session-élettartam- vagy sémaváltozás. Az azonosítórészlet
megkülönböztető jelzés, nem hitelesítési adat és nem garantáltan egyedi.

Smoke: context régi/új kérés, lejárt owner elutasítása, válaszaláírás,
JYSK bootstrap és profiljelzés. Guard: identity_and_points, deploy_guard.
Kiadás: először az additív saját WP bridge, majd az app; guarded egyfájlos
release, előhash, privát mentés, CAS, utóhash/0444. Az új app régi bridge-dzsel
is működik, ilyenkor nem állít valódi azonosítót.
Rollback: saját bridge ellenőrzött elődje; app előző deploymentje. Nincs DB-
restore, pontreset vagy kódgenerálás. UI: kapcsolódás után az azonosító
egyezzen a profil első öt karakterével; bontás után a jelzés tűnjön el.

Állapot: forráskész. 224 bridge-állítás, PHP code-save/lint, 8 célzott Chrome
eset sikeres; független delta-review nincs blokkoló. Merge/deploy még nincs.

## Kapcsolódó, reprodukált jelszómentési visszatérési hiba

A felhasználó szerint a kód már megjelenik és menthető, de a mentőgomb után
ott marad; a sima továbbhivatkozás működik. Valódi helyi HTTP/Chrome-próba
igazolta: form-action 'self' tiltja a POST utáni 303-lánc JYSK hopját.
Az egyetlen hozzáadott CSP-cél a https://jysk.sharity.hu origin. A form action
és a kódos POST továbbra is saját WordPress; a külső hop kizárólag GET,
adat nélkül. Az összes többi CSP-direktíva és redirect-validator változatlan.
Negatív ellenőrzés: tetszőleges másik originre továbbra sem mehet az űrlaplánc.
UI: egyszeri kódnézet → Mentés a jelszókezelőbe és tovább → JYSK játék.
Más profilcélra semmilyen átirányítást nem írunk át. A korábbi kézi link marad.
