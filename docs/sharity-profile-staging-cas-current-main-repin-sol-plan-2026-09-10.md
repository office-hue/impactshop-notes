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
    `a4863e43c5ea5c3ce6a407215764fe9cd33dec2897954d7eaec1044d5e32cea6`;
  - `docs/impactshop-guard-hashes.sha256` ->
    `b5fdeb82a868101e96ba5407ea32eb5403270ee6ebc1e90fdbf8d6e63f078c14`.
- The five previously reviewed CAS blob pins, exact seven protected paths,
  exact six support paths, production companion and provider denial remain
  unchanged.
- The future manifest keeps the complete `44547312` protected inventory (155
  protected paths and the same 157 hash keys). It changes the four
  CAS-controlled script/test digests and the stale
  `.github/workflows/ci.yml` digest introduced by PR #197 from
  `4033833c90a90b54d32b5a80eb6232eafa32adb1793208eeb8bbce5d45deebe9`
  to the raw `44547312` blob SHA-256
  `ab43c08a72c69d56187771b5b4f8cf81deda401d22685adf4cd84f0d52886015`.
  The explicit deterministic `generated_at` is
  `2026-09-10T20:30:00+00:00`; serialization matches the guard writer
  (`json.dump`, two-space indent, UTF-8, no trailing newline).
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
