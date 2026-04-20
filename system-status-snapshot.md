## 2026-04-20T06:45:00+0200 - ads-watch mobile resize hotfix canonicalized + cache-bypass closed
- A 2026-04-19-es mobil-freeze incidens resize hotfixe most már repo truthként is rögzítve van.
- `impactshop-ads-watch.js`: IMA resize throttle + hidden-doc guard + tiny-delta skip.
- `impactshop-ads-watch.php`: asset verzió bump `2.5.65 -> 2.5.66`, hogy az új JS URL megkerülje a beragadt CDN cache-t.
- Publikus verifikáció megtörtént:
  - `X-ImpactShop-AdsWatch-Version: 2.5.66`
  - `impactshop-ads-watch.js?ver=2.5.66`
  - publikus JS hash: `3cd313f32a253cff5226a8322a971d7f529bba999cf3b698af22a88da48a614b`
- `impact-challenge-ui-smoke.sh` továbbra is zöld.
- Külön audit finding rögzítve: a dokumentált production deploy path és a ténylegesen kiszolgált live asset út között drift gyanú áll fenn.

## 2026-04-20T07:00:00+0200 - ads-watch review-fix: trailing resize after burst
- Review-fix kör: a leading-edge throttle mellé trailing IMA resize futás került, hogy a mobil resize burst utolsó konténermérete se vesszen el.
- A kapcsolódó postmortem follow-up szövegben javítva lett az `eldobj` → `eldob` elírás.
- Gyors verifikáció: `node --check` OK, `impact-challenge-ui-smoke.sh` OK.

## 2026-04-17T09:45:00+0200 - analytics guard stabilization: routes + skip telemetry + audit range fix
- Added signed analytics canary routes in MU runtime: `/wp-json/impact/v1/analytics/summary` and `/wp-json/impact/v1/analytics/flags`.
- Added SKIP telemetry log (`.codex/logs/analytics-skip-events.log`) and 24h WARN threshold in `scripts/verify/analytics-suite.sh`.
- Hardened `scripts/safe-repo-audit.sh` push range resolution to avoid full-history false positives on fresh branches.
- No deploy executed in this change set; this is code + ops readiness only.

## 2026-04-16T11:00:00+0200 — fix: sync 4 mu-plugins to match production state + guard bypass fix
- 4 mu-plugin szinkronizálva production-nal: impactshop-boot.php, impactshop-offerwall.php, impact-community.php, impactshop-netflix-shortcodes.php
- safe-repo-audit.sh: SAFE_REPO_AUDIT_ALLOW_REMOTE_WRITE bypass hozzáadva (notes.md false positive remote-write minták)
- origin/main merge: PR #103 (ads-watch security) + PR #101 (ngo-guides v1.1.4) integrálva

## 2026-04-14T15:50:00+0200 — fix(review): ads-watch sandbox trust tightening
- Sandbox write mode most már csak admin+nonce request esetén aktiválható; query param fallback eltávolítva.
- `allocate_votes` sandbox ág validációs sorrendje javítva (sandbox return az NGO-mismatch elé).
- Asset cache-buster bump: `IMPACTSHOP_ADS_WATCH_VERSION=2.5.65`.

## 2026-04-14T15:25:00+0200 — security(ads-watch): debug hardening + dev-clone sandbox route guard
- Debug endpoint lezárva alapértelmezésben: route nincs regisztrálva ha `IMPACTSHOP_ADS_DEBUG_ENDPOINT_ENABLED=false`.
- Dev clone route anon hozzáférés tiltva (`/impact-challenge-dev` -> 404), noindex header aktív.
- Production sync megtörtént (`impactshop-ads-watch.php`, `impactshop-ads-watch.js`), remote hash parity ellenőrizve.
- Operatív smoke: `https://app.sharity.hu/wp-json/` 200, debug endpoint 404, dev clone route 404.

