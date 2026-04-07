# 2026-03-31 Guard Control Plane Hardening

## Summary

This change closes a control-plane gap in the `impactshop-notes` guard baseline.
The guard model and guarded push entrypoint are moved under the protected control
plane so that weakening the shield itself requires the same protected workflow as
runtime changes.

## Protected files touched

- `docs/impactshop-protected-files.json`
- `scripts/guarded-push.sh`

## Risk

- Before this change, parts of the guard control plane could still be changed as
  additive or plain ops scope.
- That created a path where the protection model itself could be weakened more
  easily than the protected runtime it was meant to defend.
- This change intentionally raises the control-plane files to protected status.

## Rollback

- Revert this commit on the `chore/guard-baseline` branch, or restore the
  previous versions of `docs/impactshop-protected-files.json` and
  `scripts/guarded-push.sh` from git history.
- If rollback is needed after branch publication, reset the control-plane file
  classification to the previous baseline and remove the pre-push guard calls
  from `scripts/guarded-push.sh`.

## Smoke

- `deploy:guard-preflight`
- `deploy:checksum-verify`

## Notes

- `scripts/guarded-push.sh` now runs both lane and protected-touch checks before
  invoking `git push`.
- The guard model now classifies the control-plane scripts and docs as protected
  and maps them to the `deploy_guard` smoke group.
