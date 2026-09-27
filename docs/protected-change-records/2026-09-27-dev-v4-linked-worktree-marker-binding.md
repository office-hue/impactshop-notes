# DEV v4 linked-worktree marker binding — protected change record

Date: 2026-09-27

Status: source-only; no publication or live activation

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/worktree-task-start-linked-marker-binding-2026-09-27.md#authorization-and-boundary",
  "operatorApprovalRef": "operator-approval:dev-v4-linked-worktree-fix-20260927",
  "protectedPaths": ["config/dev-delivery-v2-policy.json", "config/dev-v4/activation-policy.v1.json", "config/dev-v4/graded-delivery.v1.json", "config/dev-v4/lifecycle-contract.v1.json", "config/dev-v4/stage-b-activation-policy.v1.json", "config/dev-v4/task-selectors.v1.json", "scripts/dev-v4-admission.mjs", "scripts/dev-v4-lifecycle-client.mjs", "scripts/worktree-task-start.sh"],
  "rollbackNote": "Revert the single linked-worktree task-start helper checkpoint; no runtime, provider, deploy, VPS, secret, cron, watchdog or product state changed.",
  "smokeTags": ["deploy:guard-preflight", "deploy:checksum-verify"]
}
<!-- END PROTECTED SOURCE ADMISSION -->

## Protected files touched

- `config/dev-delivery-v2-policy.json`
- `config/dev-v4/activation-policy.v1.json`
- `config/dev-v4/graded-delivery.v1.json`
- `config/dev-v4/lifecycle-contract.v1.json`
- `config/dev-v4/stage-b-activation-policy.v1.json`
- `config/dev-v4/task-selectors.v1.json`
- `scripts/dev-v4-admission.mjs`
- `scripts/dev-v4-lifecycle-client.mjs`
- `scripts/worktree-task-start.sh`

## Scope

The task-start helper now runs readiness and the task-start guard from the
linked worktree and registers the active coordination snapshot against that
same worktree. The marker, decision artifact and snapshot identity therefore
remain bound to the worktree selected by task start.

The focused linked-worktree regression and existing multi-active continuity
test cover the correction. No product, provider, build, deploy, VPS, runtime,
secret, cron or watchdog behavior changes.

The target-local lifecycle bundle admits one retained installed engine digest.
The normal source selector requires an adapter brief, while governance uses
`dev-governance-source`; lifecycle status and resume remain read-only and the
legacy Stage B projection remains compatible without becoming a second gate.

## Rollback

Revert this exact source checkpoint. No remote or runtime rollback applies.
