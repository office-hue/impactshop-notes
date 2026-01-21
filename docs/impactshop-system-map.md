# Impact Shop System Map (authoritative)

## 0) Scope & goal
This document is the single source of truth for:
- shortcodes and their outputs
- data sources (CSV, REST, API)
- redirect flows (/go, /go-deal)
- donation math rules
- guard/lock policy (bastyavedelem)

Any change in this system must be reviewed against this map.

---

## 1) Environments
- **Production**: `/home/sharityh/app`
- **Staging**: `/home/sharityh/app-staging`
- Deploy path: `wp-content` mapping (`bin/deploy-wpcontent-map.sh`)
- Default host: `https://app.sharity.hu`

---

## 2) Active plugins (production)
From WP-CLI (`wp plugin list --status=active`):
- advanced-custom-fields
- all-in-one-accessibility, pojo-accessibility
- complianz-gdpr, complianz-terms-conditions
- dognet-pap-publisher
- elementor, elementor-pro, essential-addons-for-elementor-lite
- head-footer-code / insert-headers-and-footers
- hellopack-client
- impact-bridge-local
- impact-local-tops
- impact-mini-shortcodes
- impact-report-mvp
- impactshop-report-compat
- impact-simple-widgets
- limit-login-attempts-reloaded, really-simple-ssl, redirection, wp-file-manager, duplicate-post, wordpress-seo
- sharity-aff-check-lite
- sharity-impact-mini
- sharity-offers-import
- google-site-kit, templately, worker, wp-all-import

Notes:
- MU-plugins override many of the above and are loaded unconditionally.
- WPCode snippet status is unknown from CLI; treat as “potential external code”.

---

## 3) MU-plugins (core Impact Shop)
Critical MU-plugins for business logic:
- `impactshop-netflix-shortcodes.php` – all Netflix/deals/coupons shortcodes + CSV loaders
- `impactshop-boot.php` – `/go` and `/go-deal` route + Dognet link generation
- `impactshop-metrics-ngo.php` – REST ticker/leaderboard/activity based on Dognet conversions
- `impactshop-rest-totals.php` – REST totals endpoint `/impact/v1/totals`
- `impactshop-rest-coupons.php` – REST coupons (Dognet)
- `impactshop-full-leaderboard.php` – full NGO leaderboard HTML
- `impact-sum-sticky-ui.php` – sticky sum UI uses totals endpoint
- `sharity-default-d1-helper.php` – default NGO (d1) injection + front link rewrite
- `impact-banners-fillout-rewriter.php` – converts Fillout links to /go-deal when d1 exists
- `impactshop-go-bridge.php`, `impact-arukereso-hardguard.php`, `impact-cid-arukereso-fix.php` – redirect guards / arukereso fixes

---

## 4) Shortcodes & outputs

### 4.1 Shops / Deals / Coupons
- **`[impactshop_netflix]`**
  - File: `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`
  - Inputs: Shops CSV + Banners CSV + Dognet coupons (badge) + CJ links
  - Output: Shop cards (Netflix rail) with CTA links
  - CTA logic:
    - if `d1` present → `/go/{shop}?d1=...`
    - if no `d1` → Fillout URL `...?shop=...` (and optional `u`)
  - NGO banner: “Jelenleg ezt a szervezetet támogatod: …” when `d1` exists

- **`[impact_deals_netflix]`**
  - File: `impactshop-netflix-shortcodes.php`
  - Inputs:
    - REST: `/wp-json/impactshop/v1/deals_banners` or `/impactshop/v1/deals?type=banner`
    - Banners CSV (fallback)
  - Output: Deal cards (image + shop + badge + price)
  - CTA logic (current enforced behavior):
    - if `d1` present → `/go-deal/{shop}?d1=...&u=base64(product)`
    - if no `d1` → Fillout `...?shop=...&u=...`

- **`[impact_coupons_netflix]`**
  - File: `impactshop-netflix-shortcodes.php`
  - Inputs: Dognet coupons API + Shops CSV
  - Output: Coupon cards with expiry + copy
  - CTA logic:
    - if `d1` → `/go/{shop}?d1=...`
    - if no `d1` → Fillout `...?shop=...`

