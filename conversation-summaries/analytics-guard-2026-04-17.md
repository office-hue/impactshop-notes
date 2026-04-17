# Analytics Guard Follow-Up (2026-04-17)

- Added signed analytics REST routes for canary calls (`/analytics/summary`, `/analytics/flags`).
- Added SKIP telemetry log and 24h WARN threshold in analytics suite.
- Fixed push-range derivation fallback in safe audit (`upstream -> origin/main -> main -> HEAD^`).
- Existing unrelated dirty files were intentionally left untouched.
