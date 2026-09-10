# Sharity profile summary closure — protected change record

Date: 2026-09-10
Status: source-only Luna checkpoint; no publication or live activation

## Approved bounded touch

This checkpoint implements only profile summary, additive profile API state,
profile-route cache/privacy headers, producer-level ad suppression and the
fail-closed bootstrap correction. Existing owner grants remain the authority;
the central owner-policy registry and cross-module migration are deferred.

## Protected files touched

- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `wp-content/mu-plugins/impactshop-identity-panel.js`
- `wp-content/mu-plugins/impactshop-adsense-head.php`

## Coherence and risk

Directly affected are `/impact/v1/identity/profile`, the identity shortcodes,
`template_redirect`, REST post-dispatch, `wp_head`, Site Kit AdSense filters and
Elementor's exact AdSense widget hook. Points/votes writers, grant schema
migration, other mutation routes, provider and runtime state are unchanged.

Risks are live hook/name drift, stale page-builder output, and the remaining
unmigrated pseudo mutators. The implementation is additive where possible,
uses producer hooks, and does not rewrite rendered HTML.

## Smoke scope and required post-merge/staging checks

- full/compact profile nickname, pseudo, level/points, votes and canonical anchor;
- active, legacy read-only, binding-pending, invalid/revoked and unavailable
  profile states;
- SELECT-only malformed/negative/missing vote normalization and wrong-pseudo
  denial;
- two cookie-jar cache isolation, `private/no-store/no-cache`, `Vary: Cookie`;
- zero repo AdSense/Site Kit/Elementor AdSense output under `/profil/**`, with
  control page ads unchanged;
- keyboard tooltip, mobile layout and no ad-induced layout shift.

No production deploy, database migration, cron/watchdog, push or PR is part of
this record. Rollback is source revert before publication; no live data was
changed.
