# Sharity profile AdSense producer suppression — protected change record

Date: 2026-09-11

Status: production-accepted from exact merged main; no cron/watchdog change

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "operatorApprovalRef": "operator-approval:sharity-profile-summary-live-closure-20260910",
  "planRef": "docs/sharity-profile-summary-live-closure-luna-continuity-20260910.md#luna-follow-up-canonical-elementor-render-filter",
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

Elementor suppression now uses the canonical
`elementor/frontend/widget/should_render` boolean filter (`accepted_args=2`),
keeping exact AdSense widget-name suppression and additionally suppressing
only `html`/`text-editor` widgets whose render settings, recursively, contain
an actual script, `ins`, or `pagead2.googlesyndication.com` URL marker. Plain
explanatory/code-sample `adsbygoogle` text, near misses and benign content
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
- Canonical boolean filter fixture covers text-editor producer suppression,
  benign/explanatory text-editor and control-route PASS
- Hook-order fixture proves `register_tag` was not called before dispatch;
  explanatory/code-sample negative fixture PASS
- Existing profile summary/CLS/bootstrap/remediation/static and owner-policy
  tests PASS
- guard hash verification and `git diff --check` PASS
- protected-touch, strict audit and maximum-bastion checks PASS

Publication completed through PR #206 and PR #207. The final merged-main source
is `e9d9c934fb7bfce4ec9bb2af0586f19a8d9782f6`; the deployed PHP SHA-256 is
`72980c402f05666c73f4a7b1f69df68d4ad54bf45bf7ecdd6b333b40f0f54cbc`.
Exact-file staging release `20260911T071009Z-e9d9c934fb7b-20804a79` and
production release `20260911T071058Z-e9d9c934fb7b-20804a79` both verified
mode `0444`. Final staging and production browser checks reported zero
AdSense markers and zero overflow; production CLS was `0.006905` desktop and
`0.070579` mobile. Two isolated production cookie jars returned distinct
active pseudo IDs, private/no-store cache headers, `Vary: Cookie`, Cloudflare
`DYNAMIC`, and zero ad markers. Production rollback restores the release
preimage through the recorded release ID and deployed SHA; no database change
was made by this follow-up.
