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
- Next gate: `gpt-5.6-terra`, high reasoning for independent plan QA and exact
  implementation allowlist.
