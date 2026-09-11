# DEV v4 Stage B v2 protected change record

Date: 2026-09-11; status: source-only.

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/dev-v4/DEV-V4-IMPACTSHOP-STAGE-B-V2-PLAN-20260911.md#dev-v4-impactshop-notes-stage-b-v2",
  "operatorApprovalRef": "operator-approval:dev-v4-impactshop-stage-b-v2-20260911",
  "protectedPaths": [".github/workflows/ci.yml", "config/dev-v4/activation-policy.v1.json", "config/dev-v4/central-contract-snapshot.v1.json", "config/dev-v4/central-contract-snapshot.v2.json", "config/dev-v4/repo-capabilities.v2.json", "docs/bastion-guard-status.md", "scripts/dev-v4-admission.mjs", "scripts/dev-v4-stage-b-verifier.mjs", "scripts/worktree-task-start.sh"],
  "rollbackNote": "Revert the Stage B v2 source checkpoint only; no remote or runtime state changes.",
  "smokeTags": ["dev-v4-stage-b-v2", "v2-only-premerge", "source-only", "deploy:checksum-verify", "deploy:guard-preflight"]
}
<!-- END PROTECTED SOURCE ADMISSION -->

Protected files touched are limited to the repo-local admission trigger and
documentation perimeter. Smoke checklist covers temporary-fixture admission,
v2 adapter, CI static checks and shell syntax.
