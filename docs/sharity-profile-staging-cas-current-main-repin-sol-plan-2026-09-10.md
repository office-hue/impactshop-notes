# Sharity Profile staging CAS current-main repin — Sol plan

## Control-plane repin

Operator approval reference:
`operator-approval:sharity-profile-production-minimal-runs-20260910`.

Plan ID: `sharity-profile-staging-cas-current-main-repin-20260910`.

### Objective and identity

Repin the existing repo-local
`deploy-control-source:sharity-staging-cas-v1` profile to the exact CAS
candidate derived from `origin/main@44547312cb06e24fe2999faf9abb4e2f63fb945e`.
The candidate preserves the current protected inventory, adds the previously
reviewed staging exact-release CAS bytes, and corrects the staging HTTP base to
`https://app.sharity.hu/impactshop-staging`.

This first checkpoint changes only the target contract, its derived policy
digest and synchronized governance evidence. It does not change release code,
connect to S59, write a database, deploy, or activate runtime state.

### Security contract

- Only the two current-main-derived manifest blob pins change:
  - `docs/impactshop-guard-hashes.json` ->
    `0e5fcc5d4e4306f550b02b4a202887f052d4a1affca9fc2a48f4a4380c47e054`;
  - `docs/impactshop-guard-hashes.sha256` ->
    `e2f7620eb6f77817f92f4df471711bc06065c4c009b8e1dcf32ae25be5cf62d4`.
- The five previously reviewed CAS blob pins, exact seven protected paths,
  exact six support paths, production companion and provider denial remain
  unchanged.
- The future manifest keeps the complete `44547312` protected inventory (155
  protected paths and the same 157 hash keys) and changes only the four
  CAS-controlled script/test digests plus the explicit deterministic
  `generated_at` value `2026-09-10T20:30:00+00:00`.
- The ordinary schema-v1 protected lane closes this repin. The staging CAS
  profile cannot admit its own control-plane change.

### Closure and follow-up

Closure requires contract/policy parity, independent future-blob hash
recalculation, adapter and protected-lane tests, full validation, maximum
bastion, DocSync, continuity, `git diff --check`, clean tree and one checkpoint.
After merge, a fresh exact-main CAS checkpoint applies the already reviewed
release code and runs the exact schema-v2 admission profile. Only its successful
merge permits one staging release wave, followed by one production wave if all
runtime and worker checks pass. No cron or watchdog is required.
