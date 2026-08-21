# Sharity Shopping opaque sat1 production cutover — implementation-ready plan

Status: `approved-for-luna`

Date: 2026-08-21
Worktree: feat/sharity-shopping-opaque-sat1-adapter-sol-20260821
Operator approval: the next bounded SOL package, protected release and minimum
push/PR/merge posture were explicitly approved on 2026-08-21.

## 1. Scope and non-goals

This package completes one production capability end to end: the already
merged, reviewed and maximum-bastioned Shopping Assistant adapter in
wp-content/mu-plugins/impactshop-boot.php replaces the legacy network-visible
NGO plus raw pseudo attribution with one opaque sat1 Dognet d1 value for the
existing Árukereső canary.

The exact production boot is the historical legacy blob
3fa415ae92e8f7e2270dc43a1466d1235400eb68 with SHA-256
cccc3f4147c0d849a4d53bf1567150c94e1493afda88686b6709d89f2136b56f.
Current merged origin/main@1716e6fc276184793b4e7b32dfe51fe191ae90d0
contains the reviewed adapter from commit 13ee81a6; its boot SHA-256 is
e05a538fe4fdc5ca7af4220e03e3924cd4090f0d2ca5adf7c2f355cad545ba06.
The complete live-to-main diff is three hunks, 61 insertions and 17 deletions,
all inside the Shopping Assistant attribution/logging branch.

Falsifiable hypothesis: deploying exactly the merged-main boot file through
the one-file CAS release lane, while the already active affiliate runtime,
schema, cleanup schedule and central watchdog remain admitted, makes the next
human Árukereső canary emit d1=sat1_…, omit data5, keep the selected NGO plus
HMAC subject only in WordPress, and preserve every non-Shopping /go and
/go-deal behavior. Any hash, admission, test, route or rollback mismatch blocks
or rolls back the release.

Non-goals:

- no newly admitted merchant activation and no expansion beyond Árukereső;
- no CJ activation or new provider/network contract;
- no partner feed, campaign binding, redirect algorithm or deeplink rewrite;
- no automated affiliate click, purchase, callback, commission, reward,
  point, vote, donation or settlement write;
- no NGO-vote/fund truth mutation and no claim that a click proves support;
- no broad rsync, sibling MU-plugin write, direct copy, force-push or
  dirty-primary use;
- no source modification to the already reviewed boot/runtime algorithms.

## 2. Context and canonical sources

- AGENTS.md, docs/pr-policy.md and docs/protected-file-change-checklist.md:
  protected Impact Shop release and manual UI evidence contract.
- docs/protected-change-records/2026-08-19-sharity-affiliate-runtime.md:
  approved adapter coherence, security, affected functions and rollback.
- docs/sharity-affiliate-runtime-wp-sol-handover-2026-08-19.md: runtime,
  schema, retention and correlation-only authority.
- scripts/sharity-affiliate-runtime-bastion-guard.sh and
  tests/sharity-affiliate-runtime-bastion.test.sh: exact source and mutation
  protection for both the runtime and boot adapter.
- bin/impactshop-guard-deploy.sh, bin/deploy-wpcontent-map.sh and
  scripts/impactshop-exact-release-remote.py: merged-main exact-file release,
  remote backup, compare-and-swap, PHP lint, atomic apply, physical read-only
  closure and executable rollback.
- ai-agent central authority: postactivation admission, exact cleanup tuple
  and automation-watchdog freshness remain the recurring supervision owner;
  this package adds no scheduler or Cronos job.
- production read-only evidence on 2026-08-21: runtime SHA-256
  4347dded2ad009b5fe793836b57bbb163f3ffe94e55c0ed6dedeff93e0ef4859 at
  mode 0444; the boot is the legacy SHA above and contains no runtime caller.
- Material operational discovery: the installed central cron still points to
  the clean but stale runtime head 61ea1359, while current ai-agent origin/main
  is 3e00844d. Its retained state is 2026-06-30 FAIL, so postactivation
  admission cannot be honestly reused for this release.

## 3. Acceptance criteria

1. Continuity edits occur only in the dedicated ImpactShop feature worktree;
   production deploy occurs only from a separate clean detached worktree whose
   HEAD equals current origin/main.
2. Read-only live inventory proves the exact expected-before boot SHA, regular
   file ownership/mode and no symlink before release.
