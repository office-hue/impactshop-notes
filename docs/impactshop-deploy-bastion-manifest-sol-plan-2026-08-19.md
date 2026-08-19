# ImpactShop deploy bastion manifest and true dry-run — implementation-ready plan

Status: `approved-for-luna`

## 1. Scope and non-goals

This package restores the missing remote-bastion check in the canonical
ImpactShop mapping deploy and makes `DRY_RUN=1` genuinely free of remote
writes. It adds deterministic regression coverage, updates protected-change
and continuity evidence, publishes one guarded PR, merges once, and reruns the
merged-main staging and production dry-runs.

Non-goals: no manual `scp`/`rsync`, no production activation, no WordPress
option/schema/cron mutation, no affiliate redirect/feed/settlement/reward
change, no remote manifest rewrite, and no overwrite or salvage decision for
the live-only identity-panel drift.

Classification: max-protected deployment-control repair. The operator
explicitly approved this SOL package after the existing wrapper failed before
rsync. Falsifiable hypothesis: `bin/deploy-wpcontent-map.sh` calls an undefined
`verify_remote_bastion_manifest`, and its dry-run path still executes remote
directory creation and WordPress maintenance; restoring a schema-validating
check and suppressing every remote write in dry-run makes the wrapper testable
and exposes live drift without altering it.

## 2. Context and canonical sources

- `AGENTS.md` worktree, guarded-deploy and protected-surface policy.
- `docs/impactshop-deploy.md` merged-main-only deployment and rollback path.
- `docs/protected-file-change-checklist.md` coherence, risk, parity, smoke and
  manual verification requirements.
- `docs/impactshop-protected-files.json` and
  `docs/impactshop-guard-config.json` machine-readable protected perimeter.
- `bin/impactshop-guard-deploy.sh` canonical snapshot/approval wrapper.
- `bin/deploy-wpcontent-map.sh` mapping and remote execution boundary.
- `docs/impactshop-guard-hashes.json` local source-integrity truth; the remote
  `.bastion/protected-hashes.json` is the previous live baseline, not a value
  that may be silently replaced or assumed equal to the new local manifest.
- VPS dev-memory result: deploy only from merged main through the guarded
  wrapper; direct remote copying is forbidden; local/CI guard parity required.
- Read-only production evidence: the remote root and manifest exist; the
  manifest JSON has 142 hashes, while live identity-panel PHP/JS differs from
  main. That drift is protected and remains an explicit later decision.

## 3. Acceptance criteria

1. The mapping deploy defines and invokes one fail-closed remote-manifest
   verifier before rsync.
2. The verifier requires the remote root, `wp-config.php`, `wp-content`, a
   regular non-symlink bounded-size manifest, a JSON object, a non-empty
   `hashes` object, safe relative paths and lowercase SHA-256 values. The
   canonical `.bastion/protected-hashes.json` path is checked first, with only
   the already documented legacy candidates accepted afterward.
3. Missing root/config/content/manifest, invalid JSON/schema/path/hash,
   symlink, oversized file, SSH failure or unknown response blocks deployment.
   No `ok_no_manifest` continuation exists.
4. `DRY_RUN=1` performs HTTP/SSH reads and rsync itemization only. It never
   creates remote directories and never executes WordPress cache, cron or
   rewrite maintenance. Missing remote target/destination blocks rather than
   mutating it.
5. Dry-run always includes rsync no-write and itemized-change flags even if an
   env file omits them.
6. Mocked integration tests prove valid admission, invalid-manifest rejection,
   missing-target rejection and absence of remote write commands.
7. Protected-touch guard, local/CI checks, bash syntax, existing affiliate
   runtime tests, strict audit, docsync/continuity and `git diff --check` pass.
8. One checkpoint commit, one push, one PR and one allowed merge strategy are
   used. Deployment is retried only from clean merged main.
9. Staging 404 remains a recorded infrastructure blocker. Production dry-run
   may enumerate changes but cannot become a real deploy while the two
   protected identity files have unresolved live-main drift.

## 4. Design and file-level implementation plan

- `bin/deploy-wpcontent-map.sh`
  - add the remote manifest verifier near the other pre-rsync checks;
  - return one compact status line from remote Python and interpret every
    non-success status fail-closed;
  - include `.bastion/protected-hashes.json` as canonical candidate;
  - branch target/destination preparation and maintenance on `DRY_RUN` so
    read-only checks replace remote writes;
  - force `-n --itemize-changes` in dry-run without changing real deploy flags.
- `tests/deploy-wpcontent-map-bastion.test.sh`
  - isolate SSH and rsync with temporary fake executables;
  - exercise success and failure statuses and assert the command log contains
    no `mkdir`, `wp`, cache, cron or rewrite mutation in dry-run.
- `docs/impactshop-deploy.md`
  - document the manifest contract and true dry-run semantics.
- `docs/protected-change-records/2026-08-19-deploy-bastion-manifest-guard.md`
  - record protected files, approval, affected functions, rollback, smoke tags
    and the required manual UI/read-only route checklist.
- `docs/bastion-guard-status.md`, `system-status-snapshot.md`, `notes.md`
  - append this guard capability, current release posture and exact blocker.
- Machine-readable protection
  - keep the runtime digest manifest unchanged: the deploy script and deploy
    documentation are protected by `docs/impactshop-protected-files.json`,
    protected-touch and CI parity, but intentionally are not self-referential
    entries in the runtime file-hash manifest.

No storage migration, provider contract, user data or economic writer changes.
The remote manifest is read-only in this package.

## 5. Risk, coherence, and security review

- Direct impact: every staging/production mapping deploy passes the restored
  pre-rsync manifest gate. Failure is deliberate and blocks all mappings.
