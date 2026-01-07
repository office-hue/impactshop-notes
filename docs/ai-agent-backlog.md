# AI Agent Backlog – Playwright, Gmail Promotions, Reliability

Forrás: `docs/Árukereso kupon vadász.md` (lásd 30–120. sorok), `docs/coupon-harvester.md`, valamint az új `docs/ai-agent-harvester-integration.md` integrációs terv + sprint backlog (`.codex/sprint-tasks/S2.md`). Az alábbi lépések adják a T-2.8–T-2.10 feladatok részletes kidolgozását.

## T-2.8 – Playwright scraping (Árukereső kampányok)
- [ ] **Playwright runner**  
  - Script helye: `ai-agent/tools/playwright/arukereso-runner.ts`.  
  - Bemenet: `shops_registry.json` + `docs/Árukereso kupon vadász.md` 30–38. sora szerinti kampány URL-ek.  
  - Futtatás: `npm run playwright:arukereso` → `tools/out/arukereso-promotions.json`.  
  - Output mezők: `domain`, `title`, `discount_percent`, `description`, `valid_from`, `valid_until`, `source_url`, `scraped_at`.  
  - Cron: `.codex/cron/arukereso-playwright.sh` (óránként), log: `.codex/logs/arukereso-playwright.cron.log`.
- [ ] **Registry jelölés & merge**  
  - Új field: `"arukereso": true` a shops registry elemeiben → AI agent tudja, hogy a scraper adatokat is fésülje.  
  - Merge modul: `apps/ai-agent-core/src/sources/arukereso.ts` – JSON feed → belső kupon DTO.  
  - Validation: duplicate detection (domain+title hash) + expiry normalizálás.
- [ ] **Dispatcher integráció**  
  - `/api/v1/chat/command` válaszban `source:"arukereso_playwright"` ha innen érkező ajánlatot javasol.  
  - Health jelzés: `/healthz` payload `features` tömbjébe `playwright` flag.

## T-2.9 – Gmail Promotions ingest
- [ ] **OAuth + token store**  
  - Token file: `ai-agent/secrets/gmail-promotions-token.json`.  
  - Scope: `https://www.googleapis.com/auth/gmail.readonly`.  
  - CLI helper: `npm run gmail:auth` (TS script a `googleapis` klienssel).  
- [ ] **Promotions fetcher**  
  - Modul: `apps/ai-agent-core/src/sources/gmail-promotions.ts`.  
  - Lekérés: Gmail API → Promotions label → MIME parsing → JSON-LD (`gmail_structured`).  
  - Output DTO: megegyezik az Árukereső scraper sémájával, `source:"gmail_structured"`, `type` = `coupon_code` / `sale_event`.  
  - Cron script: `.codex/cron/gmail-promotions-ingest.sh` (naponta 4×), log: `.codex/logs/gmail-promotions.cron.log`, FAIL → Discord WARN.
- [ ] **Merge / dedupe**  
  - Ha ugyanaz a domain+title páros már létezik Playwright feedből, reliability score alapján döntsünk elsőbbségről.  
  - Promotions ingest után invalid recordokat írjuk ~`tmp/gmail-promotions-invalid.json`-ba manuális review-ra.

## T-2.10 – Reliability scoring
- [ ] **Scoring pipeline**: `manual_coupons_stats.json` + AI agent visszajelzések alapján számíts `reliability_score`, `last_verified` mezőt (74–105. sor).  
- [ ] **AI feedback**: `/api/v1/chat/command` válaszban jelenjen meg, hogy mely kuponokat érdemes törölni / kiemelni.  
- [ ] **Dashboard**: bővítsd a meglévő kupon statisztika táblát (impactshop-status.md) az AI score mezővel.  
- [ ] **Cleanup javaslat**: napi AI jelentés a duplikátumokról, hibás kódokról; log: `.codex/logs/ai-agent/reliability.log`.
- [ ] **Pipeline részletek**  
  - Források: manual stats, Playwright feed, Gmail feed, user feedback (AI agent + `manual_coupons_stats.json`).  
  - Formula: reliability = weighted avg (`manual_success_rate`, `ai_success_rate`, `age_decay`).  
  - Output mezők: `reliability_score` (0–1), `reliability_label` (Super / Stable / Risky), `last_verified`, `evidence` (utolsó teszt).  
- [ ] **Agent integráció**  
  - `/api/v1/chat/command` → javaslat lista (`to_remove`, `highlight`).  
  - Periódikus jelentés: `.codex/logs/ai-agent/reliability.log` + Discord summary.  
  - `/healthz` payload `features` tömbjébe `reliability` flag, plusz `reliability_last_run` timestamp.

## Post-launch / Healthz frissítés
- Amint a Playwright + Gmail + scoring modulok élesben futnak, cseréld az `AI_AGENT_HEALTH_URL` értékeit a tényleges szolgáltatás URI-jára (staging/prod `.deploy.*.env`).
- Bővítsd a `/healthz` payloadot `features: ["playwright","gmail","reliability"]` mezővel, hogy a guard azonnal lássa, mely modulok aktívak.

## Következő lépések
1. Sprint S2 meeting: owner/követelmények egyeztetése, ETA-k frissítése a `.codex/sprint-tasks/S2.md` fájlban.  
2. AI agent service: a fenti funkciók beépítése után frissítsd a `/healthz` payloadot (pl. `features: ["playwright", "gmail", "reliability"]`).  
3. Guard: amint a valódi AI agent fut, `AI_AGENT_HEALTH_URL`-t cseréld az új szolgáltatás URI-jára.
