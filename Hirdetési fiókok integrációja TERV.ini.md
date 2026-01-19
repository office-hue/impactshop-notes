Hirdetési fiókok integrációja TERV

Ide illik egy rövid, szigorú, mégis világosan megfogalmazott bevezető — a Codex számára egyfajta „védőpajzs”. Ez a blokk kerül a legelső sorok elé (a korábbi „CODEx MASTER SCRIPT” elé), és garantálja, hogy minden művelet csak staging környezetben fusson.

Íme a pontos, másolásra kész bevezető:

──────────────────────────────────────────────────────────────────────────────
⚠️  SAFETY PREAMBLE – STAGING-ONLY EXECUTION DIRECTIVE
──────────────────────────────────────────────────────────────────────────────
You are operating strictly in STAGING mode. 
Under no circumstances may you alter, deploy, or push changes to any PRODUCTION
instance, domain, database, or configuration.

Environment enforcement:
- STAGING_URL must contain “impactshop-staging” or “staging.sharity.hu”.
- Any task referring to the live domain (sharity.hu without “staging”) must be
  automatically rejected or rewritten to target the staging environment.
- Never modify wp-config.php, live REST endpoints, or production DB tables.
- All file writes, npm installs, or CLI runs must target the staging directory
  defined in ~/.staging_env.
- All test data, logs, and reports go to `.codex/staging/` or `/impactshop-staging`.

Codex must prepend `[STAGING-ONLY]` to every log line and refuse any action that
points to production. Production safety overrides all other instructions.

If unsure about environment context, stop and output:
  “STAGING SAFETY: Environment not confirmed — no action taken.”

This preamble ensures that all automation, tracking, and analytics integrations
(Ads, Meta CAPI, TikTok, GA4, Impact Ledger, etc.) execute only in the staging
sandbox and can never affect real users or live data.
──────────────────────────────────────────────────────────────────────────────

Ezt tehát közvetlenül a korábbi „CODEx MASTER SCRIPT” elé illeszd, így minden futtatás egyértelműen sandbox-módban indul, és a Codex még véletlenül sem fog az éles Sharity.hu-t érinteni.

Imádom. Jöjjön a „mindent visz” Codex / Copilot Chat prompt-csomag egyetlen blokkban, sprintekre bontva. Ezeket sorban bemásolod a VS Code Copilot Chatbe (mappa gyökeréből), és hagyod, hogy dolgozzon. A promptok a korábbi ImpactShop-állapotodra, a CAPI-proxy scaffoldra és a staging→prod pipeline-odra épülnek. Mindegyik sprint: cél, elfogadási kritériumok, módosítandó fájlok, generálandó kód, QA smoke-test, majd „Next steps”.

Megjegyzés: a promptok nem piszkálják a wp-config.php-t; a MU pluginek és a bin/impactctl a te jelenlegi architektúráddal kompatibilisek. Minden kód egyetlen blokkban itt van, és minden sprint önállóan futtatható.

──────────────────────────────────────────────────────────────────────────────
CODEx MASTER SCRIPT · SHARITY IMPACTSHOP · END-TO-END ADS & ANALYTICS PIPELINE
──────────────────────────────────────────────────────────────────────────────

GLOBAL INSTRUCTIONS (paste once before Sprint 0)
You are Codex acting as a senior full-stack engineer. 
Repo structure is WordPress + custom MU plugins + Node service(s) + bin CLI + .codex ops.
Do not modify wp-config.php. Keep UTF-8, no BOM. 
All scripts must be idempotens, safe to re-run, and log into .codex/deploy-log.txt.
Prefer small, composable files. Provide minimal, production-ready defaults.

Use these constants if not present:
- Domain: sharity.hu
- WP paths: wp-content/mu-plugins, public/js
- Node service root: services/
- CLI root: bin/impactctl
- Cache and logs: .codex/

For each Sprint below:
1) Create/patch files exactly as requested.
2) Add comments: 3-liner header WHAT/WHY/HOW at top of each new file.
3) Append a one-line status to .codex/deploy-log.txt: "YYYY-MM-DDTHH:mm:ssZ SPRINT-X OK: <summary>"
4) Output a ready-to-run "QA commands" section.

If a file already exists, patch it minimally. 
If an env var is missing, read from .env or fall back to safe dummy.

