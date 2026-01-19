# 168. Beszélgetés összefoglaló: NGO codes CSV integráció

## Áttekintés
A Google Sheets-ből származó `ngo_codes.csv` (Név → slug) bekerült mind az Impactall, mind az AI Agent repo gyökerébe, és a shop registry mostantól fel is használja, hogy automatikusan hozzárendelje az NGO-kódokat a Gmail/Playwright promókhoz.

## Megfigyelések
- `impactshop-notes/ngo_codes.csv` és `ai-agent/ngo_codes.csv` mindig a Google Sheets export utolsó verzióját tartalmazzák (kérésre `curl -L`-lel letöltve).
- `ai-agent/tools/ingest/shops-registry.ts` kibővült: a `loadNgoOverrides()` már a Shops/CJ feed mellett az `ngo_codes.csv` alapján ismeri fel az NGO neveket, így ha a shop CSV-ben csak a név szerepel, automatikusan slugot kap (`normalizeNgoName`).
- `tools/gmail/promotions-runner.ts` a registry alapján tölti ki a `ngo_slug` mezőt; a Graphiti ingest fallbackje és az Impi prompt fallbackja így már valid slugokra épül.

## Következő lépések
1. Időnként frissítsd a Google Sheetet, majd futtasd le a `curl -sSL <URL> -o ngo_codes.csv` lépést, hogy naprakész maradjon a slug lista.
2. Ha bővül a Playwright/Árukereső ingest, ugyanebből az NGO mappingből kapják a slugot; jelezd, ha ezt is bekössem.
