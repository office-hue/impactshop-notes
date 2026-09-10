# Sharity NGO preference source adapter — change record

Date: 2026-09-10

Status: source-only; disabled and not published

## Scope and approval

Terra re-QA approved a bounded implementation after Sol resolved the exact
issuer-origin and global-catalog-policy findings. The adapter is additive and
must retain the `.php.off` suffix. This record covers source admission only;
activation, data population, secrets, provider, staging, production, and deploy
are not authorized.

## Protected files touched

- `wp-content/mu-plugins/impactshop-sharity-ngo-preference-source.php.off`
- `tests/impactshop-sharity-ngo-preference-source-static.test.py`
- `tests/impactshop-sharity-ngo-preference-source-contract.test.php`
- `docs/bastion-guard-status.md`
- `docs/protected-change-records/2026-09-10-sharity-ngo-preference-source.md`
- `conversation-summaries/2026-09-09-sharity-ngo-preference-source-sol.md`
- `notes.md`

No existing MU-plugin, protected runtime file, guard manifest, deployment file,
client/secret/key configuration, or database schema/data is in scope.

## Coherence and risk boundaries

The adapter owns only pure source-authority contracts for exact-origin code
issuance, owner-grant-bound CSRF, one-time credentials, opaque subject/session
checks, global preference-catalog revision, and no-fallback selection status.
Campaign flags, legacy slug selection, votes, points, rewards, activity writers,
and historical attribution remain separate authorities. The principal failure
mode is accidental activation or inferred catalog data; the `.off` suffix, empty
registry/policy fail-closed behavior, static assertions, and protected-touch
guard prevent those paths.

## Smoke scope

The source-only smoke scope covers the identity web-session authorization route,
the NGO preference route, and fail-closed `selection_required` resolution. No live
browser or deployment smoke ran because the module remains disabled.

## Evidence and rollback

Python static evidence, PHP lint and hermetic PHP contract execution passed using
the admitted local PHP 8.4 runtime. `git diff --check`, continuity, and
protected-touch checks remain required at checkpoint. Rollback is a
revert/removal of this source-only commit before activation; no live schema or
data exists to roll back.

## Protected source admission

The machine-readable manifest below covers the exact protected endpoints in the
pinned base-to-HEAD candidate. It grants source-only admission only; it grants
no activation, staging, provider, VPS, database, secret or production authority.

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "operatorApprovalRef": "operator-approval:sharity-ngo-preference-source-publication-20260910",
  "planRef": "docs/sharity-ngo-preference-source-authority-sol-plan-2026-09-09.md#sharity-ngo-preference-source-authority-sol-decision",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "wp-content/mu-plugins/impactshop-sharity-ngo-preference-source.php.off"
  ],
  "rollbackNote": "revert the exact source-only candidate commits before activation; no live schema or data exists",
  "schemaVersion": 1,
  "smokeTags": [
    "sharity:ngo-preference-source-static",
    "sharity:ngo-preference-source-contract",
    "sharity:disabled-adapter"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->
