# Protected change record — exact-file production deploy safety

Date: 2026-08-20

Status: implemented locally; production writes remain blocked

Operator approval: Arnold requested the next SOL Impact Shop package on
2026-08-20 after the production dry-run exposed broad `--delete` and protected
live-main drift.

## Protected files touched

- `bin/deploy-wpcontent-map.sh`
- `.deploy.production.env`
- `.deploy.staging.env`
- `.github/workflows/ci.yml`
- `docs/impactshop-deploy.md`

Executable evidence:

- `tests/deploy-wpcontent-map-bastion.test.sh`
- `tests/deploy-wpcontent-map-exact-file.test.sh`

## Why additive-only code was insufficient

The unsafe behavior lives in the canonical mapping deploy and its paired env
profiles. A parallel deploy command would leave the supported entrypoint able
to delete or overwrite protected production files. The existing control plane
therefore needs an approved protected-file change.

## Coherence analysis

Upstream inputs are the guarded wrapper, the paired env profiles,
`DEPLOY_ENVIRONMENT`, `IMPACTSHOP_DEPLOY_FILE`, `DRY_RUN` and the mapping list.
Downstream consumers are staging admission, production preflight, remote
bastion-manifest verification, origin alignment, rsync selection, WordPress
maintenance and post-deploy smoke.

Affected operational functions:

- complete mapping-profile parsing and validation;
- staging/production identity consistency;
- repository ownership and symlink-boundary checks;
- exact source-to-remote destination resolution;
- scoped rsync option sanitization;
- real production write admission;
- full dry-run drift inventory;
- manifest, origin and no-mutation evidence.

No user-facing route, REST contract, redirect, attribution, profile, point,
reward, vote, donation, callback, settlement or feed behavior changes.

## Risk analysis and production evidence

A read-only production inventory found 20 live-only MU-plugin entries and six
common regular files whose SHA-256 differs from repository `main`. A broad
`mu-plugins` rsync with `--delete` would therefore remove runtime secrets and
multiple vote/fund/pledge/identity modules and would overwrite divergent
protected files. The malformed `impact-short codes-legacy` mapping also failed
late because both sides contain whitespace.

The new contract fails before network when mapping syntax, paths, ownership or
scope are invalid. Exact dry-run removes all `--delete*` variants and processes
one regular non-symlink file only. Every real production write remains blocked,
including exact scope, because no remote backup/CAS/executable rollback
admission exists yet.

## Validation and smoke tags

- `deploy:mapping-prevalidation`
- `deploy:exact-file-resolution`
- `deploy:no-delete-scope`
- `deploy:production-write-block`
- `deploy:guard-preflight`
- `deploy:checksum-verify`

Automated checks:

- shell syntax for the deploy script and both deploy tests;
- bastion manifest/no-write regression test;
- exact-file early-rejection and sibling-isolation test;
- GitHub CI parity step running both deploy-control tests;
- affiliate runtime PHP/lifecycle/tamper regression tests;
- protected-touch, worktree continuity and strict safe audit;
- `git diff --check`.

## Rollback

Source rollback is a normal PR revert of this package. No production rollback
is needed because the package authorizes and executes dry-run only.

The earlier runbook reference to `bin/impactshop-guard-rollback.sh` was not an
executable truth: that file does not exist. Opening real exact-file production
writes requires a later approved package with remote backup, pre-write hash
comparison, post-write hash/read-only verification and an executable rollback
path.

## Manual reviewer checklist

No UI is changed. After merge, the reviewer should only confirm:

1. `https://app.sharity.hu/` still loads normally;
2. the Shopping Assistant page still loads;
3. one existing `/go-deal` route still behaves as before;
4. the exact-file dry-run names only
   `impactshop-sharity-affiliate-runtime.php` and contains no `*deleting` line;
5. production still reports runtime file absent, activation option unset and
   cleanup cron absent.

Do not activate the affiliate runtime or overwrite any of the six divergent
production files in this package.
