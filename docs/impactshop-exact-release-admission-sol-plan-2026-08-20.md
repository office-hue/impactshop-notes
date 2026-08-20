# ImpactShop exact-file production release admission — implementation-ready plan

Status: `approved-for-luna`

## 1. Scope and non-goals

This package opens only the already isolated exact-file production path after
adding a remote, machine-verifiable release transaction. One selected
repository file receives an immutable release record, an exact pre-state check,
a verified remote backup when it already exists, an atomic compare-and-swap
replacement, post-write SHA-256/PHP/mode verification and an executable,
compare-and-swap rollback. Broad production mapping remains blocked.

Task classification: max-protected deployment-control and reversible production
release hardening. The operator requested a coherent SOL package with minimum
publication operations. Planning follows the mandatory Terra gate and the
implementation is divided into bounded Luna chunks.

Falsifiable hypothesis: the exact-file resolver already proves one local file
maps to one remote destination, but the real-write branch is correctly blocked
because it cannot prove or restore the remote pre-state. A locked remote release
manifest plus explicit expected-before state and verified intended hash can
make that one write and its rollback deterministic without changing siblings.

Non-goals:

- no broad production directory sync and no `--delete*` production behavior;
- no affiliate redirect, link builder, attribution, commission, settlement,
  reward, points, vote, NGO truth, callback, feed or partner configuration
  change;
- no runtime activation option, database/schema mutation, cleanup cron or
  watchdog installation;
- no live-only file import, overwrite or ownership declaration;
- no staging repair and no generic multi-file release framework;
- no automatic rollback that can delete or overwrite a concurrently changed
  target.

## 2. Context and canonical sources

- `AGENTS.md`: dedicated worktree, canonical guarded deploy, protected-touch,
  continuity and checkpoint policy.
- VPS/dev memory: merged-main release, exact backup/rollback, physical relock,
  targeted smoke and protected evidence are mandatory.
- `docs/ai-cost-control/impact-capability-recovery-and-storage-plan-2026-08-06.md`
  in the canonical `ai-agent` repo: affiliate redirects and attribution remain
  deterministic protected backend authority; agent skills are advisory only.
- `docs/impactshop-deploy.md`: the sole canonical entrypoint is
  `bin/impactshop-guard-deploy.sh`; production truth is `/home/sharityh/app`.
- `docs/impactshop-exact-file-deploy-safety-sol-plan-2026-08-20.md`: exact
  mapping, no-delete dry-run and sibling isolation are merged; real writes must
  wait for remote backup, CAS, relock and executable rollback.
- `bin/deploy-wpcontent-map.sh`: exact source/destination resolver, production
  lock, bastion-manifest and origin-alignment boundary.
- `bin/impactshop-guard-deploy.sh`: clean-main/protected approval wrapper and
  truthful executable-gated rollback handover.
- `docs/impactshop-protected-files.json` and
  `docs/impactshop-guard-config.json`: max-protected control-plane inventories.
- `.github/workflows/ci.yml` and the three deploy-control tests: current local
  and GitHub parity surface.

Read-only production evidence collected on 2026-08-20:

- `python3`, `php`, `sha256sum`, `install`, `flock`, `mktemp` and `stat` are
  present on the VPS;
- `/home/sharityh/app/wp-content/mu-plugins` exists;
- `impactshop-sharity-affiliate-runtime.php` is absent, so its required first
  release pre-state is the literal `absent`, not a guessed digest;
- the existing `.bastion/protected-hashes.json` admission and public-to-app
  origin alignment remain independently required before release;
- the canonical wrapper delegates to the mapping deploy, while legacy direct
  references are documentation/history only and do not authorize bypass.

## 3. Acceptance criteria

1. Real production remains impossible without all of: exact file scope,
   `IMPACTSHOP_EXACT_RELEASE=1`, explicit `IMPACTSHOP_EXPECT_REMOTE_SHA256`
   (`absent` or 64 lowercase hex), clean named `main` or detached release
   worktree, and local `HEAD` equal to `origin/main`.
2. Broad production writes remain blocked before any network access. Existing
   exact and broad production dry-runs retain their no-write behavior.
3. A release ID is safe, unique and printed with the repository-relative target
   and intended SHA-256; host credentials and absolute local paths are not
   persisted or logged.
4. Remote preparation runs under an exclusive lock, validates the app root and
   target parent without symlink traversal, compares the actual state with the
   operator-declared expected state, and creates a mode-`0700` release directory.
