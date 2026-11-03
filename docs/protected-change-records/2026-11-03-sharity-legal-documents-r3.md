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
