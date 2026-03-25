## 2026-03-25 — vagy egyszerű adományozás szeparátor
- solo jegyek és preset összeg ikonok közé beszúrva (jovonkvize.js)

## 2026-03-25 — v1.5.1 cert aláírási blokk javítva
- igazgatósági tagja → meghatalmazott, Sharity Zrt. → Sharity Adományszervező Alapítvány (PDF + email szöveg)
- meghatalmazás HTML dokumentum létrehozva: docs/meghatalmazas-adomanyigazolas-kiallitas.html

## 2026-03-25 — v1.3.0 solo CSS !important
- solo-select bg/color/border !important, host CSS override javítva.

## 2026-03-24 — solo ticket CSS fix
- __or-sep, __solo-tickets, __solo-label, __solo-select CSS hozzáadva (hiányzó stílusok, fehér bg szivárgás javítva).

## 2026-03-24c — PHP version 1.2.0 deploy
- IMPACTSHOP_EVENT_DONATION_VERSION bumped 1.1.0 → 1.2.0, prod verified.

## 2026-03-24c — PHP version 1.2.0 deploy
- IMPACTSHOP_EVENT_DONATION_VERSION bumped 1.1.0 → 1.2.0, prod verified.

## 2026-05-28c - jovonkvize.js production deploy (solo jegyvásárlás)
- `impactshop-event-donation-widget-jovonkvize.js` frissítve: TICKET_UNIT_PRICE=150000, solo dropdown, change handler.

## 2026-05-28b - Jövőnk Vize: version bump 1.1.0 + cache-bust embed kód
- `IMPACTSHOP_EVENT_DONATION_VERSION` 1.0.0 → 1.1.0, prod-ra deploy-olva.
- JS embed URL már `?v=1.1.0`-t tartalmaz.

## 2026-05-28 - Jövőnk Vize: solo jegyvásárlás + tranzakció értesítő + cert BCC
- Branch: `feat/jovonkvize-ticket-count` — dev JS + PHP módosítások.
- JS dev: `TICKET_UNIT_PRICE=150000`, `STANDALONE_TICKET_MAX=10`, solo dropdown, change handler, init() feltöltés.
- PHP: `impactshop_event_donation_send_transaction_notification()` — minden COMMIT után email bujdoso.arnold@ + koncz.veronika@.
- PHP: adományigazolás emailekbe BCC: `bujdoso.arnold@bujdosoiroda.com`.
- Nincs deploy — csak dev verzió, production deploy külön jóváhagyás után.

## 2026-03-24 - Jövőnk Vize gála widget: ticket_count + selected_package
- Branch: `feat/jovonkvize-ticket-count` from `main`.
- `impactshop-event-donation-widget.php` schema 1.0.0 → 1.1.0: `ticket_count` + `selected_package` mentés DB-be és Stripe metadatába.
- DB: `dbDelta` additive (meglévő soroknál DEFAULT érték).
- Embed URL fixelve: `impactshop-event-donation-widget-jovonkvize.js`.

## 2026-03-24 - Impact Community (Hatás Körök) Sprint 1+2
- Branch: `feat/impact-community-sprint1` from `origin/main`.
- New modules: `impact-community.php` (backend, 978 lines) + `impact-community-app.php` (SPA frontend, 1279 lines).
- DB: 7 tables (ic_circles, ic_memberships, ic_missions, ic_mission_completions, ic_buddies, ic_posts, ic_circle_stats).
- REST API: 11 endpoints under `impact/v1`.
- Feature flag: `IMPACT_COMMUNITY_ENABLED` — must be set in production wp-config.php.
- Route: `/hatas-korok/` via `template_redirect` priority 4.
- Guard coverage: `impactall` mu-plugins parity guard monitors presence.

## 2026-03-23 15:45:00 CET - autobanner rotation and canonical dognet snapshot
- Branch continuity for autobanner runtime hardening: `fix/autobanner-feed-import-clean`.
- Added per-user autobanner rotation state via `pseudo_id`, removing the old 300-item repeat-prone pool behavior.
- Canonical Dognet autobanner mapping now accepts `dognet_program_id` / `program_id` from the `Shops` CSV in addition to `dognet_base` CID parsing.
- Sheets banner ingest now keeps every non-empty canonical banner row, allowing shop-logo fallback when `img` is empty instead of dropping the offer.
- Production follow-up remains blocked by MU-plugin filesystem permissions on `/home/sharityh/app/wp-content/mu-plugins/*.php` (read-only `0444`).

