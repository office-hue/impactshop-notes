# ImpactShop exact-file production deploy safety — implementation-ready plan

Status: `approved-for-luna`

## 1. Scope and non-goals

This package adds a fail-closed, exact-file production mode to the canonical
ImpactShop mapping deploy. It validates the complete mapping profile before
HTTP or SSH access, blocks every real unscoped production mapping run, removes
all rsync delete semantics from an exact-file run, and proves that one selected
repository file resolves to one exact remote destination without touching its
siblings. The paired staging and production profiles lose the malformed legacy
mapping whose source and destination contain a space.

Task classification: max-protected deployment-control and protected env-pair
hardening. The operator requested the next SOL package after the merged-main
dry-run exposed destructive production behavior. The plan phase follows the
mandatory Terra gate; implementation is split into bounded Luna chunks.

Falsifiable hypothesis: the current profile treats the whole MU-plugin folder
as repository-owned and combines it with global `--delete`, although production
contains live-only and divergent protected files. Prevalidating the mapping and
requiring one regular repository file for every real production run will make
the affiliate runtime independently deployable while leaving all other live
files byte-untouched.

Non-goals:

- no production write, runtime installation or activation in this package;
- no remote file deletion, live-only file salvage or identity-panel overwrite;
- no WordPress option, schema, cron, redirect, attribution, reward, vote,
  donation, callback, settlement or partner-feed change;
- no staging infrastructure repair;
- no multi-file atomic release or broad production ownership declaration;
- no change to the existing affiliate runtime source or boot adapter.

## 2. Context and canonical sources

- `AGENTS.md`: dedicated worktree, protected deploy and continuity policy.
- VPS/dev memory: guarded deploy from merged `main`, protected-touch evidence,
  rollback and smoke coverage are mandatory.
- `docs/impactshop-deploy.md`: `bin/impactshop-guard-deploy.sh` is the only
  canonical deploy entrypoint; production root is `/home/sharityh/app`.
- `docs/protected-file-change-checklist.md`: coherence, risk, affected-function,
  rollback and manual verification requirements.
- `docs/impactshop-protected-files.json`: mapping script and both env profiles
  are protected as one control-plane lane.
- `bin/impactshop-guard-deploy.sh`: snapshot, approval and protected-source
  wrapper; it delegates to `bin/deploy-wpcontent-map.sh`.
- `bin/deploy-wpcontent-map.sh`: mapping parser, preflight, remote manifest,
  rsync and maintenance boundary.
- `.deploy.production.env` and `.deploy.staging.env`: paired canonical mapping
  profiles. Both currently contain `impact-short codes-legacy` and global
  `--delete`.
- `tests/deploy-wpcontent-map-bastion.test.sh`: deterministic fake SSH/rsync
  baseline for remote admission and true dry-run behavior.
- `docs/sharity-affiliate-runtime-wp-sol-handover-2026-08-19.md`: the only
  intended next runtime file is
  `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`; it remains
  default-off and production-absent.

Read-only production evidence collected on 2026-08-20:

- local MU-plugin top level: 128 entries;
- production MU-plugin top level: 147 entries;
- production-only entries: 20, including runtime secrets, vote, fund, pledge,
  offerwall and FactLens identity files;
- repository-only regular file: `impactshop-sharity-affiliate-runtime.php`;
- 121 common regular files, of which six differ by SHA-256:
  `impactshop-boot.php`, `impactshop-email-proxy.php`,
  `impactshop-fast-data-backup.php`, `impactshop-identity-panel.js`,
  `impactshop-identity-panel.php`, `zzz-impactshop-ui-lock.php`;
- the malformed `impact-short codes-legacy` directory exists locally, so the
  previous dry-run reached and rejected its unsafe destination only after
  itemizing the broad MU-plugin mapping.

## 3. Acceptance criteria

1. The full mapping profile is parsed and validated before staging guard,
   preflight HTTP, SSH, manifest inspection or rsync.
2. Every non-comment mapping has exactly one `->`, a non-empty repository-
   relative source and wp-content-relative destination, and no whitespace,
   absolute path, traversal component or duplicate source/destination.
3. The paired env files declare exact `DEPLOY_ENVIRONMENT=staging|production`
   values and no longer contain the malformed space-bearing legacy mapping.
