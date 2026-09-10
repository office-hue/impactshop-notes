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
including the Sharity owner policy and VB2026 route, and replaces only the
four CAS-controlled deploy/rollback test and script hashes. The deterministic
`generated_at` is `2026-09-10T20:30:00+00:00`; the resulting manifest and
checksum blob SHA-256 values are
`0e5fcc5d4e4306f550b02b4a202887f052d4a1affca9fc2a48f4a4380c47e054`
and `e2f7620eb6f77817f92f4df471711bc06065c4c009b8e1dcf32ae25be5cf62d4`.

## Resume boundary

After this checkpoint is independently QA-reviewed and merged, create a fresh
exact-main CAS worktree. Apply the pinned seven protected bytes and exact six
support paths, then run schema-v2 admission. Do not deploy from this metadata
branch. Staging and production remain separate Sol release gates; reuse evidence
only while source and environment identity remain unchanged.
