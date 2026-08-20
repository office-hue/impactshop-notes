# Protected change record — exact-file release admission

Date: 2026-08-20

Status: implemented locally; production release not yet executed

Operator approval: Arnold repeatedly approved the next coherent SOL Impact Shop
package and requested safe production readiness with minimum push/PR/merge
operations. This record does not authorize runtime activation or business-truth
changes.

## Protected files touched

- `bin/deploy-wpcontent-map.sh`
- `bin/impactshop-guard-rollback.sh`
- `scripts/impactshop-exact-release-remote.py`
- `tests/impactshop-exact-release-remote.test.sh`
- `tests/impactshop-guard-preflight-detached.test.sh`
- `tests/deploy-wpcontent-map-exact-file.test.sh`
- `tests/impactshop-guard-rollback-truth.test.sh`
- `.github/workflows/ci.yml`
- `docs/impactshop-protected-files.json`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-guard-config.sha256`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `docs/impactshop-deploy.md`
- `docs/bastion-guard-status.md`

## Why additive-only code was insufficient

The remote transaction engine and rollback entrypoint are additive. The
canonical mapping deploy must nevertheless open its existing exact production
lock only after those controls pass; leaving the old hard block unchanged would
make the capability unreachable, while adding a parallel unguarded deploy path
would bypass the canonical bástya.

## Coherence and affected-function analysis

Inputs are the protected production profile, exact repository file,
operator-declared expected remote SHA/absence, clean merged-main Git state and
the existing remote bastion/origin checks. The new engine owns only private
release manifests, verified backups, one staged payload and one exact remote
file transition.

Affected operational functions:

- exact production release admission;
- local and remote SHA-256 verification;
- remote release locking and schema validation;
- existing-file backup or explicit absent-state recording;
- staged PHP lint and atomic replacement;
- post-write `0444` relock and verification;
- read-only release inspection;
- explicit SHA-bound compare-and-swap rollback;
- deploy-control CI and protected inventory.

Affiliate link construction, redirect selection, user/NGO attribution,
commission import, settlement, reward, points, votes, donation, callbacks,
partner feeds and runtime activation are unchanged.

## Risk analysis

- Broad production mapping and every real unscoped write remain blocked.
- A real exact write requires the canonical `.deploy.production.env`, exact
  `/home/sharityh/app` root, explicit opt-in/expected state and `HEAD ==
  origin/main`. A clean named `main` or clean detached merged-main release
  worktree is accepted; every named feature branch and stale detached commit is
  rejected.
- Preparation never changes the target. Apply repeats the original-state CAS
  under the canonical remote lock after payload upload.
- Existing sources receive a verified private backup. An absent source is
  recorded as absent and rollback may remove only the exact deployed hash.
- Apply stages and lints the final PHP bytes before replacement, then verifies
  final SHA, PHP syntax and mode `0444`.
- Rollback is inspect-only by default. Mutation requires `--production`,
  `--apply`, exact release ID and the expected deployed SHA. Remote drift blocks
  overwrite or deletion.
- Manifests, backups and release directories are private and reject unsafe
  schema, path, symlink, permission, phase and hash state.
- Outputs contain no SSH credentials, user pseudo, NGO choice, URL, token or
  transaction contents.

## Rollback

Source rollback is a normal PR revert. It does not mutate production.

After a successful release the deploy prints the only canonical runtime
rollback form:

```text
bin/impactshop-guard-rollback.sh --production --apply --release-id=RELEASE_ID --expected-deployed-sha=DEPLOYED_SHA256
```

The command restores the verified original bytes/mode or, for a first install,
removes the file only while its live SHA still equals the recorded deployed
SHA. A stale, repeated, corrupt or racing rollback fails closed.

## Smoke and validation tags

- `deploy:exact-release-prepare`
- `deploy:exact-release-cas`
- `deploy:remote-backup-verify`
- `deploy:php-lint-before-write`
- `deploy:readonly-relock`
- `deploy:exact-rollback-cas`
- `deploy:no-sibling-mutation`
- `deploy:clean-merged-main`

Automated evidence:

- Python compile and real temporary-filesystem prepare/apply/rollback suite;
- absent and existing origin round trips;
- pre-apply and pre-rollback drift rejection;
- corrupt payload, PHP, manifest and backup rejection;
- symlink and unsafe release permission rejection;
- exact fake SSH/rsync/git production admission and sibling isolation;
- detached merged-main preflight acceptance with named/stale negative cases;
- legacy bastion/dry-run/rollback-truth regression suites;
- protected-touch, strict audit, continuity/docsync and `git diff --check`.

## Manual reviewer checklist

1. Confirm only the intended affiliate runtime appears in exact dry-run.
2. Confirm no `--delete*`, sibling mapping, WP maintenance or activation write.
3. Confirm production pre-state remains `absent` immediately before first
   release.
4. After release, confirm manifest phase `deployed`, intended live SHA and mode
   `0444`.
5. Run rollback in inspect mode and preserve the printed exact apply command.
6. Confirm public Sharity and Shopping Assistant baseline still load while the
   feature remains default-off.
7. Do not set the activation option or install cleanup cron/watchdog in this
   package.

## Post-merge dry-run truth closure

The first merged-main detached dry-run was no-write and correctly itemized one
new affiliate runtime only. It also exposed an outer-wrapper handover defect:
the now executable exact rollback script caused the local source snapshot ID to
be printed as an unsupported positional runtime rollback command. No production
write was attempted.

The wrapper now always labels its artifact as a local source snapshot. Only a
successful real exact apply may print the remote rollback command, and that
command must contain both release ID and deployed SHA. The regression guard
forbids any `rollback.sh` command built from `latest_snap`.