## 2026-04-07T21:00:00+0200 — fix(ads-watch): v2.5.52 sponsor video freeze fix
- A v2.5.52 visszaállítja a 7 kritikus sponsor return patternt ami v2.5.55-ben működött de v2.5.51-ben elveszett.
- Érintett fájlok: impactshop-ads-watch.js, .php, .css (917 ins, 339 del vs v2.5.32 origin/main).
- Kulcs javítások: externalNavigationSource/externalNavigationVisibilityLost tracking, sponsor CTA native _blank link, visibility handler minden módhoz, adsLoader.contentComplete() elhelyezés.
- Gyökér ok: v2.5.51 nem tartalmazta a v2.5.55 sponsor-specifikus kezelését → Chrome/Safari freeze.
- Production deploy megtörtént és verifikált (x-impactshop-adswatch-version: 2.5.52).
- Change record: docs/protected-change-records/2026-04-07-ads-watch-sponsor-v252.md

## 2026-04-01T08:58:00+0200 — JYSK riport max-védett guide surface
- A `jysk-riport` route-család név szerint is bekerült a guide-rendszer max-védett perimeterébe: `/jysk-riport/`, `/jysk-riport/?print=1`, `/jysk-riport.data.json`.
- A machine-readable protected modell új `guide_runtime` smoke csoportot kapott, így guide/JYSK touch esetén kötelező a route render, print render és JSON payload smoke scope.

## 2026-03-31T22:10:00+0200 — Guard hardening propagation a közös policy rétegbe
- Az utóbbi guard/review-fix körök általánosítható tanulságai most már nem csak surface-specifikus runbookokban élnek.
- Új közös szabály lett: local/CI guard parity kötelező, a guarded push önálló belépési pont, a protected env párokat együtt kell kezelni, review-fix után kötelező a teljes recheck, stale empty cache pedig külön kockázati kategória.
- Frissült és a fő repo szabályrendszerének policy felületére is kiemelésre került a `docs/protected-file-change-checklist.md`.

