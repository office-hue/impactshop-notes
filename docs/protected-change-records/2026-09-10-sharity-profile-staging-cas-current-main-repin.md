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
  `0e5fcc5d4e4306f550b02b4a202887f052d4a1affca9fc2a48f4a4380c47e054`.
- Future `docs/impactshop-guard-hashes.sha256` blob SHA-256:
  `e2f7620eb6f77817f92f4df471711bc06065c4c009b8e1dcf32ae25be5cf62d4`.
- Inventory parity: 155 protected paths and the identical 157 hash keys;
  only the four inventoried CAS script/test values change. The fifth reviewed
  CAS byte, `.deploy.staging.env`, remains separately pinned by the profile.

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