5. If the target exists it must be a regular non-symlink file and is copied to a
   mode-`0600` backup whose SHA-256 equals the recorded original. If absent, the
   manifest records absence and no fake backup is created.
6. The schema-versioned JSON manifest atomically records release ID, relative
   target, original state/hash/mode, intended hash and phase. Unknown fields do
   not grant authority; invalid schema/path/hash/mode/phase fails closed.
7. Only the selected local file is uploaded to the release staging directory.
   The remote staged payload must be regular, non-symlinked, PHP-lint clean and
   byte-equal to the intended SHA before replacement.
8. Under the same canonical lock, apply rechecks the current target against the
   recorded original state immediately before atomic replacement. Any drift
   stops without modifying the target.
9. Successful apply uses an atomic same-filesystem replacement, sets the live
   file to `0444`, verifies PHP lint, SHA-256 and mode, fsyncs the relevant
   metadata, and marks the manifest `deployed` atomically.
10. Post-write verification failure triggers a bounded CAS rollback only while
    the target still equals the intended payload. It never overwrites a third
    state. Failure and recovery phase remain visible in the manifest/output.
11. `bin/impactshop-guard-rollback.sh` is read-only by default. Mutation requires
    `--production --apply`, a safe release ID and an explicit expected deployed
    SHA matching both manifest and current target.
12. Rollback restores and verifies the exact original bytes/mode when the target
    previously existed, or removes the file only when the original state was
    absent and the live target still matches the deployed SHA. Repeated or stale
    rollback fails closed.
13. Deterministic tests cover prepare/apply/rollback success, absent and existing
    origins, all admission failures, pre-apply race, pre-rollback race, symlink
    rejection, corrupt manifest/backup/payload, no sibling mutation, permission
    relock and legacy dry-run parity.
14. Release and rollback scripts, manifests and tests are added to max bastion
    inventories and CI. Protected change record, deploy runbook, status,
    continuity and docsync truth are updated.
15. Targeted and necessary full tests, `git diff --check`, strict audit,
    continuity/docsync checks and a checkpoint commit pass. Publication uses one
    guarded push, one PR and one merge unless the repository guard requires a
    separate documentation checkpoint.
16. After clean merged-main evidence passes, the default-off affiliate runtime
    may be released with expected state `absent`; activation, cron and watchdog
    remain a later independently approved package.

## 4. Design and file-level implementation plan

### `scripts/impactshop-exact-release-remote.py`

- Add one dependency-free Python remote transaction engine with explicit
  `prepare`, `apply`, `inspect` and `rollback` actions.
- Treat every CLI value and every stored manifest value as untrusted. Resolve a
  safe app root, allow only safe repository-relative targets below it, reject
  symlinked roots/parents/targets/release artifacts, and use descriptor-aware
  regular-file checks where replacement matters.
- Serialize every action with `fcntl.flock` on a fixed mode-`0600` lock below
  `/home/sharityh/app/.bastion`; never accept an operator-supplied lock location.
- Store releases below
  `/home/sharityh/app/.bastion/exact-file-releases/RELEASE_ID/` with directory mode
  `0700`, manifest/backup mode `0600`, and atomic JSON writes.
- `prepare` validates expected-before state and creates a verified backup or an
  explicit absent record. It never changes the target.
- `apply` validates payload SHA and PHP syntax, rechecks original CAS, performs
  same-parent atomic replacement and `0444` relock, verifies the result and
  updates phase. A post-write failure attempts only the manifest-bound CAS
  recovery and reports whether the original state was restored.
- `inspect` emits a small stable JSON/status contract without file contents or
  credentials.
- `rollback` validates deployed phase and explicit expected deployed SHA, then
  CAS-restores the verified backup or CAS-removes a newly created target.

### `bin/deploy-wpcontent-map.sh`

- Preserve all current parsing, mapping, manifest, origin and dry-run behavior.
- Replace only the exact real-production lock with a strict admission gate:
  exact scope, explicit release opt-in, expected-before value, clean named
  `main` or detached worktree, `HEAD == origin/main`, and required local/remote
  tools. Named non-main branches are rejected.
- Compute local SHA-256 portably, generate a constrained release ID, invoke
  remote `prepare`, upload exactly one payload without delete semantics, invoke
  remote `apply`, and require the final deployed/hash/mode result.
- Never use the legacy direct exact rsync path for real production. Dry-run
  continues through its current itemized no-write rsync branch.
- Print one executable rollback command containing only release ID and deployed
  SHA after verified success.

