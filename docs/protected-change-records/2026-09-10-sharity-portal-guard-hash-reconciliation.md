# Sharity portal guard-hash reconciliation

Date: 2026-09-10

Status: implementation-ready; source publication pending

## Scope and cause

The post-merge production dry-run for the approved public portal redirects
failed closed before any remote write. The guard found two source-manifest
entries that did not match the already merged, CI-green `main` tree:

- `.github/workflows/ci.yml` changed in approved PR #191, but its guard hash was
  not refreshed there;
- `docs/impactshop-guard-config.sha256` changed in approved PR #192, but its
  own guard-manifest entry retained the preceding digest.

This repair changes only those two digest values plus the manifest timestamps
and outer checksum. It does not change either protected source file, any PHP
runtime, route, provider, database, scheduler, cron, Cronos or watchdog.

## Protected files touched

- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`

## Coherence, risk and rollback

Both admitted digests are computed from exact files already present at the
merged `origin/main` commit. PR #191 and PR #192 completed all required GitHub
checks. The repair does not bless an unknown worktree state and cannot alter
runtime behavior. Rollback is the ordinary revert of this hash-only commit;
production deployment remains blocked until this reconciliation is itself
merged and the guard passes from exact `origin/main`.

Required smoke scope remains the exact-file deploy guard/checksum verification
plus the two portal redirect routes and their fixed public destination.

## Protected source admission

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "operatorApprovalRef": "operator-approval:sharity-public-portal-redirects-20260910",
  "planRef": "docs/sharity-public-portal-redirects-sol-plan-2026-09-10.md#sharity-public-portal-redirects",
  "protectedPaths": [
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256"
  ],
  "rollbackNote": "revert only this hash-reconciliation checkpoint; no runtime rollback is associated with this source-only repair",
  "schemaVersion": 1,
  "smokeTags": [
    "route:adomany-automata-portal-1",
    "route:adomany-automata-portal-2",
    "destination:sharity-public-home",
    "deploy:guard-preflight",
    "deploy:checksum-verify"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->
