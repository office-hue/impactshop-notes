# JYSK meglévő fiók — pontos belépési horgony

Felhasználói felhatalmazás: a „Van profilom – kapcsolódás” gomb a profiloldal
meglévő fiókba belépési részéhez vezessen, más funkció/visszatérés változása nélkül.
Protected files touched: wp-content/mu-plugins/impactshop-jysk-game.php,
docs/impactshop-guard-hashes.json és docs/impactshop-guard-hashes.sha256.

Egy runtime-sor: PROFILE célhoz #impactshop-signin; az identity panel már
létező horgonyát használja. A jysk_return=1, PKCE és tulajdonosi ellenőrzés
megmarad. Koherencia, kockázat és tényleges tesztek:
docs/jysk-game-bridge-2026-10-27.md 2026-11-11 szakasza.

Smoke: identity_and_points, deploy_guard. Belépést igénylő handoff 303 pontos
URL/anchor; meglévő és új owner visszatérés; idegen host/query/POST tiltása;
változatlan jutalmazási tesztek. 226 bridge- és14 konfigurációs állítás sikeres.
Kézi UI: kijelentkezett JYSK-gomb a meglévő fiók formjához visz; szokásos
belépés visszahoz a játékhoz; már hitelesített profilhoz nem kell új kód.

A meglévő guard szerinti kiadási smoke-scope (nem elvégzett éles PASS):
route:profil, route:impact-challenge, route:factlens-vb-prod,
flow:profile-return-account, flow:profile-return-restore, flow:points-jump,
flow:message-popup, flow:legacy-pool-visibility. Az e route-okat kezelő összes
függvény, közös profil és JS forrása byte-azonos; az egyetlen eltérés a fent
tesztelt PROFILE konstans. Kiadás után az érintett JYSK-belépési út éles
ellenőrzése kötelező, a többi route-hoz nincs módosítási engedély.

Rollback: merge után egyfájlos guarded kiadás, privát előkép, friss CAS,
utóhash és0444; saját változatlan utóállapotból az előző bridge visszaállítása.
Nincs profil/pont/DB/config reset. Forráskész, még nem merge-elt vagy telepített.
