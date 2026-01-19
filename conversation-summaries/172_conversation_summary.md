# 172. Beszélgetés összefoglaló: Áresett termék scraper + heti cron

## Áttekintés
A kérés szerint az Árukereső „Áresett termékek” oldaláról kell adatot gyűjtenünk, nincs más hivatalos kampány URL. Ennek megfelelően átállítottam a Playwright-alapú scraptert erre az egy oldalra, és létrehoztam egy heti cron futást.

## Megoldás
- A `ai-agent/tools/playwright/arukereso-config.json` kizárólag a `https://www.arukereso.hu/aresett-termekek/` oldalt listázza; a runner (`tools/playwright/arukereso-runner.ts`) új `extractFromProductBoxes()` helperrel a `.product-box` DOM elemekből olvassa ki a kedvezményt, árat és ajánlatszámot, így 24 rekord került a `tools/out/arukereso-promotions.json` fájlba.
- Új wrapper: `.codex/cron/arukereso-playwright.sh`, amely hetente egyszer (hétfő 04:00) lefut a `.codex/cron/guards.crontab` bejegyzésén keresztül (`.codex/logs/arukereso-playwright.cron.log`), így automatizáltan frissül az áresett snapshot.

## Következő lépések
1. Figyeld a hétfő hajnali cron logot; ha az áresett oldal markupja megváltozik, frissítsd a DOM szelektorokat.
2. Amint szükséges, a feldolgozott rekordokat kösd össze a Gmailben érkező kuponokkal (20% szabály) vagy manuális shop-auditokkal.
