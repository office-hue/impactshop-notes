# Admitad meglévő adspace tulajdonosi ellenőrzés — 2026-11-16

Felhasználói scope: az elfogadott Impactshop/Admitad terv implementálása a
telepítés/aktiválás végéig. A meglévő saját adspace2888572 ma Inactive;
a Mitgo ellenőrzőfelülete a lent rögzített nyilvános meta tag-et kéri.
Ez az adott meglévő URL tulajdonosi ellenőrzése, nem új programhoz csatlakozás,
szerződés-, credential-, felületcím-, fiókbiztonság- vagy kifizetésmódosítás.

Koherencia/érintettség: új, kicsi `impactshop-admitad.php` MU-plugin, egy
`wp_head` hook. Csak az app.sharity.hu WordPress-site `/impactshop/` oldalának
head részébe adja a statikus tag-et. Nincs script, request, cookie, redirect,
REST endpoint, cron, pont, szavazat vagy gazdasági writer. Nem változik
meglévő Challenge/JYSK/guide/identity/affiliate fájl vagy működés.

Konkrét bemenet a bejelentkezett meglévő Mitgo verification oldalról:
`<meta name="mitgo-verification" content="348a9888-0449-4a2e-8dbb-122bd395fe0a" />`.
Ez nyilvános domain-ownership jel, nem API-token. A 2026-11-16 GET200
`https://app.sharity.hu/impactshop/` headjében jelenleg nincs ilyen tag.

Kockázat: hibás URL/site esetén rossz oldalhoz kerülne a jel. Exact WordPress
home host és exact request-path határ zárja; a content konstans. Új MU-plugin
függőség és konfiguráció nélkül betölthető. A hálózati adapter/outbound/poll
még nincs ebben a változásban, ezért tracking vagy pénzügyi aktiválást nem okoz.

Teszt/ellenőrzés: PHP lint és az új hook célzott helyi futtatása az exact
host/path, más path és staging/idegen host eseteire. Kiadás csak merge-elt
főágból a meglévő exact-file guard útján, remote absent/előhash CAS-szal.
Új modul source-inventory/guard státuszának átvezetése külön ellenőrzött.

Mentés/rollback: meglévő exact-file remote backup/rollback, új file esetén
igazolt absent előállapot. Visszaállítás csak a saját változatlan telepített
hash-ből; más fájl, options/táblák és webalias nem érintett. Maximum2napos
protected-file backup a repo szabálya szerint, teljes mentést nem törlünk.

Éles ellenőrzés: app.sharity.hu/impactshop GET200 és pontosan egy tag a headben;
más oldal/staging nem kapja meg. Ezután a Mitgo oldalon a valós elhelyezés
jelölhető és Finish kérhető; bizonytalan eredménynél adspace státusz-readback,
nincs vak újraküldés. A szolgáltató legfeljebb15perces ellenőrzési ideje
külső várakozás, nem üres polling-ciklus. Esetleges új feltételeket külön kell
áttekinteni, nem automatikusan elfogadni.

Kézi UI checklist Arnoldnak: `/impactshop/` normál betöltés; meglévő partnerlista
és linkek a szokott módon működnek; nincs új látható banner/popup; Admitad
adspace külön Active/readback állapota szükséges. Kód/merge/deploy/ownership
ellenőrzés/programjog/outbound/pont külön állapot.

Protected files touched: `wp-content/mu-plugins/impactshop-admitad.php`, a meglévő guard-config/protected-files inventory és társ hash/checksum fájlok. Egy exact új bejegyzés, meglévő védelmi szabály törlése vagy programváltozás nélkül. Smoke scope: `route:impactshop`, `deploy:guard-preflight`, `deploy:checksum-verify`; a helyi5ellenőrzés nem éles smoke. Rollback és érintett fájl a fenti szakaszokban.


## Tényleges kiadás és szolgáltatói kérés
PR282 merged0ba3078d. Egyedüli production exact-file release `20261116T140458Z-0ba3078de226-472aae0c`; SHA256 `2cd2313c2931c7de32013d862af1578e0562969b22f123e9c38d4f6558c5930b`, távoli lint/hash/0444 és parent0555 readback PASS. Rollback a fenti meglévő script e releaseID/expectedSHA értékeivel. Normál Mozilla böngészőazonosítós Air és szerver GET200, pontosan1meta tag; profil GET200 és0tag. Python-urllib alapazonosító403-at kap; nem általános oldalhiba. A Mitgo ellenőrzés valós elhelyezés jelölése és Finish kérése megtörtént; visszaérkezett az adspace listába, amely még Inactive. Ez pending szolgáltatói ellenőrzés, nem Active/program/outbound/financial PASS. Nincs újraküldés vagy programcsatlakozás.

Újabb szolgáltatói readback: Under review. A kész read-only shadow forrás az új CJ közös rekordban követett, ownership hook változatlan; még nincs új merge/telepítés/API-aktiválás.
