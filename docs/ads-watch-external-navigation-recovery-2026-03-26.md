# Ads Watch external-navigation recovery

Date: 2026-03-26

## Problem

Safari users reported that after clicking an autobanner or sponsor CTA that opens a new tab, the original `impact-challenge` tab could come back blank until a manual reload.

The previous mitigation only handled the `pageshow.persisted` branch, which is too narrow when Safari returns without a classic bfcache restore event.

## Change

- Added external-navigation tracking in `impactshop-ads-watch.js`
- Mark outbound sponsor CTA and autobanner clicks before the browser opens the new tab
- Record whether the original tab lost visibility
- Force a reload when the tab becomes visible again or regains focus after that outbound navigation
- Bumped `IMPACTSHOP_ADS_WATCH_VERSION` from `2.5.29` to `2.5.30` to bust Cloudflare/browser cache

## Verification

- `node --check wp-content/mu-plugins/impactshop-ads-watch.js`
- `php -l wp-content/mu-plugins/impactshop-ads-watch.php`
- server grep confirms `2.5.30` and external-navigation helpers are deployed
- live HTML references `impactshop-ads-watch.js?ver=2.5.30`
- direct header check for `?ver=2.5.30` returned `cf-cache-status: MISS`

## Deploy

- targeted rsync of:
  - `wp-content/mu-plugins/impactshop-ads-watch.js`
  - `wp-content/mu-plugins/impactshop-ads-watch.php`
- production host: `sharityh@s59.tarhely.com:/home/sharityh/app/wp-content/mu-plugins/`
