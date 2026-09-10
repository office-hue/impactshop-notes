# Sharity NGO preference Package B — Sol handoff

- Canonical worktree: `impactshop-notes-feat-sharity-ngo-preference-source-authority-terra-20260909`
- Branch: `feat/sharity-ngo-preference-source-authority-terra-20260909`
- Base: `origin/main@073f2854d4e4bc01ad928636125b7a18dc43efa0`
- Plan: `docs/sharity-ngo-preference-source-authority-sol-plan-2026-09-09.md`
- Decision: additive `.php.off` source module; no existing protected runtime edit.
- Identity bridge: current owner grant -> one-time authorization code + PKCE ->
  confidential BFF exchange -> short-lived opaque bearer.
- Data: versioned HMAC subject, numeric current-catalog validation, revision-bound
  CAS, idempotency, append-only audit, prospective preferences only.
- Boundaries: no UI/activity integration, provider, deploy, schema execution, secret,
  cron, watchdog, push, PR or merge.
- Terra QA result (2026-09-10): Sol revision required before a Luna allowlist. The
  existing owner-grant origin helper is cross-host, not exact issuer-origin; the
  current catalog is campaign-scoped and lacks a canonical mapping for the four
  global preference contexts. See
  `docs/sharity-ngo-preference-source-authority-terra-qa-2026-09-10.md`.
- Sol revision (2026-09-10): the issuer uses one exact HTTPS `home_url('/')` origin
  per environment plus a purpose-bound one-time CSRF token on code-issuing POST;
  missing `Origin` fails closed and the existing cross-host helper is insufficient.
- Global preference eligibility moves to a new policy table shared by all preference
  scopes and independent of campaign flags. It starts empty/fail-closed; initial
  population and activation require a later explicit Sol data operation.
- Next gate: `gpt-5.6-terra`, high, for independent re-QA and the exact Luna
  implementation allowlist/test matrix. No runtime or protected source changed.
- Terra re-QA (2026-09-10): QA-B1 and QA-B2 are resolved by the Sol revision.
  Bounded Luna work is admitted only for a new `.php.off` adapter, two hermetic
  tests, bastion/change-record evidence, and continuity notes. No existing MU plugin,
  client/secret/key, policy row, schema execution, activation, provider, or deploy is
  allowed. See the Terra QA document for the exact allowlist and test matrix.
- Luna implementation (2026-09-10): the additive `.php.off` adapter and both
  contract tests are present, with no WordPress/runtime registration. Python static
  evidence passes; PHP lint/contract execution is blocked because this environment
  has no `php` executable. No dependency installation or alternate runtime was used.
- Terra post-Luna QA (2026-09-10): the adapter is only a helper skeleton and route
  list, missing the approved owner-grant, code/PKCE, subject, route, persistence,
  CAS/idempotency/audit, and response contracts. The bounded Luna allowlist remains
  sufficient for remediation; no Sol decision is needed. PHP evidence remains
  blocked until an admitted PHP-capable environment exists.
- Luna remediation (2026-09-10): added owner-grant gate, subject-HMAC, secure exact
  redirect validation, PKCE-bound one-time code issue/redeem, route-auth metadata,
  response headers, five table contract, and CAS/idempotency/audit helpers. Python
  static evidence passes; PHP evidence remains blocked by the absent executable.
- Terra re-QA (2026-09-10): the remediation is not yet functionally sufficient.
  Raw CSRF storage, retryable invalid code redemption, ungated catalog revision and
  selection, weak idempotency/audit, and missing schema/handler descriptors require
  another bounded Luna correction. No Sol decision is required.
