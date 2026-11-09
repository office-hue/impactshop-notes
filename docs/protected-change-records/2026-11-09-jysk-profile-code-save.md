# Profilkód mentése – célzott JYSK incidensjavítás

Felhasználói engedély: „sos javtsd az új fiók létrehozása is könnyedén menjen”.

## Koherencia, kockázat és scope

Protected files touched: wp-content/mu-plugins/impactshop-identity-panel.php,
wp-content/mu-plugins/impactshop-identity-panel.js,
wp-content/mu-plugins/impactshop-identity-panel-intl-overlay.js,
docs/impactshop-guard-hashes.json, docs/impactshop-guard-hashes.sha256,
docs/bastion-guard-status.md. Smoke scope: identity_and_points, deploy_guard.

A közös identity-panel PHP/JS és intl-overlay érintett. A kétablakos reveal
helyett egy navigáció marad. Az üres „Mentés” nem generál új kódot és nem ad
pontot. Új kód csak aktív tulajdonossal és külön megerősítéssel készül.
A v2 kérési szerződés hiányában a generálás még hashírás előtt elutasított.
A mentés normál POST után 303-mal tér vissza; a korábbi save ág változatlan.
A visszatérési URL azonos originű, validált; a nyers query és aláírt kontextus
megmarad, a kétértelmű/dupla vezérlőparaméterek elutasítottak. A pontos JYSK
kontextus aktív profilja külön, fix handshake-hivatkozást is kap.

Változatlan: kódhash, 60 másodperces egyszeri reveal, tulajdonosi ellenőrzés,
nonce/origin, restore, signed bridge, pontok, civil választás, adatbázisséma.
A kód csak az első félnek küldött POST bodyban szerepel, nem URL-ben.
Kockázat: a böngészős jelszókezelő prompt nem garantálható. A már ismert kód
mentése nem állít szerveroldali kódellenőrzést vagy új tulajdonosi hitelesítést.

## Ellenőrzés és kiadás

Igazolt helyi eredmény: 28/28 böngészős eset, benne JYSK-link láthatósági
teszt (aktív/pending/visszavont/elérhetetlen profil és idegen return). PHP
generálás/mentés/reveal/nyers-return szerződésteszt, útvonalteszt, 126 közös
visszatérési eset a tényleges resolverrel, 219 bridge-állítás sikeres.
A független review compact/fordítás/rejtett-link észrevételei javítva.
Ez nem élő jelszókezelő- vagy bejelentkezési elfogadás.

Rollback és kiadás: a három runtime-fájl merge után, meglévő guard-deploy útján,
előhash/privát mentés/utóhash védelemmel adható ki. Először a kompatibilis PHP,
majd JS és overlay; ez nem atomi háromfájlos csere. Idegen driftet nem írunk
felül. Hiba esetén a kódgenerálás saját pause kapcsolója használható; a régi
PHP feltétlen visszaállítása nem biztonságos az új kliens mellett. JS/overlay
csak ellenőrzött elődből állítható vissza; PHP célzott javítást igényelhet.
Nincs adat-, kódhash- vagy pontreset. Forrás WIP; nem merge-elt, nem telepített.

Kézi UI lista: JYSK kapcsolódás → profil → kód megjelenítése → azonosító/kód
mentése → tovább a játékra → bontás/újracsatlakozás. Másik böngészőben mentett
azonosító+kód belépés. A kód újratöltése továbbra sem jelenítheti meg újra.
