# Sharity Human Touch profile dock — protected change record

Date: 2026-09-11

Status: production-accepted from exact merged main

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

## Production acceptance

PR #210 was squash-merged as
`94e25a9acbb995b1f26fa23ebfc360114f76c114`. Exact-file production
release `20260911T081100Z-94e25a9acbb9-actionbar` deployed the action-bar
source at SHA-256
`4fb191637a55ea8c0b3a9e85ff7f571ab9d4218383f8a0d89be9191523dbea0e`;
release `20260911T081200Z-94e25a9acbb9-identity` deployed the identity source
at SHA-256
`a92582a7f7904ef736fdcb47bf07234226793bef1f0265ca6e323c8c409c3395`.
Both targets verified mode `0444` and retain executable CAS rollback records.

The public `/profil/` response contains one `sharity-profile-dock`, no
rendered `sharity-action-bar`, both exact anchors and the sign-in target.
One desktop/mobile browser run reported zero horizontal overflow, profile
data in the dock, desktop CLS `0.005874`, and screenshots under
`/tmp/sharity-profile-dock-live-20260911/`.
