# Sharity jogi dokumentumok R3 – védett változtatási lap

## Felhatalmazás és cél
Arnold a Sharity ÁSZF/GDPR és kapcsolódó szabályzatok véglegesítését és sharity.hu-oldalakon történő közzétételét kérte, majd külön a megfelelő PDF-ek, hivatkozások és űrlapverziók összekapcsolásának folytatására utasított. Ez a csomag a már létező magyar és angol jogi dokumentumindex pontos cseréjét készíti elő saját munkafában. A forrás előkészítése nem éles közzététel. Main művelet előtt szűk quiet window szükséges.

## Érintett fájlok – protected files touched
- `wp-content/mu-plugins/impactshop-ngo-guides/jogi-dokumentumok.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/jogi-dokumentumok-en.html`

A korábbi index beágyazott, korábbi időállapotú normaszöveget is tartalmaz; mellette új index létrehozása a régi belépési ponton továbbra is ellentmondó tájékoztatást hagyna. Ezért az azonos útvonal tartalmának cseréje indokolt. A központi új PDF-hub a külön ai-agent csomag része.

## Koherencia és érintett funkciók
Mindkét oldal ugyanarra a kilenc, pontos fájlnévvel és ellenőrzött SHA256-tal rögzített nyilvános PDF-re hivatkozik a `https://sharity.hu/legal/2026-11-03-r3/` alatt. A PDF-ek magyar nyelvűek, ezt az angol oldal jelzi. A Win4Good anyag történeti archívumként szerepel. Belső audit, döntési lap és szolgáltatói nyilvántartás nem kerül az indexbe. A központi hub `/jogi-dokumentumok`.

A guide router, JYSK riport HTML/print/JSON, REST, cron, azonosítás, pont/szavazat/jutalom, ledger és affiliate adatfolyam nem változik. Az Elementor lábléc külön CMS-művelet, nem része ennek a Git-változtatásnak.

## Kockázatok és sorrend
A fő kockázat a még nem publikált PDF-re mutató link, az eltérő fordítás, a cache-ben maradó régi tartalom és a véletlenül széles deploy. Előbb a kilenc központi PDF éles elérhetőségét és bájtszintű azonosságát kell ellenőrizni; utána csak a két megnevezett index telepíthető. Más guide-fájl vagy teljes könyvtár szinkronizálása nem engedélyezett.

## Backup és rollback terv
A Git kiinduló commitja `0acd768150d787fc63d98d9739f6adb700866a99`. Ez forrás-preimage, nem az éles állapot backupja. Telepítés előtt a guarded release-nek a két éles célfájl aktuális hashét, módját és célpontját ellenőriznie és külön visszaállítható mentést kell készítenie. Eltérő élő preimage esetén újraértékelés kell; nincs vak felülírás. A telepítés csak merge-elt pontos mainból, `bin/impactshop-guard-deploy.sh` útvonalon történhet. Tilos közvetlen SCP/copy útján éles fájlt cserélni.

Rollback: csak a két indexet állítsuk vissza a telepítés előtti éles backupból CAS-ellenőrzéssel, majd ellenőrizzük a két jogi útvonalat és a guide router sértetlenségét. A központi verziózott PDF-eket régi hozzájárulások hivatkozásai miatt ne töröljük. A helyi és éles guide-fájlok módja telepítés után 0444, a guide-könyvtáraké 0555; ez ellenőrizendő, nem előre igazolt eredmény. Implementációs backup legfeljebb két napig, sikeres UI-ellenőrzésig; teljes rendszerbackup érintetlen.

## Smoke scope és funkció-ellenőrzőlista
Kötelező címkék: `route:jysk-riport`, `route:ngo-guides`, `flow:guide-route-render`, `flow:guide-print-mode`, `flow:guide-data-json`.

Forrásellenőrzés: mindkét HTML pontosan kilenc engedélyezett R3-PDF-linket tartalmaz; nincsen belső irat vagy régi normatív törzsszöveg. Git diff csak a két HTML-t és e változtatás dokumentációját érinti. A HTML statikus linkellenőrzése nem éles smoke.

Telepítés után: HU/EN jogi index HTTP/render; kilenc PDF HTTP/content-type/hash; központi hub; guide főoldal; JYSK riport normál és print nézet, JSON előzetes állapothoz képest. Cache és read-only mód visszaolvasás. Egyik sem jelölhető PASS-nak tényleges futás nélkül.

