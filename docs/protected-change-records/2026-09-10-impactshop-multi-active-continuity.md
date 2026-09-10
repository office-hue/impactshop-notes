# Impactshop multi-active continuity protected change record

## Protected files touched

The protected set contains the repository's worktree starter, coordination and
continuity guards, guarded-push and hook installers, CI contract, protected
inventory, hermetic test and bastion status. The change creates no provider,
VPS, remote-write, deploy or runtime authority.

The coordination authority is isolated under the repository common Git
directory with private modes. Workspace-global legacy evidence is never written
or deleted; only an accessible same-repo primary may be adopted during the first
namespaced sync. Foreign or invalid legacy evidence is ignored.

## Rollback plan

Revert the exact checkpoint commit, then regenerate the ephemeral coordination
snapshot from the operator-selected valid primary worktree. No external state
or data rollback is required.

## Smoke checklist

- `deploy:guard-preflight`
- `deploy:checksum-verify`
- hermetic two-repository, multi-worktree continuity contract
- protected-touch and commit-lane checks
- DocSync/continuity and strict repository audit

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/impactshop-multi-active-continuity-sol-plan-2026-09-10.md#multi-active-continuity-architecture",
  "operatorApprovalRef": "operator-approval:impactshop-multi-active-continuity-sol-20260910",
  "protectedPaths": [
    ".github/workflows/ci.yml",
    "docs/bastion-guard-status.md",
    "docs/impactshop-protected-files.json",
    "scripts/guarded-push.sh",
    "scripts/install-hooks.sh",
    "scripts/start-feature-worktree.sh",
    "scripts/worktree-continuity-guard.sh",
    "scripts/worktree-coordination-sync.sh",
    "scripts/worktree-task-start.sh",
    "tests/worktree-multi-active-continuity.test.sh"
  ],
  "rollbackNote": "revert the exact source-only checkpoint and regenerate coordination evidence from an explicitly selected valid primary",
  "smokeTags": [
    "deploy:guard-preflight",
    "deploy:checksum-verify"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->