### `bin/impactshop-guard-rollback.sh`

- Add the canonical rollback entrypoint using the production env profile and
  the same remote transaction engine.
- Parse a small strict option set; reject unknown/duplicate/malformed inputs.
- Default to `inspect`. Require `--production --apply --release-id` and
  `--expected-deployed-sha` for mutation.
- Re-run production root/path admission and remote engine checks; never accept a
  free-form target or backup path from the operator.

### Tests and protection

- Extend `tests/deploy-wpcontent-map-exact-file.test.sh` with fake SSH/rsync/git
  release admission and exact upload assertions while preserving existing
  no-network block and dry-run tests.
- Add a focused remote-engine test using a temporary local app root to exercise
  actual filesystem locking, backup, atomic apply, relock, drift rejection and
  rollback without network or production state.
- Strengthen `tests/impactshop-guard-rollback-truth.test.sh` so executable
  rollback, explicit SHA/release arguments and the production gate are asserted.
- Wire syntax and all new deterministic tests into `.github/workflows/ci.yml`.
- Add the remote engine, rollback entrypoint and tests to the protected model;
  refresh protected hashes/checksums through the canonical guard workflow.

### Continuity and release evidence

- Add `docs/protected-change-records/2026-08-20-exact-release-admission.md`.
- Update `docs/impactshop-deploy.md`, `docs/bastion-guard-status.md`,
  `docs/impactshop-governance-system-plan-2026-06-16.md`,
  `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md`,
  `docs/impactshop-notes-doc-sync-map-2026-06-23.md`,
  `system-status-snapshot.md` and `notes.md`.
- Record the exact merged commit, release ID, expected-before state, intended
  SHA, final SHA/mode and rollback command. Never store SSH credentials or
  source/backup contents.

## 5. Risk, coherence, and security review

- Money/attribution boundary: this package changes only deployment mechanics.
  It does not modify the affiliate runtime, redirect rules or transaction truth.
- Destructive scope: broad writes and delete flags stay blocked. The only new
  mutation is one CAS-bound file replacement and its exact inverse.
- Race risk: prepare and apply are separate network calls, so the lock cannot be
  held across upload. This is intentional; apply reacquires the lock and repeats
  the original-state CAS immediately before replace. A concurrent change fails
  closed rather than being overwritten.
- First-install rollback: because production truth is currently absent,
  rollback may remove the new file only if its current SHA equals the explicitly
  supplied deployed SHA. A different live file is never deleted.
- Existing-file rollback: backup SHA and original metadata are verified before
  restore; the live target must still equal the deployed SHA.
- Manifest tampering: release directories and records are private/read-only to
  the deployment account, schema/path/hash/phase are revalidated on every
  action, and operator-supplied expected SHA adds an out-of-band comparison.
- Symlink/path escape: local exact scope validation remains; remote root,
  parents, target, payload, backup and manifest reject symlink/traversal forms.
- Partial failure: prepared-but-not-applied releases are inert. Failed uploads
  cannot change the target. Apply updates the manifest only after verified live
  state; bounded recovery never overwrites unrecognized state.
- Compatibility: Python 3, PHP, SHA-256 and file utilities were confirmed
  present on the production VPS. The engine itself uses the Python standard
  library, reducing shell/version variance.
- Source-state risk: real release requires clean merged `main`, preventing a
  protected production file from being deployed from an unreviewed worktree.
- Secrets/privacy: outputs contain only relative path, release ID, phase, hashes
  and modes. No URL query, user pseudo ID, NGO choice, token, transaction or
  remote file content is read into evidence.
- Cronos/watchdog: the release mechanism is operator-invoked and has no recurring
  job. The runtime remains default-off, so no cleanup cron exists yet and no
  watchdog wiring is correct in this package. Activation must add both together.

## 6. QA evidence

| QA | Method | Expected evidence | Result |
| --- | --- | --- | --- |
| QA-1 correctness | Inspect current real-write gate, exact mapping branch and rollback truth test | Real write is blocked exactly because backup/CAS/rollback is absent; dry-run isolation remains reusable | PASS — lines 186–193 block production, lines 421–439 are the exact dry-run transfer, rollback is executable-gated |
| QA-2 regression | Narrow caller/CI/runbook search | Canonical wrapper and all deploy-control regression surfaces are identified | PASS — `bin/deploy.sh` delegates, wrapper delegates to mapping deploy, CI runs all three current deploy tests |
| QA-3 security | Read canonical authority map and run read-only VPS capability/target probe | No business-truth writer is touched; tools exist; target pre-state is known rather than guessed | PASS — affiliate authority remains backend-only; required tools present; runtime target is absent and parent exists |
| QA-4 operational/docs | Verify clean dedicated worktree, current `origin/main`, task-start continuity, protected inventories and VPS memory | Release starts from an isolated current branch and every governance/output surface is named | PASS — branch starts at `72741137`, status clean, continuity allowed, deploy scripts/env are max-protected |

