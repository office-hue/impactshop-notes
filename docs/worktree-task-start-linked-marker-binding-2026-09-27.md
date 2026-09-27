# Linked worktree task-start marker binding — 2026-09-27

## Finding

`scripts/worktree-task-start.sh` created a linked worktree and wrote its
marker under linked Git metadata, then invoked readiness and the task-start
guard from the clone root. The guard consequently reported
`missing-worktree-active-marker`. Coordination registration also used the
clone root as the active repository context.

## Authorization and boundary

Operator approval: `operator-approval:dev-v4-linked-worktree-fix-20260927`.

The approved source boundary is the existing task-start helper and its focused
linked-worktree regression evidence. Product behavior, WordPress, provider,
build, deploy, VPS, runtime, secret, cron and watchdog authority remain out of
scope. The change is source-only and must not be published or activated by
this record.

## Correction

Readiness and the task-start guard now run with `WT_DIR` as the current
directory. Coordination sync receives `--repo-root "$WT_DIR" --register
"$WT_DIR"`, so marker, decision artifact and active snapshots use one linked
worktree identity.

## Evidence

- `tests/worktree-task-start-linked-worktree.test.sh`: PASS
- `tests/worktree-multi-active-continuity.test.sh`: PASS
- `bash -n scripts/worktree-task-start.sh`: PASS
- `git diff --check`: PASS

This is a helper and test maintenance change, not a new product module. No
product, provider, deploy, runtime, secret, cron or watchdog behavior changed.
