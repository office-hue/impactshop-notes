# DEV v4 Stage A bootstrap — protected change record

Date: 2026-09-11

Status: source-only; activation pending

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/dev-v4/DEV-V4-IMPACTSHOP-STAGE-A-PLAN-20260911.md#operator-approval-reference",
  "operatorApprovalRef": "operator-approval:dev-v4-impactshop-stage-a-20260911",
  "protectedPaths": [
    "config/dev-v4/central-contract-snapshot.v1.json",
    "config/dev-v4/repo-capabilities.v1.json",
    "config/dev-v4/stage-a-bastion-policy.v1.json",
    "scripts/dev-v4-stage-a-verifier.mjs"
  ],
  "rollbackNote": "Revert the Stage A checkpoint commits; no provider, build, deploy, VPS, runtime, secret, cron, watchdog or hook state is changed.",
  "smokeTags": [
    "dev-v4-stage-a-static-verifier",
    "dev-v4-stage-a-negative-fixtures",
    "governance-only"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->

## Scope and evidence

This record covers only the inert repo-local contract snapshot, capability
snapshot, maximum bastion and static verifier. It grants no activation or
publication authority; `ready` remains impossible. The exact central identity
is checked by the verifier, and the existing PHP/WordPress release policy is
unchanged.

## Approval provenance

The opaque approval reference above is an external operator-originated
conversation instruction for this session to implement the full DEV Upgrade
target rollout with source publication authorized, subject to the existing
fail-closed boundaries. It is independently corroborated by the merged central
approved plan `dev-v4-activation-rollout-20260911` in `office-hue/ai-agent` at
merge `94db78c66b21979c9511594344e9a518a4d31d8`. Candidate text does not create
authority; independent QA corroborates the external instruction. No runtime or
deploy authority is added by this record.
