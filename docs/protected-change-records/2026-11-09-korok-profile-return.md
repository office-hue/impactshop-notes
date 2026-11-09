# Körök: Profil-visszatérés — 2026-11-09

Arnold jóváhagyta a közös Profil-visszatérés szűk javítását saját ágon,
célzott teszttel és a meglévő kiadási úton. Alap: `99f9efcd`.
A korábbi JS és PHP completion ugyanazon két pontos visszatérési címet ismeri;
a Körök hiánya miatt a sikeres profilakció a Profil oldalon marad.

Érintett fájlok: `wp-content/mu-plugins/impactshop-identity-panel.js`,
`wp-content/mu-plugins/impactshop-identity-panel.php`; a meglévő source-manifest
két saját hash-kötése és származtatott checksum. Távoli baseline nem változik.

Koherencia / scope: `impactshop-identity-panel.js` megőrzi a pontos
`https://sharity.hu/hatas-korok` returnt a szerver completion-hívásáig;
a PHP ugyanennek a címnek a meglévő session-start hatas-korok intentjét választja.
A szerver aláírt FactLens-kontextusa elsőbbséget élvez; jelen volt, de hibás
kontextus esetén a natív fallback marad. Közös hitelesítés, pontírási és profil-
mentési műveletek nem változnak. Nincs új endpoint, séma, dependency vagy helper.
Ez jóváhagyott legacy touch: külön ráépülő modul helyett két meglévő zárt lista
és a meglévő intentválasztás célzott bővítése szükséges.

Smoke / kockázat / ellenőrzés: hibás vagy ismételt query ne nyisson átirányítást;
account/restore és meglévő Saját Sharity/Vásárlási Segéd út megmaradjon.
A meglévő PHP- és teljes JS-böngésző-fixture mindhárom célpontot és a
FactLens-kontextus elsőbbségét ellenőrzi, szintetikus adaton, éles írás nélkül.
Végleges ellenőrzés: PHP-lint és JS-szintaxis sikeres; 108 szintetikus PHP-
completion eset stubbal, további 108 a helyi kanonikus resolver tényleges
függvényével; 33 teljes JS/PHP Chrome-fixture sikeres. A meglévő profile-v2
statikus teszt és route-path teszt is sikeres. Nincs függőségtelepítés.
Független célzott review: nincs P1/P2 finding; saját 108 PHP-eset és diff-check.
A PHP a beérkező nyers completion-queryt ellenőrzi; az eredeti caller URL-t
a meglévő JS builder előbb normalizálja. Ezt a javítás nem változtatja.
Az exact két forráshash és a manifest-checksum frissül, a többi kötés megmarad.
A protected-touch ellenőrzés sikeres; a smoke-tagek scope-jelölők, nem éles
UI-PASS-ok. Hálózatmentes fixture és helyi resolver nem igazol live-paritást.

Kiadás / rollback: merge után a meglévő exact-file engine; friss host/root/hash,
privát kétfájlos backup, fájlonkénti CAS/atomikus csere, PHP-lint, hash/HTTP és
0444/eredeti szülőmód-readback. PHP először, JS másodszor. Saját változatlan
utóállapotból engine-rollback; DB-visszatöltés nincs. A Mini jelenleg offline,
szerverforrás-paritás és telepítés még nem igazolt. Forrásjelölt, nem éles javítás.

Kézi UI: Körök → Profil → sikeres mentés és helyreállítás után vissza Körökbe;
hibás helyreállítás érthető hibával marad. Saját Sharity / Vásárlási Segéd saját
visszatérése, meglévő FactLens-kontextus elsőbbsége és eltérő böngészőben újratöltés.
Impi OFF; a room-aktiválás külön, változatlan artifact-elfogadás alapján folytatandó.
Megőrzendő hivatkozás: `/Users/bujdosoarnold/ai-agent/SUBSTACK-HANDOVER.md`.
