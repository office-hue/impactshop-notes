# Sharity profile Human Touch shell — Terra QA closure

Date: 2026-09-11

Plan ID: `sharity-profile-summary-live-closure-20260910`

## Identity

- Reviewed worktree/branch:
  `impactshop-notes-fix-sharity-profile-bootstrap-owner-grant-luna-20260910` /
  `fix/sharity-profile-bootstrap-owner-grant-luna-20260910`.
- Published source: PR #211 squash merge
  `05b3d147837127355539bbd46beb919214d7c2e9` equals current `origin/main`.
- Production target readback:
  `wp-content/mu-plugins/impactshop-identity-panel.php` SHA-256
  `d2cc8c83f7707a756f3aa2edb3b48dd63bbec7acc9cd06f04ef0199b92e9aaea`,
  mode `0444`.

## QA result

- PASS, technical: the live `/profil/` response contains the new shell and
  intro, while the exact-source/exact-runtime browser evidence confirms stable
  layout, anchors and profile data.
- REJECTED, product acceptance: the output is not the requested all-page
  floating action bar. The old eight-action `.sharity-action-bar` markup was
  replaced by a two-cell profile/sign-in dock, even though its interaction JS
  still expects the eight `data-bar` actions.
- REJECTED, visual acceptance: the teal glass profile shell is not consistent
  with the live Human Touch reference at `https://sharity.hu/hatas-korok`.
  That reference uses a warm light canvas, dark outlined cards and controls,
  playful purple/lime/coral accents and compact pill actions.

## Corrective implementation boundary

- Restore a real global floating action bar on every eligible app page using
  the retained eight action contracts: video, tasks, shop, donate, profile,
  NGO, messages and points. The profile link remains the canonical
  `/profil/#impactshop-account-top` target.
- Retire the two-cell profile/sign-in dock rather than leaving two competing
  fixed controls.
- Rework the full profile shortcode to the same Human Touch component grammar:
  light canvas, dark outlines, purple/lime/coral accents, structured cards and
  pill controls. Preserve identity, owner-grant, REST, cookie and data logic.
- Add focused static contracts for all eight global action identifiers and the
  absence of the obsolete dock; validate desktop/mobile layout before release.
- This is one bounded Luna/high source package, followed by Terra/high visual
  QA. A later exact production publication remains Sol/high.

## Scope and residuals

The prior technical release is not product-accepted after this QA correction.
The Elementor outer header/footer remains outside the corrective package. The
reused browser run logged two unattributed 403 resource messages; they did not
affect the profile HTTP 200 or layout and are not changed by this package.

No schema, database, cookie policy, cron, watchdog, shared dependency, provider
or further production action is required.