4. Every real production run exits before network access in this package. A
   missing `IMPACTSHOP_DEPLOY_FILE` reports the broad-scope violation; an exact
   scope reports that remote backup/CAS/rollback admission is not implemented.
   Full and exact production `DRY_RUN=1` remain available for read-only drift
   inventory.
5. `IMPACTSHOP_DEPLOY_FILE` accepts exactly one safe repository-relative,
   regular non-symlink file whose physical path remains under the active repo
   root. Missing, directory, unsafe, ambiguous, outside-mapping or duplicate
   selections fail before network access.
6. The selected file resolves through exactly one configured mapping root to
   exactly one safe remote path under `REMOTE_WP_CONTENT`; no sibling mapping
   or file is processed.
7. Exact-file rsync removes every option whose name starts with `--delete`,
   forces `--checksum`, and in dry-run also forces `-n --itemize-changes`.
8. Exact-file dry-run requires the remote parent directory but never creates
   it, never runs WordPress maintenance and never runs post-deploy smoke.
9. Tests prove early validation/no-network failure, production full-write
   rejection, exact path resolution, deletion-option stripping, sibling
   isolation and existing manifest/dry-run behavior.
10. Merged-main production exact-file dry-run shows only the absent affiliate
    runtime as an addition and shows no deletion or protected sibling change.
11. Protected-touch, continuity, strict safe audit, relevant runtime tests,
    `git diff --check` and GitHub CI pass. One guarded push, PR and merge are
    used; commits are split only if the repository commit-lane guard requires
    protected and documentation checkpoints separately.

## 4. Design and file-level implementation plan

### `bin/deploy-wpcontent-map.sh`

- Add a trimming helper and safe repository-relative path validator.
- Parse `MAPPINGS` once into source/destination arrays immediately after the
  env file is sourced.
- Reject malformed arrows, unsafe paths, whitespace, duplicate mappings and an
  invalid `DEPLOY_ENVIRONMENT` before the staging guard or any network command.
- Derive `IS_STAGING` and `IS_PRODUCTION` from the explicit environment field,
  with the existing remote-root/file-name inference retained only as a
  consistency check. A contradictory profile fails closed.
- Parse optional `IMPACTSHOP_DEPLOY_FILE` as one exact safe file. Require a
  regular non-symlink local file and resolve it against exactly one mapping
  source root; compute its remote suffix without shell evaluation.
- Before preflight, reject every real production run. Preserve full production
  dry-run so operators can audit drift safely and exact production dry-run so
  one selected file can be characterized without remote mutation. The exact
  real-write gate remains closed until a later package supplies remote backup,
  compare-and-swap/hash verification and an executable rollback path.
- Split execution into two paths:
  - unscoped path preserves existing mapping behavior and delete itemization,
    but can never perform a real production write;
  - exact-file path checks only the selected remote parent and transfers only
    the selected file to its exact destination.
- Sanitize exact-file rsync arguments by dropping `--delete` and every
  `--delete-*` token, then append `--checksum`; append dry-run flags through the
  existing no-write contract.
- Keep manifest and production-origin admission ahead of rsync. No new remote
  mutation, backup format or rollback executor is introduced because real
  production writes remain outside this package.

### `.deploy.production.env` and `.deploy.staging.env`

- Add paired `DEPLOY_ENVIRONMENT` declarations.
- Remove the malformed `impact-short codes-legacy` mapping from both profiles.
- Keep the legacy directory in the repository; this package changes only its
  invalid deploy ownership declaration.
- Keep existing global rsync options for unscoped dry-run parity. The script,
  not operator convention, removes delete flags in exact-file mode.

### Tests

- Extend `tests/deploy-wpcontent-map-bastion.test.sh` only where its fixture
  needs the explicit environment field.
- Add `tests/deploy-wpcontent-map-exact-file.test.sh` with isolated fake SSH and
  rsync commands. It records calls and asserts:
  - malformed/space-bearing mappings fail with empty SSH and rsync logs;
  - real production both without and with exact scope fails with empty network
    logs;
  - missing, symlink, directory and outside-mapping scopes fail early;
  - an exact nested file resolves to the exact remote file;
  - all delete variants are absent while `--checksum`, `-n` and
    `--itemize-changes` are present;
  - no sibling source or destination is mentioned;
  - remote mkdir and WP maintenance commands are absent.

