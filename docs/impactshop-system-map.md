# Impact Shop System Map (WIP)

## Purpose
Central mapping of Impact Shop data flows, shortcodes, and guard (bastyavedelem) boundaries.
This file is the baseline for a stricter guard policy and change control.

## Core entrypoints (shortcodes)
- `impactshop_netflix` → `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`
  - Input: Shops CSV, Banners CSV, Dognet API, CJ links
  - Output: Netflix-style shop cards
  - CTA logic:
    - if `d1` present → `/go/{shop}?d1=...` (shop card) or `/go-deal/{shop}?d1=...&u=base64(product)` (deal card)
    - if no `d1` → Fillout URL with `shop` (+ `u` when available)
  - NGO banner: renders “Jelenleg ezt a szervezetet támogatod: …” when `d1` exists
- `impact_deals_netflix` → `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`
  - Input: `/wp-json/impactshop/v1/deals_banners` or `/wp-json/impactshop/v1/deals` + banners feed
  - Output: Deal cards (image + shop + badge + price)
  - CTA logic: same as above
- `impact_coupons_netflix` → `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`
  - Input: Dognet coupons API + shops CSV
  - Output: Coupon cards
  - CTA logic: same as above
- `impact_leaderboard` → `wp-content/plugins/sharity-impact-mini/sharity-impact-mini.php`
  - Uses `/wp-json/impact/v1/leaderboard`
  - Accepts: `tab`, `limit`, `from`, `to`, `status`, `currency`, `rate_huf`
- `impact_full_leaderboard` → `wp-content/mu-plugins/impactshop-full-leaderboard.php`
  - Rich HTML layout for full NGO list
- `impact_sum_sticky` → `wp-content/mu-plugins/impact-sum-sticky-ui.php`
  - Uses `/wp-json/impact/v1/totals`

## Core redirects / go-deal
- `/go/{shop}` and `/go-deal/{shop}` handled by `wp-content/mu-plugins/impactshop-boot.php`
  - Requires: `shop`, `d1` (NGO slug)
  - Uses Dognet campaign link generation
  - `u` parameter (base64 product URL) used for deeplink

## Key data sources
- Shops CSV: `impactshop_settings()['shops_csv_url']`
- Banners CSV: `impactshop_settings()['banners_csv_url']`
- NGO map: `ngo_codes.csv` (root)
- Dognet API: conversions, coupons, campaign links
- CJ links: `impactshop_load_cj_links()`

## Guard (bastyavedelem) scope
- Backend guard: `/go` and `/go-deal` endpoints (redirect control)
- Frontend link rewrite helper: `wp-content/mu-plugins/sharity-default-d1-helper.php`
  - Now **skips Fillout host** to avoid unintended rewrite

## Recent critical settings (must keep)
- Fillout URL: `https://form.fillout.com/t/eM61RLkz6jus`
- Default `d1` fallback in `sharity-default-d1-helper.php`
- Deal CTA logic: `d1` → go-deal + `u`, no `d1` → Fillout (+ `u`)

## TODO (next)
- Full system graph: all shortcodes, plugins, REST endpoints, external APIs
- Active/inactive plugin list (from WP)
- Active snippets list (from WPCode)
- Server + cPanel settings required for operation
- Add guard policy: file-level write protections and deployment rules
