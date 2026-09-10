# Sharity Profile owner-policy REST method-map — protected change record

## Protected files touched

- `docs/bastion-guard-status.md`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `wp-content/mu-plugins/000-impactshop-owner-policy.php`

The policy change is limited to fail-closed recognition of WordPress's native
associative boolean route-method map. It adds no route, token, cookie, data,
provider, VPS, production, cron or watchdog authority.

## Rollback plan

Before runtime publication, revert this exact source checkpoint. After the
owner-grant v2 schema has been activated, do not restore the old policy blob;
keep `impactshop_owner_policy_safe_disable=1` and publish a forward-safe exact
CAS correction instead.

## Smoke checklist

- `security:owner-policy-runtime-self-test`
- `runtime:wp-rest-method-map`
- `runtime:exact-callback-inventory`
- `deploy:exact-release-cas`
- `flow:legacy-pool-visibility`
- `flow:message-popup`
- `flow:points-jump`
- `flow:profile-return-account`
- `flow:profile-return-restore`
- `route:factlens-vb-prod`
- `route:impact-challenge`
- `route:profil`

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/sharity-profile-owner-policy-method-map-sol-plan-2026-09-10.md#maximum-bastion-source-admission",
  "operatorApprovalRef": "operator-approval:sharity-profile-owner-policy-method-map-20260910",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "wp-content/mu-plugins/000-impactshop-owner-policy.php"
  ],
  "rollbackNote": "revert the exact source checkpoint before runtime publication; after v2 schema activation keep safe-disable enabled and use a forward-safe exact CAS correction",
  "smokeTags": [
    "security:owner-policy-runtime-self-test",
    "runtime:wp-rest-method-map",
    "runtime:exact-callback-inventory",
    "deploy:exact-release-cas",
    "flow:legacy-pool-visibility",
    "flow:message-popup",
    "flow:points-jump",
    "flow:profile-return-account",
    "flow:profile-return-restore",
    "route:factlens-vb-prod",
    "route:impact-challenge",
    "route:profil"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->