### Protection and continuity

- Add both deploy-control tests to `.github/workflows/ci.yml` as one
  deterministic, network-free bastion step so local and GitHub evidence match.
- Add `docs/protected-change-records/2026-08-20-exact-file-deploy-safety.md`.
- Update `docs/impactshop-deploy.md`, `docs/bastion-guard-status.md`,
  `docs/impactshop-governance-system-plan-2026-06-16.md`,
  `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md`,
  `system-status-snapshot.md`, `notes.md` and
  `docs/impactshop-notes-doc-sync-map-2026-06-23.md`.
- The mapping script and paired env files are already in the max-protected
  inventory. The new regression test becomes their executable bastion
  evidence; no runtime hash-manifest entry or Cronos job is appropriate.

## 5. Risk, coherence, and security review

- Direct impact: all mapping deploy callers inherit early profile validation;
  malformed mappings that previously failed late will now stop before network.
- Production compatibility: real broad production mapping becomes forbidden.
  This is an intentional safety break because repository ownership is disproven
  by 20 live-only entries and six common content drifts.
- Staging compatibility: broad staging behavior stays available, subject to
  early safe-path validation; the known staging HTTP 404 remains independent.
- Exact-file overwrite risk: no real production branch is reachable. A later
  authorized release must add remote backup, compare-and-swap/hash verification
  and executable rollback before opening that branch.
- Delete risk: all `--delete*` flags are removed in exact mode regardless of
  profile contents. Tests include `--delete`, `--delete-before`,
  `--delete-during`, `--delete-after`, `--delete-excluded` and
  `--delete-missing-args`.
- Path trust boundary: env/profile and scope values are treated as untrusted
  strings. No `eval`, command interpolation or arbitrary absolute source is
  accepted. Remote paths remain below the validated wp-content root.
- Symlink risk: exact local symlinks are rejected so a selected path cannot
  escape repository ownership after validation.
- Secrets and user data: no remote contents, database rows, tokens, URLs,
  pseudos or credentials are logged. Live audit used filenames and SHA-256 only.
- Operational risk: direct callers named in old release templates remain
  non-canonical; documentation continues to point to the guarded wrapper.
- Cronos/watchdog: this deploy-control capability has no scheduled runtime.
  The affiliate cleanup watchdog already belongs to the separate activation
  lane and remains intentionally inactive while the runtime is undeployed.

## 6. QA evidence

| QA | Method | Expected evidence | Result |
| --- | --- | --- | --- |
| QA-1 correctness | Inspect mapping parser/order and run current bastion test | Invalid mapping is checked late today; baseline dry-run/manifest test is green | PASS — unsafe destination occurs inside the rsync loop; baseline test PASS |
| QA-2 regression | Search all direct and wrapper callers plus profile history | Canonical wrapper is identified; old direct references and staging behavior are named | PASS — guard wrapper delegates at two paths; `deploy.sh` delegates; old release template/direct memory references are non-canonical |
| QA-3 security | Read-only production filename and SHA-256 inventory | Broad repository ownership is false and exact-file isolation is necessary | PASS — 20 live-only entries, six common hash drifts, one repo-only affiliate runtime |
| QA-4 operational/docs | Clean dedicated worktree, task-start/continuity, protected model and VPS memory | Protected env pair, docs targets, rollback limit and no-Cronos decision are explicit | PASS — worktree allowed/clean; continuity green after canonical refresh; all three implementation files already protected |

Required validation after implementation:

```text
bash -n bin/deploy-wpcontent-map.sh
bash -n tests/deploy-wpcontent-map-bastion.test.sh
bash -n tests/deploy-wpcontent-map-exact-file.test.sh
bash tests/deploy-wpcontent-map-bastion.test.sh
bash tests/deploy-wpcontent-map-exact-file.test.sh
bash tests/impactshop-guard-rollback-truth.test.sh
php -l wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php
php -l wp-content/mu-plugins/impactshop-boot.php
php tests/sharity-affiliate-runtime-test.php
bash tests/sharity-affiliate-runtime-bastion.test.sh
bash scripts/sharity-affiliate-runtime-bastion-guard.sh
bash scripts/check-protected-file-touch.sh --mode local
bash scripts/git-health-check.sh
bash scripts/worktree-continuity-guard.sh --mode local
bash scripts/safe-repo-audit.sh --strict --mode push
git diff --check
```