Required validation after implementation:

```text
python3 -m py_compile scripts/impactshop-exact-release-remote.py
bash -n bin/deploy-wpcontent-map.sh
bash -n bin/impactshop-guard-rollback.sh
bash -n tests/deploy-wpcontent-map-exact-file.test.sh
bash -n tests/impactshop-guard-rollback-truth.test.sh
bash tests/impactshop-exact-release-remote.test.sh
bash tests/deploy-wpcontent-map-bastion.test.sh
bash tests/deploy-wpcontent-map-exact-file.test.sh
bash tests/impactshop-guard-rollback-truth.test.sh
php -l wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php
php tests/sharity-affiliate-runtime-test.php
bash tests/sharity-affiliate-runtime-bastion.test.sh
bash scripts/sharity-affiliate-runtime-bastion-guard.sh
bash scripts/check-protected-file-touch.sh --mode local
bash scripts/git-health-check.sh
bash scripts/worktree-continuity-guard.sh --mode local
bash scripts/safe-repo-audit.sh --strict --mode push
git diff --check
```

Before any real release, repeat from clean merged `main`:

```text
DRY_RUN=1 IMPACTSHOP_DEPLOY_FILE="wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php" IMPACT_ENV=production IMPACTSHOP_ALLOW_FULL_SCAN=1 bin/impactshop-guard-deploy.sh --production --non-interactive --auto-approve --reason="exact release preview"
IMPACTSHOP_EXACT_RELEASE=1 IMPACTSHOP_EXPECT_REMOTE_SHA256=absent IMPACTSHOP_DEPLOY_FILE="wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php" IMPACT_ENV=production IMPACTSHOP_ALLOW_FULL_SCAN=1 bin/impactshop-guard-deploy.sh --production --non-interactive --auto-approve --reason="approved exact default-off release"
remote inspect: phase=deployed, expected SHA, mode=0444
public default-off health/read-only canary
```

## 7. Rollback and observability

- Source rollback: revert the checkpoint through one normal PR. Reverting code
  does not silently mutate production.
- Runtime rollback: use the emitted exact command with release ID and deployed
  SHA. It inspects by default and mutates only with explicit production/apply
  flags; CAS ensures a later change cannot be overwritten or deleted.
- Release records live below the production app root's private `.bastion`
  directory and expose phases `prepared`, `deployed`, `rolled_back`, or
  `failed_recovered`. Prepared orphan records are harmless and auditable.
- Successful output provides release ID, relative target, before/intended/final
  hashes, final mode and rollback command. Errors name the failed invariant but
  never dump content or credentials.
- Default-off runtime verification checks that existing public Sharity behavior
  is unchanged. Activation, WordPress option state, cron freshness and watchdog
  are explicitly deferred to the next package.

## 8. Luna implementation chunks

### Chunk 1 — remote transaction engine and filesystem proof

- Files/interfaces: new `scripts/impactshop-exact-release-remote.py` and
  `tests/impactshop-exact-release-remote.test.sh`.
- Preconditions: this plan passes; worktree is clean and continuity is allowed.
- Exact change: implement strict manifest, lock, prepare/apply/inspect/rollback,
  atomic writes, CAS and mode enforcement with no network dependency in tests.
- Validation: Python compile plus the focused temporary-filesystem suite.
- Done when: absent/existing round trips pass and every corrupt/racing state
  fails without sibling mutation.

### Chunk 2 — guarded production admission and rollback entrypoint

- Files/interfaces: `bin/deploy-wpcontent-map.sh`, new
  `bin/impactshop-guard-rollback.sh`, focused deploy/rollback tests.
- Preconditions: chunk 1 is green; no production command has run.
- Exact change: open only explicit clean-main exact releases, stage one payload,
  call the remote engine and publish a truthful SHA-bound rollback command.
- Validation: shell syntax, fake SSH/rsync/git suite, legacy dry-run tests and
  rollback truth test.
- Done when: valid exact release reaches only prepare/upload/apply and all
  missing/racing/malformed admissions stop before target mutation.

