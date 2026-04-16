# 2026-04-07 - CTA Freeze Fix v2.5.53

## Summary

Fix two issues discovered after v2.5.52 deploy: (1) CTA button click didn't navigate to sponsor page (missing `window.open()`), (2) MutationObserver in `ui-cta-bundle.js` caused UI freeze during ad playback (~240 observer callbacks/sec from RAF progress updates).

## Protected Files Touched

- `wp-content/mu-plugins/impactshop-ads-watch.js`
- `wp-content/mu-plugins/impactshop-ads-watch.php`
- `wp-content/mu-plugins/impactshop-ads-watch-ui-cta-bundle.js`
- `wp-content/mu-plugins/impactshop-ads-watch-ui-cta-bundle.php`

## Key Changes

1. CTA click handler: add `window.open(href, '_blank', 'noopener')` after `event.preventDefault()` — sponsor page now opens in new tab
2. CTA repeat clicks: still navigate but skip duplicate bonus award
3. ui-cta-bundle.php: `return;` before `add_action()` — completely disables MutationObserver bundle
4. ui-cta-bundle.js: re-entrancy guard (`DEFER_STATE._applying`) as safety net for future re-enable
5. Version bump: 2.5.52 → 2.5.53, bundle: 20260326.1 → 20260407.2

## Rollback

Revert this commit, redeploy v2.5.52 from git history. To re-enable bundle: remove `return;` from ui-cta-bundle.php.

## Smoke

- Production deployed via SCP (2026-04-07)
- `curl` verified: `impactshop-ads-watch.js?ver=2.5.53` present, `ui-cta-bundle` absent from page source
- MD5 checksums: production = local = backup (all 4 files verified)