## 7. Rollback and observability

- Source rollback: revert this package through one normal PR. The old broad
  production behavior is not automatically restored; an explicit operator
  would have to revert the safety gate.
- Runtime rollback: none is needed because this package performs no production
  write.
- Dry-run output prints exact-file scope, resolved remote destination and
  sanitized rsync options. Test logs prove no sibling or mutation command.
- A later actual-write package must add/verify remote backup, exact SHA-256,
  read-only permissions, rollback command, option state, cron/watchdog and human
  canary before activation. Those are not silently inferred here.

## 8. Luna implementation chunks

### Chunk 1 — early profile validation and exact-file resolver

- Files and interfaces: `bin/deploy-wpcontent-map.sh`;
  `DEPLOY_ENVIRONMENT`, `IMPACTSHOP_DEPLOY_FILE`.
- Preconditions: this plan passes; worktree is clean and continuity is allowed.
- Exact change: parse and validate all mappings before network, resolve one
  exact regular file, block every real production execution and split exact/
  unscoped dry-run execution without changing the manifest contract.
- Validation: bash syntax and focused fake-command probes for early failure and
  exact destination computation.
- Done when: every invalid input has zero SSH/rsync calls and one valid exact
  dry-run reaches only its selected remote file.

### Chunk 2 — paired profiles and executable bastion coverage

- Files and interfaces: `.deploy.production.env`, `.deploy.staging.env`,
  existing bastion fixture, new exact-file test.
- Preconditions: chunk 1 behavior is stable; no production command has run.
- Exact change: add paired environment identity, remove the invalid legacy
  mapping, and cover delete stripping, symlink/directory rejection, full-write
  block and sibling isolation.
- Validation: both deploy tests, shell syntax and paired-env diff review.
- Done when: the focused tests prove the acceptance criteria deterministically.

### Chunk 3 — max protection, continuity and minimal publication

- Files and interfaces: `.github/workflows/ci.yml`, deploy runbook, change
  record, bastion status, system snapshot, notes and docsync map.
- Preconditions: chunks 1–2 pass and the protected-touch evidence has exact
  affected functions, rollback and smoke tags.
- Exact change: wire deterministic deploy tests into CI, document the contract
  and current live blockers; run the full relevant QA/guard set; create
  checkpoint commit(s), one push, one PR and one merge; rerun exact-file
  production `DRY_RUN=1` from clean merged `main`.
- Validation: local/CI parity, strict audit, diff check, clean worktree, exact
  merged SHA and a dry-run containing only the affiliate runtime addition.
- Done when: the exact-file capability is merged and production-characterized
  with no remote state change.

### Chunk 4 — post-merge rollback-message truth closure

- Terra re-entry trigger: the merged-main production dry-run succeeded and
  proved exact one-file/no-delete scope, but the wrapper still printed a quick
  rollback command for the absent `bin/impactshop-guard-rollback.sh`.
- Risk decision: this is a control-plane truth defect, not authorization to
  implement remote rollback or open production writes. The safe additive fix
  is to advertise quick rollback only when the executable exists; otherwise
  identify the artifact as a local source snapshot and retain the production
  write block.
- Files and interfaces: `bin/impactshop-guard-deploy.sh`, a deterministic
  rollback-message guard test, CI, protected change record and continuity docs.
- Validation: shell syntax, focused rollback-truth test, both deploy-control
  tests, affiliate bastion regression, CI, strict audit and `git diff --check`.
- Done when: no successful guard run can claim an unavailable executable
  rollback path, while real production writes and activation remain blocked.

## 9. Handoff decision

The file contracts, validation order, production failure policy, exact scope,
tests, continuity targets and release boundary are fixed. No architectural
choice remains for implementation. The plan is `approved-for-luna`; Luna may
implement the three bounded chunks. Any discovery that requires multi-file
production writes, remote backup format, live-only salvage or actual
activation returns to a new Terra/operator gate.
