# AI Agent Roadmap – Harvester + Reliability

Az alábbi feladatterv lépésről lépésre végigvezeti a Playwright scraper, Gmail Promotions ingest és Reliability scoring modulok implementációját, valamint a `/healthz` frissítését. A sorrend a függőségek logikáját követi.

## 1. Infrastruktúra előkészítés
1. **Shops registry bővítése**
   - Nyisd meg az `ai-agent/tools/shops_registry.json` fájlt.
   - Minden olyan bejegyzéshez, amelyet az Árukereső Playwright runner támogat, adj hozzá `"arukereso": true` mezőt.
   - Ellenőrző script: `python scripts/diagnostics/check-shops-registry.py --require-flag arukereso` (új, ha nincs, hozz létre) – célja, hogy figyelmeztesen, ha kimarad a flag.
   - Dokumentáld a flag jelentését a `docs/coupon-harvester.md` fájl „Whitelist” szekciójában.
2. **Playwright futtatókörnyezet**
   - Lépj az `ai-agent/` gyökérbe és futtasd `npm install`-t.
   - Telepítsd a böngészőket: `npx playwright install --with-deps`.
   - Állíts be `npm scripts`-et (`playwright:arukereso`) ha még nincs.
3. **Gmail OAuth előkészítés**
   - Google Cloud Console → OAuth Client ID exportálása `ai-agent/secrets/gmail-promotions-credentials.json` néven.
   - Készíts TS CLI-t `tools/gmail/auth.ts` néven, amely`googleapis` klienssel kéri a token-t és `secrets/gmail-promotions-token.json`-ba menti.
   - Teszteld lokálisan: `npm run gmail:auth` → ellenőrizd, hogy a token frissül.

## 2. Playwright scraper integráció (T-2.8)
1. **Runner befejezése**
   - Konfig fájl: `tools/playwright/arukereso-config.json`. Vedd át a docs/Árukereso ... 30–38. sorban lévő URL-eket, slugokat, beállításokat.
   - Ellenőrizd, hogy a runner `tools/out/arukereso-promotions.json` fájlba JSON tömböt ír strukturált mezőkkel (`domain`, `title`, `description`, `discount_percent`, `valid_from`, `valid_until`, `source_url`, `scraped_at`).
   - Adj hozzá request-timeout és retry logikát (`page.goto` → `waitUntil:'domcontentloaded'`, hiba esetén `console.error`).
2. **Cron + log**
   - Hozz létre scriptet: `.codex/cron/arukereso-playwright.sh`:
     ```bash
     #!/usr/bin/env bash
     set -euo pipefail
     cd "$HOME/Documents/GitHub/ai-agent"
     npx ts-node --esm tools/playwright/arukereso-runner.ts \
       --config tools/playwright/arukereso-config.json \
       >> "$HOME/.codex/logs/arukereso-playwright.cron.log" 2>&1
     ```
   - Vedd fel a `scripts/install-guard-cron.sh` template-be: `0 * * * * ... arukereso-playwright.sh`.
3. **AI agent merge modul**
   - Új fájl: `apps/ai-agent-core/src/sources/arukereso.ts`.
   - Feladat: `tools/out/arukereso-promotions.json` beolvasása, DTO validálás, domain/slug mapping `shops_registry` alapján.
   - Dedup kulcs: `slug + coupon_code` vagy `slug + title`. Adj `source:"arukereso_playwright"` mezőt.
   - Exportálj `loadArukeresoPromotions()` függvényt, amit az ingest pipeline hív.
4. **Dispatcher integráció**
   - `apps/api-gateway/src/handlers/chat.ts`: ha a találat az Arukereso feedből jön, a válasz JSON-ban `source:"arukereso_playwright"` jelenjen meg.
   - `/healthz` implementáció: `features` tömbbe add hozzá a `playwright` flaget; ha a runner >24h nem futott, `missing_features` figyelmeztetést adj.

## 3. Gmail Promotions ingest (T-2.9)
1. **Fetcher modul**
   - Hely: `apps/ai-agent-core/src/sources/gmail-promotions.ts`.
   - Feladat: Gmail API `users.messages.list` (`labelIds=Promotions`, `q=kupon/coupon/kedvezmény`). A kapott MIME bodyból HTML-t nyer, JSON-LD promóciót keres.
   - Output: azonos sémájú DTO, mint az Arukereso feed (`source:"gmail_structured"`, `type`: `coupon_code` vagy `sale_event`).
   - Hibakezelés: rate limit retry, üzenet-ID log.
