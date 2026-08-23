# Protected change record — Impi source-owner context (2026-08-23)

## Protected files touched

- `docs/bastion-guard-status.md` — continuity/bastion evidence only.
- `wp-content/mu-plugins/impact-community-impi-source.php` — new additive,
  default-off, read-only internal context route.
- `config/impact-impi-source-authority.json` — non-secret policy and SHA lock.
- `scripts/impact-impi-source-bastion-audit.py` — adversarial source guard.
- `tests/impact-impi-source-context.test.php` — hermetic contract/redaction
  fixtures.

The existing `impact-community.php`, identity/profile, points, votes, rewards,
donation, Offerwall and VB2026 files were not modified.

## Coherence and risk

The module is loaded independently from the browser/session routes and only
registers a GET route when the explicit feature flag is true. It reads active
circle/post rows with parameterized SQL and returns a strict, redacted
circle-only projection. There is no SQL writer, publication endpoint, public
permission callback, token fallback, cron, watchdog hook or provider mutation.
The primary risks are accidental secret/config drift and cross-circle or PII
leakage; the separate credential, pilot allowlist, redaction fixture and SHA
locked bastion fail closed on these conditions.

## Rollback

Remove only the new Impi source plugin and its policy/guard/test/docs files in
one guarded revert. Do not touch the existing community or identity runtime;
there is no database, option, secret, cron or provider rollback to perform.
Runtime activation remains off until a separate operator-controlled admission.

## Smoke checklist

- PHP syntax and hermetic context/redaction test pass.
- Python maximum-bastion audit passes, including exact plugin SHA and forbidden
  writer/token/publication patterns.
- `git diff --check` and strict safe-repo audit pass after staging.
- Normal `/hatas-korok`, profile-return, points/votes/reward, Offerwall and
  VB2026 UI/runtime surfaces are unchanged; no live UI smoke is claimed because
  no deploy or feature activation occurred.
- No cron or watchdog change is expected; the existing ai-agent receipt guard
  remains the sole supervision owner.
