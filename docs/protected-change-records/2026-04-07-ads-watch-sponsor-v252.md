# 2026-04-07 - Ads Watch Sponsor Video Freeze Fix v2.5.52

## Summary

Fix sponsor video freeze on Impact Challenge page that appeared after "Hatás Körök" feature activation. The v2.5.51 production code was missing 7 critical sponsor return patterns that existed in the working v2.5.55 version.

Root cause: v2.5.56/v2.5.57 broke things → rolled back to v2.5.49 → incremental patches to v2.5.51, but v2.5.51 was missing sponsor-specific handling from v2.5.55.

v2.5.52 merges the 7 missing sponsor patterns from v2.5.55 into v2.5.51.

## Protected Files Touched

- `wp-content/mu-plugins/impactshop-ads-watch.js`
- `wp-content/mu-plugins/impactshop-ads-watch.php`
- `wp-content/mu-plugins/impactshop-ads-watch.css`

## Key Changes

1. `externalNavigationSource` / `externalNavigationVisibilityLost` tracking restored
2. Sponsor CTA native `_blank` link instead of JS `window.open`
3. Visibility change handler for all modes (not just non-sponsor)
4. `adsLoader.contentComplete()` placement after ad completion
5. Proper sponsor forward with `returnToSponsor()` lifecycle
6. CSS updates for sponsor CTA button styles

## Rollback

Revert to v2.5.51 via `hotfix-sync.sh` using the previous version from git history.

## Smoke Test

- Already deployed to production via `hotfix-sync.sh` (2026-04-07)
- Production header confirmed: `x-impactshop-adswatch-version: 2.5.52`
- `web_investigate` with interactive steps: cookie accept → player click → DOM verify
- Result: 0 JS errors, Google Ads iframe loading, `<video>` element present
