# Sharity profile early asset enqueue — protected change record

Date: 2026-09-11

Status: source-only Luna checkpoint; no publication or live activation

## Scope

The prior staging Playwright measurement after the min-height reservation was
mobile CLS `0.2585` and desktop CLS `0.2704`; the largest desktop shift was
`0.216` at `664ms`, attributed to the identity card/page content. The profile
shortcode was enqueueing its already-registered inline stylesheet only during
content rendering, after `wp_head`.

The bounded correction calls the existing asset enqueue helper from the
existing `wp_enqueue_scripts` registration only when the canonical profile
route classifier matches. The three profile-only async reservations remain.
No global/shared dependency, host/origin rule, owner policy, JS behavior or
accessibility behavior is changed.

## Protected files touched

- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`

## Smoke scope

Required identity/profile smoke tags: `route:factlens-vb-prod`,
`route:impact-challenge`, `route:profil`, `flow:message-popup`,
`flow:points-jump`, `flow:legacy-pool-visibility`,
`flow:profile-return-account`, `flow:profile-return-restore`,
`flow:profile-open`, `browser:webkit`, `browser:chrome`.

## Evidence and rollback

- `php -l wp-content/mu-plugins/impactshop-identity-panel.php` PASS
- `node --check wp-content/mu-plugins/impactshop-identity-panel.js` PASS
- `python3 tests/impactshop-profile-cls-contract.test.py` PASS
- Existing profile summary/bootstrap/remediation/static and owner-policy tests
  PASS
- guard hash verification and `git diff --check` PASS

The `<0.1` desktop/mobile CLS and zero-horizontal-overflow targets remain
staging remeasurement gates. Rollback is a source-only revert of this exact
checkpoint before publication; no live rollback or data change is included.

## Protected source admission

This manifest covers every protected endpoint in the exact
`origin/main..HEAD` candidate. It grants source publication authority only;
runtime staging and production acceptance remain separate Sol gates.

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "operatorApprovalRef": "operator-approval:sharity-profile-summary-live-closure-20260910",
  "planRef": "docs/sharity-profile-summary-live-closure-luna-continuity-20260910.md#luna-follow-up-early-profile-asset-enqueue",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "wp-content/mu-plugins/impactshop-identity-panel.php"
  ],
  "rollbackNote": "revert the exact early-enqueue checkpoint before runtime publication; after deployment use its exact-file CAS preimage and retain the owner-grant v2 schema and verifier",
  "schemaVersion": 1,
  "smokeTags": [
    "browser:chrome",
    "browser:webkit",
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