3. Local PHP lint, lifecycle tests, runtime bastion, exact release engine,
   exact-file mapping, rollback-truth and protected-touch checks pass.
4. The existing clean worktree dedicated only to the central watchdog preserves
   its old branch/head and switches to a new runtime branch at exact current
   origin/main. The guarded cron installer records a rollback backup, points
   exactly once to that runtime and a manual run produces fresh state before
   postactivation admission. Option, schema, table, one cleanup hook, next-run
   and watchdog freshness remain aggregate-only evidence.
5. The guarded command has exactly one IMPACTSHOP_DEPLOY_FILE, exact
   expected-before SHA and IMPACTSHOP_EXACT_RELEASE=1; no delete or sibling
   mapping is reachable.
6. The remote engine creates a private release manifest and verified backup,
   rechecks CAS, lints PHP, atomically installs exactly the boot payload and
   restores target 0444 plus parent 0555.
7. Post-release boot SHA equals the merged-main SHA, adapter markers occur
   exactly once and exact src=shopping-assistant cannot send raw data5.
8. Non-mutating Shopping Assistant and five Impact endpoint smokes are non-5xx;
   no automated revenue-bearing URL is followed.
9. Postactivation admission and central watchdog remain green; no new
   Cronos/watchdog hook is needed.
10. The exact release ID and deployed SHA produce a read-only rollback inspect
    result and bounded apply command; rollback is not executed on success.
11. Continuity records actual release/admission evidence and the manual-click
    status without raw pseudo, token, URL, row or economic data.
12. Targeted and required full tests, git diff --check, docsync/continuity,
    strict audit and a checkpoint commit pass; publication uses at most one
    branch push, one PR and one merge.

## 4. Design and file-level implementation plan

### Protected exact-file release

- Do not edit impactshop-boot.php; deploy the already merged and protected
  source from exact origin/main.
- Create a clean detached exact-main release worktree. Run the guarded wrapper
  first as exact-file DRY_RUN=1, then as real apply with the observed live SHA.
- Accept only the release engine's own ID, before/deployed hashes, backup
  verification, PHP lint and mode closure. Any drift or broader itemization is
  a hard stop.

### Runtime and route admission

- Before and after deploy, run ai-agent postactivate admission and the bounded
  production collector. Do not inspect affiliate rows.
- Preserve the old central-runtime branch/head. The storage guard blocks a new
  local worktree with remote_only, while a remote path cannot own the local
  hourly cron. Reuse only the already dedicated, clean central-watchdog runtime
  by creating a new branch there at exact main; run the guarded cron installer,
  record its backup and execute the watchdog once before admission. This is a
  runtime pointer refresh only; no watchdog source or tuple changes.
- Verify source markers and SHA over SSH only. Public smoke stops at the
  Sharity/ImpactShop boundary and never follows a Dognet link.
- Keep the runtime enabled on success. On regression disable the option first,
  then use exact release rollback with emitted release ID and deployed SHA.
  Retain the table and cleanup hook.

### Continuity and maximum bastion

- Add a protected release record and update the runtime handover, bastion
  status, local governance system plan, env/auth/runtime adapter, system
  snapshot, notes and docsync map.
- Re-run the existing maximum bastion over impactshop-boot.php plus
  impactshop-sharity-affiliate-runtime.php. No new recurring guard is needed
  because the central automation watchdog owns retention freshness.
- The documentation checkpoint contains no implementation-file change, so the
  reviewed algorithm/digest baseline is not regenerated.

## 5. Risk, coherence, and security review

| Risk | Control |
| --- | --- |
| Legacy /go regression | Exact source gate changes only src=shopping-assistant; run lifecycle/bastion and public non-click smokes. |
| Raw pseudo/NGO remains provider-visible | Provider d1 becomes regex-bounded sat1; data5, SID and click-log pseudo are blank on this lane. |
| Intent stored but redirect not completed | mark_redirected must succeed before 303; failure has no legacy fallback. |
| Hidden boot drift | Live blob matches historical commit; complete diff is only the reviewed three-hunk adapter patch. |
| Broad overwrite | Exact-file wrapper, one target, no delete, remote CAS, private backup and atomic apply. |
| Concurrent change | Fixed expected-before SHA; prepare and apply recheck CAS. |
| Rollback destroys correlation | Disable option first; restore only boot; retain table/cleanup for 45 days. |
| False financial/NGO truth | Correlation flags remain false; affiliate NGO data stays separate from vote/fund truth. |
| Automated canary | Automated checks stop before affiliate redirect; only operator clicks. |
| Cron duplication | Reuse daily cleanup plus central watchdog; add no scheduler. |
| Stale/failed watchdog evidence | Preserve old branch/head; branch-switch only the clean dedicated watchdog runtime to exact main, use guarded crontab backup/install and require a fresh manual run. |

