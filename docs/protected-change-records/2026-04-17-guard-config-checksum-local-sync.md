# Protected Change Record - 2026-04-17 - local guard config checksum sync

## Protected files touched
- docs/impactshop-guard-config.sha256

## Why this change is needed
- Guarded staging deploy requires checksum parity between `docs/impactshop-guard-config.json` and `docs/impactshop-guard-config.sha256`.
- Local parity was broken and blocked guarded deploy preflight.

## Rollback
- Restore previous checksum line in `docs/impactshop-guard-config.sha256` if needed.

## Smoke
- deploy:guard-preflight
- deploy:checksum-verify
