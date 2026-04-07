# 2026-04-07 - Ads Watch Sponsor Video Freeze Fix v2.5.52

## Summary

Fix sponsor video freeze on Impact Challenge page that appeared after Hatás Körök feature activation. v2.5.51 was missing 7 critical sponsor return patterns from v2.5.55.

## Protected Files Touched

- wp-content/mu-plugins/impactshop-ads-watch.js
- wp-content/mu-plugins/impactshop-ads-watch.php
- wp-content/mu-plugins/impactshop-ads-watch.css

## Key Changes

1. externalNavigationSource / externalNavigationVisibilityLost tracking restored
2. Sponsor CTA native _blank link instead of JS window.open
3. Visibility change handler for all modes (not just non-sponsor)
4. adsLoader.contentComplete() placement after ad completion
5. Proper sponsor forward with returnToSponsor() lifecycle

## Rollback

Revert to v2.5.32 (origin/main) via git revert + hotfix-sync.sh

## Smoke Test

- Deployed to production via hotfix-sync.sh (2026-04-07)
- Production header: x-impactshop-adswatch-version: 2.5.52
- web_investigate interactive test: cookie accept + player click + DOM verify = OK