──────────────────────────────────────────────────────────────────────────────
SPRINT 0 – Repo Bootstrap, Safety Net, Codex Harness
Goal
- Stabilize project skeleton for Codex-driven edits, consistent logs, sanity checks.

Acceptance
- `bin/impactctl status` prints domain, pixel, CAPI health.
- `.codex/deploy-log.txt` exists and receives entries.
- Pre-commit QA hook (optional) runs shellcheck on bin/* and basic JSON/PHP lint.

Tasks
- Ensure .codex/deploy-log.txt creation.
- Create .codex/hooks/pre-commit.sh with bash safety checks (shellcheck optional).
- Add bin/dev-qa.sh to run: node -v, php -v, jq presence, lint JS/PHP/JSON quick scan.

QA commands
- bash bin/dev-qa.sh
- bin/impactctl status

──────────────────────────────────────────────────────────────────────────────
SPRINT 1 – Tracking v1 (Browser + CAPI dual fire, clid cookies, WP MU glue)
Goal
- Production-ready ViewContent/PageView dupla forrás (Browser+Server) dedup event_id-vel.
- First-party clid cookie (fbclid/gclid/ttclid/dclid) + továbbítás külső linkekre.

Acceptance
- Events Manager: ugyanazon event_id Browser és Server source-szal.
- /capi/health → {ok:true}
- ImpactShop kártyák kattintásakor POST /capi/event/meta 200.

Tasks
- services/capi-proxy/index.js: add robust error classes, 429 retry-after support, structured JSON logs.
- public/js/impact-ads.js: add consent gate stub `window.IMPACT_CONSENT_OK === true` feltételre.
- wp-content/mu-plugins/impact-ads-bridge.php: add admin notice when META_PIXEL_ID unset; enqueue csak consent után (wp_localize_script flag).
- .codex/ops/nginx-capi-proxy.conf: comment a Cloudflare + X-Forwarded-For kezeléshez.

QA commands
- bin/impactctl capi:start
- Open: https://sharity.hu/impactshop?fbclid=TEST123
- Click a [data-impact-shop] link → check Network: /capi/event/meta 200
- Events Manager "Test events": Browser+Server visible.

──────────────────────────────────────────────────────────────────────────────
SPRINT 2 – Unified Ledger + Reconcile Skeleton (WordPress)
Goal
- Egyesített könyvelés a hirdetés-indukált eseményekhez és shop klikkekhez.
- Minimális admin riport és napi összevetés váz.

Acceptance
- New DB table: wp_impact_ledger (delta safe installer).
- PHP API: impact_ledger_insert(), impact_ledger_report_range().
- CLI: `impactctl reconcile:meta-vs-wp --date=YYYY-MM-DD` placeholder JSON diffet ír.

Tasks
- Create mu-plugins/impact-ledger.php:
  - On activation (mu-load safe): create table if not exists.
  - Table columns: id, ts, source ENUM('shop','view'), ngo_code, advertiser_code, amount DECIMAL(12,2), currency CHAR(3), status ENUM('approved','pending','rejected'), event_id VARCHAR(64), meta JSON.
  - Functions:
    - impact_ledger_insert($args) with sanitization, default currency EUR.
    - impact_ledger_report_range($from,$to,$filters=[]) returning aggregates.
  - WP-Admin page: Tools › Impact Ledger (from, to, totals, export CSV).
- Modify impact-ads-bridge.php: on ViewContent server ack, optionally log to ledger with status 'pending' and meta raw payload (feature flag IMPACT_LEDGER_LOG_VIEWS=1).
- bin/impactctl:
  - Add subcommand reconcile:meta-vs-wp --date=... (load .codex/ads/meta/* JSON for the day, compare clicks/impressions/spend to ledger events; just print a JSON summary + save to .codex/reconcile/YYYY-MM-DD.json)

QA commands
- wp-cli eval 'echo function_exists("impact_ledger_report_range")?"OK":"NO";'
- bin/impactctl ads:fetch --meta ; bin/impactctl reconcile:meta-vs-wp --date=$(date -I -d "yesterday")

──────────────────────────────────────────────────────────────────────────────
SPRINT 3 – Meta Insights Fetch v2 + Scheduler (cPanel cron friendly)
Goal
- Meta Insights stabil fetch, backoff, napi rotáció, JSON + CSV export.

Acceptance
- `.codex/ads/meta/ACC_YYYY-MM-DD.json` + `.csv` is generated.
- Backoff on 429, token error logging separate file.

Tasks
- bin/impactctl ads:fetch --meta: 
  - add `--since` `--until` args; default yesterday UTC.
  - write CSV sibling via `jq -r` (create helper bin/json2csv.js if needed).
  - log lines appended to deploy log.
- .codex/cron/meta-insights.sh: cron friendly wrapper, ensures `PATH`, checks jq, retries up to 3.

QA commands
- bash .codex/cron/meta-insights.sh
- Check .codex/ads/meta/*.csv

──────────────────────────────────────────────────────────────────────────────
SPRINT 4 – TikTok & GA4 Server Adapters (optional but wired)
Goal
- Node adapter bővítések: /event/tiktok és /event/ga4 kompatibilis payloadok.

Acceptance
- POST /capi/event/tiktok returns 2xx in test mode.
- POST /capi/event/ga4 returns 2xx with measurement protocol.

Tasks
- services/capi-proxy/index.js:
  - add /event/tiktok and /event/ga4 endpoints with env-gated enable.
  - share hashPII and clid cookie logic where applicable.
- public/js/impact-ads.js:
  - add feature flags data-attrs: data-tt, data-ga4; no-op if unset.

QA commands
- curl -XPOST http://localhost:8787/event/ga4 -d '{"event_name":"page_view"}' -H 'content-type: application/json'

──────────────────────────────────────────────────────────────────────────────
SPRINT 5 – Reporting UI v1 (WP Admin + Front Blocks)
Goal
- Admin: havi riportok (NGO/Shop/Advertiser dimenziók) + CSV export.
- Front: rövid /impacthub widget shortcode-ok.

Acceptance
- Admin menü „Impact Reports” → táblázat + export CSV.
- Shortcodes: [impact_total], [impact_total_range from="YYYY-MM-DD" to="YYYY-MM-DD"] működnek a ledger adataiból.

Tasks
- mu-plugins/impact-reports.php:
  - Admin page with date filters; uses impact_ledger_report_range.
  - Export link: outputs text/csv.
- mu-plugins/impact-shortcodes.php:
  - Implement [impact_total] and [impact_total_range] based on ledger.

QA commands
- Open WP admin → Impact Reports, run export.
- Place shortcode on a test page and verify output.

──────────────────────────────────────────────────────────────────────────────
SPRINT 6 – Fraud/Anomaly Guardrails v1
Goal
- Minimális anomália detektálás és riasztás (log + JSON artefakt).

Acceptance
- .codex/anomaly/YYYY-MM-DD.json keletkezik ha spike/outlier van.
- bin/impactctl guard:scan writes summary to deploy log.

Tasks
- bin/impactctl guard:scan:
  - heuristic rules: velocity_spike, geo_outlier, device_fingerprint counts (stub).
  - scans last 24h ledger + raw CAPI logs (if any), outputs JSON.

QA commands
- bin/impactctl guard:scan

──────────────────────────────────────────────────────────────────────────────
SPRINT 7 – Monthly Close Automation
Goal
- Minden hónap 5-én összesített jelentés + könyvelési CSV.

Acceptance
- .codex/monthly/2025-10_close.csv + .json summary generálódik.
- Deploy log bejegyzés készül.

Tasks
- .codex/cron/monthly-close.sh:
  - calculates previous month range
  - calls wp-cli via eval to get totals
  - writes CSV + JSON summary + posts a short text file for email/slack relay.

QA commands
- bash .codex/cron/monthly-close.sh

──────────────────────────────────────────────────────────────────────────────
SPRINT 8 – Campaign Deploy Skeleton (Meta first)
Goal
- impactctl campaign:deploy --meta --dry-run képes lenne kampány/adset/ad kreatív skeletont készíteni (NEM élesít, csak request-preview).

Acceptance
- .codex/campaigns/requests/* contains pretty-printed POST bodies.
- Dry-run prints what would be sent (account, objective, targeting, placements).

Tasks
- bin/impactctl campaign:deploy --meta [--dry-run]:
  - read creative JSON from .codex/campaigns/blueprints/*.json (provide example)
  - produce Graph API request JSONs; if not dry-run, POST (but default keep dry).

QA commands
- bin/impactctl campaign:deploy --meta --dry-run

──────────────────────────────────────────────────────────────────────────────
SPRINT 9 – Public Impact API (read-only) + Rate Limit
Goal
- Minimal REST (WP) read-only végpontok metrikákhoz, rate-limittel.

Acceptance
- /wp-json/impact/v1/metrics?from=&to= returns JSON totals.
- Basic rate limit per IP (transient), 60 rpm.

Tasks
- mu-plugins/impact-api.php:
  - route /impact/v1/metrics
  - verifies simple nonce (optional), rate limit with transients.
  - data via impact_ledger_report_range.

QA commands
- curl yoursite/wp-json/impact/v1/metrics?from=2025-10-01&to=2025-10-10

──────────────────────────────────────────────────────────────────────────────
SPRINT 10 – Gamification Hooks (badges table + award logic stub)
Goal
- User badge keretrendszer (WP users táblához kötve), később UI.

Acceptance
- Table: wp_impact_badges (user_id, badge_key, ts).
- Function: impact_award_badge($user_id,$badge_key).
- Hook példa: első ViewContent → 'first_donation_touch' badge.

Tasks
- mu-plugins/impact-gamification.php:
  - installer + functions + minimal admin list page.

QA commands
- wp-cli eval 'impact_award_badge(1,"first_donation_touch"); echo "OK";'

──────────────────────────────────────────────────────────────────────────────
SPRINT 11 – Corporate Dashboard Skeleton
Goal
- Vállalati összesítők (összekapcsolt user-ek/kuponok alapján) placeholder.

Acceptance
- Admin oldal: Impact › Corporate – stat kártyák dummy aggregátummal.

Tasks
- mu-plugins/impact-corporate.php: admin page + TODO stubs.

QA commands
- Open admin → Impact › Corporate.

──────────────────────────────────────────────────────────────────────────────
APPENDIX – Code Sketches to Generate (patch/extend where exist)

1) .codex/hooks/pre-commit.sh
#!/usr/bin/env bash
set -euo pipefail
# WHAT: quick QA hook; WHY: prevent silly mistakes; HOW: lint/sanity only
command -v jq >/dev/null || { echo "jq missing" ; exit 1 ; }
find . -name "*.json" -maxdepth 5 -print0 | xargs -0 -I{} jq empty {} || { echo "JSON lint fail"; exit 1; }
echo "[hook] ok"

2) bin/dev-qa.sh
#!/usr/bin/env bash
set -euo pipefail
# WHAT: environment QA; WHY: fast fail; HOW: check binaries and lint
echo "Node: $(node -v)"; echo "PHP: $(php -v | head -n1)"; command -v jq >/dev/null && echo "jq: OK" || echo "jq: MISSING"
[ -f ".codex/deploy-log.txt" ] || touch .codex/deploy-log.txt
echo "$(date -u +%FT%TZ) DEV-QA OK" >> .codex/deploy-log.txt

3) mu-plugins/impact-ledger.php  (installer + insert/report + admin page)
<?php
// WHAT: unified ledger; WHY: cross-channel accounting; HOW: safe installer + helpers
add_action('muplugins_loaded', function(){
  global $wpdb; $t=$wpdb->prefix.'impact_ledger';
  $charset = $wpdb->get_charset_collate();
  $sql = "CREATE TABLE IF NOT EXISTS $t (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source ENUM('shop','view') NOT NULL,
    ngo_code VARCHAR(64) NULL,
    advertiser_code VARCHAR(64) NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    status ENUM('approved','pending','rejected') NOT NULL DEFAULT 'pending',
    event_id VARCHAR(64) NULL,
    meta JSON NULL
  ) $charset;";
  require_once ABSPATH.'wp-admin/includes/upgrade.php';
  dbDelta($sql);
});
function impact_ledger_insert($args){
  global $wpdb; $t=$wpdb->prefix.'impact_ledger';
  $def=['source'=>'view','ngo_code'=>null,'advertiser_code'=>null,'amount'=>0,'currency'=>'EUR','status'=>'pending','event_id'=>null,'meta'=>null];
  $a = array_merge($def, array_intersect_key($args,$def));
  $wpdb->insert($t, $a);
  return $wpdb->insert_id;
}
function impact_ledger_report_range($from,$to,$filters=[]){
  global $wpdb; $t=$wpdb->prefix.'impact_ledger';
  $where = $wpdb->prepare("WHERE ts BETWEEN %s AND %s",$from,$to);
  if(isset($filters['status'])) $where.=" AND status='".esc_sql($filters['status'])."'";
  $sum = $wpdb->get_row("SELECT COUNT(*) c, SUM(amount) s FROM $t $where");
  return ['count'=>intval($sum->c),'sum'=>round(floatval($sum->s),2)];
}
add_action('admin_menu', function(){
  add_management_page('Impact Ledger','Impact Ledger','manage_options','impact-ledger', function(){
    $from = $_GET['from'] ?? date('Y-m-01'); $to = $_GET['to'] ?? date('Y-m-d');
    $r = impact_ledger_report_range($from.' 00:00:00',$to.' 23:59:59',[]);
    echo '<div class="wrap"><h1>Impact Ledger</h1>';
    echo '<form><input type="date" name="from" value="'.esc_attr($from).'">-<input type="date" name="to" value="'.esc_attr($to).'"><button class="button">Filter</button></form>';
    echo '<p><b>Rows:</b> '.esc_html($r['count']).' <b>Total:</b> '.esc_html($r['sum']).' EUR</p>';
    echo '<p><a class="button" href="?page=impact-ledger&from='.$from.'&to='.$to.'&export=csv">Export CSV</a></p>';
    if(($_GET['export'] ?? '')==='csv'){ header('Content-Type:text/csv'); header('Content-Disposition:attachment; filename="impact-ledger.csv"'); echo "ts,source,amount,currency,status\n"; exit; }
    echo '</div>';
  });
});

4) bin/impactctl patches (reconcile + guard + campaigns stubs)
# Add cases:
#  - reconcile:meta-vs-wp --date=YYYY-MM-DD
#  - guard:scan
#  - campaign:deploy --meta [--dry-run]
# Each should create .codex/* artefacts and append to deploy-log.

5) mu-plugins/impact-reports.php (admin monthly report + CSV)

6) mu-plugins/impact-shortcodes.php ([impact_total], [impact_total_range])

7) .codex/cron/meta-insights.sh and .codex/cron/monthly-close.sh (cron wrappers)

8) services/capi-proxy/index.js (extend with 429 backoff + tiktok/ga4 endpoints)

9) public/js/impact-ads.js (consent gate, feature flags)

10) mu-plugins/impact-api.php (read-only REST; rate limit)

11) mu-plugins/impact-gamification.php (badges table + award helper)

12) mu-plugins/impact-corporate.php (admin skeleton)

──────────────────────────────────────────────────────────────────────────────
FINALIZE (Codex, print at end of each sprint)
- Append log line into .codex/deploy-log.txt with ISO time and summary.
- Print "QA commands" block for me to copy/paste.
- Do not change server credentials or wp-config.
──────────────────────────────────────────────────────────────────────────────

<!-- IMPACTALL: AUTOLOAD -->
### Impactall – Ads integráció gyors infók (nem secret)
- CAPI base URL: `https://app.sharity.hu/wp-json/impact/v1/capi`
- CAPI endpoints: `/event/meta`, `/event/tiktok`, `/event/ga4`, `/event/googleads`, `/event/youtube`
- CAPI health: `/health`
- Ads management: `https://app.sharity.hu/wp-json/impact/v1/ads/execute`
- Secret fájlok (szerver): `/home/sharityh/app/secrets/ads-management.secret`, `/home/sharityh/app/secrets/ads-execute-mode`, `/home/sharityh/app/secrets/ads-management.json`
- Meta ad account: `act_704809472916006`
- Meta page ID: `409581609762060`
- TikTok advertiser ID: `7415920446899765249`
- Google Ads MCC (login customer): `6169110444`
- Google Ads customer ID: `8974881927`
- Google Ads conversion action ID: `7440853323`
- YouTube conversion: ugyanaz, mint a Google Ads conversion action ID
- Developer token státusz: test → management hívás csak Approved után működik
- AI Agent API (prod, SSH): `http://127.0.0.1:4000` (s59.tarhely.com-on, publikus reverse-proxy nélkül)