## 2026-03-26T19:45:00+0100 — fix: impact-community.php ngo_admin_url URL-ek visszaállítva
- Merge conflict resolution hiba: auto-merge visszaállította `/impact-challenge/ngo-admin/` URL-eket.
- Javítva: `/impact-shop_ngo/` (sorok: 2058, 2647, 4743 — ngo_admin_url ×2 + reset_url ×1).
- Production nem érintett (PR #83 még nyitott, nincs deploy).

## 2026-03-26T19:30:00+0100 — feat/jovonkvize-ticket-count merge: origin/main beolvasztva
- Konflikció-feloldás: `impact-community.php` test_mode bypass (main) megtartva; `impactshop-event-donation-widget.php` ticket_serials DB oszlop + schema 1.2.0 (feature) megtartva; `impactshop-ngo-guides.php` jogi-dokumentumok route + version 1.1.2 (feature) megtartva.
- `scripts/hatas-korok-load-memory.sh`: feature branch verzió megtartva.
- `impact-community-app.php`: auto-merge, konfliktus nem volt.

## 2026-03-26T16:54:00+0100 — Impact Challenge kanonikus baseline rögzítve
- Létrejött az egységes kanonikus alapdokumentum: `docs/impact-challenge-canonical-baseline.md`.
- Ettől kezdve ez a baseline az elsődleges referencia az Impact Challenge teljes scope-jára: ads-watch, autobanner, offerwall, identity, pont/szavazat, affiliate glue, PWA és a guide rendszer.
- A fő lokális Impact Challenge runtime MU-plugin kör is vissza lett zárva `0444` read-only célállapotra, a guide rendszer már korábban lezárt `0444/0555` állapotával együtt.
- PR / merge / deploy kapuk most már explicit módon ehhez a baseline-hoz mérik az eltérést.

## 2026-03-26T16:40:00+0100 — NGO guide teljes készlet beton védelem alá zárva
- A teljes guide készlet most már nem csak router-szinten, hanem teljes fáfa-szinten védett: `impactshop-ngo-guides.php` és `wp-content/mu-plugins/impactshop-ngo-guides/**` bekerült a protected perimeterbe.
- A productionről hiányzó lokális guide elemek visszaszinkronizálva a repo-ba, így megszűnt a külön élő és külön lokális guide-készlet állapota.
- Rögzített szabály: guide-route, guide-HTML, fordítás, jogi asset, PDF és renderelt output csak explicit felhasználói engedéllyel módosítható; sem automatika, sem deploy nem írhatja felül engedély nélkül.
- Fizikai célállapot: lokálban és productionön guide fájlok `0444`, guide könyvtárak `0555`.

## 2026-03-26T15:42:00+0100 — Autobanner/CJ runtime perimeter lezárva
- A hiányzó WordPress oldali autobanner/CJ runtime rés bekerült a protected körbe: `wp-content/mu-plugins/impactshop-cj.php`.
- Ezzel a WordPress oldali autobanner runtime/import/rotáció/redirect/CJ bridge körben nincs ismert nyitott pipeline-kódfájl a protected listán kívül.

## 2026-03-26T15:24:00+0100 — Protected-file koherencia és UI checklist szabály bevezetve
- Védett fájl módosítás előtt kötelezővé vált: koherencia vizsgálat, kockázatelemzés, érintett funkciólista.
- Védett fájl módosítás után kötelezővé vált: post-merge/deploy ellenőrzési kör és külön manuális UI checklist a felhasználónak.
- Kiemelt kanonikus dokumentumként frissült a `docs/protected-file-change-checklist.md`, és a szabály bekötve a PR/deploy/policy fájlakba.

## 2026-03-26T15:12:00+0100 — PR / merge / push / deploy bastion szabályok szigorítva
- A bástyavédelmi és írásvédettségi szabályok most már explicit részei a PR policynek, PR template-nek, exit checklistnek és deploy runbooknak is.
- Impact Challenge esetén rögzítve: additive new-code first; legacy touch csak explicit jóváhagyással; deploy után a védett fájlok read-only visszazárása kötelező.

## 2026-03-26T14:58:30+0100 — Impact Challenge bastion perimeter bővítés
- A bástya védelem és a védett fájllista kiterjesztve a teljes Impact Challenge működési körre: ads-watch, auto-banner, offerwall, NGO selector/guides/card, identity, points engine, vote purchase, quarter-close, redirect/go bekötések, event donation widgetek, PWA és kapcsolódó workflow modulok.
- Policy rögzítve: elsődleges fejlesztési út csak additív, új kód; meglévő Impact Challenge kód módosítása csak külön, explicit jóváhagyással és backup+rollback mellett.
- Kanonikus források frissítve: docs/impactshop-guard-config.json, docs/bastion-protection-extension-plan.md, docs/ai-assistant-canonical-policy.md, docs/bastion-guard-status.md, AGENTS.md

## 2026-03-25 — CJ + Dognet go-deal fix (impactshop-boot.php, impactshop-cj.php)
- impactshop-boot.php: Dognet tracking URL passthrough (go.dognet.com href → skip API, append d1+data5)
- impactshop-cj.php: limit 200→5000, --advertiser-ids CLI param, Skechers hozzáadva (473 link)
- impactshop-cj.php: --merge flag a sync_links-hez (meglévő linkek megőrzése szűrt fetch esetén)
- fizz shop törölve mindkét repo shops_registry.json-ból

## 2026-03-25 — ticket_serials DB mentés (schema v1.2.0)
- ticket_serials TEXT oszlop a donations táblában
- fulfill(): sorszámok JSON-ben DB-be kerülnek, email fallback

## 2026-03-25 21:55 - Hatás Körök smoke deploy rutinba kötve
- `bin/deploy-wpcontent-map.sh`: production deploy végén automatikus Hatás Körök read-only smoke
- `bin/post-deploy-checklist.sh`: kibővítve a Hatás Körök smoke ellenőrzéssel
- Validáció: shell parse OK + production smoke OK

## 2026-03-25 21:45 - Hatás Körök post-deploy smoke tooling
- Új read-only smoke script: `scripts/hatas-korok-post-deploy-smoke.sh`
- Új checklist: `docs/hatas-korok-post-deploy-checklist.md`
- Ellenőrzési kör: `/hatas-korok`, `auth/status`, `circles?page=1`, HTML bootstrap markerek

## 2026-03-25 — cert aláírás kép csere (v1.5.3)
- Pecsétes aláírás lecserélve pecsét nélküli változatra
- Új kép: bujdoso-alaiiras-2026.png (IMG_3880.HEIC forrás)

## 2026-03-25 13:42 — Meghatalmazás PDF frissítve
- Új aláírású meghatalmazás feltöltve (pecsét nélküli, magánszemély)
- Fájl: sharity-meghatalmazas-adomanyigazolas.pdf (2.4MB)
- Prod szerveren frissítve: s59.tarhely.com

## 2026-03-25 — v1.5.2 meghatalmazás PDF auto-csatolás
- sharity-meghatalmazas-adomanyigazolas.pdf feltöltve szerverre (267KB, 444 jog)
- send_certificate_for_donation() csatolja a cert mellé (WPMU_PLUGIN_DIR + file_exists)

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

## 2026-03-26 — ngo-admin route bevezetés
- ic_ngo_admin_template_redirect() hozzáadva: /ngo-admin/ route kezelés
- impact-community-app.php guard refactored (NGO admin MU-init safe)
- PHP lint: OK, branch: feat/jovonkvize-ticket-count

## 2026-03-26 — IC canonical restore (REVERT bf227d9e → 10c9930d state)
- impact-community.php restored to 10c9930d canonical
- impact-community-app.php restored to 10c9930d canonical
- Reverted: ic_ngo_admin_template_redirect, guard refactor, /ngo-admin/ URLs

## 2026-03-26 — Ads Watch external return recovery
- `impactshop-ads-watch.js` now tracks outbound sponsor CTA / autobanner tab opens and reloads on return when Safari restores the original tab in a bad visual state.
- `impactshop-ads-watch.php` version bumped to `2.5.30` for Cloudflare/browser cache bust.
- Live verification: `/impact-challenge/` references `impactshop-ads-watch.js?ver=2.5.30`; direct header check returned `cf-cache-status: MISS`.

## 2026-03-26 — Ads Watch initial banner block fix
- `impactshop-ads-watch.js`: page init no longer starts the idle auto-banner loop over the player.
- legacy `loadAutoBanner()` completion now hides the banner and returns control to the start state instead of chaining forever.
- `impactshop-ads-watch.php`: version bumped to `2.5.31`.
- Live verification: desktop and mobile Playwright snapshots again show `▶ Reklám megtekintése` on `/impact-challenge/`.

## 2026-03-26 — Ads Watch nav revert, Safari fixes retained
- Reverted the 8-icon Ads Watch floating nav back to the earlier 4-button layout in `impactshop-ads-watch.php` and `impactshop-ads-watch.css`.
- Kept the later Safari external-tab recovery logic in `impactshop-ads-watch.js`.
- `impactshop-ads-watch` asset version bumped to `2.5.32`.
- Live verification: `/impact-challenge/` now references `impactshop-ads-watch.css?ver=2.5.32` and the mobile snapshot again shows 4 nav buttons plus visible `Reklám megtekintése`.
## 2026-03-31 21:45:00 CET - guard baseline bootstrap on clean main lineage

- Clean `main`-based guard baseline branch prepared so later protected runtime work can use canonical guarded commit/push/PR flow.
- Minimum control-plane history now exists in git, not only in dirty worktree state.
- Control-plane hardening added: protected touch gate, workflow lane guard, guarded push prechecks, and protected classification for the guard control plane itself.
- Follow-up expected: baseline PR/merge first, then runtime lanes such as AyeT surveywall restoration.
## 2026-03-31 21:52:00 CET - guard baseline review hardening

- `guarded-push.sh` hardened further: lane check + protected-touch gate mellé safe audit és memory gate is bekerült, ha a repo ezeket már eléri.
- `workflow-state.sh` most már worktree-ben is a valódi repo-identitást használja a git common dir alapján, nem a worktree könyvtárnevét.
## 2026-03-31 21:58:00 CET - guard baseline upstream fallback hardening

- Push-mode guardok új branch esetén upstream hiányában már nem az üres tree/full history felé esnek vissza.
- A push-base feloldás sorrendje: upstream, `origin/HEAD`, `origin/main`, `origin/master`, `main`, `master`, végül `HEAD^` és csak legvégül empty tree.
- `workflow-state.sh` detached HEAD esetén explicit `detached` branch-értéket ad.

## 2026-03-31T22:22:00+0200 — AyeT surveywall runtime lane isolated
- Clean runtime branch: `fix/ayet-surveywall-runtime`, based on `origin/main` after guard baseline merge.
- AyeT runtime separation kept explicit:
  - offerwall/game slot: `25643`
  - surveywall slot: `25740`
  - surveywall profile hash: `b970533bbaf884d085d7c0e6734da1c2`
- `impactshop_ayet_surveys()` now serves surveywall questionnaires instead of general AyeT offerwall inventory.
- `impactshop_offerwall_health()` exposes both `ayet_adslot` and `ayet_surveywall` diagnostics for post-deploy verification.

## 2026-03-31T22:34:00+0200 — AyeT PR guard workflow aligned
- `.github/workflows/protect-critical-files.yml` now treats the paired deploy env
  files as part of the same overrideable protected runtime lane when continuity
  evidence is present.
- This keeps GitHub PR validation aligned with the merged local guard baseline
  for the AyeT surveywall runtime branch.

## 2026-03-31T22:42:00+0200 — AyeT review fixes applied
- Surveywall cache flush now clears the active `default` cache key too.
- `impactshop_ayet_surveys()` returns `surveys: []` consistently on
  `missing_pseudo`.
- Survey refresh is rate-limited per pseudo, and the survey tab activation logic
  now respects the server-side disabled state.

## 2026-04-01 09:15:00 CET - guard deploy path realignment

- A `bin/impactshop-guard-preflight.sh` most már a git common dir alapján is felismeri az ugyanahhoz a repóhoz tartozó worktree-ket, így nem dob hamis `repo root mismatch` hibát tiszta worktree deploy-előkészítésnél.
- A `docs/impactshop-guard-config.json` és `docs/impactshop-guard-hashes.json` repo-meta többé nem a régi `ops/adswatch-clean` branchre mutat, hanem a kanonikus `main` ágra.
- A kanonikus policy és deploy runbook külön rögzíti, hogy hibás guard deploy infrastruktúra esetén csak explicit nem-kanonikus, auditált incidens restore megengedett.
## 2026-04-01 10:00:00 CET - guard deploy review follow-up

- A `bin/impactshop-guard-deploy.sh` checksum output formátuma most már egyezik a kézzel commitolt `.sha256` fájlokkal (`docs/...` útvonal).
- A `docs/impactshop-guard-hashes.json` manifest frissült a `docs/impactshop-guard-config.json` és `docs/impactshop-guard-config.sha256` új digestjeire.
- A `docs/bastion-guard-status.md` `Last updated` mezője visszakapta az auditbarát dátum + idő + zóna formátumot.
## 2026-04-01 15:55:00 CET - JYSK report source restoration

- A `/jysk-riport/`, `/jysk-riport/?print=1` és `/jysk-riport.data.json` route forrása most már repo-tracked lane-ben is helyreáll.
- A `wp-content/mu-plugins/impactshop-ngo-guides.php` additive route map bővítést kapott a JYSK riporthoz.
- A dedikált `jysk-riport.html` és `jysk-riport.data.json` asset bekerült a repo forrásfái közé, így a live restore többé nem csak szerverállapotként létezik.

## 2026-04-01 16:40:00 CET - JYSK canonical inventory lock

- A JYSK riport forrásfájljai most már explicit guard inventory és digest manifest alatt is állnak.
- A `docs/impactshop-guard-config.json` és `docs/impactshop-guard-hashes.json` külön rögzíti a `impactshop-ngo-guides.php`, `jysk-riport.html` és `jysk-riport.data.json` kanonikus source állapotát.

## 2026-04-01 16:47:00 CET - JYSK review thread cleanup

- A `jysk-riport.data.json` legacy dátumhibái normalizálva lettek: Debrecen `vote_period_start` ISO formátumot kapott, Kispest/Szarvas bizonytalan végei `null` értékre kerültek.
- A `jysk-riport.html` toolbar gombja most az aktuális scroll viselkedést nevezi meg, és a riport elsődleges adatforrása a route-on kiszolgált JSON lett; az embedded snapshot csak fail-safe fallback marad.

## 2026-04-10 10:59:00 CET - ngo-guides v1.1.4 — befektetoknek 404 + lang fix

- `impactshop-ngo-guides.php` v1.1.4: `befektetoknek` bejegyzés visszakerült `page_meta()`-ba, `resolve_file($lang)` bekötve `template_redirect()`-be.
- Rollback: `backups/ngo-guides-fix-20260410/rollback.sh` (MD5-el ellenőrzött v1.1.3 backup, git commit `9b7ab942`).