### Chunk 3 — maximum bastion and continuity closure

- Files/interfaces: protected inventories/hashes, CI, deploy runbook, protected
  record, system/bastion status, governance adapter, notes and docsync map.
- Preconditions: chunks 1–2 pass and affected-function/rollback/smoke evidence
  is complete.
- Exact change: make every new release surface max-protected, document the exact
  operational contract and run the full relevant QA/guard set.
- Validation: protected-touch, CI parity, strict audit, continuity/docsync,
  `git diff --check`, clean checkpoint commit.
- Done when: one reviewable checkpoint contains implementation and evidence with
  no live mutation.

### Chunk 4 — minimal publication and default-off release

- Files/interfaces: guarded push, one PR/merge, clean merged-main deploy lane and
  release evidence only; no implementation edits unless a material discovery
  returns to planning.
- Preconditions: checkpoint/CI/review green; `origin/main` contains the exact
  commit; production target is still `absent`; operator authorization remains
  valid.
- Exact change: rerun exact dry-run, execute one exact release with expected
  state `absent`, inspect `deployed`/SHA/`0444`, and run default-off public
  read-only canary. Do not activate the runtime.
- Validation: remote manifest and live hash/mode, no sibling change, public
  baseline health and executable rollback inspection.
- Done when: the file is safely present but functionally default-off, with exact
  rollback evidence and no cron/watchdog requirement yet.

### Chunk 5 — detached merged-main release-worktree admission

- Terra/SOL re-entry trigger: the canonical preflight requires branch `main`,
  but that branch is already checked out in the user's dirty primary worktree.
  Git cannot attach the same branch to the required second clean worktree.
- Risk decision: never clean, move or reuse the user's primary worktree and
  never weaken feature-branch admission. Permit only an explicitly opted-in
  exact release from detached HEAD when the worktree is clean and HEAD equals
  the local `refs/remotes/origin/main` byte-for-byte.
- Files/interfaces: `bin/impactshop-guard-preflight.sh`,
  `bin/deploy-wpcontent-map.sh`, one deterministic preflight regression test,
  protected inventory/hash, CI and release docs.
- Exact change: preflight recognizes detached state only while
  `IMPACTSHOP_EXACT_RELEASE=1`; it compares detached HEAD with origin/main and
  otherwise preserves the existing branch mismatch. The mapping write gate
  accepts the same conjunction and no named non-main branch.
- Validation: normal feature branch rejected; detached stale commit rejected;
  detached exact origin/main accepted; clean/status and all other production
  admissions still required.
- Done when: a clean dedicated post-merge detached worktree can run the
  canonical guarded release without touching the dirty primary worktree.

### Chunk 6 — post-merge snapshot/rollback handover truth

- Terra/SOL re-entry trigger: merged-main exact dry-run passed every remote
  admission and showed one no-delete addition, but the outer guard interpreted
  the newly executable exact rollback script as a legacy local-snapshot restore
  command and printed an unsupported positional snapshot ID.
- Risk decision: local source snapshots and remote release manifests are
  different truth domains and must never share an identifier or command. The
  mapping release already prints the exact release-ID + deployed-SHA rollback
  only after verified real apply; the outer wrapper must always label its own
  artifact as local source snapshot evidence.
- Files/interfaces: `bin/impactshop-guard-deploy.sh`,
  `tests/impactshop-guard-rollback-truth.test.sh`, protected hash/checksum and
  continuity docs.
- Exact change: remove the executable-presence shortcut and unsupported
  positional rollback suggestion. Always print local source snapshot truth and
  state that runtime rollback is available only from a successful exact release
  output with release ID and deployed SHA.
- Validation: the old snapshot-ID command is statically forbidden; dry-run
  output contains local snapshot wording and no runtime rollback command; real
  exact mapping tests continue to require the valid two-argument rollback form.
- Done when: no guard path can present a local snapshot ID as a remote runtime
  rollback credential, and production remains unwritten until the follow-up
  merge is green.

## 9. Handoff decision

The release format, state machine, path and hash trust boundaries, apply and
rollback CAS rules, detached merged-main worktree constraint, tests, continuity
targets and default-off production boundary are fixed. No architectural choice
remains for implementation. The plan is `approved-for-luna`; Luna may implement
the bounded chunks. Any need to
change affiliate behavior, activate runtime state, write database/cron state,
accept a non-absent unexpected target, broaden scope or weaken CAS returns to a
new Terra/SOL/operator gate.
