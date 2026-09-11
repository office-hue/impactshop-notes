# Sharity profile responsive CLS — protected change record

Date: 2026-09-11

Status: source-only Luna checkpoint; no publication or live activation

## Scope

The exact staging timeline after early profile asset enqueue measured desktop
CLS `0.0413`, mobile CLS `0.2506` and horizontal overflow `0`. Remaining
shifts were push (`157px` desktop / `181px` mobile), points (`265px` /
`301px`), votes (`139px`) and history (`75px`).

The bounded correction adds only profile-scoped responsive CSS reservations.
The hidden push block uses `display:block` and `visibility:hidden`, with the
same min-height as its visible state at each breakpoint. Existing compact and
last-NGO reservations remain. Auth, JS, global/shared dependencies,
host/origin rules and functionality are unchanged.

## Protected files touched

- `wp-content/mu-plugins/impactshop-identity-panel.php`
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

The mobile `<0.1` target remains a staging remeasurement gate. Rollback is a
source-only revert of this exact checkpoint before publication; no live
rollback or data change is included.
