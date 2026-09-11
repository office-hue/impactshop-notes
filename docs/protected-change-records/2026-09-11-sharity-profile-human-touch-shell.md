# Sharity Human Touch profile shell — protected change record

Date: 2026-09-11

Status: production-accepted; bounded presentation follow-up closed

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/sharity-profile-summary-live-closure-luna-continuity-20260910.md#2026-09-11-human-touch-profile-dock-follow-up",
  "operatorApprovalRef": "operator-approval:sharity-profile-summary-live-closure-20260910",
  "protectedPaths": [
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "wp-content/mu-plugins/impactshop-identity-panel.php"
  ],
  "rollbackNote": "Revert the exact profile-shell checkpoint and restore the prior identity-panel CAS preimage.",
  "smokeTags": [
    "browser:chrome",
    "browser:mobile",
    "flow:legacy-pool-visibility",
    "flow:message-popup",
    "flow:points-jump",
    "flow:profile-open",
    "flow:profile-return-account",
    "flow:profile-return-restore",
    "route:factlens-vb-prod",
    "route:impact-challenge",
    "route:profil"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->

## Scope

Add a profile-only Human Touch presentation shell and friendly intro around
the existing identity card. Data, owner-grant policy, REST contracts, cookies,
schema, cron, watchdog and shared dependencies remain unchanged.

## Protected files touched

- docs/impactshop-guard-hashes.json
- docs/impactshop-guard-hashes.sha256
- wp-content/mu-plugins/impactshop-identity-panel.php

## Minimum evidence

- PHP lint for impactshop-identity-panel.php
- profile summary, owner-policy and profile-shell static tests
- protected hash verification and git diff --check
- one desktop/mobile production DOM/layout check after publication

## Production closure

- PR #211 squash-merged as `05b3d147837127355539bbd46beb919214d7c2e9`.
- Exact-main release: `20260911T082700Z-05b3d147-profile-shell`.
- Production target: `wp-content/mu-plugins/impactshop-identity-panel.php`.
- CAS postimage SHA-256:
  `d2cc8c83f7707a756f3aa2edb3b48dd63bbec7acc9cd06f04ef0199b92e9aaea`;
  remote mode `0444`.
- Production preflight passed all five public control endpoints.
- Browser QA: desktop/mobile HTTP 200, Human Touch intro visible, profile dock
  visible, canonical anchors present, pseudo/nickname/votes visible, 0 px
  horizontal overflow and 0 profile ad markers. Screenshots:
  `/tmp/sharity-profile-shell-live-20260911-yAwN/desktop.png` and
  `/tmp/sharity-profile-shell-live-20260911-yAwN/mobile.png`.
- Rollback remains the recorded exact release rollback command; no database,
  cron, watchdog or shared dependency change.
