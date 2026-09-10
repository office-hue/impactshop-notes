# Sharity profile owner-policy remediation — protected change record

Date: 2026-09-10

Status: source-only checkpoint; staging schema/browser acceptance pending

## Scope

This bounded Luna remediation closes the Sol scan `2e0aa501-788f-4e33-8183-30361081557f`
and Terra blockers for owner-grant lifecycle ordering, duplicate registry
precedence, VB2026 policy modes, exact callback bastion checks and transactional
grant storage. This follow-up also closes conditional debug-route admission,
active-only profile UI mutation gates, cookie restoration compensation and
VB2026 service-principal mismatch. No remote, provider, database, OPcache or
deployment authority
is included.

## Protected files touched

- `wp-content/mu-plugins/000-impactshop-owner-policy.php`
- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `wp-content/mu-plugins/impactshop-identity-panel.js`
- `wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-guard-config.sha256`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `docs/impactshop-protected-files.json`
- `docs/bastion-guard-status.md`

## Invariants

- Newly issued raw owner tokens stay out of `$_COOKIE`, DB and response bodies;
  same-request profile state is `binding_pending`.
- Restore never revokes the current valid grant before replacement activation.
  Activation queues both cookies, locks pending/superseded rows, applies one
  exact 365-day expiry, verifies both invariants and commits atomically.
- Cookie/header or DB/readback/commit failure expires replacement cookies,
  returns inactive/false and safe-disables when consistency is uncertain.
- VB2026 policy modes preserve pre-auth intent creation and the native exact
  Bearer service callback; owner-or-service central admission cannot mint or
  widen service authorization.
- Registry duplicates for identity total and NGO selector get are removed;
  runtime self-test validates every route's method and exact callback identity.
  Static negative fixtures prove that duplicate classification and callback
  drift both fail source admission.
- Grant mutation requires actual InnoDB engine readback. Existing non-InnoDB
  tables are not implicitly converted; operator-controlled staging migration
  is required.
- Binding-pending and unavailable responses expose no profile values; a
  revoked/invalid binding exposes only its pseudo ID and status.
- The debug-rotation route is absent-safe only when its debug feature flag is
  false; a registered route always requires exact method/callback identity.
- Profile UI pseudo mutations are active-state-only, including vacation,
  last-NGO reset, push subscription changes and credential-save point award.
- Failed initial/restore owner-cookie issuance restores the prior valid pseudo
  cookie or expires a new one; restoration header failure safe-disables.
- A valid VB2026 service bearer selects its validated header pseudo as the sole
  target; invalid/partial bearer headers never fall back to a browser cookie.

## Evidence

- `php -l` for the three changed PHP MU modules and two PHP behavior fixtures: PASS.
- `php tests/impactshop-owner-policy-runtime.test.php`: PASS.
- `php tests/impactshop-vb2026-principal-binding.test.php`: PASS.
- `node --check wp-content/mu-plugins/impactshop-identity-panel.js`: PASS.
- `python3 tests/impactshop-owner-policy-inventory.test.py`: PASS.
- `python3 tests/impactshop-identity-profile-v2-static.test.py`: PASS.
- `python3 tests/impactshop-profile-summary-checkpoint-a.test.py`: PASS.
- `python3 tests/impactshop-profile-owner-luna-remediation.test.py`: PASS.
- `php scripts/impactshop-owner-policy-inventory.php`: PASS.
- `git diff --check`: PASS.

Residual prerequisite: staging must prove InnoDB engine/schema readback,
two-request cookie activation, failure compensation, browser cache invalidation,
native VB2026 Bearer/browser/pre-auth cases including mixed-principal and invalid
bearer denial, conditional debug-route registration, active-only UI behavior and
IDOR denial before any live
acceptance. No push, PR, merge or deploy occurred.

<!-- BEGIN PROTECTED CHECKPOINT EVIDENCE -->
{
  "operatorApprovalRef": "operator-approval:sharity-profile-summary-live-closure-remediation-20260910",
  "planRef": "sharity-profile-summary-live-closure-20260910",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "docs/impactshop-guard-config.json",
    "docs/impactshop-guard-config.sha256",
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "docs/impactshop-protected-files.json",
    "wp-content/mu-plugins/000-impactshop-owner-policy.php",
    "wp-content/mu-plugins/impactshop-identity-panel.js",
    "wp-content/mu-plugins/impactshop-identity-panel.php",
    "wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php"
  ],
  "rollbackNote": "revert this checkpoint's exact candidate commit before any source merge; retain the grant table and do not perform live schema conversion",
  "schemaVersion": 1,
  "smokeTags": [
    "deploy:checksum-verify",
    "deploy:guard-preflight",
    "route:profil",
    "route:factlens-vb-prod",
    "route:impact-challenge",
    "flow:legacy-pool-visibility",
    "flow:message-popup",
    "flow:points-jump",
    "flow:profile-return-account",
    "flow:profile-return-restore",
    "flow:vb2026-selection-intent",
    "flow:owner-grant-two-request-activation"
  ]
}
<!-- END PROTECTED CHECKPOINT EVIDENCE -->