## Kézi UI checklist Arnoldnak
- Asztali és mobil nézetben a magyar/angol index olvasható, nincs levágott címsor vagy link.
- Az általános ÁSZF, adatkezelés és cookie külön elérhető.
- NGO- és JYSK-dokumentumok megfelelő cím alatt nyílnak; angol oldalon látszik a magyar PDF nyelve.
- A Win4Good címke egyértelműen archív.
- PDF letöltés és visszanavigálás működik; a szokásos guide/JYSK riport belépés változatlanul működik.

## Aktuális állapot
Helyi forrás-előkészítés. Éles backup/CAS, guarded deploy, HTTP/hash smoke és kézi UI-elfogadás még nem történt. A külön jogi L2 a két új JYSK irat pontos szövegét jóváhagyta; a csomag másik 17 PDF-je változatlan R2. GA4 éles beállítása 2 hónap / újraindítás kikapcsolva, visszaolvasva; a Google 24 órás érvényesülést jelez.


## 2026-11-03 – merge utáni reviewed baseline egyeztetés

A két HTML már a `3d81c7b66aa574e2e33baa0a5e0bdb91874949d8` mainban van. A Mini guarded dry-run távoli írás előtt megállt: „Non-interactive auto-approve tiltva: protected runtime/shell drift észlelve”, kizárólag e két HTML-re. Bizonyíték: az ügy `evidence/publication-r3/WP-GUARDED-DRY-RUN.log` fájlja, snapshot `deploy-20261103-215618`. A manifest még a régi tartalmak hashét őrizte.

Ez a kiegészítés kizárólag `docs/impactshop-guard-hashes.json` két már reviewzott HTML-bejegyzését és a hozzá tartozó `docs/impactshop-guard-hashes.sha256` checksumot egyezteti a merge-elt forráshoz. Normál reviewed baseline update: nincs policy-, inventory-, más pin-, jogosultság-, guardlogika- vagy programkódváltozás; nincs emergency/manual bypass. A meglévő felhatalmazás és a kétfájlos deploy-kör nem bővül.

- `wp-content/mu-plugins/impactshop-ngo-guides/jogi-dokumentumok.html`: `fee9791226a805ca455965a9a641604ba8da2c2370e926d3ebdef8dd7ef12bee` → `c0b1d9f31bc5b27ae39779fe90b2722ace4031a7be1de976b69a7e3aa88d1426`.
- `wp-content/mu-plugins/impactshop-ngo-guides/jogi-dokumentumok-en.html`: `28a10c3ba57955932cab7b87f029b1e29fc1b0342cd91f9d88ab6ffc4ae838a6` → `bbeb21276cd507267563370b79430f2fcc8fe640619136bfd5b7dfd219682d7d`.

Statikus ellenőrzés: a 169 elemű `hashes` map pontosan két kulcsa változik; a JSON minden más bájtja változatlan. Mindkét HTML byteazonos az alapcommit tartalmával, egyik sem része e patchnek. Az új manifest SHA256 `b4ced6211d803f813bfed12a96288695c023da12c6f081a20b7163fb34d4333e`; a checksum sora repo-relatív `docs/impactshop-guard-hashes.json` névre mutat. A meglévő többi pin és szabály változatlan. Ez helyi statikus bizonyíték, nem ismételt Mini dry-run vagy éles smoke PASS.

Érintettség/kockázat: kizárólag a reviewed tartalom hash-baseline-ja; egy téves hash hibás tartalmat fogadtatna el, ezért a JSON-kétkulcsosságot és a merge-elt HTML-bájtazonosságot külön ellenőriztük. A korábbi route-/guide-/print-/JSON-smoke és kézi HU/EN, kilenc PDF, archív Win4Good UI-checklist változatlan. Következő lépés a külön fixág reviewja és main merge után az eredeti guarded dry-run; sikere itt nem előlegezett.

Rollback: saját fixcommit visszavonásával a két hashfájlt együtt az alapcommit verziójára állítani; a már merge-elt HTML-t nem szabad e metadata-rollback részeként visszaírni. Ez visszahozhatja az eredeti driftblokkot, nem engedély annak megkerülésére. Éles fájlírás nem történt; a korábban rögzített valódi backup/CAS/rollback-rend megmarad.

A metadata-kiegészítés kötelező smoke scope-ja: `deploy:guard-preflight`, `deploy:checksum-verify`; a korábbi guide/JYSK render/print/JSON scope megmarad.
