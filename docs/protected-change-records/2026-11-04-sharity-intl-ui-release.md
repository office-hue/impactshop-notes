# Sharity felületi kivágás — 2026-11-04

## Jóváhagyott határ, koherencia és kockázat
Saját `release/sharity-intl-ui-20261104` ág, mainalap `e0836f3ee1662db1c3c1bcd2534a107a5e48e8f3`, donor `27688245397c83d8fc499a51e66be042ebee323d`. A gyűjtőág record113 WIP-je és output könyvtára megmarad. A csendablak lezárt; nincs mainmerge vagy deploy.

Protected files touched: `impactshop-action-bar.php`, `impactshop-identity-panel.js`, `impactshop-netflix-shortcodes.php`, `impactshop-ngo-card.php`, `impactshop-ngo-card.js`, `impactshop-sum-pack.php`. Meglévő intl-overlay-k és új display-only `sharity-support-finance-read.php` a szűrt forrásfüggőség. Csak saját fordítás, preferencia és pénzformátum; nincs policy/rangsor-, reward-, ledger-, consent- vagy affiliate-változás. A main marketing-, profil-, guide- és jogi fájljai megmaradtak. Az NGO negyedéves policy-függő rangsorhunk nem kerül át. A már meglévő modulok módosítása kifejezetten jóváhagyott scope; új teljes megoldás helyett a Hermes082–085 draftok és javításaik újrahasználata.

Érintett: dock link/selector, profil dinamikus állapotai, három Netflix-shortcode, Sum Pack, NGO full/compact/widget/share/PDF-bemenet. Fő kockázat: fordítás eltakarhat adatot vagy hibás pénzegységet mutathat; a tesztek az eredeti adatokat, node-okat, linkeket és kéréseket is összehasonlítják. FX a meglévő parser/MNB-függvényt használja; publikus GET csak cache. Nincs DDL, új donation-modul vagy szolgáltatói POST. Az overlay és FX új általános guardot nem kap; a meglévő exact-file kiadás, szintaxis, valódi render- és negatív FX-tesztek adják az arányos védelmet.

## Tényleges ellenőrzés
69 valódi PHP/Chrome-teszt sikeres: az első futásban 68, az egyetlen hiányzó NETFLIX_BASELINE_FILE miatti eset az eredeti forrással külön 1/1. Profil, 16 preferenciapár, blokkolt tárolás, linkadatok, GET-only, saját Elementor-copy, referenciaegységek és lejárat. FX: 150 állítás sikeres. Kilenc forrás szintaktikai ellenőrzése sikeres. A teljes Elementor CSS, valódi PDF és éles 200% zoom még nincs elfogadva.

## Rollout / rollback
Csak ellenőrzött merge-SHA tiszta release-checkoutjából, a meglévő exact-file guarded úton staging, majd ugyanazon byte-ok productionre. Friss host/path/hash, saját érintett fájl mentése, CAS és readback. Az FX-olvasó függőségei és cache/option/cron előállapota ellenőrzendő; explicit enable, egy napi esemény és egy frissítés/GET readback. Hiányos FX esetén eredeti összeg; hibás napi claimet nem törlünk újrapróbáláshoz. Next/Tippjáték fogadó ellenőrzése után WP-küldő. A Tippjáték rész hiánya külön nyitott elfogadás, nem rejtett teljes PASS.

Rollback: új FX-frissítés saját beállítása vissza, csak e release mentett fájljai a telepítő visszaadott rollback-parancsával; más option/cron/adat változatlan. Pont, szavazat, pénzügyi adat és affiliate-átirányítás nincs átírva.

## Smoke / kézi UI checklist
- Impact Shop: kártyasorok, összegző és saját ismertető HU/EN/DE/FR; ne változzon ár, kuponkód, kereskedői név vagy cél-URL.
- Profil: belépés/visszatérés, becenév, NGO-választás, push és előzmény meglévő működése; nyelvváltás ne indítson üzleti POST-ot.
- NGO teljes/beágyazott/megosztott: eredeti HUF és dátumos ≈ érték; link, QR, másolás és PDF-folyamat.
- 320/375 px, desktop, 200% zoom és billentyűzet/fókusz; üres/betöltési/hibaállapot.

Forrásjelölt; nincs merge, telepítés, aktiválás vagy teljes élő elfogadás. Új Hermes/JEV-hívás és új providerköltés nincs; teljes ráfordítás ismeretlen. Csak hat saját meglévő hash frissül külön commitban; idegen pin és guardlogika változatlan.
