# Protected Change Record: IC Shell Fail-Closed Hardening

## Protected files touched
- `bin/deploy-wpcontent-map.sh`
- `bin/impactshop-guard-deploy.sh`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-guard-config.sha256`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `docs/impactshop-protected-files.json`
- `wp-content/mu-plugins/impactshop-action-bar.php`
- `wp-content/mu-plugins/zzz-impactshop-ui-lock.php`

## Why
- A production `impact-challenge` shell regresszió után fail-closed guard kellett arra az esetre, ha a kanonikus IC shell source hiányzik a mapped repóból, vagy ha IC runtime/shell driftet valaki non-interactive auto-approve lane-en próbálna átvinni.

## Rollback
- Git rollback: `git revert <guard-fix-commit>`
- File rollback: restore from `/Users/bujdosoarnold/Developer/GitHub/impactshop-notes/.codex/backups/guard-hardening-20260402-ic-shell` and `/Users/bujdosoarnold/Developer/GitHub/impactshop-notes/.codex/backups/guard-hardening-phase2-20260402-095345`
- Runtime rollback only if needed after validation; this change set itself nem módosít production tartalmat, csak a guard lane-t és a kanonikus source készletet.

## Smoke
- `deploy:guard-preflight`
- `deploy:checksum-verify`
- `route:impact-challenge`
- `route:impactshop`
- `route:home`
- `flow:mobile-shell-render`
- `flow:consent-overlay`
- `flow:pwa-install-entry`
- `flow:legacy-pool-visibility`
- `browser:mobile`
- `scripts/impact-challenge-ui-smoke.sh https://app.sharity.hu/impact-challenge/`

## Notes
- Auto-approve mostantól csak guard control-plane fájlokra engedett.
- IC runtime/shell drift esetén kézi jóváhagyás kötelező.