Affected functions and surfaces:

- isb_handle_go only for exact src=shopping-assistant;
- impactshop_sharity_affiliate_prepare and mark_redirected filters;
- Dognet generation receives opaque d1 and blank pseudo/data5;
- Shopping unknown-shop logging is reduced to shop plus fixed source;
- legacy /go, /go-deal, CJ, saved offers, feeds, Ads Watch, Autobanner,
  rewards, points, votes and settlement remain unchanged.

## 6. QA evidence

| QA | Method | Evidence | Result |
| --- | --- | --- | --- |
| QA-1 correctness | Trace web canary through boot, prepare, Dognet builder, mark-redirected and correlator. | Only missing edge is production boot caller; d1 echo and hash-only store are proven. | PASS |
| QA-2 regression | Match live boot to Git history and diff against current main. | Live matches 3fa415ae; diff is exactly three adapter hunks with no later boot commit. | PASS |
| QA-3 security | Review raw identity flow, prewrapped rejection, CAS/backup/atomic/mode closure and rollback. | Opaque-only provider data, no fallback and recoverable exact release. | PASS |
| QA-4 operational/docs | Verify clean worktrees, origin/main, max bastion, exact release admission, storage guard, watchdog ownership and strict governance sync; inspect installed cron/state. | Existing tuple was correct but runtime/state stale; new worktree was policy-blocked. Branch-preserving transition of the clean dedicated runtime plus guarded cron backup/install is the bounded canonical correction. Post-commit strict audit exposed the required local governance-system-plan sync; the system plan and env/auth/runtime adapter are therefore explicit continuity targets. | PASS |

Plan gate:

    npm run codex:dev-plan:check -- --plan docs/sharity-shopping-opaque-sat1-production-cutover-sol-plan-2026-08-21.md

## 7. Rollback and observability

- Primary emergency action: set
  impactshop_sharity_affiliate_runtime_enabled to exact 0.
- Retain runtime data; table deletion and cleanup-hook removal are forbidden as
  first rollback actions.
- File rollback uses only bin/impactshop-guard-rollback.sh with --production,
  --apply, exact release ID and expected deployed SHA after read-only inspect.
- Observe only SHA/mode, parent mode, release state, aggregate admission,
  watchdog freshness and HTTP status. Never log raw token, pseudo, URL, NGO row
  or transaction.
- If human canary misses the merchant page, leaks data5, lacks sat1, fails local
  correlation or causes regression, disable runtime and use bounded rollback.

## 8. Luna implementation chunks

### Chunk 1 — exact-main watchdog runtime admission

- Record the clean dedicated runtime's old branch/head, then create a new
  branch there at exact origin/main without reset/delete; run watchdog
  guard/installer tests.
- Use the guarded cron installer, preserve its backup, run the watchdog once
  and pass aggregate postactivation admission.
- Done when supervision is fresh, exact-main and rollbackable.

### Chunk 2 — release preflight and one exact production boot cutover

- Run plan checker, targeted tests and exact-file dry-run from a clean detached
  ImpactShop current-main release worktree.
- Deploy merged-main impactshop-boot.php through one guarded exact-file apply.
- Validate release ID, backup/CAS, SHA, PHP lint, target 0444, parent 0555 and
  rollback inspect.
- Done when production contains the reviewed adapter or is restored.

### Chunk 3 — post-release admission and continuity checkpoint

- Run read-only verification and public non-click smokes.
- Update protected record, handover, bastion status, system snapshot, notes and
  docsync map; run all required tests, strict audit and diff check.
- Done when one checkpoint commit is clean and self-contained.

### Chunk 4 — minimum publication

- One guarded push, one PR, required green checks and one merge.
- Done when main contains continuity and no further source/deploy run is needed.

## 9. Handoff decision

The source file, before/after hashes, deploy mechanism, rollback order, tests,
observability and publication count are fixed. The package is
approved-for-luna. A new partner, CJ, different provider field, callback or
economic writer is a separate SOL/operator decision.
