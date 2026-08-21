# Hatás Körök Human Touch route checkpoint — 2026-08-21

## Eredmény

Az `app.sharity.hu/hatas-korok` és az opcionális záró perjeles változat a
Sharity Human Touch Hatás Körök felületére ad ideiglenes, pontos `302`
átirányítást. A cél rögzített `https://sharity.hu/hatas-korok`; a kérés query
paraméterei, cookie-értékei és más felhasználói adatai nem kerülnek a
`Location` fejlécbe.

## Biztonsági határ

- Az új MU-plugin additív és `template_redirect` prioritása `1`.
- Csak az `app.sharity.hu` host pontos publikus dokumentumútját kezeli.
- Csak `GET` és `HEAD` kérés irányítható át.
- Admin, AJAX és REST kérés kizárt.
- A `/hatas-korok-dev`, az `/impactshop-staging/hatas-korok-dev`, a community
  REST API, az identitás/profil-visszatérési rendszer, a pont-, szavazat-,
  reward- és pénzügyi írók, az Offerwall és a VB2026 runtime változatlan.
- Rollback: kizárólag az új MU-plugin eltávolítása, amely után az érintetlen
  legacy route-handler veszi vissza az útvonalat.

## Ellenőrzési evidencia

- Terra tervkapu: `approved-for-luna`, canonical plan checker `PASS`.
- Célzott route-teszt: 6/6 `PASS`.
- PHP lint: `PASS`.
- Shell syntax: `PASS`.
- Guard JSON parse és checksum-paritás: `PASS`.
- Exact-release remote teszt: `PASS`.
- Exact-file deploy mapping teszt: `PASS`.
- Bastion mapping teszt: `PASS`.
- Rollback truth teszt: `PASS`.
- Detached preflight teszt: `PASS`.
- A legacy Wallet PHPUnit integráció nem tartozik a route scope-jába, és ebben
  a clean worktree-ben nem futtatható külön WordPress DB-fixture nélkül
  (`WP tests not found`); a hiányzó fixture miatt éles vagy megosztott
  adatbázist nem hoztunk létre. A teljes, DB-független repotesztkör zöld.
- Worktree continuity/docsync guard: `allowed`, figyelmeztetés nélkül.
- `git diff --check`: `PASS`.

## Élesítési kapu

Az élesítés kizárólag a GitHubon összeolvasztott, friss `origin/main` pontos
fájlos release-ével történhet. Deploy után kötelező a read-only Hatás Körök
smoke, az exact `Location` ellenőrzés, a dev-route és community API regressziós
ellenőrzés, valamint a VB2026 publikus route változatlanságának bizonyítása.