## 2026-03-23 12:25:00 CET - autobanner feed import snapshot
- Branch continuity for autobanner inventory path: `fix/autobanner-feed-import-clean`.
- Added direct JSON feed discovery/import helpers and `wp impactshop auto-banner import-feed`.
- Raised source caps: Google Sheets `50 -> 1000`, Dognet `120 -> 1000`.
- Validation: PHP lint clean on `impactshop-auto-banner.php`, `impactshop-auto-banner-sync.php`, `impactshop-auto-banner-dognet.php`.

# ImpactShop – Projekt státusz

## 2026-03-23 10:47:00 CET - offerwall hotfix continuity snapshot
- Branch continuity for protected MU-plugin changes: `hotfix/offerwall-sync-20260323`.
- Production hotfix scope: survey chooser restore (`Sharity`, `CPX Research`, `AyeT`), article quiz reward-token fallback, signed WP email proxy deployment.
- Production validation recorded: `impact-challenge` HTML contains survey chooser + CPX/AyeT containers; email proxy test returned `HTTP 200` with `{"sent":true,"count":1}`.
- Rollback path: restore the pre-deploy MU-plugin backup directory created on the production host before targeted copy.

*Generálva:* 2026-02-26 07:48:32 +0100 (Bujdoso-Mac-mini)

## Meta
- Gyökér: /Users/bujdosoarnold/Developer/GitHub
- Környezet: local
- SSH_HOST: nincs megadva
- Git ág: main
- Git hash: 1950945
- Módosított fájlok száma: 33

## REST healthcheck
- Staging: HTTP 200 (1641 ms, ok) – https://sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
- Production: HTTP 200 (1222 ms, ok) – https://app.sharity.hu/wp-json/

## Git státusz
 D .codex/cron/impact-social-ledger-sync.sh
 D .codex/cron/impactshop-snippet-refresh.sh
 D .codex/cron/workspace-backup.sh
 D .codex/scripts/impact-social-ledger-sync.php
 D .codex/scripts/lib/guard-common.sh
 D .codex/scripts/tradetracker-scope-check.sh
 M .gitignore
 M docs/system-recovery-map.md
 M scripts/shortcode_sync/shortcode_sync_run_REAL.sh
 M scripts/workspace-backup.sh
 M services/capi-proxy/README.md
?? .codex
?? .continue/
?? .venv-old-20260225-1940/
?? .vscode/
?? "Befektet\305\221 c\303\255mlista/"
?? "Cikk kv\303\255z/"
?? "Civil szervezetek/"
?? "Fillout export/"
?? IKEA/
?? JYSK/
?? "K\303\251perny\305\221k\303\251pek hib\303\241kr\303\263l/"
?? "OTP Simple API/"
?? Survey/
?? _archive/
?? ads.txt
?? impactshop-notes-clean-2/
?? impactshop-notes-clean/
?? impactshop-notes-pr/
?? partner-docs.html
?? robots.txt
?? services/capi-proxy/DEPRECATED.md
?? "\303\201SZF/"