2. **Cron script**
   - `.codex/cron/gmail-promotions-ingest.sh`: naponta 4× fut, `npx ts-node --esm tools/gmail/promotions-runner.ts` hívással.
   - Log: `.codex/logs/gmail-promotions.cron.log`; FAIL esetén Slack webhook.
3. **Merge / dedupe**
   - Ingest pipeline (`tools/ingest/normalizer.ts`): domain+title+expires kulccsal összehasonlítja a manual/Arukereso rekordokkal.
   - Hibás sorok `tmp/gmail-promotions-invalid.json` fájlba kerülnek, review flaggel (`needs_mapping`).
4. **Dispatcher frissítés**
   - `/api/v1/chat/command`: `source:"gmail_structured"` mező.
   - `/healthz`: `features` → `gmail`, plusz `gmail_last_sync` timestamp.

## 4. Reliability scoring (T-2.10)
1. **Adatgyűjtés**
   - Források: `manual_coupons_stats.json` (ingat pipeline output), Playwright/Gmail logok, user feedback (AI agent chat history).
   - Készíts helper scriptet `tools/ingest/collect-stats.ts`, amely Normalized feedből generál `manual_success_rate` és `ai_success_rate` mezőket.
2. **Scoring modul**
   - Új modul `apps/ai-agent-core/src/services/reliability.ts` vagy `scripts/reliability-scoring.ts`.
   - Formula: `score = 0.5*manual_success_rate + 0.3*ai_success_rate + 0.2*age_decay`. Output: `reliability_score (0-1)`, `reliability_label`, `last_verified`, `evidence`.
   - Integráld a normalizer pipeline-ba (minden kupon rekord kapja meg a score mezőt).
3. **Chat integráció**
   - `/api/v1/chat/command`: javaslatlisták (pl. `to_remove`, `highlight`) reliability indoklással.
4. **Dashboard / log**
   - `impactshop-status.md`: új tábla a reliability statisztikáknak (pl. átlag score, kockázatos kupon száma).
   - Periodikus jelentés: `scripts/reliability-report.sh` → `.codex/logs/ai-agent/reliability.log` + Discord summary.
5. **Guard jelzés**
   - `/healthz`: `features` tömb bővítése `reliability` flaggel, `reliability_last_run` timestamp.

## 5. Healthz és konfiguráció frissítése
1. **AI_AGENT_HEALTH_URL**
   - Amint a Playwright/Gmail/Reliability modul élesben fut, állítsd át a `.deploy.*.env` fájlokban az `AI_AGENT_HEALTH_URL` értékét a valódi szolgáltatásra (staging/prod).
2. **/healthz bővítése**
   - JSON példa:
     ```json
     {
       "status": "ok",
       "features": ["playwright", "gmail", "reliability"],
       "missing_features": [],
       "latency_ms": 7
     }
     ```
   - Gondoskodj róla, hogy a `guard-common` log is kiírja a hiányzó flag-eket.
3. **Guard futások**
   - `./.codex/guards/ai-agent-guard.sh` + `~/bin/impactall` futtatása a módosítások után, `notes.md` dokumentációval.

## 6. Ellenőrzés és dokumentáció
1. **Tesztelés**
   - Playwright runner: `npm run playwright:arukereso` (dry-run + cron).
   - Gmail fetcher: mock + valódi token teszt.
   - Reliability pipeline: unit tesztek (berakva a `scripts/tests` alá) + guard futások.
2. **Dokumentálás**
   - `notes.md`, `guard-actions.md`, `docs/ai-agent-harvester-integration.md` frissítése az új modulokkal.
   - Conversation summary a változásokról.
3. **Cron/Guard monitor**
   - Figyeld a `.codex/logs/*.cron.log` és `.codex/logs/guard-events.log` fájlokat, amíg stabilan PASS állapotot nem kapnak.

## 7. Deploy & QA runbook
1. **Branching / release cut**
   - Feature branch → `hardening/prod-guard-baseline` rebase → `npm run build` az `ai-agent/` gyökérben.
   - Generálj release taget: `git tag ai-agent-milestone-T2.10` (példa), push + release notes.
2. **Staging deploy**
   - `./impactctl deploy ai-agent --env=staging` (ha script elérhető) vagy manuális `rsync` a cp40 szerverre (`/home/sharityh/ai-agent-staging`).
   - Futtasd: `ssh sharityh@cp40.ezit.hu "cd ~/ai-agent-staging && npm run start:staging"`.
   - Guardok: `~/bin/impactall`, `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh`.
