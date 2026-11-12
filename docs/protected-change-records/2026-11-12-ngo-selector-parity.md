# NGO-katalógus és választási felület paritása — 2026-11-12

Állapot: helyi forrás és célzott teszt kész; független integrációs review,
merge, telepítés és élő felhasználói elfogadás külön, itt még nem igazolt.
Kijelölt saját ág: `fix/ngo-selector-parity-20261112`, induló HEAD
`c52e96b6cfbbdd9647fda36256c310d397567540`. A meglévő idegen dokumentációs WIP
megőrzött; nincs fetch, main-módosítás, paid call vagy távoli írás.

## Protected files touched és megoldási döntés

- `wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php`: a teljes és kiemelt
  katalógus meglévő SQL-projekciója expliciten kiválasztja a kampánykulcsot és
  választási jogosultságot. A publikus NGO-elem `campaign` és logikai
  `allow_user_selection` mezőt ad; hiányzó jogosultság false. Az inline
  katalógus UI csak aktív, azonos kampányú, explicit engedélyezett elemet kínál
  választásra. Az auth-intent kerülő kizárólag 401 után indul, 409 vagy más
  jogosultsági/szolgáltatási hiba után nem.
- `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`: egy közös
  NGO-slug validátor a shopping GET és zárolt POST meglévő 128 byte-os határán
  elfogadja a kanonikus kisbetűs százalékkódolt tokeneket. Nincs dekódolás,
  slug-újragenerálás, új NGO-ID vagy pénzügyi számításváltozás.
- `tests/sharity-affiliate-runtime-test.php`: a valós termékfüggvények GET és
  POST ágait szintetikus DB-határon teszteli, a tárolt NGO-ref byte-egyezésével.
- Új célzott tesztek: `tests/impactshop-vb2026-ngo-catalog-parity.test.php`,
  `tests/impactshop-vb2026-ngo-catalog-ui.test.mjs`.
- Meglévő inventory változatlan; `docs/impactshop-guard-hashes.json` csak a két
  saját forrásdigestet követi, a `.sha256` társellenőrzőösszeg ennek megfelelően
  frissül. Config, guardmodell és más forráspinek nem változnak.

A felhasználó által jóváhagyott bounded parity-csomag explicit legacy touch:
a hiba a meglévő projekcióban és belépővalidációban van, külön párhuzamos
katalógus/route nem adna azonosan jó, egyszerű megoldást. A jogosultsági
ellenőrző és gazdasági writer nem kerül agenthez vagy klienshez.

## Koherencia, funkció-érintettség és kockázat

Adatút: `sharity_ngo_catalog` + kampányflag → `/impact/v1/ngo-catalog` és
`/impact/v1/vb2026/featured-ngos` → Sharity katalógus és VB-fogyasztó.
A meglévő kampány-, active-, public-listing- és race/region-korlátok megmaradnak;
a kiemelés továbbra is prezentáció, nem választási felhatalmazás. A statikus
felület meglévő 48-as paginációja változatlanul minden oldalt betölt.
Érintett kiválasztási flow: `/vb2026/select-ngo`, auth esetén
`/vb2026/selection-intent`; a szerveres selection/upsert szabály változatlan.

Shopping adatút: hiteles owner-bound session → `/sharity/v1/shopping/ngos` →
BFF/UI → `/sharity/v1/shopping/dognet-activate` → a zárolt kanonikus NGO-slug
változatlan tárolt referenciája. A session/CSRF, partner/campaign binding,
idempotencia, tranzakció/readback, Dognet redirect és retention változatlan.
Import/sync/cron, admin, reward/pont/szavazat/wallet/ledger és settlement
nem módosul. A publikus listázás nem ad közös, globális engedélyt.

Konkrét kockázat: percent-token csonkolása vagy dekódolása rossz NGO-referenciát
adna; hiányzó/hamis kampányflag mellett kattintható UI téves felhatalmazást
sugallna; 409 utáni login-fallback eltakarná a valódi eligibility hibát.
Ezeket byte-egyezéses, invalid/128–129 határ-, explicit flag-, más kampány- és
401/409/403/422/503 ágtesztek fedik. A PHP DB-határ fixture, nem valódi WP/DB
vagy production elfogadás.

## Tényleges ellenőrzés és smoke scope

A saját worktree gyökerében sikeresen futott:

```sh
php -l wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php
php -l wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php
php tests/impactshop-vb2026-ngo-catalog-parity.test.php
php tests/sharity-affiliate-runtime-test.php
php tests/impactshop-vb2026-principal-binding.test.php
node --test tests/impactshop-vb2026-ngo-catalog-ui.test.mjs
bash tests/sharity-affiliate-runtime-bastion.test.sh
```

PHP lint és a három PHP-futás PASS; az inline termékfüggvényeket végrehajtó
Node-suite 6/6 PASS; affiliate bastion tamper-próba PASS. A projekciós teszt
254 elem/48-as lap/6 oldal alakot, explicit kampányt és false/hiányzó flaget
vizsgál; a meglévő principal-binding külön sikeres.

