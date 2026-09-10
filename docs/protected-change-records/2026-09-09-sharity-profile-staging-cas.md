# Sharity Profile staging exact-release CAS — protected change record

## Protected files touched

This exact source package applies the reviewed staging CAS implementation and
the current-main guard-manifest pins. Production bytes remain unchanged; no
provider, VPS, remote-write, deploy, database, runtime, cron or watchdog
authority is created.

- `.deploy.staging.env`
- `bin/deploy-wpcontent-map.sh`
- `bin/impactshop-guard-rollback.sh`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `tests/deploy-wpcontent-map-exact-file.test.sh`
- `tests/impactshop-guard-rollback-truth.test.sh`

The reviewed protected blobs are pinned by the admission profile. The current
production companion is unchanged and its SHA-256 is recorded below.

## Rollback plan

Revert the exact source checkpoint. After a separately authorized staging
release, use the wrapper's printed `--staging --apply
--expected-deployed-sha=...` command with its release ID; broad rsync and
manual remote replacement remain prohibited.

## Smoke checklist

- `deploy:guard-preflight`
- `deploy:checksum-verify`
- `deploy:exact-release-cas`
- `deploy:exact-rollback-cas`

No live staging, schema, page, database, provider or production mutation is
part of this source-only package.

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 2,
  "admissionProfile": "deploy-control-source:sharity-staging-cas-v1",
  "planRef": "docs/sharity-profile-sp1-sol-release-gate-2026-09-09.md#canonical-staging-cas-implementation",
  "operatorApprovalRef": "operator-approval:sharity-profile-staging-cas-20260909",
  "protectedPaths": [
    ".deploy.staging.env",
    "bin/deploy-wpcontent-map.sh",
    "bin/impactshop-guard-rollback.sh",
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "tests/deploy-wpcontent-map-exact-file.test.sh",
    "tests/impactshop-guard-rollback-truth.test.sh"
  ],
  "reviewedHeadSha256": {
    ".deploy.staging.env": "1bc12eb1bde5184e97e159081cd4be9a7d23ad4238c1a557efbc527e47a7ed25",
    "bin/deploy-wpcontent-map.sh": "eaa6990de8c843cacd219859615e5f0d917aa7c6ee7309dab470a5ef9fa1fe8a",
    "bin/impactshop-guard-rollback.sh": "225211c03db3e921b4c8538de6f3522f283f6caa22a52b049d311c1f3c864993",
    "docs/impactshop-guard-hashes.json": "a4863e43c5ea5c3ce6a407215764fe9cd33dec2897954d7eaec1044d5e32cea6",
    "docs/impactshop-guard-hashes.sha256": "b5fdeb82a868101e96ba5407ea32eb5403270ee6ebc1e90fdbf8d6e63f078c14",
    "tests/deploy-wpcontent-map-exact-file.test.sh": "a64167d629f257a10ff759651ffbe06e6c9a4254bddb0efdab681054f23f150f",
    "tests/impactshop-guard-rollback-truth.test.sh": "600e56326cf1b041786c90502aa0888ca89ad6f7b1fe8d00adc308a08cbb5756"
  },
  "reviewedUnchangedPaths": {
    ".deploy.production.env": "ea894097c343148cb375c74148021ac684ef44ebb04dc56be766f8c86966885d"
  },
  "rollbackNote": "revert the exact staging CAS source checkpoint; after an admitted staging release use the printed staging rollback command with its release ID and deployed SHA-256",
  "smokeTags": [
    "deploy:guard-preflight",
    "deploy:checksum-verify",
    "deploy:exact-release-cas",
    "deploy:exact-rollback-cas"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->