## Fájlstruktúra (max depth 2, top 200 elem)
~~~
.
.DS_Store
.backups
.backups/git-status-20251207-202853.txt
.backups/git-status-20251207-203439.txt
.backups/impactshop-git-20251207-202853.bundle
.backups/impactshop-git-20251207-203439.bundle
.backups/working-tree-20251207-203439.patch
.codex
.continue
.continue/agents
.git
.git/.DS_Store
.git/COMMIT_EDITMSG
.git/FETCH_HEAD
.git/HEAD
.git/config
.git/description
.git/hooks
.git/index
.git/index 2
.git/info
.git/logs
.git/objects
.git/refs
.github
.github/copilot-instructions.md
.github/dependabot.yml
.github/workflows
.gitignore
.markdownlint.json
.prettierignore
.production_env
.staging_env
.venv
.venv-1
.venv-1/.gitignore
.venv-1/bin
.venv-1/include
.venv-1/lib
.venv-1/pyvenv.cfg
.venv-1/share
.venv-old-20260225-1940
.venv-old-20260225-1940/bin
.venv-old-20260225-1940/include
.venv-old-20260225-1940/lib
.venv-old-20260225-1940/pyvenv.cfg
.venv-old-20260225-1940/share
.venv/bin
.venv/include
.venv/lib
.venv/pyvenv.cfg
.venv/share
.vscode
.vscode/extensions-installed.txt
.vscode/extensions.json
.vscode/extensions.lock
.vscode/launch.json
.vscode/settings.json
.vscode/tasks.json
000-dognet-token-ttl.php
=
ÁSZF
ÁSZF/.DS_Store
ÁSZF/1sz_melleklet_szponzori_megallapodas.md
ÁSZF/ÁSZF 1.docx
ÁSZF/Sharity ÁSZF_2022_11_10_korr nélk.docx
ÁSZF/Sharity_ASZF_2026.md
ÁSZF/Sharity_Adatkezelési_tájékoztató_2024_PDF.pdf
ÁSZF/Win4Good szabályzat.pdf
Befektető címlista
Befektető címlista/TokePortal - Sharity részvény-allokációs tábla_08.05_nagykövetek nélkül.xlsx
Cikk kvíz
Cikk kvíz/articles_quiz.csv
Civil szervezetek
Civil szervezetek/szervezetek-2026-02-13.xlsx
Fillout export
Fillout export/Fillout Válassz egy ügyet results.csv
Google vision
Google vision/durable-verve-458410-s5-df32776d6854.json
Google vision/f6e927b0994e7d7fb36abd600a100b05.webp
Graphiql
Graphiql/graphiql-main
IKEA
IKEA/Átvilágításhoz szükséges nyilatkozat_DD short_NEW (1).pdf
IKEA/Sharity_IKEA_Veled_Kozosen_Ajanlat_2026.docx
IKEA/Untitled-1.ini
IKEA/dr._Korossy_Csaba___A_kiadmanyozas_hibaja_miatti_semmisseg_es_az_elevules_a_Kuria_itelkezesenek_tukreben.pdf
IKEA/fy26_twy_reszveteli_szabalyzat_hu_plg.pdf
IKEA/generate_ajanlatot.py
IKEA/~$arity_IKEA_Veled_Kozosen_Ajanlat_2026.docx
Impactshop Wallet Key.cer
Impactshop Wallet Key.p12
Impactshop.p12
JYSK
JYSK/JYSK_WhoisJYSK_Cutdown_20sec_JHU.mp4
Képernyőképek hibákról
Képernyőképek hibákról/IMG_3734.png
Képernyőképek hibákról/IMG_3739.png
Képernyőképek hibákról/IMG_3741.mov
Képernyőképek hibákról/IMG_3746.png
Makefile
OTP Simple API
OTP Simple API/PaymentService_SimplePay_2.x_Payment_HU_251105 (3).pdf
README.md
Survey
Survey/.DS_Store
Survey/Kérdőív kutatás és gamifikáció.docx
Survey/codex_batch_logic_implementation_guide.md
Survey/codex_implementation_guide.md
Survey/codex_implementation_guide.zip
Survey/kerdesbank_minta.csv
Survey/question_mapping.csv
Survey/segment_taxonomy.csv
Survey/sharity_master_builder
Survey/sharity_master_builder.zip
Survey/sharity_questions_MASTER_1250.csv
Survey/sharity_questions_batch1_250.csv
Survey/sharity_questions_batch2_250.csv
Survey/sharity_questions_batch3_250.csv
Survey/sharity_questions_batch4_250.csv
Survey/sharity_questions_batch5_250.csv
_archive
_archive/aszf-extract
_archive/examples
_archive/old-snippets
active_plugins_diff_testdiff.txt
ads-bridge
ads-bridge/.codex
ads-bridge/.env
ads-bridge/.env.example
ads-bridge/AYET_OFFERWALL_INTEGRATION.md
ads-bridge/IMPACT_ADS_BRIDGE_SPEC.md
ads-bridge/bin
ads-bridge/services
ads-bridge/setup-ads-bridge.sh
ads-bridge/wp-content
ads.txt
ai-agent
ai-agent/.DS_Store
ai-agent/.codex
ai-agent/.env
ai-agent/.env.example
ai-agent/.env.graphmemory
ai-agent/.env.local
ai-agent/Drive desktop APP 
ai-agent/Feladatok
ai-agent/Google Ads
ai-agent/Impi Tudásbázis
ai-agent/OCR
ai-agent/ai-agent-baseline-2026-01-05.md
ai-agent/apps
ai-agent/client_secret_438682830954-tr4grg5b1gqrr7eribckcihcigk3nfmu.apps.googleusercontent.com.json
ai-agent/config
ai-agent/data
ai-agent/dist
ai-agent/docs
ai-agent/durable-verve-458410-s5-3b5f4ae2531f.json
ai-agent/durable-verve-458410-s5-9eeee98b5969.json
ai-agent/dwd_clients.csv
ai-agent/ngo_codes.csv
ai-agent/node_modules
ai-agent/package-lock.json
ai-agent/package.json
ai-agent/scripts
ai-agent/secrets
ai-agent/services
ai-agent/system-status-snapshot.md
ai-agent/tests
ai-agent/tmp
ai-agent/tools
ai-agent/tsconfig.json
ai-agent/types
backup20251208
backup20251208/.DS_Store
backups
backups/impactshop-netflix-shortcodes.php.20251202-174457
backups/impactshop-shortcode-pack.php.20251202-174457
backups/page-refresh-2026-02-19
bin
bin/codex-tui
bin/dev-qa.sh
bin/ngo-rate-limit-check.sh
bin/preflight-check.sh
blog
blog/.dockerignore
blog/.git
blog/.gitattributes
blog/.github
blog/.gitignore
blog/.graphiticfg.yml
blog/.kamal
blog/.rubocop.yml
blog/.ruby-version
blog/Dockerfile
blog/Gemfile
blog/Gemfile.lock
blog/README.md
blog/Rakefile
blog/app
~~~

