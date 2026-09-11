# Sharity profile hidden push CLS — protected change record

Date: 2026-09-11

Status: source-only Luna checkpoint; no publication or live activation

## Scope

The exact shared Playwright simulation measured desktop CLS `0.041389` and
mobile CLS `0.137899`. The remaining mobile shift was the hidden profile push
block computed at `display:none` and then expanding to `181px`, because the
browser `[hidden]` rule overrode the profile reservation.

The bounded correction makes only the narrow
`.impactshop-identity-push[data-role=push-section][hidden]` selector use
`display:block !important` and `visibility:hidden`. The HTML hidden attribute,
JS/auth behavior, responsive dimensions, accessibility semantics and global
dependency boundaries remain unchanged.

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
- `python3 tests/impactshop-profile-cls-contract.test.py` PASS
- Existing profile summary/bootstrap/remediation/static and owner-policy tests
  PASS
- guard hash verification and `git diff --check` PASS

The mobile `<0.1` target remains a staging remeasurement gate. Rollback is a
source-only revert of this exact checkpoint before publication; no live
rollback or data change is included.