- Indirect impact: existing deployments that relied on absent manifests will
  now stop. This is required for the max-protected lane and is covered by
  explicit diagnostics and rollback.
- Dry-run compatibility: it becomes stricter when a destination is absent;
  this prevents a supposedly read-only command from preparing production.
- Trust boundary: SSH output is untrusted. Only fixed status prefixes are
  accepted; manifest paths and entries are validated remotely without shell
  evaluation, secret output or operator-controlled Python interpolation.
- Destructive risk: real rsync still uses the existing `--delete` mapping,
  therefore real deploy remains blocked behind clean merged main, guard
  approval, a narrow dry-run and the identity-drift decision.
- Secrets: env values and manifest contents are not logged; tests use fakes.
- Regression surface: all mapping deploys, preflight, guard snapshots,
  production origin alignment, post-deploy maintenance and smoke hooks.
- Rollback: revert the single merged guard commit; no remote rollback is needed
  for the code/test package because only dry-runs are authorized afterward.

## 6. QA evidence

| QA | Method | Expected evidence | Result |
| --- | --- | --- | --- |
| QA-1 correctness | Inspect merged-main call graph and historical definitions; production dry-run reproduction | Undefined call is the exact failure; dry-run remote-write sites are named | PASS — call exists at pre-rsync boundary, definition is absent, `mkdir` and WP maintenance are unconditional |
| QA-2 regression | Inspect mappings, env flags, protected model and historical guard branch | Full MU-plugin `--delete` scope and all downstream maintenance are included in tests/non-goals | PASS — 19 mappings identified; no affiliate or WP runtime source needs modification |
| QA-3 security | Read-only remote root/manifest/schema/hash and live-vs-main inspection | No secret output; manifest is present but stale; protected identity drift is isolated and not overwritten | PASS — canonical manifest present, 142 hash entries; only identity PHP/JS differ between current main and live among the 12 stale-baseline findings |
| QA-4 operational/docs | Clean feature branch, canonical task-start marker, VPS memory, deploy docs and continuity interfaces | Dedicated worktree allowed; merged-main-only and docs/guard obligations are explicit | PASS — readiness/task-start allowed; dev-memory confirms guarded deploy-only path |

Validation commands after implementation:

```text
bash -n bin/deploy-wpcontent-map.sh
bash tests/deploy-wpcontent-map-bastion.test.sh
php -l wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php
php -l wp-content/mu-plugins/impactshop-boot.php
php tests/sharity-affiliate-runtime-test.php
bash tests/sharity-affiliate-runtime-bastion.test.sh
bash scripts/sharity-affiliate-runtime-bastion-guard.sh
BASTION_OVERRIDE=1 BASTION_CHANGE_RECORD=... BASTION_ROLLBACK=... BASTION_SMOKE=... bash scripts/check-protected-file-touch.sh --mode local
bash scripts/git-health-check.sh
bash scripts/worktree-continuity-guard.sh --mode local
bash scripts/safe-repo-audit.sh --strict --mode push
git diff --check
```

## 7. Rollback and observability

- Source rollback: revert the single guard commit through a normal PR.
- Dry-run produces itemized paths, manifest admission status, target/destination
  existence and an explicit maintenance-skipped marker.
- No remote snapshot restoration is necessary unless a later real guarded
  deploy occurs; that deploy must use the snapshot ID emitted by
  `bin/impactshop-guard-deploy.sh` and rollback through
  `bin/impactshop-guard-rollback.sh`.
- Production activation option remains unset/`0`; affiliate cleanup cron stays
  absent until the separately approved activation package.

## 8. Luna implementation chunks

### Chunk 1 — fail-closed manifest and true dry-run

- Files and interfaces: `bin/deploy-wpcontent-map.sh`, new mocked test.
- Preconditions: this plan passes; dedicated branch remains clean; no remote
  write or deploy command runs during implementation.
- Exact change: implement the validated status contract and suppress all
  remote mutation in dry-run; add deterministic fake-SSH/rsync coverage.
- Validation: bash syntax plus all new test cases.
- Done when: valid dry-run reaches itemized rsync and every invalid/mutating
  scenario fails without a write command in the mock log.

### Chunk 2 — protected perimeter and continuity closure

- Files and interfaces: deploy docs, change record, guard status, snapshot,
  notes and docsync map.
- Preconditions: chunk 1 green and reviewed against the acceptance criteria.
- Exact change: document the contract, evidence, rollback and manual checklist;
  pass the protected-touch override without changing the runtime digest map.
- Validation: protected-touch, continuity, strict audit, existing runtime
  regression tests and diff check.
- Done when: one clean checkpoint is push-ready with local/CI parity evidence.

### Chunk 3 — minimal publication and merged-main dry-run

- Files and interfaces: one branch, PR, merge and the canonical deploy wrapper.
- Preconditions: chunk 2 green, PR checks green, exact head verified.
- Exact change: one push/PR/merge; switch this same dedicated worktree to fresh
  main; rerun staging and production with `DRY_RUN=1` only.
- Validation: merged SHAs, staging evidence, production itemized diff and proof
  that option/runtime/cron remain unchanged.
- Done when: the repaired control plane is merged and safely characterizes the
  remaining deployment blockers without remote mutation.

## 9. Handoff decision

The architecture, failure behavior, files, test harness, rollback and release
limits are exact; no implementation choice is left open. Status is
`approved-for-luna`. The operator explicitly requested SOL execution, so the
approved bounded chunks may be executed by SOL without weakening the Terra
gate. A real production deploy remains outside this handoff until the resulting
dry-run and protected identity drift receive a separate operator decision.
