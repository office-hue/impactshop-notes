# Kanonikus pseudo-pontíró tranzakciós hibakezelése — előzetes felmérés

Protected files touched: `sharity-points-manager.php`, `sharity-level-manager.php`,
az új `impactshop-jysk-game.php` és pontos source inventory/hash metadata.
Smoke scope: identity_and_points + deploy_guard (a pontos inventory/hash fájlok miatt).
A manifest metadata nem módosít guardlogikát. A kötelező tagek ellenőrzési
scope-ot jelölnek, nem lefutott production UI-/deploy-smoke bizonyítékot.

Felhatalmazott feltételes scope: csak élő forrásparity igazolása után minimális
helyi javítás a JYSK által használt kanonikus pseudo writerhez és a hívott
level-managerhez; nincs deploy vagy éles adatírás. A feltétel teljesült.
2026-10-27: Mini `mac-primary` → meglévő `s59` alias saját kulcsa,
kapcsolatszintű `HostName=we040.tarhely.com`, ismert
`HostKeyAlias=s59.tarhely.com`, `StrictHostKeyChecking=yes`, BatchMode.
Nincs kulcsmásolás, host- vagy klienskonfig-módosítás. Távoli olvasás:
`/home/sharityh/app/wp-content/mu-plugins/`.

Exact live/base SHA-256:

- `sharity-points-manager.php`: `3a295f14ed81170234559b71aab8afb256c24f0516a9ccf0310c733dc07e5504`
- `sharity-level-manager.php`: `f2612b61c9cc51e399bc6bca635505f26325143fb39e04ad799e77a4cebae8b7`

Mindkét SHA a kijelölt worktree base-fájljával azonos; a célzott élő kódrészlet
ugyanazt az ignorált INSERT/UPDATE/COMMIT hibát igazolja. A korábbi S59
default-key hiba nem a valódi olvasási képesség végleges hiánya.

Koherencia: a bridge csak `award_points_for_pseudo()` `bonus` útját hívja.
Ez own user_points sort, globális point_transactions sort, totals és level
update-et/historyt ír. A level-changed action és points-earned action downstream
értesítést/egyéb hookokat indíthat; ezek nem futhatnak COMMIT előtt.
A nem-pseudo `award_points()` ága külön megmarad, a javítás nem igazolja
minden létező globális writer tranzakciós biztonságát.

Tervezett minimális változás: a pseudo tranzakció összes érintett SQL-false és
last_error eredményét ellenőrizni; user-row létrehozást ugyanabba a tranzakcióba
vinni; level-read/update/history hibát továbbadni; reconnectet a teljes kritikus
szakaszra lezárni és finally visszaállítani; kapcsolatot COMMIT előtt ellenőrizni;
level actiont a manager hívásánál sikeres COMMIT utánra halasztani. Hiba esetén
rollback-kísérlet és sikertelen/bizonytalan válasz; nincs success vagy jutalomaction.

Kockázat: a korábban hamis sikerként kezelt hiba most sikertelen lesz; a
level-action időzítése a pseudo awardnál COMMIT mögé kerül. A connection drop
vagy kétértelmű COMMIT nem lehet automatikus újrapróbálás. A bridge tartós
claimje és default-false transaction readiness megmarad.

Backup/rollback: a két exact base-blob Gitben ismert SHA-val megmarad;
csak saját helyi diff készül. Kiadás előtt mindkét exact live fájlra távoli backup,
CAS, rollback és read-only relock kötelező. DB-backup/restore és valódi staging
fault/párhuzamosság hiányában a bridge readiness nem nyitható. Runtime rollback
először JYSK reward flag off, nem gazdasági adatok törlése.

Érintett funkciólista és kiadás utáni ellenőrzés: pseudo pontjóváírás,
duplikátum, totals, új pseudo létrehozás, szintváltás és level history,
points-earned/level-changed értesítés; Ads Watch, Offerwall és meglévő sponsor
bonus ágak célzott regressziója. Kézi UI: saját profilon normál jutalom,
duplakattintás/párhuzamos ablak, szintküszöb-átlépés és profil totals;
hiba injektálásakor ne jelenjen meg nyugtázott jutalom. Valódi UI/DB ellenőrzés
még nem futott; független review és kiadási felhatalmazás szükséges.
