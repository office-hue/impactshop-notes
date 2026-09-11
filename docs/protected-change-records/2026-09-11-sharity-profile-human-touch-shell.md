# Sharity Human Touch profile shell — protected change record

Date: 2026-09-11

Status: source checkpoint; bounded presentation follow-up

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
