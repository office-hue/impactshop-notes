# User Card — szűk profil-visszatérés

## Aktuális korrekció — a szerveres FactLens-döntés elsőbbsége

Arnold jóváhagyott kétfájlos terve alapján, saját
`fix/signed-profile-return-20261028` ágon, `02fbec8b` kiindulással.
A lentebbi JS-only leírás és eredmények a korábbi kiadás történeti állapota.

Protected files touched: `wp-content/mu-plugins/impactshop-identity-panel.php`,
`wp-content/mu-plugins/impactshop-identity-panel.js`,
`docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`.
A manifestben kizárólag a két runtime hash és a származtatott checksum változik.

JS: a pontos User Card-bemenet csak az azonos originű account/restore completion
linkre kerül át; nincs közvetlen böngészős PKCE-kerülőút. PHP: a HttpOnly
kontextuscookie eredeti jelenléte számít (az üres/hibás is jelenlét), a meglévő
FactLens-resolver egyszer fut az eredeti fallbackkel. Az eredménye jelenlévő
cookie mellett érintetlen. Csak hiányzó cookie, pontos GET/origin/path és
egyértelmű nyers query esetén, kizárólag a natív fallback helyett indulhat a
rögzített User Card-session-start. Hiányzó resolver nem nyit kerülőutat.
Az éles előellenőrzés a kanonikus helyi bridge-től eltérő, meglévő `prctx`
visszatérési utat talált. A bridge érintetlen marad; bármilyen `prctx` jelenléte
szintén kizárja az új Card-fallbacket, így érvényes és hibás query-kontextusnál
is az eredeti szerverdöntés marad. Ez a jóváhagyott prioritáskorrekció szűkítése.
Nincs bridge-, titok-, jogosultság-, pontozás-, web-, DB-, cron- vagy sémaírás.
Account-save rejtett mező, iframe POST és jutalmazási hívásszám változatlan.

### Ellenőrzés és ismert tesztkorlát

PHP szintaxis, JS szintaxis, a meglévő identity-profile-v2 statikus teszt és
profile-route-path viselkedési teszt sikeres. A valódi completion-függvény
Korábbi 33 eset sikeres stub és helyi kanonikus resolverrel; a végleges kibővített
kör 36 stub és 42 ténylegesen telepített resolveres esetben sikeres, szintetikus
DB-vel. Az éles forrás írásmentes másolatának SHA256-ja:
`be8ba48ddddc421a00007cc93425765b158b377ee71e596b3a2f609aaaacf296`.
A telepített bridge `prctx[]=bad` bemenetre meglévő Array-to-string warningot
ad: a fixture csak ezt az egy, pontos forrásból és bemenetből származó jelzést
rögzíti/ellenőrzi külön, nem állít warningmentességet és nem nyom el más hibát.
Az integráció explicit
`FACTLENS_BRIDGE_SOURCE` bemenettel fut; nélküle NOT RUN, nem integrációs PASS.

A bővített böngészős fixture első futása hibásan HTTP 303-at adott vissza:
a böngésző átirányított anonim GET-je kilépett a Playwright mockolásából.
Nem volt élő restore/account-save/pontírási próba; ezek kéréseit helyi fixture
válaszolta meg. A tesztet javítottuk: PHP 303-döntés ellenőrzése után külön
szimulált navigáció, minden kérés helyi fulfill, elérhetetlen hálózati proxy.
Ez a JS→PHP döntési lánc tesztje, nem élő HTTP-redirect vagy Safari-elfogadás.
A javított teljes böngészős kör 25/25 PASS, a kanonikus resolverrel.
Parancs: `USER_CARD_TEST_DEPENDENCIES_ROOT=<meglévő-User-Card-worktree>
FACTLENS_BRIDGE_SOURCE=<kanonikus-bridge> node --test tests/impactshop-user-card-return.test.mjs`.
Az éles resolveres végleges böngészős futás 25/25 PASS (56.9 s).
Független 6.1 Sol/high review: nincs P0/P1/P2 az első diffen és a `prctx`
kiegészítésen; saját reviewer-futásban 42 production és 36 kanonikus PHP-eset
PASS. A helyi profiltesztek nem részei a jelenlegi hosted CI-nek.
Natív staged content/titok/protected/lane ellenőrzés sikeres a jóváhagyott
protected-change record, rollback és pontos identity_and_points smoke-tagek
átadásával. A tagek scope-ot jelentenek, nem új éles teszteredményt.

### Kiadás, részleges hiba és rollback

Egy Notes PR, meglévő CI, normál merge; nincs Vercel-build vagy termékwebdeploy.
Merge után tiszta main/detached kiadási checkout, HEAD=helyi origin/main.
Mini→hosting meglévő exact-file deploy, előhash/CAS és mentés fájlonként:
PHP → visszaolvasás → JS → visszaolvasás. A pár nem egyetlen atomi tranzakció.
Cél: `/home/sharityh/app/wp-content/mu-plugins/`; fájl 0444, könyvtármód megmarad.
Csak a saját kiadási checkoutban dry-run; nincs auto-commit/auto-approval,
tömeges rsync, guardpolicy-, távoli manifest-, watchdog- vagy cronmódosítás.
Két külön release ID; backup legalább hét napig, automatikus törlés nélkül.

Részleges PHP-siker után readback; a kompatibilis PHP megmaradhat, de nem
nevezhető aktív javításnak. Bizonytalan válasznál meglévő release inspect,
nem új beküldés. Saját regressziókor JS, majd PHP rollback saját telepített
hashre feltételesen, utána fájl/HTTP-ellenőrzés. Idegen fájlt, DB-t és sessiont
nem állítunk vissza. Élő resolver-meghívás nem írásmentes vizsgálat.