### 4.2 Identity / account panel
- **`[impactshop_identity_panel]`**
  - File: `wp-content/mu-plugins/impactshop-identity-panel.php`
  - Inputs: pseudo ID cookie, recovery code, nickname (stored in WP option)
  - Output: account panel + recovery form + share/copy actions

- **`[impactshop_identity_id]`**
  - File: `wp-content/mu-plugins/impactshop-identity-panel.php`
  - Output: account message slot (targeted/global messages)

### 4.2 Leaderboard / totals
- **`[impact_leaderboard]`**
  - File: `wp-content/plugins/sharity-impact-mini/sharity-impact-mini.php`
  - REST: `/wp-json/impact/v1/leaderboard`
  - Supports params: `tab`, `limit`, `from`, `to`, `status`, `currency`, `rate_huf`

- **`[impact_full_leaderboard]`**
  - File: `wp-content/mu-plugins/impactshop-full-leaderboard.php`
  - Output: full NGO leaderboard HTML (rich layout)

- **`[impact_sum_sticky]`**
  - File: `wp-content/mu-plugins/impactshop-sum-pack.php`
  - REST: `/wp-json/impact/v1/totals`

### 4.3 Ticker / activity / strict fallbacks
- **`[impact_ticker]`, `[impact_leaderboard]`, `[impact_activity]`**
  - Primary source: `wp-content/mu-plugins/impactshop-metrics-ngo.php`
  - Fallback / compat: `wp-content/mu-plugins/impact-combat-pack.php`, `wp-content/mu-plugins/sharity-impact-compat.php`
  - Strict variants: `impactshop-strict-pack.php` (`impact_ticker_strict`, `impact_activity_strict`)
  - Caches:
    - `impactshop_ticker_v1` (TTL 180s)
    - `impactshop_lb_v1_{tab}` (TTL 300s)
    - `impactshop_activity_v2` (TTL 120s)

### 4.4 Diagnostics & ops
- **`[impact_diag]`, `[impact_flush]`**
  - File: `wp-content/mu-plugins/impact-diag.php` / `impact-diag-flush.php`
  - Output: diagnostics + cache flush helpers (use with care)

- **`[impactshop_probe]`**
  - File: `wp-content/mu-plugins/impactshop-probe.php`
  - Output: probe data for quick verification

---

## 5) Redirect flow (go / go-deal)

- **Routing**: `/go/{shop}` and `/go-deal/{shop}`
- Handler: `wp-content/mu-plugins/impactshop-boot.php`
- Required params: `shop`, `d1`
- Optional: `u` (base64 product URL)
- Output: Dognet affiliate link (generated or fallback base URL)

Flow:
1) User clicks CTA
2) `/go` or `/go-deal` endpoint validates `shop` + `d1`
3) Dognet link generated with campaign ID
4) Redirect (307) to final affiliate link

---

## 6) REST endpoints

- `/impact/v1/ticker` → `impactshop-metrics-ngo.php`
- `/impact/v1/leaderboard` → `impactshop-metrics-ngo.php`
- `/impact/v1/activity` → `impactshop-metrics-ngo.php`
- `/impactshop/v1/totals` → `impactshop-rest-totals.php`
- `/impactshop/v1/coupons` → `impactshop-rest-coupons.php`
- `/impactshop/v1/deals_banners` → from banners/deals service (internal REST path)

---

## 7) External data sources

1) **Shops CSV (Google Sheets)**
   - Source: `impactshop_settings()['shops_csv_url']`
   - Required columns: `shop_slug`, `name`, `dognet_base`, `default_d1`, etc.

2) **Banners CSV (Google Sheets)**
   - Source: `impactshop_settings()['banners_csv_url']`
   - Used for deals cards (titles/prices/images)

3) **Dognet API**
   - Conversions: for ticker/leaderboard/activity
   - Coupons: for coupon cards and badges
   - Campaign link generation: for go/go-deal

4) **CJ API**
   - `impactshop_load_cj_links()` for extra shop/deal items

5) **Fillout**
   - Form URL: `https://form.fillout.com/t/eM61RLkz6jus`
   - Used when no `d1`

6) **Local CSV / HTML assets**
   - `ngo_codes.csv` → NGO slug → ékezetes név mapping
   - `ngo-leaderboard.html` → full NGO leaderboard layout template
   - `shop-donation-cards.html` → optional shop donation cards template

