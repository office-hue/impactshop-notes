# Protected Change Record - 2026-04-17 - safe-repo-audit push range fix

## Protected files touched
- scripts/safe-repo-audit.sh

## Why this change is needed
- Fresh feature branches without upstream could fall back to empty tree for push-range scanning.
- This caused false-positive whole-history scans in strict pre-push audit.

## Change summary
- Added deterministic push-base resolver:
  - upstream
  - origin/main or origin/master
  - local main or master
  - HEAD^
  - empty tree as last resort

## Rollback
- Revert this commit.
- Restore previous `scripts/safe-repo-audit.sh` implementation if pre-push behavior regresses.

## Smoke
- smoke tag: deploy:guard-preflight
- smoke tag: deploy:checksum-verify
- local checks: `bash -n scripts/safe-repo-audit.sh`
