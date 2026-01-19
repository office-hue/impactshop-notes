# 41. Beszélgetés összefoglaló: AI agent Playwright runner váz

## Áttekintés
Elkészült az első Playwright-alapú Árukereső scraper váz (`ai-agent/tools/playwright/arukereso-runner.ts`), konfig/sample fájllal és npm scripttel. Ez az AI agent backlog T-2.8 lépésének elindítása.

## Fő lépések
- Új `ai-agent/tools/playwright/arukereso-config.sample.json` + `arukereso-runner.ts` – chromium headless futás, slug+URL bejárás, JSON output `tools/out/arukereso-promotions.json` mezőkkel (slug, url, title, headline, discountPercent, scrapedAt).
- `ai-agent/package.json` kapott `playwright:arukereso` scriptet és `playwright` dependency-t, így `npm run playwright:arukereso` futtatható.
- A script támogatja az `ARUKERESO_CONFIG` / `ARUKERESO_OUTPUT` env változókat, arról logol, hány rekord készült.

## Következő lépések
- Finomítsd a Playwright selectort és írd meg a cron/scheduler scriptet (`.codex/cron/arukereso-playwright.sh`).
- Kapcsold be a shops registry merge modult és a deduplikációt, majd haladj tovább a Gmail ingest feladatra.