A kapcsolódó saját ai-agent worktree-ben a UserCardEditor 9/9, shopping BFF
30/30, Fund-választó 4/4 célzott Vitest-eset sikeres. A Fund-futás végső célzott
újrafuttatása helyesbített tesztmatcherrel PASS; az eredeti szomszédos spanek
accessible name-je szóköz nélkül kapcsolódik össze. Web type-check:
`npm run type-check -- --incremental false` PASS. Ezek helyi tesztek.

A DOCSYNC után a célzott Node-ellenőrzés PASS: pontosan a két saját digest
változott a HEAD-manifesthez képest, mindkettő a tényleges forrással egyezik;
a három bővített dokumentum teljes eredeti byte-prefixe változatlan. Futtatott
`shasum -a 256 -c docs/impactshop-guard-hashes.sha256`: OK; manifest SHA256
`ef778fdec667e31ce47e0ba28c9bd23e29614dbfa9d295ccd271538b8dbf7c72`.
`bash scripts/sharity-affiliate-runtime-bastion-guard.sh`: PASS.
`scripts/check-protected-file-touch.sh --mode local`: OK az explicit saját
change recorddal, rollback-note-tal, a jóváhagyott helyi touch-ot jelölő
`BASTION_OVERRIDE=1` és az alábbi smoke scope mellett. A teljes idegen
hashinventory nem lett újragenerálva vagy általános PASS-ként feltüntetve.
`git diff --check`: PASS. A kötelező smoke tagek ellenőrzendő scope-ot jelölnek,
nem lefutott élő smoke-ot: `route:factlens-vb-prod`, `route:impact-challenge`,
`route:profil`, `flow:message-popup`, `flow:points-jump`,
`flow:legacy-pool-visibility`, `flow:profile-return-account`,
`flow:profile-return-restore`, `route:impactshop`, `flow:saved-offers-open`,
`flow:go-deal`, `browser:webkit`, `browser:chrome`, `deploy:guard-preflight`,
`deploy:checksum-verify`.

**Függő:** valódi WordPress `sanitize_title` roundtrip. Ebben a kijelölt helyi
fában nincs elérhető WP-core/CLI. Az affiliate-teszt régi sanitize_title stubja
nem bizonyítja a WordPress viselkedését; a percent-token teszt ezt nem állítja.
Telepítés előtt külön írásmentes, tényleges WordPress-futás kell az eredeti
token és sanitize_title(token) pontos egyezésére. Éles UI, jogosultsági és
shopping útvonal nem lett kipróbálva, production írás nem történt.

## Backup és rollback

Változtatás előtt készült két pontos saját lokális snapshot:
`tmp/ngo-parity-20261112/impactshop-vb2026-ngo-catalog.php.before` SHA256
`43547e58096395f3b2872eec349c02b32fc3ca09f659d575c2c12d0b8af37976`, valamint
`tmp/ngo-parity-20261112/impactshop-sharity-affiliate-runtime.php.before` SHA256
`472476e5101a0542b1bd1b0619eb52288fd965b78b412b0f775a4969ae2bdb01`.
Ezek nem teljes adatbázis- vagy rendszermentések. Alapretenció max. 2 nap;
most nincs törlés, mert a kézi UI-elfogadás függő. A megtartás és esetleges
hosszabbítás a függő elfogadásnál újra felülvizsgálandó.

Helyi rollback kizárólag saját változatlan utóhashről, újabb WIP hiányában:
a két snapshot pontos forrás-visszaállítása és a két régi manifestérték+
manifest-checksum visszaállítása, majd PHP lint/source guard. Jelen utóhash:
katalógus `b0e4a4fa24306b74f9759ad4f0afaacac0cfe1367a7da449152c84fc6f0ee02c`,
affiliate `88e4e955fe2178c43748121253214a0882d5dbcf21ba975b50a11489808c5296`.
Nincs séma- vagy adatrollback. A kijelölt helyi fájlok eredetileg 0644 módúak,
ezt nem változtattuk meg; production telepítés saját 0444 visszazárása külön
release-követelmény, itt nem történt telepítés.

## Külön kézi UI checklist Arnoldnak

1. Szívügyeim szerkesztő: 254 katalóguselem/1 kiválasztott esetén 253 még
   hozzáadható; a keresési találat ettől külön látszik. Hozzáadás/eltávolítás
   után a számok és a keresés hu/en/de/fr nyelven is következetesek.
2. Közös alap választó: További szervezetek gombbal az utolsó NGO-ig el lehet
   jutni; keresésben is működik a további betöltés, billentyűzettel választható.
3. `/szervezetek/`: nem kiemelt, későbbi oldal NGO-ja látszik; hiányzó/tiltott
   eligibility vagy más kampány mellett a választás nem kattintható. 409
   hiba marad, 401 esetén a meglévő biztonságos belépés indul.
4. Shopping választó: a százalékkódolt NGO megjelenik, kiválasztható, és a
   kanonikus referencia változatlan. Valódi vásárlás/gazdasági tesztírás ehhez
   a forráscsomaghoz nincs engedélyezve.

Merge/telepítés után a fenti érintett API/UI-funkciók és a megőrzött
profile-return/affiliate útvonalak külön visszaellenőrzése szükséges.
