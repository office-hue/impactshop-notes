# Publikus marketingmérés — 2026-11-02

A jóváhagyott Sharity-lefedettséghez a meglévő két MU-fájl szerveres és
kliensoldali útvonallistája bővül:

- `/reklamozoknak` → `sharity-app.advertisers`
- `/palyazati-tajekoztato` → `sharity-app.grants`
- `/partner-api` → `sharity-app.partner-api`
- `/impact-challenge` → `sharity-app.impact-challenge`
- `/ngo-guides/jogi-dokumentumok` → meglévő `sharity-app.ngo-guides`

Nem változik a guide/Challenge forrás vagy jogosultság; az új mérési útvonal
nem kapcsol be termékfunkciót. Preview/admin/személyes/fizetési/teszt útvonal
kimarad. A v2 consent és névtelen napi számláló, pontos origin/2 KiB/body és
szerveres credential megmarad. Nincs új raw/session adat vagy függőség.

## Ellenőrzés és státusz

59 PHP ellenőrzés + 5 JS/helyi PHP HTTP-fixture sikeres; független célzott
implementációs review nem talált hibát. Ezek forrásteszt-eredmények.
Merge, exact-file guarded deploy és aktiválási readback még nem történt.
A kanonikus közös terv az ai-agent
`docs/marketing/sharity-coverage-meta-insights-2026-11-02.md` dokumentuma.

## Kiadás és visszaállítás

A gateway route-katalógusa előbb telepítendő. Saját PR, zöld kötelező CI,
friss rövid main-csendablak és merge-SHA readback után csak a két
`impactshop-marketing-measurement.php` / `.js` fájl guarded exact-file
telepítése, célzott előzetes mentéssel és hash-readbackkel.
A mérési kapcsoló kikapcsolása az első rollback; korábbi fájlpár csak saját,
azóta változatlan utóállapotból állítható vissza. Adat-visszatöltés nem kell.

Kézi UI elfogadás: kijelölt nyilvános oldal consent előtt nulla esemény,
engedélyezés után egy v2 kérés, visszavonás után nincs küldés; preview és
bejelentkezett védett oldalak kizártak; meglévő navigáció és űrlap változatlan.
Éles PASS csak collector/MCC visszaolvasással állítható.

Megőrzendő szerzői átadás: `SUBSTACK-HANDOVER.md`, nem végrehajtandó feladat.
