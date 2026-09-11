# Sharity Human Touch profile dock — protected change record

Date: 2026-09-11

Status: source checkpoint; exact merged-main production publication required

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "operatorApprovalRef": "operator-approval:sharity-profile-summary-live-closure-20260910",
  "planRef": "docs/sharity-profile-summary-live-closure-luna-continuity-20260910.md#2026-09-11-human-touch-profile-dock-follow-up",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "wp-content/mu-plugins/impactshop-action-bar.php",
    "wp-content/mu-plugins/impactshop-identity-panel.php"
  ],
  "rollbackNote": "revert the exact Human Touch dock checkpoint and restore both production PHP preimages by their CAS release records",
  "schemaVersion": 1,
  "smokeTags": [
    "browser:chrome",
    "browser:mobile",
    "flow:consent-overlay",
    "flow:legacy-pool-visibility",
    "flow:message-popup",
    "flow:mobile-shell-render",
    "flow:points-jump",
    "flow:profile-open",
    "flow:profile-return-account",
    "flow:profile-return-restore",
    "flow:pwa-install-entry",
    "route:factlens-vb-prod",
    "route:home",
    "route:impact-challenge",
    "route:impactshop",
    "route:profil"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->

## Scope

Remove the rendered legacy eight-item floating action bar and replace it with
a compact Human Touch dock. Its account action exposes the cookie-bound
nickname/pseudo, points and spendable votes and opens the canonical profile
anchor. Its second action opens the explicit existing-account sign-in anchor.

No database, owner-grant policy, schema, cookie, cron, watchdog, provider or
shared dependency behavior changes. Rollback is an exact source revert plus
the two recorded PHP preimages.

## Protected files touched

- `wp-content/mu-plugins/impactshop-action-bar.php`
- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `docs/bastion-guard-status.md`

## Minimum evidence

- PHP lint for both touched MU plugins
- `tests/sharity-profile-dock-static.test.py`
- existing profile-summary and owner-policy inventory tests
- protected hash verification and `git diff --check`
- one production DOM/layout check after exact-file publication
