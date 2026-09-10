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
