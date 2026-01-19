# Coupon & Akció begyűjtési módszerek

Az ImpactShop jelenleg három párhuzamos csatornát használ a kuponok és promóciós tartalmak gyűjtésére. Mindegyik módszer sandbox/draft kimenetet ad, így az adatok a manuális review után kerülhetnek csak éles feedbe.

## 1. Gmail kupon-harvester
**Scriptlánc**
- `scripts/coupon_harvester_pipeline.py` – a tényleges feldolgozó, amely Gmail API-ból olvas, HTML/gmail fixture-t kezel, regex-szel kódot és lejáratot keres, majd CSV-t ír.
- `scripts/coupon-harvester-smoke.sh` – guard/CI wrapper. Gondoskodik a konfig létrehozásáról, a DRY_RUN flagről és a log útvonalakról.

**Konfigurációs részletek**
- `.codex/cron/coupon-harvester-config.json` `gmail` blokkja tartalmazza a credential/token/history fájlokat, a lekérdezést (`(kupon OR coupon OR kedvezmény) newer_than:14d`), a label ID-ket (`INBOX`, `CATEGORY_PROMOTIONS`), a maximális találatszámot és az `allowed_domains` listát.
- A whitelistet a Dognet/CJ feedekből generált `tools/shops_registry.json` táplálja; ez biztosítja, hogy csak jóváhagyott partnerek kerüljenek a feldolgozásba.

**Pipeline lépései**
1. **Authorization + fetch** – Gmail API `users.messages.list` hívása a fenti lekérdezéssel; 14 napos csúszó ablakot használunk, így heti 2 futás lefedi az összes új levelet.
2. **Message parsing** – a pipeline letölti a levelek HTML/plain bodyját, regex-szel keres kuponkódot, kedvezmény %-ot/összeget, lejárati dátumot; a domain alapján slugot rendel (whitelist).
3. **CSV írás** – draft fájlok: `tmp/coupon-harvester/manual_coupons_draft-<timestamp>.csv` kuponok, `shops_manual_draft-<timestamp>.csv` slug mappinghez.
4. **Log + stat** – `.codex/logs/coupon-harvester-smoke.log` tartalmazza a futás idejét, kupon darabszámot, reject okokat.

**Manuális review kapcsolódás**
- A DRY_RUN=1 fixture-futás után manuális ellenőrzés történik; a valid sorok kerülnek át a `../ai-agent/tmp/ingest/raw/manual_coupons.csv` fájlba.
- A Gmail ablak szűkíthető (`newer_than`, label lista), vagy bővíthető több kulcsszóval ha új kampányok jelennek meg.

## 2. Playwright HTML snapshot ("vadász")
**Cél**: whitelistes promóciós oldalak HTML-jének gyors, emberi beavatkozás nélküli letöltése, hogy a smoke teszt DRY_RUN módban is valós tartalomból dolgozzon.

**Összetevők**
- `tools/playwright/harvester-runner.ts`: ESM + TSX alapú futtatható script. Headless Chromiumot indít, betölti a megadott URL-t, opcionális szelektorra vár, majd HTML-t ment.
- `tools/playwright/harvester-config.json(.sample)`: meghatározza az oldalakat (`slug`, `url`), a várakozási időket (`waitForSelector`, `waitAfterLoadMs`) és a kimeneti fájlneveket.
- `package.json`: Playwright + tsx devDependency; scriptek: `playwright:install`, `playwright:harvest`, `playwright:harvest:config`.

**Folyamat**
1. `npm install` – Playwright és tsx install.
2. `npm run playwright:install` – a böngésző binárisok letöltése.
3. Konfig feltöltése valós kampány URL-ekkel (pl. `https://www.notino.hu/akciok/`, `https://www.decathlon.hu/specialis-ajanlatok`).
4. `npm run playwright:harvest:config` – HTML snapshotok mentése `fixtures/coupon-harvester/html/<slug>.html`, összegzés `tmp/coupon-harvester/playwright-summary.json`.
5. `scripts/coupon-harvester-smoke.sh` → `.codex/cron/coupon-harvester-config.json` `html_sources` mezőjébe bekerülnek az új snapshotok, a smoke ezekből a fájlokból olvas.

