# Sharity profile summary closure — protected change record (checkpoints A+B)

Date: 2026-09-10
Status: source-only Luna checkpoint; no publication or live activation

## Approved bounded touch

Checkpoint A implemented profile summary, additive profile API state,
profile-route cache/privacy headers, producer-level ad suppression and the
fail-closed bootstrap correction. Checkpoint B now closes the approved owner
grant v2 lifecycle and central policy boundary without provider or runtime
activation.

This source-only follow-up additionally corrects first-request init ordering:
owner-grant storage/new-browser bootstrap runs before the legacy pseudo-cookie
callback, while ordinary no-source failures block legacy fallback and leave
profile output unavailable. Query identity overrides and existing legacy
cookie sources remain compatibility paths.

## Protected files touched

- `wp-content/mu-plugins/impactshop-boot.php`
- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `wp-content/mu-plugins/impactshop-identity-panel.js`
- `wp-content/mu-plugins/impactshop-adsense-head.php`
- `wp-content/mu-plugins/000-impactshop-owner-policy.php`
- `wp-content/mu-plugins/impactshop-action-bar.php`
- `scripts/impactshop-owner-policy-inventory.php`
- `tests/impactshop-owner-policy-inventory.test.py`
- `docs/impactshop-protected-files.json`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`

## Coherence and risk

Directly affected are `/impact/v1/identity/profile`, `/identity/restore`,
`/identity/code/generate`, the identity shortcodes, grant schema/lifecycle,
the first-loaded policy registry and route inventory, pseudo-backed profile,
vote, ad-watch, points, NGO, saved-offer, push, tracking and VB2026 mutators,
the action-bar account target, `template_redirect`, REST post-dispatch, `wp_head`,
Site Kit AdSense filters and Elementor's exact AdSense widget hook. Existing
service/webhook/admin callbacks remain authoritative and are explicitly
classified rather than weakened.

Risks are live hook/name drift, DB/schema migration and cookie readback failure,
runtime route registration drift, and unverified staging/browser E2E. The
implementation is fail-closed: schema/DB/readback/cookie/compensation failure
denies owner mutation and can engage safe-disable; no plaintext owner token is
stored or accepted in request bodies.

The follow-up's additional risk is callback ordering: a failed priority-0
bootstrap must not fall through to the priority-1 legacy callback. A
request-local block is asserted by hermetic contracts and honored by boot and
profile resolvers; no cross-request state or new owner-token exposure is added.

## Smoke scope and required post-merge/staging checks

- full/compact profile nickname, pseudo, level/points, votes and canonical anchor;
- active, legacy read-only, binding-pending, invalid/revoked and unavailable
  profile states;
- SELECT-only malformed/negative/missing vote normalization and wrong-pseudo
  denial;
- two cookie-jar cache isolation, `private/no-store/no-cache`, `Vary: Cookie`;
- zero repo AdSense/Site Kit/Elementor AdSense output under `/profil/**`, with
  control page ads unchanged;
- grant v2 pending/active/expiry/supersedes migration, exact-origin negative
  tests, wrong pseudo and other cookie-jar denial, account-switch revocation,
  and coupled owner+pseudo renewal;
- runtime route/policy/callback self-test and token_get_all inventory PASS;
- action-bar profile anchor plus legacy `#impactshop-account` resolver;
- keyboard tooltip, mobile layout and no ad-induced layout shift.
- first ordinary HTML request creates one pending grant plus both cookies;
  second request activates; storage/cookie failure leaves no pseudo and no
  duplicate grant; query and REST paths retain their intended behavior.

No production deploy, database migration execution, cron/watchdog, push or PR
is part of this record. Rollback is forward-safe source disable/revert before
publication; the v2 verifier/table must be retained and no plaintext runtime
rollback exists. No live data was changed.
