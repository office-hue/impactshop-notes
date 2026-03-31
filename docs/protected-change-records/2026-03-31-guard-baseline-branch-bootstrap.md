# 2026-03-31 Guard Baseline Branch Bootstrap

## Summary

This branch bootstraps the `impactshop-notes` guard baseline onto a clean
`main`-based history so that later protected runtime work can use a canonical
guarded commit, push, PR, merge, and deploy path.

## Protected files touched

- `docs/impactshop-protected-files.json`
- `scripts/check-protected-file-touch.sh`
- `scripts/check-commit-lane.sh`
- `scripts/guarded-push.sh`
- `scripts/workflow-state.sh`

## Risk

- Before this branch, the guard baseline existed only in dirty worktree state.
- That meant the runtime protection model depended on uncommitted local files.
- This branch moves the minimum control plane into clean git history and also
  hardens the control plane itself so the shield cannot be weakened more easily
  than the protected runtime it defends.

## Rollback

- Revert the guard baseline bootstrap commits on the branch if the baseline must
  be withdrawn before merge.
- After merge, rollback means reverting the baseline commits in reverse order so
  the previous `main` state is restored.
- If needed, restore the preflight backup bundle from
  `.codex/backups/guard-baseline-preflight-20260331T192306Z`.

## Smoke

- `deploy:guard-preflight`
- `deploy:checksum-verify`

## Notes

- The branch contains three protected control-plane commits:
  - `chore(guard): add protected touch model and gate`
  - `chore(guard): add workflow lane and push guards`
  - `chore(guard): protect guard control plane`
- Follow-up hardening on the same branch:
  - `fix(guard): harden guarded push and worktree repo detection`
  - `fix(guard): handle no-upstream push mode and detached heads`
- `scripts/guarded-push.sh` now runs both lane and protected-touch checks before
  invoking `git push`.
- `scripts/guarded-push.sh` also runs `safe-repo-audit` and the memory gate when
  those entrypoints are available.
- The guard model now treats the guard control plane itself as protected.
- `scripts/workflow-state.sh` now derives repo identity from the git common dir,
  so worktree names do not distort deploy guidance.
- Push-mode guards now choose a commit-ish base for new branches without an
  upstream instead of diffing against the empty tree/full history.
- `scripts/workflow-state.sh` now also normalizes empty branch output to
  `detached`.
