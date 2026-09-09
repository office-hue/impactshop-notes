# Sharity Profile SP1 — Sol release gate

Status: **source-reviewed; repo-local admission bound to checkpoint validation;
publication and live acceptance pending**.

## Frozen identity

- repository: `impactshop-notes`
- worktree: `sharity-profile-sp1-20260909`
- branch: `dev/sharity-profile-sp1-20260909`
- base: `origin/main@a6f83e37566d6e44183e8be5a5459b8461c64e93`
- reviewed historical checkpoint: `0a4755947224fbef1269116df0d33da94f38d027`
- reviewed historical tree: `e58a29417ea20624349a287a8d2f3ac95af07b9d`
- publication commits: identity/test `6f7aa97`, AdSense `3ead4ee`, bastion
  digests `1ca1fb0`
- plan/package: Sharity Profile SP1, identity-v2

The checkpoint delta was empty when this Sol gate started. The never-pushed
history was later repacked under local backup ref
`backup/sharity-profile-sp1-pre-publication-20260909@837a053` solely to satisfy
the one-primary-lane-per-commit guard; reviewed runtime bytes did not change. No remote source,
provider, staging, production, database or scheduler mutation was performed.

## Gate decision

The repository-local DEV-v2 adapter is the authority. Its current decision for
this branch is `operator-review`, class `protected-or-deploy`, and
`automaticProductDeployAuthority` is false. The installed release-operations
matrix independently classifies `impactshop-notes` as source-only unless live
authority is granted separately.

The generic `npm run dev:operations:preflight` entrypoint is not available in
this repository because it has no root `package.json`. Adding a new root package
or a parallel release adapter would be an architectural/shared-dependency change
and is not part of SP1. The existing repository-native guard and exact-file
release engine remain canonical.

A real production release is not admissible from this branch. The canonical
engine requires a clean `main` or detached `origin/main` checkout whose HEAD
equals `origin/main`, an exact single-file scope, a verified pre-deploy remote
SHA, backup/CAS admission and an executable rollback identity. SP1 changes three
runtime files, so each production mutation would also need its own exact-file
release identity after the source has been reviewed and merged.

## Required staging acceptance package

After explicit operator authorization, one bounded Sol staging package must
freeze source, environment and target identities and prove:

1. a read-only repository-native preflight/dry-run for the exact three runtime
   files, without remote writes, cache flushes, cron changes or retries;
2. a recoverable database backup before activating the owner-grant schema;
3. HTTPS browser/DB E2E for new anonymous profile creation and persistent device
   ownership;
4. denial of a manually changed pseudo cookie without the matching owner grant;
5. denial of a valid owner grant when paired with the wrong pseudo ID;
6. legacy portable-code sign-in, hash migration and owner-grant minting;
7. account switching revokes the prior device grant before issuing the new one;
8. portable-code reveal is one-time, first-party and `no-store`;
9. the whole `/profil` subtree is free of the Google AdSense head injection; and
10. source rollback is executable without dropping the v2 hash or owner-grant
    data tables.

Other direct pseudo-cookie mutation routes recorded in the Sol owner-grant
decision remain outside SP1 and continue to block full production acceptance
until centrally protected.

## Scheduler and rollback decision

SP1 needs no Cronos/cron/watchdog job. Grant expiry is enforced synchronously by
the authorization query, and bounded cleanup is request-driven. Adding a
scheduler would enlarge production authority without improving correctness.

Database rollback is forward-compatible: deactivate the new source paths but do
not drop the owner-grant table or erase migrated code hashes. Production source
rollback must use the repository's per-file release IDs, deployed SHA checks and
CAS rollback lane; broad rsync or history rewriting is prohibited.

## Operator boundary

The next remote step requires an explicit grant for a **read-only staging
preflight/dry-run** against the registered staging profile. Push, PR, merge,
provider build, database activation and production release are separate later
effects and are not implied by that grant.

## Local closure evidence

- `tests/deploy-wpcontent-map-exact-file.test.sh`: PASS
- `tests/deploy-wpcontent-map-bastion.test.sh`: PASS
- `tests/dev-context-policy-guard.test.sh`: PASS
- unchanged source evidence reused from `0a47559`: identity-v2 static PASS,
  JavaScript syntax PASS and VPS PHP lint PASS

## Read-only staging preflight attempt

At `2026-09-09T12:29:46Z`, one approved exact-file staging dry-run was started
for `wp-content/mu-plugins/impactshop-identity-panel.php` from the frozen
feature-branch identity. The canonical guard stopped before SSH or rsync:

```text
Guard preflight: branch mismatch.
current=dev/sharity-profile-sp1-20260909 expected=main
```

Result: **BLOCKED, no remote effect**. The other two exact-file previews were
not started. The guard must not be bypassed and this identity-bound attempt must
not be retried. Staging dry-run can resume only after the reviewed source is
published through the separate source-publication gate and the resulting clean
`main == origin/main` identity is frozen.

Source-publication preparation subsequently split the never-pushed mixed
commit into guard-compliant lanes, registered the protected change record and
pinned the three candidate runtime digests. The refreshed remote base remained
`a6f83e3`; local tests, checksum and PR-body validation passed before the single
guarded push attempt.

The first guarded-push invocation performed no push: its strict audit required
the bástya status change to be mirrored in the governance system-plan. That
missing DocSync anchor was added without changing any executable guard or
release policy. Commit-lane and protected-touch had already passed.

The second guarded-push invocation also performed no push. Commit-lane,
protected-touch and strict audit passed, then the old wrapper attempted a
cross-repository central memory check. The current DEV-v3 handbook makes
`worktree-shared-deps.sh check --node-only` an `ai-agent`-only command; it is
`not-applicable` in `impactshop-notes` and no sibling worktree may be used to
satisfy it. No bypass, install, shared-tree repair or third push attempt was
made. The remote feature branch is still absent.

One subsequent `origin/main` refresh remained at `a6f83e3`; integrating it was
an `Already up to date` no-op with no conflict or tree change. The applicable
local checks are `worktree-task-start-guard.sh`,
`worktree-readiness-check.sh` and `dev-delivery-v2-adapter.sh`. Task start is
allowed; readiness exposes protected-source operator review rather than a
dependency failure. The exact machine-readable admission record now covers all
six protected base-to-HEAD paths and points to the source-only operator approval
in the owner-grant decision. This creates no remote or runtime authority.

Checkpoint `fcf770d` / tree `bd2b675` subsequently passed the repository-local
full-validation, maximum-bastion and freeze/verify sequence with
`sourceMergeAdmission=true`. No push was attempted from that identity.
Checkpoint `87f4567` then overinterpreted the lack of a newer repository commit
as absence of DEV-v3. The operator corrected that conclusion: the new global
DEV rule and handbook are already installed, and the `ai-agent` node/dependency
path is `not-applicable` for this repository. The source-only publication lane
therefore uses the explicitly executed `impactshop-notes` task-start,
continuity, strict audit and DEV-v2 admission evidence without entering a
sibling worktree or repairing shared dependencies.
