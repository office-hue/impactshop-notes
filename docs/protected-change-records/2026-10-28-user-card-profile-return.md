# User Card — szűk profil-visszatérés

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
