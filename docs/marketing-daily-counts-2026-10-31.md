# Impact Shop napi anonim marketingmérés — 2026-10-31

Saját forrásjelölt: `feat/marketing-daily-counts-20261031`, base
`88cb74f485229ddb2a04a8868a4bbd3eda29fb62`. A jóváhagyott többwebhelyes
marketingmérési csomag WordPress-ága. Nincs commit, kiadás vagy aktiválás.

## Működés és határ

Két új MU-plugin fájl: `impactshop-marketing-measurement.php` és `.js`.
A külön `__Host-impactshop-measurement-consent` preference csak
`granted-v2` vagy `denied-v2`; nem látogató- vagy munkamenet-azonosító.
Hozzájárulás után navigációnként egyszer, kétmezős `{schemaVersion:2,routeId}`
üzenet növeli a központi napi számlálót. A query/hash változás nem navigáció.
Nincs automatikus retry; visszavonás megszakítja a függő kéréseket és leállítja
az új mérést. A böngészőkérés `referrerPolicy: no-referrer` beállítású.

Az öt stabil útvonalcsoport:

- `/impactshop/` → `sharity-app.impactshop`
- `/rolunk/` → `sharity-app.about`
- `/cegeknek/` → `sharity-app.companies`
- `/befektetoknek/` → `sharity-app.investors`
- `/ngo-guides/`, `/ngo-guides/impact-shop/`, `/ngo-guides/impact-challenge/`,
  `/ngo-guides/ngo-card/` → `sharity-app.ngo-guides`

Csak a fenti publikus oldalak kaptak engedélyt; a JYSK-riport, jogi iratok,
profilok, affiliate útvonalak, vásárlás és pont/szavazat mechanizmus kívül marad.
A korábbi guide/PHP/HTML/JS állományok bájtjai változatlanok. A publikus guide
renderelt HTML csak az engedélyezett új script taggel bővül, `template_redirect`
0 prioritású kimeneti bufferből: ez lefedi a meglévő `echo/readfile + exit`
oldalakat is. Csak HTTP 200, `text/html`, teljes HTML/body válasz bővül.
JSON, hiba, redirect és az összes nem engedélyezett út változatlan.

## Konfiguráció és proxy

Mindegyik érték szerveroldali konstans vagy környezeti változó; a konstans az
elsődleges. Titok nem kerül JS-be vagy exportba.

- `MARKETING_SHARITY_APP_MEASUREMENT_ENABLED=1`, alapból OFF.
- `MARKETING_MEASUREMENT_GATEWAY_URL` kizárólag
  `https://sharity-legal-production.up.railway.app` lehet.
- `MARKETING_SHARITY_APP_MEASUREMENT_BFF_TOKEN`: a webhely saját tokenje.

A publikus, azonos-origin POST `/wp-json/impactshop-marketing/v1/events`
maximum 2048 byte JSON-t fogad. Pontos origin/host és — ha küldött —
`Sec-Fetch-Site: same-origin`, továbbá v2 hozzájárulási preference kell.
A proxy újraépíti a kétmezős törzset és a belső hitelesítő/origin fejléceket;
bejövő cookie, query és referer nem továbbítódik. 8 másodperc timeout,
0 redirect, nincs retry, legfeljebb 2048 byte upstream-válasz. Kifelé csak
`accepted` logikai érték és fix HTTP státusz jut. WordPress DB/séma nincs.
A központi gateway meglévő webhelyenkénti limitje megmarad.

## Koherencia, kockázat és rollback

Az additív modul a hozzájárulással növelt anonim napi számlálókhoz kapcsolódik;
nem módosítja az Impact Challenge kanonikus baseline-t vagy pénzügyi írókat.
Konkrét kockázat a rossz felületre történő injekció és az upstream-adat/titok
kijutása: pontos útvonal/HTML/status kapu és újraépített proxy kezeli ezeket.
A védett guide-forrásokhoz nincs új hash vagy override. A Bástya modellje és
jogosultságai változatlanok; runtime-only protected-touch és affiliate-bastion ellenőrzés
sikeres; a protected státuszdokumentumhoz külön dokumentációs record készül. Új modul célzott védelmi fixture-jei alább; nincs guard-infrastruktúra.

Rollback: a kapcsoló OFF és az új PHP/JS eltávolítása a későbbi engedélyezett
kiadási folyamatban; meglévő guide/source visszaírása nem szükséges. Nincs
adatmigráció vagy saját tárolt esemény. Cache-elt script a kapcsoló OFF után
sem gyűjthet, mert a szerveroldali fogadó 503-mal zár.

## Tényleges helyi ellenőrzés

- PHP-szintaxis és JS-szintaxis sikeres.
- `php tests/impactshop-marketing-measurement.test.php`: 54 ellenőrzés sikeres;
  WordPress hálózati hívás kizárólag stub.
- `MARKETING_TEST_DEPENDENCIES_ROOT=<meglévő ai-agent/apps/web> node --test tests/impactshop-marketing-measurement.test.mjs`:
  5 teszt sikeres Node 22.23.2 alatt, meglévő jsdom használatával, install nélkül.
  Consent/navigáció/retry/withdraw és valódi helyi PHP HTTP `echo+exit` fixture;
  minden szervercél 127.0.0.1, külső hálózati hívás nincs.
- `bash scripts/check-protected-file-touch.sh --mode local`: a runtime-only diff sikeres;
  a teljes diffben a kötelező protected státuszdokumentumhoz rögzített override kell
  (`docs/protected-change-records/2026-10-31-marketing-daily-counts.md`).
- `bash scripts/sharity-affiliate-runtime-bastion-guard.sh`: PASS.

A kezdeti helyi HTTP-fixture-ben a Node fetch nem vitte át a kért Host fejlécet;
`node:http.request` pontos Host-tal igazolta a tényleges szűrést. Ez tesztjavítás,
nem hostellenőrzés-lazítás.

## Kiadás előtt és kézi UI

Független integrációreview és a normál kiadási jogosultság még szükséges.
Éles WordPress/cache/CSP teszt és mérés nincs, ezért nincs élő acceptance.
Engedélyezett kiadáskor: öt oldalcsaládon banner/Elfogadom/Elutasítom/
kikapcsolás, hosszú guide görgetés és mobilmegjelenés; JYSK print/JSON,
jogi guide és profil változatlansága; OFF állapot; külső fejléc/payload nélküli
azonos-origin proxy; központi összesítés és cache ürítési/readback ellenőrzés.
A korábbi anonim számlálók egyénileg nem törölhetők; ezt a banner kimondja.

Megőrzendő szerzői hivatkozás: `/Users/bujdosoarnold/ai-agent/SUBSTACK-HANDOVER.md`;
ez nem végrehajtandó mellékfeladat.
