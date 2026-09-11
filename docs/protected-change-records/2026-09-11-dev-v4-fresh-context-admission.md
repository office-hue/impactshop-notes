# DEV v4 fresh launcher context admission — protected change record

Date: 2026-09-11; status: source-only.

This record covers the exact DEV v4 admission correction that excludes the
canonical generated private `.codex/context/` family from write-surface
accounting. Product, provider, build, deploy, VPS, runtime, secret, scheduler
and watchdog authority remain denied.

## Protected files touched

- `scripts/dev-v4-admission.mjs`

## Rollback plan

Revert the exact source checkpoint; no remote or runtime state was changed.

## Smoke checklist

- `dev-v4-fresh-launcher-context-positive`
- `dev-v4-private-context-boundary-negative`
- `dev-v4-stage-b-admission-fixtures`
- `source-only`

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/dev-v4/DEV-V4-IMPACTSHOP-STAGE-B-V2-PLAN-20260911.md#dev-v4-impactshop-notes-stage-b-v2",
  "operatorApprovalRef": "operator-approval:dev-v4-activation-20260911",
  "protectedPaths": ["scripts/dev-v4-admission.mjs"],
  "rollbackNote": "Revert the exact source checkpoint; no remote or runtime state was changed.",
  "smokeTags": ["dev-v4-fresh-launcher-context-positive", "dev-v4-private-context-boundary-negative", "dev-v4-stage-b-admission-fixtures", "source-only"]
}
<!-- END PROTECTED SOURCE ADMISSION -->
