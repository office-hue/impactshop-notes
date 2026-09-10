# Sharity NGO preference Package B — Terra architecture QA

Status: `re-qa-approved-for-bounded-luna`; implementation remains source-only and
default-off.

Reviewed identity: `impactshop-notes` branch
`feat/sharity-ngo-preference-source-authority-terra-20260909`, Sol checkpoint
`b532e2d2f090ec457872bb47e641204122eb22f5`, base
`origin/main@073f2854d4e4bc01ad928636125b7a18dc43efa0`.

## Scope reviewed

- Sol source-authority decision and its planned authorization-code/BFF bridge.
- Current `impactshop_identity_owner_authorized()` owner-grant verifier.
- Current `sharity_ngo_catalog` plus campaign-flag selection model.
- Protected-file model: no runtime or protected file was changed in this QA phase.

## Blocking architecture findings

### QA-B1 — existing same-origin helper is too broad for authorization-code issue

`impactshop_identity_request_same_origin()` allows a fixed cross-host set including
`sharity.hu`, `www.sharity.hu`, `app.sharity.hu`, staging hosts, and the current
home host. That is suitable for its existing profile use, but it does not implement
the exact source-origin requirement of a bearer-session authorization-code issuer.

Package B must not reuse it as its only CSRF/origin control. Sol must decide the
exact canonical issuer origin(s), staging parity, and whether the additive module
uses a new exact-origin helper or a narrowly scoped identity API. The BFF callback
allowlist does not make a browser POST cross-origin-safe.

### QA-B2 — no canonical catalog mapping exists for global preference contexts

The current numeric catalog joins `sharity_ngo_catalog` to
`sharity_ngo_campaign_flags` by `campaign_key`; active/selectable state is therefore
campaign-specific. The Sol decision names four global preference contexts but does
not define their catalog campaign mapping or a new global catalog policy. Reusing
the VB2026 campaign implicitly would couple independent truth sources and violate the
Package A contract.

Sol must choose one explicit model before implementation:

1. an exact, versioned context-to-campaign mapping; or
2. a new global preference catalog policy with its own active/selectable flags and
   revision calculation.

Until then the source cannot calculate a meaningful current catalog revision, reject
unselectable preferences correctly, or supply an auditable no-fallback resolution.

## Positive evidence

- Owner grants are server-side hashed, cookie-bound, revocable, and pseudo-bound.
- The catalog has numeric NGO IDs and campaign-level active/selectable checks.
- The current worktree remains clean outside documentation; `git diff --check`
  passes.

## Required Sol revision

`gpt-5.6-sol`, high reasoning, must amend the architecture decision with the exact
issuer-origin policy and catalog policy/mapping. It must preserve the additive,
default-off, no-provider/no-deploy boundary. Terra will then define the exact Luna
allowlist and test matrix.

## Sol revision recorded

The Sol architecture revision now specifies:

- one exact HTTPS issuer origin per environment, derived from the normalized
  `home_url('/')` origin tuple, enforced on the issuing POST together with a
  purpose-bound one-time CSRF token; the existing cross-host helper is not the sole
  control and missing `Origin` fails closed;
- a separate exact per-environment `(client_id, redirect_uri)` BFF callback
  registry with no wildcard or suffix matching; and
- a new global preference catalog policy, common to all preference scopes and
  independent of `sharity_ngo_campaign_flags`, with zero selectable NGOs until an
  explicit later data activation.

These changes architecturally address QA-B1 and QA-B2 but required an independent
Terra re-QA before any Luna allowlist could be published. The additive, default-off,
no-provider/no-deploy boundary is unchanged.

## Terra re-QA outcome

The revision resolves both blockers. QA-B1 now separates the source-origin browser
POST check from the BFF callback registry, keeps one exact HTTPS issuer origin per
environment, and fails closed on a missing origin. QA-B2 now assigns global
preference eligibility to a distinct policy table and excludes campaign flags from
the preference revision. Neither decision requires a protected runtime edit or a
live data operation.

The later module must have no populated BFF client registry, no secret, and no
catalog-policy rows in this package. An absent registry or policy must deny/return
`selection_required`; it must not infer data from a campaign or a legacy selector.
Subject-key provisioning, client registration, policy population, activation, schema
execution, staging, and production stay outside the Luna scope.

### Approved Luna file allowlist

Only the following paths may be added or changed by the bounded implementation:

- `wp-content/mu-plugins/impactshop-sharity-ngo-preference-source.php.off` (new,
  disabled additive module only);
- `tests/impactshop-sharity-ngo-preference-source-static.test.py` (new);
- `tests/impactshop-sharity-ngo-preference-source-contract.test.php` (new,
  hermetic pure-function contract test);
- `docs/bastion-guard-status.md`;
- `docs/protected-change-records/2026-09-10-sharity-ngo-preference-source.md`
  (new);
- `conversation-summaries/2026-09-09-sharity-ngo-preference-source-sol.md`; and
- `notes.md`.

No existing file under `wp-content/mu-plugins/` may be changed. In particular, the
identity-panel, selector, and VB2026 catalog/flags files are outside the allowlist.
The implementation must not add a config value with a real client, secret, key, or
policy row, and must not rename the `.off` module to an autoloaded PHP file.

### Required Luna evidence

1. `php -l` on the new disabled module.
2. The static test must prove default-off status, no activation/schema execution,
   no network/provider call, no legacy selector reuse, no use of the broad
   `impactshop_identity_request_same_origin()` helper, no raw identifier/token
   logging, and private no-store route policy.
3. The hermetic PHP contract test must prove exact HTTPS origin normalization and
   cross-host/missing-origin rejection; exact client/redirect tuple validation;
   CSRF binding and one-time rejection; code/bearer expiry and revocation semantics;
   globally policy-based catalog revision; empty-policy, campaign-independence,
   inactive/unselectable, stale-revision, CAS, idempotency, and audit outcomes.
4. The existing protected-touch, continuity, and `git diff --check` guards must
   pass before the Luna checkpoint. Runtime, provider, browser, database, and
   deployment tests are intentionally out of scope because the module stays `.off`.

This is a narrow implementation authorization only. Any need to change an existing
runtime file, activate the module, write schema/policy data, provide a client or
secret, or resolve a test conflict returns the work to `gpt-5.6-sol`, high.
