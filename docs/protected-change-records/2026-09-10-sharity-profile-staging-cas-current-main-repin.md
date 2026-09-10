# Sharity Profile staging CAS current-main repin protected change record

## Protected files touched

The exact protected set is the DEV-v2 target contract, its maximum-protection
impact policy and the bastion evidence log. Only two reviewed future candidate
manifest pins and the derived contract digest change. This creates no provider,
VPS, remote-write, deploy or runtime authority.

## Candidate identity

- Base: `origin/main@44547312cb06e24fe2999faf9abb4e2f63fb945e`.
- Reviewed CAS source: `b1b6dd2c64e969788873daeecdf464b2eec92d0c`.
- Deterministic `generated_at`: `2026-09-10T20:30:00+00:00`.
- Future `docs/impactshop-guard-hashes.json` SHA-256:
  `a4863e43c5ea5c3ce6a407215764fe9cd33dec2897954d7eaec1044d5e32cea6`.
- Future `docs/impactshop-guard-hashes.sha256` blob SHA-256:
  `b5fdeb82a868101e96ba5407ea32eb5403270ee6ebc1e90fdbf8d6e63f078c14`.
- Inventory parity: 155 protected paths and the identical 157 hash keys;
  the four inventoried CAS script/test values and the existing
  `.github/workflows/ci.yml` value change. PR #197 changed that workflow from
  recorded `4033833c90a90b54d32b5a80eb6232eafa32adb1793208eeb8bbce5d45deebe9`
  to raw-base blob SHA-256
  `ab43c08a72c69d56187771b5b4f8cf81deda401d22685adf4cd84f0d52886015`.
  The fifth reviewed CAS byte, `.deploy.staging.env`, remains separately pinned
  by the profile. Serialization is the guard writer's two-space UTF-8 JSON with
  no trailing newline.

## Rollback plan

Revert the exact checkpoint commit. No external state or data rollback is
needed because this package changes source-control admission metadata only.

## Smoke checklist

- `deploy:guard-preflight`
- `deploy:checksum-verify`

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/sharity-profile-staging-cas-current-main-repin-sol-plan-2026-09-10.md#control-plane-repin",
  "operatorApprovalRef": "operator-approval:sharity-profile-production-minimal-runs-20260910",
  "protectedPaths": [
    "config/dev-delivery-v2-impact-policy.json",
    "config/dev-delivery-v2-target-contract.json",
    "docs/bastion-guard-status.md"
  ],
  "rollbackNote": "revert the exact source-only current-main CAS repin checkpoint; no remote state changed",
  "smokeTags": [
    "deploy:guard-preflight",
    "deploy:checksum-verify"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->