3. **QA ellenőrzőlista**
   - `/healthz` JSON feature flags.
   - Chat endpoint: `curl -X POST https://<staging>/api/v1/chat/command -d '{"query":"mutass decathlon kupont"}'` – ellenőrizd az új `source` mezőket.
   - Logs: `.codex/logs/arukereso-playwright.cron.log`, `.codex/logs/gmail-promotions.cron.log`, `tmp/gmail-promotions-invalid.json` mérete.
4. **Production deploy**
   - Cache flush: `ssh sharityh@cp40.ezit.hu "/usr/local/bin/wp --path=/home/sharityh/app impactshop cache:flush"` (ha a feedet a WP szolgálja ki).
   - `impactctl deploy ai-agent --env=production` → `aiagentall` + `impactall` megerősítés.
5. **Rollback terv**
   - Mentsd a korábbi release artefaktot `releases/ai-agent-<timestamp>.tar.gz` néven.
   - Ha guard WARN/FAIL, `impactctl resume --release=<előző tag>` + cache flush + guard futások.

## 8. Observability és incident response
1. **Metricák**: Prometheus exporter (`.codex/prometheus/ai-agent-exporter.yaml`) → `ingest_records_total`, `cron_duration_seconds`, `reliability_score_bucket`.
2. **Alerting**: `.codex/docs/automation-schedule.md` frissítése az új cronnal; Alertmanager szabály: ha `ingest_records_total` 0 3 órán át → Discord webhook (`.codex/scripts/alert-ai-agent.sh`).
3. **Incident runbook**
   - Lépések: guard log → log tail (`tail -n 200 .codex/logs/gmail-promotions.cron.log`) → issue mis.`notes.md` P0 blokk → „ai-agent incident” sablon a `conversation-summaries` mappában.
   - Kommunikáció: Slack #impactshop-ops + #ai-agent, hibaazonosító + ETA.
4. **Postmortem**
   - `.codex/retrospectives/ai-agent/<incident-id>.md` formátum: esemény, hatás, RCA, action itemek (owner, due date).
5. **Guard automatizmus**
   - Adj `ai-agent-observability` ellenőrzést a guardboardhoz (`.codex/guards/ai-agent-observability.sh`) – figyeli, hogy a legutóbbi cron log <60 perc.

## 9. Enablement & change management
1. **Tréning**: frissítsd az `Impi Tudásbázis/AI-asszisztens-trening.md` dokumentumot a Playwright/Gmail workflow-val; készíts Loom videót a moderátori review folyamatról.
2. **Stakeholder update**
   - Kétheti newsletter: haladás, KPI, blockers (Hungarian localization).
   - `conversation-summaries/` → dedikált milestone összefoglalók (pl. `140_conversation_summary.md` – „Playwright + Gmail kész”).
3. **Adat-hozzáférés**
   - Biztosítsd, hogy minden ops kolléga olvasási jogot kap az `ai-agent` repo-ra és a `.codex/logs` releváns fájljaira.
4. **Future backlog**
   - Gyűjtsd a merchant/NGO kéréseket a `docs/ai-agent-backlog.md` fájlban (feature idea, üzleti hatás, effort becslés).
   - Prioritási meeting: heti 30 perc, roadmap update + T-shirt sizing.

## 10. Haladó memória, multi-agent és hang fejlesztések
1. **Graph-alapú memória PoC**
   - Telepíts Graphiti + Neo4j környezetet (docker-compose). Ingest source: Impi beszélgetések, Gmail promók, kupon statisztikák.
   - Képezd le a csomópontokat (`person`, `ngo`, `promotion`, `event`) és az időbélyegzett éleket (`SUPPORTED`, `MENTIONED`, `EXPIRES_AT`).
   - Hibrid kereső API: embedding + gráfbejárás → `/api/v1/context/memory` endpoint, amit az Impi prompt builder hív.
   - Következő akció: `ngo_codes.csv` alapján slugot adj a dedikált promóciós feedekhez (Gmail/Playwright), hogy a Graphiti aggregáció és fallback ajánlás valós toplistát mutasson.
2. **Memória-stack alternatívák**
   - Zep Memory telepítése (SOC2 mód), `zep-agent` service integráció a LangGraph flow-ba.
   - Letta/Mem0 kipróbálása kisebb deployon (pl. Impi belső preview). Dokumentáld az összehasonlítást `docs/ai-agent-backlog.md`-ben.
3. **LangGraph orchestráció**
   - `ai-agent/apps/agent-graph` modul létrehozása: csomópontok = ingest, moderation, personalization, voice I/O.
   - CrewAI + Autogen adapterek (pl. Notion export, Zoom summary). Feature flag gal aktiválható.
