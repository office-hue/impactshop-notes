# Sharity profile CLS stability — protected change record

Date: 2026-09-11

Status: source-only Luna checkpoint; no publication or live activation

## Scope

Staging Playwright measured desktop CLS `0.064` and mobile CLS `0.326` on
`/impactshop-staging/profil/`. The async identity/points blocks initially
rendered hidden and then changed height after data loading. The bounded fix
adds profile-scoped inline CSS reservations for the full points block, compact
points summary and last-NGO block. The content remains visually hidden until
its existing JavaScript data flow reveals it; functionality, accessibility,
root/staging route behavior and owner/origin policy are unchanged.

## Protected files touched

- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `docs/bastion-guard-status.md`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`

## Smoke scope

Required identity/profile smoke tags: `route:factlens-vb-prod`,
`route:impact-challenge`, `route:profil`, `flow:message-popup`,
`flow:points-jump`, `flow:legacy-pool-visibility`,
`flow:profile-return-account`, `flow:profile-return-restore`,
`flow:profile-open`, `browser:webkit`, `browser:chrome`.

## Evidence and rollback

- `php -l wp-content/mu-plugins/impactshop-identity-panel.php` PASS
- `node --check wp-content/mu-plugins/impactshop-identity-panel.js` PASS
- `python3 tests/impactshop-profile-cls-contract.test.py` PASS
- Existing profile summary/bootstrap/remediation/static and owner-policy tests
  PASS
- guard hash verification and `git diff --check` PASS

The mobile `<0.1` target is intentionally not claimed until the corrected
source is deployed and measured again in staging. Rollback is a source-only
revert of this checkpoint before publication; no live rollback or data change
is part of this record.