---

## 8) Donation math rules
- Donation = **50% of publisher commission**
- Aggregated in:
  - `impactshop-metrics-ngo.php` (ticker/leaderboard/activity)
  - `impactshop-rest-totals.php` (totals endpoint)

Notes:
- `/impactshop/v1/totals` aggregates orders + commission by shop/ngo and returns raw amounts + commissions.
- Displayed donation numbers use `commission * 0.5` unless explicitly overridden in the UI layer.

---

## 9) Guard policy (bastyavedelem) – planned enforcement

### 9.1 Protected files (read-only)
These must be locked after stabilization:
- `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`
- `wp-content/mu-plugins/impactshop-boot.php`
- `wp-content/mu-plugins/impactshop-metrics-ngo.php`
- `wp-content/mu-plugins/impactshop-rest-totals.php`
- `wp-content/mu-plugins/impactshop-rest-coupons.php`
- `wp-content/mu-plugins/impactshop-full-leaderboard.php`
- `wp-content/mu-plugins/sharity-default-d1-helper.php`
- `wp-content/mu-plugins/impact-banners-fillout-rewriter.php`
- `wp-content/mu-plugins/impactshop-go-bridge.php`
- `wp-content/mu-plugins/impact-arukereso-hardguard.php`
- `wp-content/mu-plugins/impact-cid-arukereso-fix.php`
- `wp-content/mu-plugins/impactshop-sum-pack.php`

### 9.2 Enforcement design
- Server-side file immutability (e.g. `chattr +i` on Linux) for protected files.
- Deploy guard: checksums for protected files; deploy fails if change not explicitly approved.
- Impactall integration: include a hash report + “protected file changed” alert.
- Route lock: prevent accidental rewrite rule changes for `/go` and `/go-deal`.
- Solo-safe lock: optional `IMPACTSHOP_GUARD_LOCK_MODE=chmod` (pre/post chmod lock).
- Safe-mode: `IMPACTSHOP_GUARD_SAFE_MODE=1` → emergency override tiltás (csak normál jóváhagyás).

### 9.3 Change control policy
- All protected files require explicit manual approval.
- Changes must be documented in `notes.md` and the system map.
- Emergency override: soft confirm (type `I accept the risk`) when rate limit exceeded.
- Hash manifest integrity: `impactshop-guard-hashes.sha256` ellenőrzés a bástya guardban.
- Non-interactive mód: `--non-interactive` + `--auto-approve` (külön `IMPACTSHOP_GUARD_APPROVE_REASON` okkal).

---

## 10) Impact Shop dataflow (high level)

### 10.1 Shops / deals / coupons
1) CSV + CJ links → `impactshop-netflix-shortcodes.php`
2) CTA built based on `d1` and Fillout
3) Redirect → `/go` or `/go-deal`
4) Dognet link generation → external merchant

### 10.2 Metrics / leaderboard / ticker
1) Dognet conversions → `impactshop-metrics-ngo.php`
2) Filters: date range + status (rejects excluded)
3) Aggregation: commission * 0.5
4) REST endpoints → shortcodes render UI

### 10.3 Totals / sticky sum
1) Dognet conversions → `impactshop-rest-totals.php`
2) Campaign map from shops CSV
3) Aggregation by shop/ngo → REST totals
4) Sticky UI uses totals endpoint

---

## 11) Server + WP settings inventory (to capture)

### 11.1 Required environment references (no secrets stored here)
- SSH host/user: `.deploy.production.env`, `.deploy.staging.env`
- Central secrets: `~/.impact-secrets/env.d/capi.env`
- Dognet credentials are currently in `impactshop-netflix-shortcodes.php` (must be moved to secrets when guard is enabled).

### 11.2 cPanel / cron
- Capture active cron jobs (cPanel → Cron Jobs).
- Record any ledger sync / coupon sync / Dognet sync cron entries.
- Document WP Cron settings (if disabled, note external cron).

### 11.3 WPCode / snippets
- Export active snippets list (WP admin) and record:
  - snippet name
  - status (active/inactive)
  - scope (front/admin)
  - dependencies (shortcodes/hooks)

