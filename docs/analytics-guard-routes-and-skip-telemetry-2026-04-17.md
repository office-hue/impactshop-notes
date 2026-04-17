# Analytics Guard Stabilization (2026-04-17)

## Scope
- Add missing signed analytics REST routes used by canary checks.
- Add skip telemetry with 24h warning threshold.
- Fix push-range derivation in safe pre-push audit to avoid full-history scans on fresh branches.

## Changes
- `wp-content/mu-plugins/impactshop-email-proxy.php`
  - Added `GET /wp-json/impact/v1/analytics/summary`.
  - Added `GET /wp-json/impact/v1/analytics/flags`.
  - Both endpoints enforce timestamp + HMAC verification (`impact_ts`, `impact_sig` or equivalent headers).
- `scripts/verify/analytics-suite.sh`
  - Added SKIP event logging to `.codex/logs/analytics-skip-events.log`.
  - Added 24h SKIP volume warning (`ANALYTICS_SKIP_WARN_THRESHOLD`, default `6`).
- `scripts/safe-repo-audit.sh`
  - Added robust push base resolver: upstream -> origin/main -> main -> HEAD^ -> empty tree fallback.

## Operational Notes
- Expected canary outcome after deploy: routes should return `200` (no `rest_no_route`).
- SKIP warnings are non-blocking and intended for early operational visibility.
- No deploy executed in this change set.

## Rollback
- Revert the commit introducing these changes.
- If needed, disable only telemetry behavior by restoring `scripts/verify/analytics-suite.sh` to previous version.
