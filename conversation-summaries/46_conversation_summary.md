# 46. Beszélgetés összefoglaló: AI agent deploy + Shops.csv

## Áttekintés
Feltoltam a friss `ai-agent` buildet a `cp40.ezit.hu` szerverre, lecseréltem a `~/ai-agent-service.js` futtatott kódját az Express alapú API-ra, majd újraindítottam a szolgáltatást. Ezzel párhuzamosan frissült a WP CLI modul és a guard most már PASS. Emellett a legfrissebb `Shops.csv` (App Script/CJ export) bekerült a `ai-agent/tmp/ingest/raw/` mappába, így a normalizer valódi ár-adatokból dolgozhat.

## Fő lépések
- `rsync`-kel átmásoltam a teljes `ai-agent/` mappát a `sharityh@cp40.ezit.hu:~/ai-agent` útvonalra, majd `PATH=$HOME/node-v18/bin:$PATH npm install --omit=dev` parancs futott a szerveren.
- A `~/ai-agent-service.js` most wrapperként tölti be a `dist/apps/api-gateway/src/index.js` modult (ENV-k a `~/ai-agent-data` JSON-okra), a szolgáltatás `node ~/ai-agent-service.js` indítással fut (pid/log frissítve).
- `impactshop-ai-agent-cli.php` átmásolva mind a prod, mind a staging WP környezetbe, így a `wp impactshop ai-agent ping` JSON `features` mezőt is tartalmaz → `.codex/guards/ai-agent-guard.sh` PASS.
- `scp sharityh@cp40.ezit.hu:all_shops.csv ai-agent/tmp/ingest/raw/Shops.csv` → a következő ingest run már a legfrissebb App Script/CJ exportot használja ár-forrásként.

## Következő lépések
- Ha új `arukereso-promotions.json` születik, tedd be a `tmp/ingest/raw/` alá és futtasd a normalizert (`npm run ingest:normalize`), hogy a price map teljes legyen.
