# Sharity Profile deploy-control source bootstrap — Sol plan

## Deploy control source bootstrap

Operator approval reference:
`operator-approval:sharity-profile-deploy-control-source-bootstrap-20260909`.

### Objective

Create a one-time, repo-local source-admission profile that can recognize the
already reviewed Sharity staging exact-release CAS package as protected source
instead of granting general deploy-class admission. This bootstrap stays on its
own branch so the policy cannot admit itself.

### Identity and scope

- Repository: `impactshop-notes`.
- Branch: `ops/sharity-profile-deploy-control-source-bootstrap-20260909`.
- Base: `origin/main@073f2854d4e4bc01ad928636125b7a18dc43efa0`.
- Profile: `deploy-control-source:sharity-staging-cas-v1`.
- Source only: no push, PR, merge, provider build, VPS command, remote write or
  runtime activation belongs to this checkpoint.
- No cron or watchdog is required because the capability is a deterministic
  source-admission check and has no scheduled runtime behavior.

### Security contract

- The profile admits exactly the seven reviewed staging CAS protected files
  and the six exact plan/change-record/continuity support files already present
  in the parked candidate; subset, superset and unrelated support paths fail
  closed.
- The target contract pins the reviewed HEAD SHA-256 of every protected CAS
  file. The schema-v2 manifest must repeat the exact map, and the adapter hashes
  raw Git blob bytes independently before admission.
- Every CAS protected file must be a modification. Delete, rename and copy
  statuses are forbidden; the exact change record is the only additive path.
- `.deploy.production.env` must remain byte-identical to the base and its
  reviewed SHA-256 must be recorded in the schema-v2 change manifest.
- The plan reference and prior operator approval reference are immutable
  profile inputs.
- `providerDeployAllowed=false` is bound in contract, policy, output, private
  validation evidence and frozen-candidate verification.
- Invalid or unknown profile attempts remain deploy-class and source-blocked.
- The bootstrap itself uses the ordinary schema-v1 protected admission lane;
  changing any control-plane file makes the staging profile path set inexact.

### Verification and rollback

Focused fixtures cover the positive exact candidate, subset/superset, runtime,
workflow and remote-write extras, rename/copy/delete, wrong references, changed
production companion, reviewed-hash mismatch, evidence tampering, provider
authority drift, contract digest drift and self-admission. Closure also requires
shell syntax, protected-touch/commit-lane checks, DocSync continuity, strict
audit, `git diff --check`, a clean tree and one checkpoint commit.

Rollback is the exact revert of this source checkpoint. Because no remote or
runtime state changes in this package, rollback requires no VPS, provider or
data recovery action.

### Follow-up boundary

After this bootstrap is independently QA-reviewed and published through the
normal source lane, the parked CAS branch may integrate the resulting
`origin/main`, upgrade its exact change record to schema v2 with the reviewed
production companion digest, and run full validation/bastion/freeze/verify.
Actual staging release remains a later Sol/operator release decision.
