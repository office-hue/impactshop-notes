# Sharity profile AdSense producer suppression — protected change record

Date: 2026-09-11

Status: source-only Luna checkpoint; no publication or live activation

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "operatorApprovalRef": "operator-approval:sharity-profile-summary-live-closure-20260910",
  "planRef": "docs/sharity-profile-summary-live-closure-luna-continuity-20260910.md#luna-follow-up-adsense-hook-order-and-marker-precision",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "wp-content/mu-plugins/impactshop-identity-panel.php"
  ],
  "rollbackNote": "revert this exact producer-suppression checkpoint before publication; retain the prior profile source and use exact-file CAS preimage for any live rollback",
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

## Scope

Production acceptance passed cache, cookie and identity isolation but retained
seven AdSense markers and a desktop overflow/CLS failure. Site Kit registers
its AdSense `register_tag` callback from `template_redirect`; the profile route
now removes only the exact `Google\\Site_Kit\\Modules\\AdSense` object callback
with method `register_tag` immediately in the early suppression phase, while
retaining a late callback-registry scan for late loaders.

Elementor keeps its exact AdSense widget-name suppression and additionally
suppresses only the generic `html` widget when render settings, recursively,
contain an actual script, `ins`, or `pagead2.googlesyndication.com` URL marker.
Plain explanatory/code-sample `adsbygoogle` text, near misses and benign HTML
remain renderable. No output buffer, rendered-HTML regex, auth,
data/DB/cookie, shared dependency, provider or live runtime change is included.

## Protected files touched

- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `docs/bastion-guard-status.md`

## Smoke scope

Required identity/profile smoke tags: `route:factlens-vb-prod`,
`route:impact-challenge`, `route:profil`, `flow:message-popup`,
`flow:points-jump`, `flow:legacy-pool-visibility`,
`flow:profile-return-account`, `flow:profile-return-restore`,
`flow:profile-open`, `browser:webkit`, `browser:chrome`.

## Evidence and rollback

- `php -l wp-content/mu-plugins/impactshop-identity-panel.php` PASS
- `php tests/impactshop-profile-ads-suppression.test.php` PASS
- Hook-order fixture proves `register_tag` was not called before dispatch;
  explanatory/code-sample negative fixture PASS
- Existing profile summary/CLS/bootstrap/remediation/static and owner-policy
  tests PASS
- guard hash verification and `git diff --check` PASS
- protected-touch, strict audit and maximum-bastion checks PASS

Live AdSense-marker/CLS remeasurement and publication remain pending. Rollback
is a source-only revert of this exact checkpoint before publication; no live
rollback or data change is included.