## Jegyzetek
- A ~/bin alatt elérhető helper scriptek: codex-refresh, impactresume.
- A fájl automatikusan generálva (scripts/status-snapshot.sh).
- 2025-10-14: Netflix/Deals shortcode REST go-deal linkpreferencia él; deploy után Elementor cache flush + REST warmup kötelező.
- GPT/Sonnet promptokat mindig szakmai review előz meg – automatikus végrehajtás tiltott, eltéréseket jelezd.

---
_Auto update: 2026-03-05 10:47:00 CET_

### Health check summary

```
Pre-push safe audit now evaluates pushed commit range (--mode push)
Doc continuity checks remain strict for committed changes
```

**Baseline referencia:** impactshop-baseline-2026-03-03.md

---
_Auto update: 2026-03-05 10:12:00 CET_

### Health check summary

```
Identity nickname save path fixed (profile greeting + Legacy Wall sync)
PHP lint ok for identity/gamification modules
```

**Baseline referencia:** impactshop-baseline-2026-03-03.md

## 2026-03-09 Workflow Infra Update
- Dev-memory workflow 1-8 aktiválva (pre-task, context-pack, memory gate, PR auto-memory, commit template/hook, incident, digest, Copilot MCP guard).
- Hookok újratelepítve; napi digest cron aktív.

## 2026-03-10 15:34:42 CET - ads-watch strict audit continuity fix
- Added explicit docs continuity note for `wp-content/mu-plugins/impactshop-ads-watch.css` module update.
- Added matching conversation summary evidence so `safe-repo-audit --mode push --strict` sees note continuity in range.

## 2026-03-20 - Miele Jövőnk Vize gála widget modulok
- Új widget modulok hozzáadva: `wp-content/mu-plugins/impactshop-event-donation-widget-dev.js` (fejlesztési), `wp-content/mu-plugins/impactshop-event-donation-widget-jovonkvize.js` (éles Miele kampány).
- Funkciók: Ezüst/Arany/Platina csomag választó (1M/2M/3M Ft), gálajegy szám selector (max 2/4/6 fő), ezres törés összegmezőben.
- Stripe maximum_amount: 2 500 000 → 3 500 000 Ft (`impactshop-event-donation-widget.php`).
- Deploy: `sharityh@s59.tarhely.com:/home/sharityh/app/wp-content/mu-plugins/`

## 2026-03-24 - Impact Community plugin (Hatás Körök) — Sprint 1–16 + audit

- IC_DB_VERSION: 1.3.7 | 22 DB tábla | 47+ REST endpoint | 15+ cron
- Branch: `feat/impact-community-sprint1` | PR #73 (office-hue/impactshop-notes)
- Audit: 11 SQL séma-inkonzisztencia javítva; PHP lint OK; security OK
- Status: merge pending → prod deploy szükséges (rsync to s59)
### 2026-03-25T06:24:01Z | jovonkvize STYLE_ID collision fix deployed | branch=feat/jovonkvize-ticket-count | result=OK
### 2026-03-25T07:10:40Z | v1.4.0 solo ticket input fix deployed | result=OK
### 2026-03-25T07:39:33Z | v1.5.0 buyer email + ticket serial deploy | result=OK

### 2026-03-25 — ÁSZF link kattintható (jovonkvize widget)
- consent checkbox szöveg → kattintható `<a>` link: `/ngo-guides/jogi-dokumentumok/`
- `stopPropagation` a link click-re → checkbox nem togglel
- JS deployed, chmod 444