Latest WP-CLI probe (2026-01-21):
- `wp post list --post_type=wpcode_snippet` returned **no rows** on staging and production.
- Conclusion: nincs aktív WPCode CPT (ha mégis van, akkor más tárolási mód vagy külön plugin).

### 11.4 WP‑Cron (site cron)
Latest WP‑CLI list (2026-01-21):
- **Staging** (`/home/sharityh/app-staging`):
  - `impact_totals_cache_prewarm` (2 min)
  - `impactshop_social_ledger_sync` (10 min)
  - `impactshop_vote_cron` (5 min)
  - `impact_publisher_token_health_cron` (1 h)
  - `impactshop_pin_cleanup` (1 day)
  - plus standard WP/Jetpack/Yoast/Updraft/etc.
- **Production** (`/home/sharityh/app`):
  - `impact_totals_cache_prewarm` (2 min)
  - `impactshop_social_ledger_sync` (10 min)
  - `impactshop_vote_cron` (5 min)
  - `impact_publisher_token_health_cron` (1 h)
  - `impactshop_pin_cleanup` (1 day)
  - plus standard WP/Jetpack/Yoast/Updraft/etc.

### 11.5 Elementor template inventory
Latest `elementor_library` list (2026-01-21):
- **Staging**: `WPKurzus – Adatvédelmi nyilatkozat minta v1.0`, `Justclear`, `iframe`, `MKE_aukcio`, standard Elementor headers/footers.
- **Production**: same base templates + drafts `1–6`, `Elementor Loop Item #17510`.

### 11.6 cPanel cron
- `uapi Cron listcron` nem elérhető ezen a hoston (Cron modul hiányzik).
- cPanel cron lista (manuális export, 2026-01-21):
  1) `1 * */1 * *`  
     `/usr/bin/curl -fsS -A "SharityCron/1.0" "https://app.sharity.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1`
  2) `0,30 * * * *`  
     `bash $HOME/impact-tools/access-guard.sh ensure >/dev/null 2>&1`
  3) `*/5 * * * *`  
     `/home/sharityh/ai-agent/ai-agent-keepalive.sh >/dev/null 2>&1`
  4) `30 3 * * *`  
     `/bin/bash -lc "cd /home/sharityh/app && source .codex/.env.guard && .codex/cron/cj-coupon-sync.sh >> /home/sharityh/.codex/logs/cj-coupon-sync.cron.log 2>&1"`
- 2026-01-21: crontab ténylegesen frissítve `/var/spool/cron/sharityh` alatt (host javítva `app.sharity.hu`-ra).

---

## 12) Guard implementation plan (impactall integration)

1) **Hash manifest**
   - Generate `docs/impactshop-guard-hashes.json` with SHA256 per protected file (`bin/impactshop-guard-init.sh`).
2) **Guard check**
   - Guard script: `.codex/guards/impactshop-bastya-guard.sh` (impactall-compatible).
   - Compares hashes and prints:
     - PASS if unchanged
     - FAIL if mismatch without approval token
3) **Unlock protocol**
   - Manual approval required; temporary unlock window with explicit ticket reference.
4) **OS-level lock**
   - Apply `chattr +i` (or filesystem immutable) on prod/staging after approval.
5) **Guarded deploy**
   - Wrapper: `bin/impactshop-guard-deploy.sh` (self-approval + snapshot + emergency override).
6) **Rollback**
   - Script: `bin/impactshop-guard-rollback.sh` (restore protected files + hash manifest).
7) **Non-interactive**
   - `bin/impactshop-guard-deploy.sh --non-interactive --auto-approve` (CI/cron).

---

## 13) Outstanding mapping tasks (in progress)
1) Export WPCode snippets list + attach to this map. (done)
2) Capture cPanel cron list and document exact commands. (done)
3) Record Elementor templates/shortcodes used on:
   - Impact Shop page
   - Full leaderboard page
   - Sticky sum placement
4) Confirm CJ + Dognet endpoints and auth ownership (who maintains keys).

---

## 14) Next steps (implementation plan)
1) Export all active snippets (if any) and list them in this map.
2) Produce a full dataflow diagram (shortcode → API → output).
3) Implement “protected file” hashing + guard in `impactall`.
4) Define and apply OS-level read-only enforcement on prod/staging.