4. **Hang stack**
   - STT baseline: Wav2Vec2/NeMo ASR docker image (`ai-agent/services/stt`).
   - TTS baseline: Chatterbox TTS + Orpheus multi-lingual. Hasonlítsd össze a WER/MOS értékeket `docs/ai-agent-backlog.md` táblázatban.
   - Voice pipeline: LiveKit (RTC), Pipecat (ASR↔LLM↔TTS), Milvus (vector memória). Kapcsold a LangGraph orchestrátorhoz.
5. **Observability & feedback**
   - Langfuse telepítés: `LANGFUSE_SERVER_URL`, `LANGFUSE_SERVER_API_KEY` env. Tracking: token, költség, latency, hallucináció flag.
   - Feedback UI: Impi „hasznos/nem hasznos” esemény → Langfuse + reliability dataset.
6. **Licencelés és compliance**
   - GDPR-DPIA a hangadatokra; consent flow a TTS/STT modul bekapcsolásakor.
   - Dokumentált fallback (Whisper offline, Vosk CPU) ha cloud API nem engedélyezett.

## 11. Komplex üzleti dokumentum (Excel/PDF) pipeline
### 11.1 Ingest / normalizálás
1. `tools/excel/extract-runner.ts`
   - `exceljs` alap; minden munkalapot JSON-ra bont (`rows[]`, `headers[]`, `value`, `formula`).
   - Output: `tmp/ingest/excel/<docId>/<sheet>.json` + `metadata.json` (deviza, időszak, pivot flag).
2. Pivot normalizer (`tools/excel/pivot-normalizer.ts`)
   - Pivot táblát laposít `[{dimensions..., metric, value}]` formára; számolja a varianciákat.
   - Unit tesztek a `fixtures/excel/*` mintafájlokra.
3. PDF táblázat OCR (`tools/pdf/table-ocr.ts`)
   - Tabula/Camelot + Vision text fallback; kimenet: `tables[]`, `paragraphs[]`, `footnotes[]` (apró betűs rész).
4. CLI smoke: `npm run document:ingest --file fixtures/excel/business-plan.xlsx` → logolja a generált JSON-t.

### 11.2 LangGraph / Core Agent
1. Új state mezők: `attachments`, `structuredDocuments`, `documentInsights`, `ingestWarnings`.
2. `documentLoaderNode`
   - Bemenet: `attachments` (URL + MIME). Meghívja az ingest pipeline-t; cache `.codex/cache/document-ingest.json`.
   - Hiba esetén `ingestWarnings.push({docId, reason})` és guard jelzés.
3. `analysisNode`
   - LLM prompt: „Elemezd az Excel/PDF dokumentum fő költségpontjait, hivatkozz cellákra/munkalapokra.”
   - Output: `documentInsights[]` (summary, key_metrics, references mint `Sheet1!B12`).
4. Graphiti memória frissítés: `document_insight` csomópont, TTL 30 nap, `origin_document` mező.

### 11.3 Impi / UI
1. REST API: `/api/v1/chat/impi` bővítése `attachments` mezővel (S3/Drive link, MIME, checksum). Guard: max 3 fájl, 25MB/darab, engedélyezett MIME = Excel/PDF.
2. Impi widget: drag&drop file upload, progress bar, Attachment list (név, méret, törlés gomb).
3. Válaszkimenet: „Források” blokk, cella/munkalap hivatkozások, letölthető JSON snapshot (`document_insights.json`).
4. Admin „Dokumentum insight” oldal: listázza az utolsó 10 feltöltött dokumentumot, mutatja a generált insightokat és hibákat.

### 11.4 Guard + observability
1. Új guard: `document-ingest`
   - Cron script `scripts/document-ingest-smoke.sh` (mintadokumentum). Log: `.codex/logs/document-ingest.log`.
   - Guard output: ingest idő, hibás lapok, figyelmeztetés ha >5 perc vagy missing sheet.
2. Telemetry panelek: ingest success rate, átlagos lap/s (Grafana / Langfuse event).
3. Alerting: Slack #ai-agent ha két egymást követő ingest FAIL.

### 11.5 Enablement
1. Tudásközpont cikk: „Excel/PDF feltöltési útmutató” – screenshotok az Impi widgetről + troubleshooting.
2. Moderátor workshop: hogyan értelmezzük a generált `documentInsights` mezőt; review checklist (valid számok, referenciák).
3. Release checklist update: dokumentum-ingest guard futtatása kötelező nagyobb rollout előtt.

Ezekkel a kiegészítésekkel az AI Agent roadmap nemcsak a technikai implementációt, hanem a release, observability és enablement lépéseket is lefedi, biztosítva, hogy a Playwright + Gmail + Reliability fejlesztések stabilan, auditálhatóan kerüljenek produkcióba.
