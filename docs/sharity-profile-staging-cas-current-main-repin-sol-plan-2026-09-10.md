# Sharity Profile staging CAS current-main repin — Sol plan

## Control-plane repin

Operator approval reference:
`operator-approval:sharity-profile-production-minimal-runs-20260910`.

Plan ID: `sharity-profile-staging-cas-current-main-repin-20260910`.

### Objective and identity

Repin the existing repo-local
`deploy-control-source:sharity-staging-cas-v1` profile to the exact CAS
candidate derived from `origin/main@9d351cd45412bb95c7839d7e77155dbb46b2a92f`.
The candidate preserves the current protected inventory, adds the previously
reviewed staging exact-release CAS bytes, and corrects the staging HTTP base to
`https://app.sharity.hu/impactshop-staging`.

This first checkpoint changes only the target contract, its derived policy
digest and synchronized governance evidence. It does not change release code,
connect to S59, write a database, deploy, or activate runtime state.

### Security contract

- Only the two current-main-derived manifest blob pins change:
  - `docs/impactshop-guard-hashes.json` ->
    `fed75afef4638a6de80748e3842a3a02a15ef3ec4d98fd1654abc6a5e60025f3`;
  - `docs/impactshop-guard-hashes.sha256` ->
    `2583a3a0c60b1f24db1333624f176d86b57c1a22c9bcffc2b4378e91aa81b2e0`.
- The five previously reviewed CAS blob pins, exact seven protected paths,
  exact six support paths, production companion and provider denial remain
  unchanged.
- The future manifest keeps the complete `9d351cd` protected inventory and
  changes only the four CAS-controlled file digests plus its deterministic
  `generated_at` field.
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

