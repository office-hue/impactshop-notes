# Sharity Profile staging CAS current-main repin continuity

## Identity

- Repo: `impactshop-notes`
- Worktree: `impactshop-notes-fix-sharity-staging-profile-app-host-sol-20260910`
- Branch: `fix/sharity-staging-profile-app-host-sol-20260910`
- Plan ID: `sharity-profile-staging-cas-current-main-repin-20260910`
- Base: `origin/main@44547312cb06e24fe2999faf9abb4e2f63fb945e`
- Candidate source profile: `deploy-control-source:sharity-staging-cas-v1`

## Scope and status

This package repins only the two guard-manifest blob expectations needed by the
already reviewed staging CAS source on current main and rebinds the derived
policy digest. Production profile bytes, S59, WordPress, database, provider,
runtime, cron and watchdog state are unchanged.

The future candidate manifest retains the full current protected inventory,
including the Sharity owner policy and VB2026 route. It replaces the four
CAS-controlled deploy/rollback test and script hashes and corrects the existing
`.github/workflows/ci.yml` key to raw `44547312` SHA-256
`ab43c08a72c69d56187771b5b4f8cf81deda401d22685adf4cd84f0d52886015`.
The other PR #197 script, test and protected-model paths are not manifest keys
and are not added. With deterministic `generated_at`
`2026-09-10T20:30:00+00:00` and guard-writer serialization without a trailing
newline, the resulting manifest and checksum blob SHA-256 values are
`a4863e43c5ea5c3ce6a407215764fe9cd33dec2897954d7eaec1044d5e32cea6`
and `b5fdeb82a868101e96ba5407ea32eb5403270ee6ebc1e90fdbf8d6e63f078c14`.

## Resume boundary

After this checkpoint is independently QA-reviewed and merged, create a fresh
exact-main CAS worktree. Apply the pinned seven protected bytes and exact six
support paths, then run schema-v2 admission. Do not deploy from this metadata
branch. Staging and production remain separate Sol release gates; reuse evidence
only while source and environment identity remain unchanged.