Élő elfogadás: kontrollált profil normál User Card-visszatérése és FactLens
elsőbbsége; anonim próba nem elég. Account-save élő teszt csak igazolt,
új pontírást nem okozó feltétellel. Safari/profil A–B elfogadás külön állapot.
Most forrásmunka; publikálás, merge, telepítés és élő elfogadás még függő.

## Történeti JS-only kiadás

Protected files touched: `wp-content/mu-plugins/impactshop-identity-panel.js`,
`docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`.
Smoke scope: identity_and_points és deploy_guard. A tagek érintettséget jelölnek,
nem élő PASS-t. Account/restore: teljes JS Chrome-fixture; message-popup,
points-jump, legacy-pool és további route-ok: változatlan kód/paritás,
nem új éles pontírásos vagy gazdasági próba.

## Jóváhagyás és koherencia

Arnold explicit jóváhagyta a User Card profilösszekötési javításának teljes
tervét, ezen belül az egyetlen közös termékfájl célzott módosítását:
`wp-content/mu-plugins/impactshop-identity-panel.js`. Alap: `77c4abbf`.
A donor változatlan; külön saját `fix/user-card-profile-return-20261028` ág.
Régi JS SHA256: `cda132eac43fa8fc4d4bfde2576aa683f0f234a674eea6e41161fef9225dbd6e`.

Csak `navigateAfterIdentityAction` változik: app.sharity.hu/profil/, egyetlen
pontos `return=https://sharity.hu/sajat-sharity`, selection_intent nélkül.
Sikeres restore/account-save után rögzített User Card PKCE start URL.
Minden más út marad `targetUrl || fallbackUrl`. A rejtett jelszókezelő form
completion URL-je, PHP bridge, aláírt FactLens context és owner authority marad.
Nincs REST-, pont-, pénz-, szavazat-, séma-, cron- vagy adminmódosítás.
A saját forrásmanifestben csak e JS hashét és a külső checksumot követjük;
guardkód/inventory és távoli történeti Bástya-manifest nem változik.

## Kockázat és ellenőrzés

Fő kockázat: más termék visszatérésének eltérítése, nyitott redirect, illetve
régi profil megjelenése. Pontos origin/path/egyetlen return feltétel, negatív
tesztek és a kapcsolódó webes state/PKCE/egyszeri-visszatérés védi a határt.
Érintett felhasználói művelet: restore és account-save utáni navigáció.
A teljes valódi JS böngészős fixture-ben fut; minden hálózat mockolt, különösen
a credentials-save jutalmazási endpoint. Jelszókezelő tényleges mentése nem
következik a navigációtesztből. A teszteredmények a futás után kerülnek ide.

## Telepítés és rollback

Előbb a kapcsolódó ai-agent webjavítás, utána ez az egy JS, merge-elt forrásból.
Meglévő exact-file guard: előhash/CAS, mentés, atomikus csere, 0444 fájl és
eredeti könyvtármód, utóhash/HTTP/cache-verzió readback. Air → Mini → hosting
transport; nincs tömeges rsync, PHP-csere, maintenance vagy DB-írás.
Kiegészítő privát másolat: `/home/sharityh/.user-card-release/<release-id>/`,
0700 könyvtár, 0600 fájl. Explicit retention-kivétel: legalább 7 nap; nincs
automatikus törlés. Első rollback csak JS, a telepített hashre feltételesen;
web csak saját hibánál kerül vissza az előzetesen rögzített deploymentre.
A már nyitott lap újratöltésig régi JS-t tarthat.

A megvizsgált régi profilfigyelő jelez, kézi recoveryt kér, nem auto-restore.
Várható JS-forrásparitás eltérését nem nevezzük PASS-nak; watchdog/cron/régi
snapshot nem módosul. A telepítési határon az élő előállapot újraellenőrzendő.

## Kézi UI checklist

- Safari: Saját Sharity → összekötés → szükség esetén restore → automatikus visszatérés.
- Hibás kód/lejárt kör: látható hiba, nincs hurok vagy korábbi profiladat.
- Profil A → B és vissza/előre: csak ellenőrzött aktuális adat jelenhet meg.
- Vállalások/Hatás Körök/FactLens: a saját meglévő visszatérési út maradjon.
- Account-save: navigáció külön ellenőrizhető; éles pontírásos próba tilos.

## Helyi végrehajtási eredmény

`USER_CARD_TEST_DEPENDENCIES_ROOT=<saját-ai-agent-worktree> node --test tests/impactshop-user-card-return.test.mjs`:
19/19 PASS. Meglévő Chrome/Playwright, nincs függőségtelepítés; minden kérés
mockolt. A valós teljes baseline és módosított JS összehasonlítva a 16 negatív/
más-termék esetben, valamint a rejtett account-save return mezőnél.
Új JS SHA256: `d879918d8d80d7e109d6a0c6837921efc0f655d5be0c3c75aa20b42f4d817e01`.
Kapcsolódó web: 39 Vitest eset, typecheck és 154 fájlos NGO Bástya PASS.
Független forrásreview: nincs blokkoló P0/P1/P2; valós diff/tesztforrás vizsgálat,
diff-check és JS-szintaxis exit0. A reviewer nem állított új élő elfogadást.
Push, merge, deploy, éles UI-elfogadás és Safari-próba még nincs.
