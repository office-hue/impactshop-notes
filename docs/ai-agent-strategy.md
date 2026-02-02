# Enhancing Sharity’s Coupon & Deal Aggregator with Advanced AI Solutions

## Bevezetés
Sharity jelenlegi kupon- és akció-aggregátor technológiája sok manuális lépést igényel, így lassan reagál az új promóciókra. A cél egy teljesen automatizált (de emberi jóváhagyással kiegészített) pipeline, amely minden releváns forrásból begyűjti a deal-eket, majd gyors manuális review után publikálja őket a Impact Shop platformon és az Impi AI tanácsadó számára. Az alábbi terv egyesíti a meglévő technológiai roadmapet és a GPT‑5 Pro kutatási eredményeit.

**Koherencia megjegyzés (2026-02-01):** Ez stratégiai terv. A VS Code/IDE + MCP/Copilot SDK irány az `docs/impi-copilot-sdk-migration-plan.md` dokumentumban van részletezve; az itt szereplő implementációs lépések részben előzetesek, és a Copilot SDK migrációval összehangolva végrehajtandók.

## 1. Jelenlegi módszerek (baseline)
| Csatorna | Leírás | Fő eszközök | KPI-k / teljesítmény |
| --- | --- | --- | --- |
| Web scraping / Playwright vadászat | Whitelistelt webshopok „Akció”, „Sale” oldalainak letöltése, HTML snapshot mentése, regex/NLP alapú feldolgozás | `tools/playwright/harvester-runner.ts`, `fixtures/coupon-harvester/html/*.html`, `tmp/coupon-harvester/playwright-summary.json` | ~2 snapshot/óra, manuális értelmezés 5 perc/sor |
| Gmail kupon-harvester | `(kupon OR coupon OR kedvezmény) newer_than:14d` lekérdezés Gmail API-val, regex/NLP kódkivonás | `scripts/coupon_harvester_pipeline.py`, `.codex/cron/coupon-harvester-config.json` | 24 kupon/draft futás, ~60% reject, latency 2-3 óra |
| Affiliate feedek (Dognet, CJ) | Feed CSV/JSON import, whitelist generálás, allowed domain lista | `scripts/generate_shops_whitelist.py`, `tools/shops_registry.json` | 102 whitelist domain, 0 hamis pozitív |
| Árukereső Playwright crawler | Kiemelt Árukereső kampány oldalak Playwright/Selenium alapú lekérése, JSON feed | `ai-agent/tools/playwright/arukereso-runner.ts`, `tools/out/arukereso-promotions.json` | 43 promó/nap, ~15 perc latency |
| Manuális review + ingest | Draft CSV-k kézi ellenőrzése, ingest pipeline normalizálás | `../ai-agent/tmp/ingest/raw/manual_coupons.csv`, `npm run ingest:*` | 2 valid kupon legutóbbi review-ban, 30 perc jóváhagyási ciklus |

**Hiányosságok**: részleges lefedettség (csak whitelistelt oldalak); manual approval kötelező; pipeline latency 6-12 óra; hamis pozitív arány ~40%; throughput ~10-12 publikált kupon/nap; Impi advisor csak limitált adatot lát; KPI dashboard hiányzik.

**Aktuális pipeline folyamata**
```
Forrás (Playwright/Gmail/Affiliate/Árukereső) → Draft CSV/JSON → Moderációs dashboard → Ingest normalizer → AI agent feed → Impact Shop & Impi publikáció
```

