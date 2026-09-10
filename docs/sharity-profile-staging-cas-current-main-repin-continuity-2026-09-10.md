# Sharity Profile staging CAS current-main repin continuity

## Identity

- Repo: `impactshop-notes`
- Worktree: `impactshop-notes-fix-sharity-staging-profile-app-host-sol-20260910`
- Branch: `fix/sharity-staging-profile-app-host-sol-20260910`
- Plan ID: `sharity-profile-staging-cas-current-main-repin-20260910`
- Base: `origin/main@9d351cd45412bb95c7839d7e77155dbb46b2a92f`
- Candidate source profile: `deploy-control-source:sharity-staging-cas-v1`

## Scope and status

This package repins only the two guard-manifest blob expectations needed by the
already reviewed staging CAS source on current main and rebinds the derived
policy digest. Production profile bytes, S59, WordPress, database, provider,
runtime, cron and watchdog state are unchanged.

The future candidate manifest retains the full current protected inventory,
including the Sharity owner policy and VB2026 route, and replaces only the
four CAS-controlled deploy/rollback test and script hashes. The deterministic
candidate hashes are recorded in the plan and protected change record.

## Resume boundary

After this checkpoint is independently QA-reviewed and merged, create a fresh
exact-main CAS worktree. Apply the pinned seven protected bytes and exact six
support paths, then run schema-v2 admission. Do not deploy from this metadata
branch. Staging and production remain separate Sol release gates; reuse evidence
only while source and environment identity remain unchanged.