**Megjegyzés**: a Playwright runner nem értelmezi a HTML-t; a pipeline `html_sources` blokkján keresztül ugyanaz a regex/dedup logika dolgozik rajtuk, mint a Gmail leveleken.

## 3. Manuális review + ingest pipeline (beleértve az Árukeresőt)
**Manuális kupon feed**
- Forrás: Gmail/Playwright draft CSV-k (`tmp/coupon-harvester/manual_coupons_draft-*.csv`).
- Lépés: marketing/ops manuálisan ellenőrzi a sorokat (valódiság, feltételek, lejárat), majd csak a jóváhagyott kódok maradnak.
- Feed: `../ai-agent/tmp/ingest/raw/manual_coupons.csv` – ez kerül be az ingest pipeline-ba.

**Árukereső különlegessége**
- A Playwright runnerrel ellentétben az Árukereső adat a AI-agent repo dedikált ingest moduljából érkezik (`tools/ingest/arukereso.json`, `tmp/ingest/raw/arukereso-promotions.json`).
- A crawler (korábban `tools/playwright/arukereso-runner.ts`) a kampányurlökből JSON-t generál (`tools/out/arukereso-promotions.json`), amelyet az ingest pipeline automatikusan bemásol a `tmp/ingest/raw/` alá.
- Az ingest során a manuális CSV és az Árukereső JSON összeolvad; dedikált reliability mezők számítódnak (pl. `source`, `last_verified`, `success_rate`).
- Az ingest log (`npm run ingest:normalize`, `npm run ingest:sync`) megmutatja, hány manuális és Árukereső rekord került feldolgozásra (pl. „Normalized 2 manual coupons / 43 Árukereső rekord”).

**Pipeline parancsok**
1. `npm run ingest:normalize` (ai-agent repo) – normalizálja a manuális + Árukereső forrásokat, statisztikát ír.
2. `npm run ingest:sync` – átmásolja a `tmp/ingest/raw/` állományokat, újraszámolja a JSON feedeket (`tmp/ingest/manual-coupons.json`, `arukereso.json`).
3. A kimenetekből épül fel az AI agent feed; a manuális + Árukereső ágak külön metaadatokat kapnak (forrás, confidence).

## 4. Heti 2× full futás (összevont cron)
Az end-to-end folyamat egyben futtatható heti 2× alkalommal:
- Script: `.codex/cron/coupon-harvester-full.sh`
- Lépések: Árukereső Playwright → ingest normalize → arukereso.json átmásolás → coupon harvester → export merge.
- Kimenet: `tmp/ingest/export-coupons.csv` (egységes lista).

## Javasolt továbblépések GPT-5 Pro kutatáshoz
1. **Gmail feldolgozás gyorsítása:** új NLP/LLM alapú relevancia-szűrés a bejövő levelekben (pl. kulturális/nyelvi variációk felismerése, automatikus expiry-parzolás).
2. **Playwright kiterjesztése:** több oldal-specifikus scraper modul (React/Next oldalak, infinite scroll kezelése, anti-bot megkerülése), screenshot diff a promóciók változására.
3. **Árukereső integráció:** gépi tanulásos modell a promóciók prioritásához, dedikált reliability scoring (forrás megbízhatóság, coupon success rate).
4. **Pipeline observability:** OpenTelemetry vagy Prometheus metrikák a Gmail/API/Playwright futásokhoz, automatikus alert, ha csökken a találatszám vagy nő a hibaarány.

## AI Agent fejlesztési roadmap
Részletes feladatterv: `docs/ai-agent-roadmap.md` – tartalmazza a Playwright/Gmail/Reliability modulok lépéseit és a `/healthz` bővítését.