## 2. Új adatforrások & lefedettség
| Forrástípus | Technika | Megvalósítási ötletek | Előnyök |
| --- | --- | --- | --- |
| Retailer site monitor | Dedikált crawler + Schema.org Offer parse | Scrapy spider lista (kulcsszavas URL-ek), `offers` JSON-LD feldolgozása; change detection (hash) | Legfrissebb info, közvetlen forrás |
| Marketplace API | Amazon Gold Box RSS, eBay Finding API, AliExpress affiliate feed | API kulcsok, rate limit kezelése, JSON merge modul | Struktúrált adatok, kevés parsing |
| Affiliate network bővítés | Awin, ShareASale, Rakuten, Impact feedek | Unified feed normalizer, program ID dedup, reliability flag | Legális, megbízható adatok |
| Versenytárs monitor | RSS / change detect Slickdeals, RetailMeNot, hazai kuponoldalak | `watchtower` script → ha új deal jelenik meg náluk, trigger újracrawl | Biztonsági háló, coverage ellenőrzés |
| Social / newsletter | Twitter API (store account + #kupon), Facebook Graph, dedikált Gmail account | NLP extrakció social bejegyzésekből, newsletter parser (LLM) | Flash sale detektálás, exkluzív kódok |
| Offline / OCR | Akciós újságok (akciosujsag.hu), mobil push | OCR (Tesseract/Google Vision) + NLP, push notification sniffing | Szupermarket akciók, offline promók |
| Kereskedői API / beküldőfelület | Partner portál, hitelesített REST/GraphQL endpoint | Merchant login + `POST /merchant/deals` JSON input, moderation queue | Hitelesített, jogilag biztos adat, partner engagement |
| Árfigyelő szolgáltatások | CamelCamelCamel, Kifutó.hu, Keepa API | Price drop webhook → `price_drop` típusú deal, threshold rule | „Rejtett” kedvezmények azonnali felismerése |
| Közösségi kuponszolgáltatók | Honey, Slickdeals, RetailMeNot, hazai kuponoldalak | API/RSS monitor, coverage diff riport | Versenytárs figyelés, lefedettség bővítése |

## 3. Kuponfelismerés & érvényesség-ellenőrzés
A megbízhatóság növeléséhez több védelmi réteg szükséges – a cél, hogy a Sharity ne mások statikus listáit másolja, hanem önjavító ökoszisztémát működtessen:

1. **Multimodális felismerés**: a HTML/OCR + regex helyett utasításalapú LLM-ek (Gemini Flash, GPT-4o) dolgozzák fel a snapshotokat, képesek bannerekből és több nyelvű szövegekből `coupon_code/discount/expiry` mezőket strukturáltan visszaadni. A normalizer `source_meta` mezői jelzik, ha multimodális kinyerés történt.
2. **Automatizált kódbetöltés tesztrendeléssel**: headless Playwright kosarakban a kódot valós checkout-szimulációval próbáljuk ki (álnévvel, sandbox fizetéssel). A válaszból a rendszer eldönti, hogy működik-e, és frissíti a státuszt (`valid`, `expired`, `conditions`), miközben a megbízhatósági pontszám is változik.
3. **8112 valós idejű validáció**: ahol partner támogatja, a Coupon Bureau 8112 feed valós idejű státuszt ad (lejárt, beváltott, felfüggesztett). A partnereket érdemes ösztönözni a csatlakozásra, mert így a Sharity felületén feltüntethető az utolsó sikeres validáció időpontja.
4. **Közösségi visszajelzés**: a SimplyCodes mintájára gamifikált hüvelykujj/részletes teszt UI-t építünk az Impact Shop felületre. A visszajelzés azonnal csökkenti/növeli a `reliability_score` értéket, és a felhasználók pontot kapnak a tesztelésért.
5. **Többszintű ellenőrzési modell**: (i) gépi előszűrés (regex + RL-crawler + LLM), (ii) heti moderátori audit, (iii) publikus „Nem működik” gomb → mindegyik lépés külön szabályt kap. Csak a 0,8 feletti score-ral rendelkező kód publikálódik.
6. **Technikai szabályok beolvasása**: ahol a feed metaadatot ad (min. rendelési összeg, lokáció, user-limit), a Sharity rule engine ellenőrzi ezeket. Ha nem teljesül a feltétel, a kupon automatikusan letiltásra vagy review-ra kerül.
7. **Anomáliadetektálás**: egyszerű ML/rule engine figyeli a kuponhasználatot (azonos IP-ről sok próbálkozás, kosárérték anomáliák, hirtelen beváltásnövekedés). A gyanús minták `fraud_flag` mezőt kapnak, és a guard pipeline azonnal riaszt.
8. **Metaadatok és láthatóság**: minden kódnál jelenjen meg, mikor találtuk (`discovered_at`), mikor teszteltük (`validated_at`), milyen a státusza (`validation_status`), hány user próbálta, és melyik réteg (LLM/teszt/publikus feedback) erősítette meg. Ez növeli a bizalmat és motiválja a visszajelzést.
9. **Merchant API integráció**: ahol lehetséges, közvetlen API-kapcsolatot építünk (affiliate hálózatok, 8112, saját merchant portal). A státuszváltozásokat valós időben húzzuk be (`coupon_status webhook`).

Ezek a rétegek a roadmap T-2.11–T-2.14 feladatait adják (multimodális extractor, Playwright code tester, közösségi feedback modul, rule engine), és a reliability guard futtatásáig mindegyiknek zöld állapotot kell produkálnia.

**Implementációs megjegyzések**
1. Retailer monitor: hozz létre `scrapy` projektet `impactshop_crawler` néven; domainenként `spider_settings.json` tárolja a selectorokat + crawl intervalt; kimenet S3/Blob `raw_html` + `offers.json`.
2. Marketplace API-k: centralizált `marketplace_ingest.py` modul; rate-limit aware HTTP client; canonical schema `marketplace_deal` (store_id, sku, discount_type, source).
3. Affiliate bővítés: `feed_registry.yaml` feljegyzi a hálózatot, auth adatokat, fetch gyakoriságot; unify-szolgáltatás dedupolja a program ID alapján; reliability flag = hálózat SLA.
4. Versenytárs monitor: `watchtower` job `competitor_feed_watch.sh` → ha hiányzó deal ID-t talál, trigger `priority-crawl` queue.
5. Social/newsletter: dedikált Gmail account `deals@impactshop.hu`, `tools/social/listener.ts` a Twitter/Facebook API-hoz; LLM summarizer a newsletterből JSON-t generál.
6. Offline/OCR: pipeline `tools/ocr/leaflet-runner.ts` – PDF → image → OCR → NLP (kupon kinyerés), meta: leaflet forrás, érvényesség.
7. Merchant API: `portal.sharity.hu/merchant` modul, OAuth2 + rate limiting, moderation queue integráció, SLA ellenőrzés.
8. Árfigyelő integráció: `price_watch.yaml` store+sku mapping, threshold szabály; webhook → `price_drop_ingest.ts` create `sale_event` record.
9. Közösségi kuponszolgáltatók: licenc/ToS review, API kulcsok, coverage diff riport → coverage dashboardon látszik, hol maradtunk le.

## 3. AI-alapú adatfeldolgozás
**3.1 Többnyelvű NLP/LLM extraction**
- Input: Playwright snapshot HTML, Gmail levelek, social posztok, merchant API free-text mezők.
- Modell stack: jelenleg OpenAI GPT-4o / GPT-4o mini (HU/EN/DE/RO); Claude fallback a Copilot SDK migráció után tervezett.
- Prompt példa: „Extract coupon codes, discount value, eligibility, expiry from the following HTML. Answer JSON.”
- Fine-tuning dataset: moderátor által jóváhagyott promók + cégspecifikus kifejezések.
- Generatív összefoglalók: LLM rövid, marketingbarát leírást készít, amely Impi és felhasználók számára érthető.
- Labeling tool: Moderációs dashboardban `Correct/Incorrect/Expired` gombok → automatikus training dataset.

**3.2 Computer vision / OCR**
- Bannerek → screenshot pipeline (`tools/cv/capture-banners.ts`), OCR (Tesseract/Google Vision/AWS Textract).
- Sale badge detektor: YOLOv8/Detectron2 a -XX% / „Sale” jelvényekre, discount meghatározása.
- Social média képek (Instagram, Facebook) feldolgozása a social ingesten keresztül.

**3.3 RL / navigációs agent**
- Selenium + RLlib/Stable Baselines Agent, action: click, scroll, search, fill form.
- Reward: coupon text detektálása, conversion = +1.
- Use-case: dinamikus menük, rejtett promó oldalak, SPA-k.
- **Fallback**: szabály-alapú menü feltérképező script, ha RL agent no reward/hibás.

**3.4 ML alapú tisztítás**
- Dedup: SentenceTransformer (e5-large) embedding + cosine similarity >0.9 = duplikátum.
- Invalidity predikció: Gradient Boosting modell (jellemzők: domain, code pattern, discount %, expiry proximity, forrás megbízhatóság), output = valószínűség.
- Popularity scoring: historical CTR + user feedback → LightGBM regresszió.

**3.5 Knowledge graph / semantic search**
- Graph DB (Neo4j) entitások: Store, Category, Coupon, Condition; edge: `APPLIES_TO`, `BELONGS_TO`.
- Vector index (Pinecone/Weaviate) a leírásokra, Impi keresésnél RAG pipeline.

## 4. Tracking & Compliance
1. **Hibrid crawling framework**
   - Scrapy cluster alap pipeline statikus oldalakra; Selenium/Puppeteer modul JS-heavy lapokra.
   - Scheduler: Celery/Chronograf → domain prioritás, hibajelentések.
   - Exception handling: Slack alert + auto fallback (LLM extraction).
2. **Change detection & diffing**
   - Hash-alapú snapshot (Diffy, Watchtower). Csak változáskor indít scrapert.
   - Visual diff screenshotokra (Percy). Kiemeli új bannereket.
3. **Proxy & throttling**
   - Rotáló proxy pool (BrightData, ScraperAPI). Domain-enkénti concurrency limit.
   - Randomized delay, UA rotation (desktop/mobile mix). robots.txt tisztelet + opt-in whitelisting partnereknek.
   - Részletes robots.txt elemző + domain-specifikus crawl schedule.
   - SPA/végtelen görgetés esetén headless böngészőhöz "browser fingerprint" rotáció.
4. **Biztonság**
   - Docker-sandboxolt headless böngészők, AppArmor/SELinux policy.
   - HTML sanitization, outbound network restricted.
5. **Jogi megfelelés**
   - GDPR / ePrivacy: user profiling csak explicit hozzájárulással (Art. 7), DPIA + adatminimalizálás, cookie CMP (IAB TCF v2).
   - DSA/DMA + FTC elvek: transzparens algoritmus döntések, affiliate disclosure („jutalékot kaphatunk” jelölés), kereskedői kompenzáció feltüntetése, panaszkezelés.
   - ToS audit: scraping csak publikus adatokra, preferált API/Feed; kereskedői megkeresés hivatalos hozzáférésért.
6. **Data quality ellenőrzés**
   - Expiry check: cron, ami lejárt deal-eket archivál.
   - Currency/format normalizálás, headless checkout teszt (korlátozott set).
7. **Retention & log policy (koherencia):**
   - PII és query logolás/retenció összhangban a Copilot SDK migrációs tervvel (redakció + limitált retention).

## 5. Ember a loop-ban
1. **Moderációs dashboard**
   - Funkciók: bulk approve/reject, confidence score, AI summary.
   - Integráció: `impactshop-notes/tools/moderation-dashboard.md` (később UI/figma).
2. **Active learning**
   - Minden manuális döntés logolása → `ai-agent/tools/feedback-dataset.jsonl`.
   - Havonta modell finomhangolása (LLM prompt, classification model).
3. **Komplex promók kezelése**
   - Tiered/stacking akciók UI szerkesztése, condition field kitöltése.
4. **Graduated trust**
   - Reliability score ≥0.8 → automata publikálás + post publish monitor.
   - <0.8 → manuális jóváhagyás kötelező.

## 6. Impi AI Advisor
1. **Szemantikus index**: deals → vector embeddings (SentenceTransformers), tárolás Pinecone/Weaviate, fast similarity search.
2. **Retrieval-Augmented Generation**: Impi először vector search + strukturált filter segítségével lekérdezi a releváns deal-eket, majd az OpenAI (GPT-4 Turbo/4o mini) modell generál választ linkkel, feltételekkel; Claude integráció későbbi mérföldkő. GDPR: user data opt-in, audit trail, explainable recommendation.
3. **Recsys**: explicit feedback (click, add-to-cart), implicit behavior, collaborative filtering (Matrix factorization) + content features (category embeddings).
4. **Voice/multimodális**: Whisper STT + Azure TTS layer optional.

## 7. Pre-launch checklist
1. E2E stage teszt (összevetés kézi mintával, missing coverage analysis).
2. Load test (Black Friday szimuláció, queue méretezés).
3. Freshness SLA (kritikus források <6h latency; affiliate feedek <1h).
4. Legal review (ToS, affiliate disclosure, GDPR CMP, user opt-in mechanizmus).
5. UI/UX finomhang (deal listing, Impi chat, moderation panel) + closed beta.
6. Monitoring/analytics (Grafana dashboard, scraper success rate, user CTR).

## 8. Implementációs roadmap (részletes feladatlista)
### 8.1 Infrastruktúra
1. `tools/shops_registry.json` → `"arukereso": true` flag + diag script.
2. `ai-agent/package.json` Playwright dependency audit + `npx playwright install` (ai-agent repo).
3. Gmail OAuth CLI (`tools/gmail/auth.ts`), token storage.

### 8.2 Playwright scraper (T-2.8)
1. `tools/playwright/arukereso-config.json` → docs URL-ek.
2. Runner error-handling, JSON schema tisztázás.
3. Cron `.codex/cron/arukereso-playwright.sh` + log.
4. Merge modul `apps/ai-agent-core/src/sources/arukereso.ts` (DTO, dedup, `source:"arukereso_playwright"`).
5. Dispatcher + `/healthz` `playwright` flag.

### 8.3 Gmail Promotions ingest (T-2.9)
1. `apps/ai-agent-core/src/sources/gmail-promotions.ts` – Gmail API + JSON-LD parser.
2. Cron `.codex/cron/gmail-promotions-ingest.sh` + log.
3. Merge/dedupe a normalizerben (`gmail_structured` source).
4. Dispatcher `source:"gmail_structured"`, `/healthz` `gmail` flag.
5. `GMAIL_PERSONAL_RECIPIENTS` szűrő: a `tools/gmail/promotions-runner.ts` minden olyan levelet átugrik, amelynek címzettje kizárólag személyes (egyszer használatos) cím – ezek nem kerülhetnek a publikus feedbe, a logban `🔒` jelöléssel jelennek meg.

### 8.4 Reliability scoring (T-2.10)
1. Statisztika gyűjtő script (`tools/ingest/collect-stats.ts`).
2. Scoring modul (`apps/ai-agent-core/src/services/reliability.ts`).
3. Chat javaslatok (remove/highlight mezők).
4. Dashboard + log (`impactshop-status.md`, `.codex/logs/ai-agent/reliability.log`).
5. `/healthz` `reliability` flag + `last_run` timestamp.

### 8.5 Healthz frissítés
1. `.deploy.*.env` `AI_AGENT_HEALTH_URL` → valós szolgáltatás.
2. `/healthz` JSON kiegészítése `features` / `missing_features` mezőkkel.
3. Guard futások (`./.codex/guards/ai-agent-guard.sh`, `~/bin/impactall`).

## 9. Dokumentáció & monitoring
- `docs/coupon-harvester.md`, `docs/ai-agent-harvester-integration.md`, `guard-actions.md` frissítése.
- Conversation summary minden mérföldkőnél.
- `notes.md` napló: futások, fail logok, emberi review.
- Monitoring: `.codex/logs/*.cron.log`, `.codex/logs/guard-events.log`, analytics dashboard.
- Új `.codex/scripts/ai-agent-health-report.sh` gyorsjelentés: az utolsó guard eseményt, reliability logot és cron állapotot (AI Agent + Gmail Promotions + Playwright) egy parancsban mutatja (`.codex/scripts/ai-agent-health-report.sh`).

## 10. KPI-k & mérőszámok
| KPI | Definíció | Cél |
| --- | --- | --- |
| Coupon precision/recall | Valódi vs. hamis találatok aránya (forrásonként) | >90% precision, >80% recall 3 hónapon belül |
| Coverage ratio | Monitorozott webshopok aránya az összes releváns bolthoz képest | 95% HU webshop, 80% nemzetközi |
| Detection→publish latency | Forrás és publikálás közötti idő | <3 óra kritikus forrásokra |
| Moderátori munkaidő | Egy kuponra jutó manuális idő | <5 perc/kupon, majd <2 perc |
| CTR / redemption rate | Felhasználói kattintás és beváltás aránya | +20% növekedés 6 hónap alatt |

## 11. Kockázatelemzés & etika
| Kockázat | Hatás | Mitigation |
| --- | --- | --- |
| Adatvédelmi incidens | GDPR bírság, reputációs kár | DPIA, titkosított tárolás, role-based access, incident response plan |
| Model bias | Torz ajánlások, kereskedői panasz | Fairness audit, manuális override, több forrás |
| Scraping tiltás | Jogi vita, IP blokkolás | robots.txt tisztelet, partneri API, throttling |
| Hibás promó publikálása | Felhasználói elégedetlenség, kereskedői vita | Moderáció, gyors rollback, kereskedői feedback csatorna |
| Etikátlan tartalom | Jogszabálysértés | Policy enforcement, moderátori képzés |

## 12. Felhasználói & kereskedői visszacsatolás
- Impact Shop UI: „Kupon nem működik” jelentő gomb → ticket/moderátor queue.
- Merchant portal: deal szerkesztés/törlés, prioritás kérése.
- Feedback adatok közvetlen input az aktív tanulási pipeline-ba.

## 13. Dokumentáció & nyílt forrás
- Kód automatikus dokumentálása (TypeDoc/Sphinx), moderátori SOP.
- Megfontolandó nyílt forrású releasing (pl. deduplikációs lib), közösségi visszacsatolás érdekében.

## 14. Operatív ütemezés és mérföldkövek
| Időszak | Fókusz | Deliverable | Guard/QA lépés |
| --- | --- | --- | --- |
| 2025 Q4 (Sprint S2–S3) | Playwright + Gmail + Reliability MVP | `T-2.8`–`T-2.10` ticketek lezárása, `/healthz` feature flags aktiválása | `npm run build`, cp40 deploy, `~/bin/impactall` + `aiagentall`, `notes.md` log |
| 2026 Q1 | Marketplace + retailer monitor rollout | `impactshop_crawler` Scrapy projekt, `marketplace_ingest.py`, első partner API integráció | Új cron logok + `guard-events` WARN review, staging smoke (`./bin/staging-qa-suite.sh`) |
| 2026 Q2 | Merchant portal + feedback hurkok | Partneri `POST /merchant/deals` endpoint, moderációs queue UI, Impact Shop feedback gomb | UX review + security audit (`scripts/install-guard-cron.sh` + policy checklist) |
| 2026 Q3 | AI Advisor bővítés + real-time ajánlás | Reliability score-alapú személyre szabás, Impi chat enrichment, KPI dashboard | AI guard (`aiagentall`) cron + LLM regression teszt, Percy vizuális diff |

**Kulcs elvek**
1. Minden negyedév végén legyen publikálható snapshot (guard PASS, dokumentált release note).
2. A pipeline bővítés csak akkor léphet következő fázisba, ha az előző forrás „green” státuszban van (runbook + log + alerting stabil).
3. Az Impi tanácsadó fejlesztése mindig a legfrissebb normalized feedet használja; regresszió esetén a modul automatikusan rollbackelhető (`git tag ai-agent-milestone-*`).

## 15. Csapatstruktúra és felelősségek
| Terület | Tulajdonos | Feladatok | Escalation |
| --- | --- | --- | --- |
| Data ingestion (Playwright, Gmail, Scrapy) | AI tooling squad | Forrás monitorok karbantartása, cron logok figyelése, új domain onboarding | `guard-actions.md` → „Source ingest” szekció |
| Moderáció & minőség | Impact Shop ops (Arnold + reviewer csapat) | Draft CSV audit, manuális override, merchant kommunikáció | Slack #impactshop-ops, `notes.md` P0 bejegyzés |
| Impi AI advisor | AI platform squad | `/api/v1/chat/command` fejlesztés, reliability scoring, UI copyk | AI change review board (keddenként 15:00) |
| DevOps & guardrails | Platform team | `.codex/cron/*`, guard script karbantartás, `aiagentall`/`impactall` futtatás | Bastion guard escalations (`guard-events.log` WARN) |

**Folyamat**: minden nagyobb módosítás (új forrás, új AI funkció) change requestet kap (`docs/ai-agent-harvester-integration.md` → CR template), amelyet az AI change review board hagy jóvá. A board döntése után indulhat a fejlesztés, guard run és deploy.

## 16. Impi AI tanácsadó bővítési terv

### 16.1 Context enrichment
1. **Adatmodell bővítés** – `apps/ai-agent-core/src/impi/recommend.ts` ajánlat objektumai kapnak új mezőket (`reliability_score`, `source_variant`, `scraped_at`, `merchant_priority`). A `recommendCoupons()` visszatérési értékét és a hozzá tartozó TypeScript típusokat frissíteni kell.
2. **Prompt builder frissítés** – `apps/api-gateway/src/services/impi-openai.ts` komponens a fenti mezőkből strukturált bullet listát generál (pl. „Forrás: Playwright • Scraped: 2025-12-05 06:00 • Megbízhatóság: 0.92”). A promptban külön szekció jelöli a bizonytalanságot (`reliability_score < 0.7`).
3. **LangGraph integráció** – a `apps/core-agent-graph/src/state.ts` állapot definíciójába bekerül egy `contextMetadata` mező, a `ingestUserInputNode` pedig továbbítja ezt az LLM felé. Guard: egységteszt `tests/impi-context-enrichment.test.ts` biztosítja, hogy minden ajánlat esetén logoljuk a metaadatokat.

### 16.2 User personalization
1. **Profile cache szolgáltatás** – implementálni kell a `apps/api-gateway/src/services/profile-cache.ts` modulban:
   - percenkénti háttér sync (cron vagy `bullmq`) a WordPress felhasználói metaadatokból;
   - in-memory + Redis cache (`key = userId`, `value = {preferredNgo, preferredCategory, lastDonationAt}`);
   - fallback: ha nincs profil, log warning és üres preferencia.
2. **Scoring integráció** – `recommendCoupons()` extra paraméterként megkapja a `ProfilePreference` objektumot, amely alapján súlyoz:
   - `preferredNgo` → +0.1 impact pont;
   - `preferredCategory` → duplázott relevancia score;
   - hiányzó profil → default neutral weight.
3. **Prompt enrichment** – a GPT input első bekezdésében megjelenik: „Kedvenc NGO: …, érdeklődő kategóriák: …”. Guard: manuális A/B teszt a staging Impi UI-ban.

### 16.3 Feedback loop
1. **UI komponens** – az Impact Shop Impi widgetben ( `wp-content/mu-plugins/impactshop-impi-chat.php` + front JS ) két ikon jelenik meg „Hasznos / Nem hasznos” felirattal. Event → `POST /api/v1/chat/impi/feedback`.
2. **API végpont** – új route az `apps/api-gateway/src/index.ts` fájlban:
   - request: `{ session_id, message_id, rating (up/down), comment? }`;
   - tárolás: `feedback_events` PostgreSQL tábla (DDL: id, session_id, rating, note, created_at).
3. **Reliability tréning híd** – napi cron (`.codex/cron/feedback-export.sh`) exportálja a negatív eseményeket a reliability modulnak; `p90` küszöb fölött automatikus ticket (`.codex/scripts/create-feedback-ticket.sh`) indul, amely a guard logba is bekerül.

### 16.4 Realtime alerting (előfeltételek hiányoznak – későbbre halasztva)
- A jelenlegi Impi stackben nincs flash-sale detektor modul, nincs `POST /api/v1/alerts/flash-sale` webhook és nincs admin "flash-message" végpont sem; amíg ezek nem készülnek el, a chat buborék push sem aktiválható. Ha a későbbiekben megvalósítjuk, a fenti három komponensre lesz szükség, különben a funkció nem működik.

### 16.5 Multimodális kilátások (2026 PoC)
1. **Vision API integráció** – PoC: `tools/vision/banner-detector.ts` modul, amely Google Vision / Azure Computer Vision endpointot hív és visszaadja a banner szövegeket + kulcsszavakat.
   - Futási példa: `cd ~/Documents/GitHub/ai-agent && GOOGLE_APPLICATION_CREDENTIALS="/Users/bujdosoarnold/Documents/GitHub/Google vision/durable-verve-458410-s5-df32776d6854.json" \ npx tsx tools/vision/banner-detector.ts --image="/Users/bujdosoarnold/Documents/GitHub/Google vision/f6e927b0994e7d7fb36abd600a100b05.webp" --provider=google --json` – ez a parancs sikeresen felismerte a „BLACK FRIDAY akár 70% kedvezmény” feliratot és a kulcsszavakat.
   - Azure ághoz `.env`-ben add meg az `AZURE_VISION_ENDPOINT` + `AZURE_VISION_KEY` változókat, majd futtasd `--provider=azure` értékkel; ugyanaz a JSON séma érkezik vissza, így a LangGraph `visionNode` változtatás nélkül használhatja.
   - Admin UI: `GET /admin/banner-analysis?key=<AI_AGENT_API_KEY>` oldal (Impact Shop admin iframe-ben is megjeleníthető) → URL megadás + fájl feltöltés, a háttérben `POST /api/v1/vision/analyze` API hívással (`multipart/form-data`) dolgozik; a kimenetet JSON formában mutatja.
2. **LangGraph multimodális node** – a `CoreAgentState` kiegészül `bannerImageUrl` mezővel; a `visionNode` (opcionális) feldolgozza a screenshotot, entitásokat generál, majd ezeket a fő prompt extra kontextusaként adja át.
3. **Kísérleti UI** – Impact Shop adminban „Banner elemzés” menüpont, ahol képet lehet feltölteni; a PoC logjait a `docs/ai-agent-roadmap.md` 2026-os fejezetéhez linkeljük.

### 16.6 Google szolgáltatások + Tudásközpont
1. **Google Vision / Gmail / Drive Search / Custom Search** – a teljes Google suite be van kötve az Impi + Core Agent stackhez:
   - Gmail Promotions ingest (`tools/gmail/promotions-runner.ts`, `apps/api-gateway/src/index.ts` `/gmail/promotions`) a Google Workspace OAuth hitelesítést használja, és JSON feedként szolgáltatja a kampányokat a normalizernek.
   - Vision (Google Cloud Vision) a LangGraph `visionNode`-ban és a `tools/vision/banner-detector.ts` CLI-ben is fut; adminból a `Banner elemzés` UI ugyanazt a Google API-t hívja.
   - Google Drive keresés (Docs/Slides/Sheets) – a Tudásközpont metaadatain túl a `tools/drive/search-runner.ts` (belső script) képes végigkeresni az Impi dokumentumokat; eredményeit a prompt builder és a Tudásbázis frissítésekhez használjuk (Meeting jegyzetek, tréning deckek). A drive search a `GOOGLE_DRIVE_SERVICE_ACCOUNT` / `GOOGLE_DRIVE_SHARED_FOLDER_ID` env párral érhető el.
   - Google Custom Search Engine (`GOOGLE_SEARCH_*` env) fallbackként kigyűjti a releváns shop/kupon oldalakat, ha a whitelistes Playwright snapshot épp üres.
2. **Tudásközpont (Impi Tudásbázis)** – a tréninganyagok, prompt library és moderátori guide-ok dedikált repo-mappában élnek (`/Impi Tudásbázis/AI-asszisztens-trening.md`, `AI-training-pack.md`, `AI-training-prompts.md`, `Impi beszélgetés térkép.json`). Ezek szolgálnak:
   - **Knowledge base** inputként az Impi prompt buildernek (pl. esettanulmányok, FAQ-k, kampány script minták).
   - **Onboarding** segédletként az ügyeletes moderátoroknak (loom videók, workflow leírások).
   - **Guard referenciaként** az AI Agent runbookokhoz (`docs/ai-agent-roadmap.md` és `docs/coupon-harvester-overview.md` linkelik őket).
   - A Tudásközpont frissítése szorosan követi a Google szolgáltatások konfigurációját, így minden új Gmail/Vision/Search változásnál fel kell jegyezni a megfelelő tréning fájlba is (pl. API kulcs rotáció, Vision admin UI screenshotok).
3. **Operatív kötés** – az Impact Shop admin + guard pipeline most már „egyben látja” a Google szolgáltatásokat és a Tudásközpontot: a guard futások `notes.md`-ben és a `conversation-summaries` fájlokban hivatkozzák, hogy melyik knowledge asset alapján történt a beavatkozás (pl. Gmail ingest fail → Tudásközpont troubleshooting szekció).

### 16.7 Szkennelt dokumentumok (OCR) – következő iteráció
1. **Core Agent igény** – a LangGraph ajánlóhoz szükség lehet olyan adatokra is, amelyek csak szkennelt szerződésekben/ÁSZF-ekben vagy levelekben érhetők el. A meglévő Vision kliens (Google/Azure) képes PDF / többoldalas JPEG inputot is kezelni, de dedikált pipeline még nincs.
2. **Feladat** – hozzunk létre egy `tools/vision/document-detector.ts` + `docs/admin/document-ocr.md` párost, amely:
   - PDF/scan inputot olvas, oldalak szövegét `textBlocks` struktúrába teszi, és metaadatként jelöli, hogy „fine print / terms”.
   - Integrálódik a LangGraph `visionNode`-ba: ha `documentUrl` érkezik (például apró betűs ajánlat feltöltve), a node a teljes OCR kimenetet a state-be írja (`documentInsights`).
   - Impi prompt builder extra részt kap („Apró betűs rész: …”) – guard: fallback, ha a document OCR hiányzik.
3. **Guard + admin UI** – bővítsük a `Banner elemzés` UI-t „Dokumentum OCR” füllel, ahol PDF-et lehet feltölteni; a guard script új WARN-t kap („document_ocr_missing”), ha 30 napon belül nem futott teszt.
4. **Implementation note** – ez még hátralék; a jelenlegi sprintben csak a megvalósítási terv készült el, éles funkció nincs, amíg nincs jóváhagyott igény.

Ezen bővítések után az AI agent stratégia nemcsak adatgyűjtő, hanem aktív ajánló rendszer lesz, amely a reliability jelzőket felhasználva képes priorizálni a legjobb kampányokat, és a moderátori visszajelzéseket is visszaforgatja a pipeline-ba.

## 17. Dinamikus memória, multi-agent és hang stack
### 17.1 Graph-alapú hosszú távú memória
- **Graphiti + GraphRAG**: időben változó tudásgráf (Neo4j backend), azonnali frissítés, időbélyegzett tények. Kombinált szemantikus + kulcsszavas + gráf keresés, P95 <300 ms (Neo4j + Graphiti). A GraphRAG (Zep) valós idejű üzleti adatokat integrál és követi a tények változását.
- **Integráció**: Impi/Core agent hosszú távú memóriája Graphiti/GraphRAG párossal épülhet; beszélgetések, e-mailek, termékinformációk és események csomópont + él formában kerülnek a gráfba, majd hibrid retrieval (embedding + gráfbejárás) szolgálja ki az LLM-et.
- **Operáció**: futtatható házon belül, jelenleg OpenAI API-val működik; Claude/Gemini támogatás csak jóváhagyott rollout után kapcsolható élesbe. Webhookon keresztül frissíthető.
- **Következő lépés**: jelöld ki azokat a promóciós forrásokat (Gmail/Playwright/manual CSV), ahol fix NGO sluggal érkező kampányok vannak, és a `ngo_codes.csv` alapján töltsd fel a slug mezőt – így a Graphiti aggregáció és az Impi fallback tényleges toplistát tud szolgáltatni.

### 17.2 Memória-stack alternatívák
- **Zep Memory**: Graphiti motorra épülő, SOC2-kompatibilis memória (epizodikus dialógus + szemantikus entitás + részgráf), LangChain/LangGraph kompatibilis.
- **Letta (MemGPT)**: nyílt forrású, moduláris memória, kvázi végtelen kontextus és multi-agent tooling, gyors PoC-hoz ideális.
- **Mem0**: könnyű memória-engine intelligens deduplikációval, kis erőforráson futtatható. Kis léptékű kontextus gyors előhívásához ajánlott.
- **Javaslat**: ha Graphiti túl nagy léptékű, rövid távon Letta vagy Mem0 telepíthető, majd Graphiti-ra migrálható a tartós memória.

### 17.3 Multi-ügynök koordináció
- **LangGraph**: grafikus, állapotmentett workflow (tokenenkénti streaming, automatikus állapotmentés), javasolt alap orchestrátor.
- **CrewAI**: 700+ SaaS-integráció, no-code Studio, beépített teszt/tréning modul – sok külső szolgáltatás esetén hasznos.
- **Microsoft Autogen**: Python/.NET támogatás, lokális futtatás, aszinkron üzenetküldés, elosztott ügynökhálózat.
- **Javaslat**: Core agent moduláris felépítésénél LangGraph legyen az alap; CrewAI/Autogen plugin szinten csatlakozhat.
- **LangGraph integrációs lépések**:
  1. **Alap projekt + környezet** – hozz létre külön `apps/core-agent-graph` mappát, ahol LangGraph node-ok élnek (Node.js/TS vagy Python). Add hozzá a Graphiti, Impi API és külső szolgáltatások env változóit (`GRAPHITI_API_URL`, `OPENAI_API_KEY`, stb.).
  2. **Állapot definiálása** – tervezd meg, milyen adatot visznek át a node-ok (felhasználói kérés, ajánlatok, Graphiti kontextus, fallback állapot), és LangGraph state machine-ben deklaráld (pl. `memoryContext`, `recommendations`, `chatHistory`).
  3. **Node-ok implementálása** – készíts külön taskokat a meglévő modulok alapján: (a) bejövő üzenet elő-feldolgozás, (b) Graphiti lekérés (`/query`, `/aggregations/ngo-promotions`), (c) ajánlatválasztás (AI Agent core `recommend`), (d) LLM-válasz generálás (Impi prompt builder), (e) utófeldolgozás / logolás.
  4. **Graphiti bekötés** – a `memoryContext` node Graphiti API-t hív, a kapott csomópontokat/él adatokat állapotba írja; ha nincs találat, fallbackként a reliability score / manuális promó JSON-t adja vissza (ahogy most a prompt builder teszi).
  5. **CrewAI / Autogen plugin** – opcionálisan hozz létre extension node-okat, amelyek SaaS workflow-t vagy Python/.NET feladatokat delegálnak (pl. számlázás, CRM). Ezek LangGraph node-ként futnak, és csak akkor aktiválódnak, ha a flow feltétele teljesül.
  6. **Observability + guard** – minden LangGraph futást logolj a meglévő `.codex/logs` struktúrába, és írd be a guard pipeline-ba (`aiagentall`), hogy a graf workflow hibája is látszódjon.
  7. **Migrációs terv** – fokozatosan váltsd ki a jelenlegi monolit hívásrendszert: először csak az Impi REST-et tereld át LangGraph-ra (feature flaggel), majd ha stabil, a többi asszisztensi use case-t (pl. vállalati admin feladatok) is ugyanarra a grafra építsd.

### 17.4 Hangalapú interakciók és stack
- **STT**: Wav2Vec2 (8–25 % WER, valós idejű, finomhangolható), Whisper (10–30 % WER, 100+ nyelv), Vosk (CPU-barát, offline), NeMo ASR (6–20 % WER, GPU). Valós idejű Impihez Wav2Vec2 + NeMo; offline/mobilhoz Vosk vagy Coqui STT.
- **TTS**: Chatterbox TTS (Llama-alapú, 0.5B paraméter, gyors, hangklónozás), Orpheus (150M–3B paraméter, multi-lingual, streaming), Sesame CSM (több szereplős dialógus). Brand voice-hoz Chatterbox, skálán Orpheus.
- **Emocionális TTS**: Hume AI Octave 2 (11 nyelv, <200 ms, hangkonverzió, fonéma-szerkesztés). Licenc esetén Impi kimenethez ajánlott.
- **Voice agent stack**: LiveKit (WebRTC streaming), Pipecat (ASR/TTS ↔ LLM streaming kötés), Milvus/Pinecone (vector memória), LangGraph (dialógus logika), Langflow (vizuális design), Langfuse (observability, költség/latency/hallucináció mérés).

### 17.5 Megfigyelhetőség, visszacsatolás, elvek
- **Langfuse dashboard**: token, költség, hibaarány, latency mérés; A/B tesztek STT/TTS váltáskor.
- **Feedback loop**: Impi UI „hasznos/nem hasznos” gomb → havi modell finomhangolás; reliability modul input.
- **Privát üzem**: Graphiti, STT, TTS lehetőség szerint privát felhőben fusson, minimalizálva a külső API igényeket.
- **Modularitás**: memóriamodul (Graphiti ↔ Letta ↔ Mem0), STT/TTS modul (Whisper ↔ Wav2Vec2 ↔ Octave), orchestrátor (LangGraph ↔ CrewAI) cserélhető legyen.
- **Jogszabályi megfelelés**: hang- és biometrikus adatoknál DPIA + explicit consent; titkosított logtárolás.

### 18. Komplex üzleti dokumentumok (Excel/PDF) feldolgozási terv
1. **Use case** – üzleti tervek, költségvetések, mérlegek, beszámolók gyakran bonyolult Excel/PDF formában érkeznek (képletek, pivotok, több munkalap). Az Impi/Core Agent pipeline jelenleg nem értelmezi ezeket; a cél egy több lépcsős normalizer + LLM interpretátor.
2. **Ingest stratégia**
   - `tools/excel/extract-runner.ts` → `xlsx` vagy `exceljs` könyvtár segítségével minden munkalapot JSON-ba exportál, a képletek helyett az aktuális értéket + képletet is eltárolja (`{ value, formula }`).
   - `tools/excel/pivot-flattener.ts` → felismeri a pivot táblák struktúráját (Row/Column mezők), lapos táblázattá alakítja, metaadatot tesz mellé (milyen mértékegység, milyen idősáv).
   - `tools/pdf/table-ocr.ts` → PDF esetén Tabula / Camelot + Vision text combinációval kinyeri a táblázatokat, a maradék szöveget LLM summary pipeline kapja meg.
   - Az eredmény egy `tmp/ingest/excel/<doc-id>/normalized.json` fájl, ahol minden munkalap/BOM/összesítő külön objektumként szerepel.
3. **LangGraph integráció**
   - Új `documentLoaderNode` a Core Agentben: ha a beérkező kérdés Excel/PDF-et is tartalmaz (`attachments`), a node meghívja az ingest pipeline-t, és a visszakapott JSON-ból `structuredDocument` állapotmezőt készít (pl. `sheets: [{name, rows, formulas}]`).
   - `analysisNode` → LLM-et hív dedikált prompttal („Értelmezd a költségvetést, emeld ki a költségcsúcsokat, hivatkozz cellákra”). A node a Graphiti memóriát is frissíti „doc insight” formájában.
   - Guard: ha az ingest pipeline >5 percig fut vagy hibát dob, `document_ingest_fail` WARN.
4. **Impi UI támogatás**
   - Az Impi chat feltöltő mezője engedélyez Excel/PDF fájlokat; a backend `document_attachments` mezővel adja át a LangGraph seednek.
   - A válaszok „Forrás” blokkjában cella/munkalap hivatkozás jelenik meg (`Sheet1!B12`), plusz letölthető JSON/CSV kivonat.
   - Admin oldalon „Dokumentum insight” nézet, ahol grafikonon látszik a fontos cellák időbeli alakulása.
5. **Prioritástábla / roadmap**
   1. Proof-of-concept: `tools/excel/extract-runner.ts` + manuális JSON review egy mintadokumentummal.
   2. LangGraph `documentLoaderNode` + `analysisNode` prototípus (Guard: stub output).
   3. Impi chat feltöltő + UI output.
   4. Guard/telemetry + Tudásközpont tréning modul (hogyan értelmezz Excel insightot).

### 19. Biztonság és feltöltési szabályok
1. **Upload korlátok** – csak `.xlsx/.xls/.pdf` fájl fogadott (max 10 MB), admin oldali drag&drop form, user-facing Impiben továbbra sincs közvetlen upload.
2. **Antivírus** – minden dokumentumnak előszűrt, AV-vel védett tárhelyről kell érkeznie; a pipeline nem futtat önálló vírusellenőrzést.
3. **Graphiti dashboard** – a `documentAnalysisNode` Graphitibe is pusholja az insightokat, így a dokumentum pipeline metrikái külön panelen jelennek meg.
4. **Guard integráció** – `.codex/guards/document-ingest.sh` script + `/healthz` `document_ingest` flag monitorozza az Excel/PDF ingest állapotát.

---
Ez a dokumentum az AI-agent roadmap (docs/ai-agent-roadmap.md) és a GPT‑5 Pro kutatás kulcspontjait egyesíti. A részfeladatok így konkrét lépésekre bonthatók, és az „Advanced AI Solutions” stratégia mentén végrehajthatók.
