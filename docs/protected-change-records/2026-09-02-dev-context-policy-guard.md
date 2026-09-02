# DEV context policy guard — protected change record

Protected files touched: `.github/workflows/ci.yml`, `docs/bastion-guard-status.md`.

Rollback: revert the single checkpoint commit; the existing CI workflow and
bastion evidence are restored without touching product or deploy state.

Smoke: `tests/dev-context-policy-guard.test.sh`, `bash -n scripts/dev-context-policy-guard.sh`,
and the existing CI validation lane.
