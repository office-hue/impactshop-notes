# Ads Watch initial banner block fix

Date: 2026-03-26

## Problem

`impact-challenge` page load could immediately start the idle auto-banner loop inside the player area. That banner sat above the player and hid the `Reklám megtekintése` button on mobile and desktop, making Ads Watch appear frozen before the user could start a video.

## Change

- removed the eager `loadAutoBanner()` call from page initialization
- changed the legacy idle auto-banner completion path to hide itself and return control to the player instead of chaining into another banner
- bumped `IMPACTSHOP_ADS_WATCH_VERSION` to `2.5.31` for CDN/browser cache bust

## Verification

- `node --check wp-content/mu-plugins/impactshop-ads-watch.js`
- `php -l wp-content/mu-plugins/impactshop-ads-watch.php`
- production page references `impactshop-ads-watch.js?ver=2.5.31`
- live Playwright snapshot shows `▶ Reklám megtekintése` again on desktop and mobile viewport
