# Sharity profile subdirectory route — protected change record

Date: 2026-09-11

Status: source-only Luna checkpoint; no publication or live activation

## Scope

The staging WordPress home is mounted at `/impactshop-staging`, while the
production profile is mounted at `/profil` from the host root. The prior
profile classifier compared the raw request path directly with `/profil`, so
staging UI requests bypassed profile cache/privacy and ad suppression and the
one-time code route was not recognized.

The bounded correction uses the exact path component of `home_url('/')` as an
environment prefix. Only an exact segment match is removed; query strings and
fragments never affect classification, and near-miss or foreign nested paths
remain rejected. The reveal route check and HttpOnly reveal-cookie path derive
from the same helper. Host, origin and owner-grant checks are unchanged.

## Protected files touched

- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `tests/impactshop-profile-route-path.test.php`
- `tests/impactshop-profile-summary-checkpoint-a.test.py`
- `docs/sharity-profile-summary-live-closure-luna-continuity-20260910.md`
- `docs/bastion-guard-status.md`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`

## Evidence and rollback

- `php -l wp-content/mu-plugins/impactshop-identity-panel.php` PASS
- `php tests/impactshop-profile-route-path.test.php` PASS
- `python3 tests/impactshop-profile-summary-checkpoint-a.test.py` PASS
- Existing profile bootstrap/remediation and cookie-touch tests PASS
- `git diff --check` PASS

## Smoke scope

Required identity/profile smoke tags: `route:factlens-vb-prod`,
`route:impact-challenge`, `route:profil`, `flow:message-popup`,
`flow:points-jump`, `flow:legacy-pool-visibility`,
`flow:profile-return-account`, `flow:profile-return-restore`,
`flow:profile-open`, `browser:webkit`, `browser:chrome`.

Rollback is a source-only revert of this checkpoint before publication. No
remote, provider, database, OPcache, cron or watchdog operation belongs to
this record.
