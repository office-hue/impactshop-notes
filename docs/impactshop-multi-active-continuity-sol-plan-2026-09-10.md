# Impactshop multi-active continuity — Sol plan

## Multi-active continuity architecture

Plan ID: `impactshop-multi-active-continuity-sol-20260910`

Operator approval reference:
`operator-approval:impactshop-multi-active-continuity-sol-20260910`.

### Objective

Make concurrent `impactshop-notes` worktrees independently publishable without
silently stealing one shared active pointer and without crossing into a sibling
`ai-agent` repository for memory or dependency checks.

### Identity and scope

- Repository: `impactshop-notes`.
- Worktree: `impactshop-notes-feat-multi-active-continuity-sol-20260910`.
- Branch: `feat/impactshop-multi-active-continuity-sol-20260910`.
- Base: `origin/main@d39349a3dedad8ebda597c2d531fd2e498268990`.
- Source only: no push, PR, merge, provider build, VPS command, remote write or
  runtime activation belongs to this checkpoint.
- No cron or watchdog is required: the capability runs synchronously at local
  worktree start and push admission and owns no long-running runtime state.

### Security contract

- `--register` refreshes exact current worktree evidence while preserving a
  valid nominated primary; primary transfer requires explicit `--primary`.
- Active pointer and all-worktree snapshot are written under one lock, through
  temporary files, with a shared generation ID.
- Push admission requires exact branch, full HEAD, zero dirty paths and current
  task-start decision evidence for the publishing worktree.
- A valid non-primary worktree may continue with an explicit degraded warning;
  stale HEAD, dirty state, generation drift, missing evidence or invalid
  primary state fail closed.
- Worktree start, guarded push and generated hooks remain entirely repo-local.
  No sibling `ai-agent`, external Node cache or memory command is discovered or
  invoked.
- The coordination scripts, starter/push/hook entrypoints and hermetic contract
  test become maximum-protected deploy-guard control-plane paths.

### Verification and rollback

The hermetic test creates one repository plus two linked worktrees and verifies
primary preservation, concurrent registration, non-primary degraded admission,
stale-HEAD/dirty/generation/lock denial and generated-hook repo isolation.
Closure also requires shell syntax, protected-touch/commit-lane admission,
DEV-v2 validation, DocSync continuity, strict audit, `git diff --check`, a clean
tree and one checkpoint commit.

Rollback is the exact revert of the checkpoint commit. The shared coordination
snapshot is ephemeral evidence and can be regenerated from a chosen valid
primary with `--primary`; no provider, VPS, database or product state requires
rollback.

### Follow-up boundary

After checkpoint, use `gpt-5.6-terra`, high, for independent QA of the new
multi-active and fail-closed semantics. Source publication is a later bounded
step. Production/VPS activation is not applicable to this capability.
