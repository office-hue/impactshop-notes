# Sharity Profile SP1 — Terra QA1

Status: **closed by Terra re-review at `f26df98`**.

Review identity:

- worktree: `sharity-profile-sp1-20260909`
- range: `a6f83e3..608469b`
- security scan: `239e6c85-04a3-4bc8-8a7f-54fe7a1280fd`
- scope: profile owner grant, code issue/reveal, profile UI and profile-path AdSense exclusion

The listed scan/checkpoint IDs are the historical, never-pushed review chain.
Before publication that chain was repacked only to separate protected identity
and app-content AdSense commit lanes. The reviewed identity runtime bytes are
identical in publication commit `6f7aa97`; the AdSense-only commit is `3ead4ee`.

## Validated finding — low severity

`impactshop_identity_profile_restore()` successfully changes the active pseudo profile by issuing a new owner grant and setting the new pseudo cookie, but it does not call `impactshop_identity_owner_revoke_current()` first. The old grant remains active in the database until expiry, contrary to the approved owner-grant decision's account-switch invariant.

An attacker still needs a previously retained valid owner-grant session and matching pseudo context; the Secure, HttpOnly, Strict cookie and server-side grant binding reduce likelihood. However, a deliberate account switch must invalidate the prior device grant, so this blocks release acceptance.

Required bounded Luna fix:

1. On the successful restore path, revoke the current grant before issuing/binding the new profile grant.
2. Preserve fail-closed ordering: do not set the new pseudo cookie unless new-grant issuance succeeds.
3. Add a static contract check proving the restore path invokes the revoke helper before issuing the replacement grant.
4. Re-run the identity static test, JS check, VPS PHP lint, `git diff --check`, and local continuity guard.

Luna correction applied in the current working package: the restore path now calls `impactshop_identity_owner_revoke_current()` before owner-grant issuance, and the static test asserts that ordering.

Terra re-review result: the original account-switch path is closed. The current source revokes the owner token before replacement grant issuance, so the old row no longer satisfies the `revoked_at IS NULL` authorization query. The focused static contract test passes. No live WordPress/database E2E was run; that remains part of the later Sol staging gate.

## Passed review surfaces

- Profile code generation requires an active, bound owner grant and same-origin request check.
- Normal profile API output does not expose the portable code; the reveal flow is first-party and no-store.
- The profile subtree excludes the AdSense head injection.

## Remaining release gates

- This QA does not alter the prior Sol release boundary: live schema migration, staging browser/DB E2E, cross-origin and replay cases, backup/rollback proof, and production activation remain Sol-owned.
- Other direct pseudo-cookie mutators listed in the Sol decision remain outside SP1's source correction and block production acceptance until centrally protected.
