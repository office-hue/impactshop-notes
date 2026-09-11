# Sharity canonical quick-actions correction — protected change record

Date: 2026-09-11

Status: source-checkpoint; publication pending

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/sharity-profile-summary-live-closure-luna-continuity-20260910.md#2026-09-11-canonical-quick-actions-correction",
  "operatorApprovalRef": "operator-approval:replace-legacy-action-bar-with-canonical-sharity-quick-actions-20260911",
  "protectedPaths": [
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "wp-content/mu-plugins/impactshop-action-bar.php"
  ],
  "rollbackNote": "Restore the production action-bar preimage with SHA-256 d7682cd89f8bf3af6d68c01d35adc4353a4d2db9f546b3f44cbbedd9c6484002 through the exact-file rollback lane.",
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

Replace the rejected, restyled legacy action bar with the exact product-level
2x4 Human Touch quick-actions contract already rendered on `sharity.hu`.
Identity, owner-grant, REST, database, cron, watchdog and shared dependency
behavior are unchanged.

## Protected files touched

- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `wp-content/mu-plugins/impactshop-action-bar.php`

`docs/bastion-guard-status.md` was returned to its `origin/main` bytes before
publication, so it is not part of the final protected diff or manifest scope.

## Rollback

Use the exact-file rollback lane to restore the recorded production preimage
`d7682cd89f8bf3af6d68c01d35adc4353a4d2db9f546b3f44cbbedd9c6484002`.

## Smoke scope

- On desktop and mobile the bar has four columns and two rows with eight items.
- The labels, order, SVG icons, pastel grouping and outline/shadow treatment
  match the canonical `sharity.hu` quick-actions component.
- Üzenetek is disabled and carries the Hamarosan badge.
- Profil opens `/profil/#impactshop-account-top`; the seven other items use the
  canonical public destinations.
- The retired app labels and emoji action icons are absent.
- No horizontal overflow and no duplicate legacy/floating navigation is shown.

## Source evidence

- `impactshop-action-bar.php` SHA-256:
  `f05af5bf0c6112e78bc701637217e8ad3ffabba3ac4843f955856146e15fabfd`.
- Focused static contract, owner-policy inventory and PHP lint: PASS.
- Local Playwright binary was unavailable; no local browser PASS is claimed.
  Fresh browser acceptance is required after exact publication.
