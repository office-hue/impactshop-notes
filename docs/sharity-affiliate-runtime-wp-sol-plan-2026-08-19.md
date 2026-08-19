# Sharity affiliate runtime WordPress release — implementation-ready plan

Date: 2026-08-19
Status: `approved-for-luna`
Operator approval: the operator explicitly approved the protected SOL release
and two sequential isolated worktrees on 2026-08-19.

## 1. Scope and non-goals

Classification: protected affiliate redirect/runtime release with
privacy-sensitive correlation storage, scheduled retention and central
supervision.

Falsifiable hypothesis: an exact shopping-assistant request handled by the
existing protected /go owner can HMAC-pseudonymize the ImpactShop pseudo,
persist an NGO mapping behind one opaque sat1 token, send only that token to
Dognet, enforce 15-minute/45-day lifecycle rules, and leave every legacy /go
and /go-deal request unchanged. The hypothesis fails on any legacy drift, raw
pseudo/token/URL persistence or logging, missing cleanup, or invisible cron.

In scope:

- additive ImpactShop affiliate runtime MU-plugin;
- smallest approved impactshop-boot.php delegation, with /go remaining sole
  redirect authority;
- provider-neutral storage contract, Dognet active for current partners, CJ
  reserved until its proof becomes verified;
- WordPress table, issue, transition, correlation lookup and retention purge;
- default-off option, daily WP-Cron and sanitized health evidence;
- maximum bastion, protected hashes/config and continuity;
- second isolated ai-agent checkpoint for central watchdog registration;
- guarded staging/production release and one human canary only after both
  repositories are merged.

Non-goals:

- no reward, points, vote, donation, callback, reversal, commission or
  settlement writer;
- no feed/CSV rewrite and no /go-deal behavior change;
- no client-built affiliate URL/token;
- no public API, VPS HTTP service, Vercel route or Sharity session transfer;
- no direct Sharity profile binding in V1;
- no automated affiliate click or claim that a click proves money;
- no CJ activation while CJ proof/authority remains pending.

## 2. Context and canonical sources

- AGENTS.md
- docs/ai-assistant-canonical-policy.md
- docs/pr-policy.md
- docs/impact-challenge-canonical-baseline.md
- docs/protected-file-change-checklist.md
- docs/impactshop-deploy.md
- docs/impactshop-governance-system-plan-2026-06-16.md
- wp-content/mu-plugins/impactshop-boot.php
- wp-content/mu-plugins/impactshop-go-bridge.php
- docs/impactshop-protected-files.json
- docs/impactshop-guard-config.json
- ai-agent checkpoint 42a942c62486e724daf175076076ab01cde6677f
- ai-agent affiliate intent runtime handover and central watchdog source.

Observed ownership:

- Sharity already emits a 303 to https://app.sharity.hu/go; no Vercel change is
  required.
- impactshop-boot.php owns Dognet/CJ link construction.
- the WP cron list is hard-coded in ai-agent, so supervision requires a second
  isolated checkpoint. Existing worktrees are never edited.

## 3. Acceptance criteria

1. Runtime defaults off and activates only for exact option string 1.
2. Only exact src=shopping-assistant enters the new path.
3. Destination remains owned by existing shop resolution; adapter accepts no
   destination URL.
4. Stored rows contain no raw pseudo, provider token, URL, session,
   credential, browser/device, IP or economic outcome.
5. Provider attribution contains only sat1; new-path Dognet data5 and raw NGO
   d1 are absent.
6. A pre-wrapped Dognet destination is rejected on the new lane so inherited
   d1/data5 cannot bypass opaque attribution; legacy passthrough is unchanged.
7. Issue is idempotent for 15 minutes and redirect transition is one-time.
8. Lookup is correlation-only; purchase/commission/settlement stay false.
9. Daily cleanup deletes due rows and stores only a sanitized success marker.
10. Missing schema/key, disabled option, invalid input/transition or cleanup
   failure blocks the new path without legacy fallback.
11. Unknown-shop errors on the exact new source log only sanitized shop/source;
    legacy diagnostic logging remains unchanged.
12. Non-shopping /go, /go-deal, Dognet/CJ/API/fallback/logging behavior remains
    regression-green.
13. Central watchdog observes hook
    impactshop_sharity_affiliate_retention_cleanup with 36-hour warn threshold.
14. Both repositories pass targeted/full required tests, diff check,
    docs/continuity, strict audit and checkpoint before push.
15. Deploy occurs only from merged main through the guarded wrapper, with
    backup, rollback and read-only restoration.
16. One human Arukereso click yields an opaque sat1 Dognet report echo; no
    automation follows the affiliate URL.

## 4. Design and file-level implementation plan

ImpactShop repository:

- wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php
  - strict context validator;
  - domain-separated AUTH_SALT HMAC subject and provider token;
  - versioned table;
  - idempotent issue, atomic redirect transition, correlation-only lookup;
  - bounded purge, option-gated daily schedule and health.
- wp-content/mu-plugins/impactshop-boot.php
  - minimal protected touch after shop/provider/pseudo resolution;
  - exact-source adapter call and authorized-result requirement;
  - returned token used in provider field, raw pseudo attribution blanked;
  - redirected transition before existing safe redirect;
  - no new-path token/pseudo logging and unchanged legacy values.
- tests/sharity-affiliate-runtime-test.php
  - deterministic WordPress-stub behavior/privacy/lifecycle tests.
- scripts/sharity-affiliate-runtime-bastion-guard.sh and matching tamper test.
- protected change record with approval, coherence, risk, affected functions,
  backup, rollback, smoke tags and manual UI checklist.
- protected model/config/hash/checksum registration through canonical tooling.
- bastion, governance, docsync, notes, system snapshot and handover continuity.

Table contract:

- WordPress prefix plus impactshop_affiliate_intents;
- primary activation_id;
- unique SHA-256 provider_token_hash and request_key_hash;
- HMAC-only subject_ref;
- ngo_ref, partner_key, provider_key, provider_program_ref, source_placement;
- ready_to_redirect, redirected, expired and blocked states;
- UTC creation/expiry/redirect/delete timestamps;
- no raw provider token, pseudo, URL or economic columns.

WordPress state contract:

- activation option: impactshop_sharity_affiliate_runtime_enabled;
- schema option: impactshop_sharity_affiliate_schema_version;
- cleanup marker: impactshop_sharity_affiliate_last_cleanup;
- cron hook: impactshop_sharity_affiliate_retention_cleanup;
- daily recurrence, 36-hour watchdog threshold;
- 15-minute ready TTL and 45-day retention.

AI-agent repository, only after the ImpactShop checkpoint is clean:

- scripts/automation-watchdog-guard.sh: add the exact WP-Cron check.
- focused affiliate-runtime watchdog bastion and tamper test.
- aggregate Shopping bastion/package registration.
- memory/docs/system/notes continuity.

## 5. Risk, coherence, and security review

Affected surfaces:

- /go only for exact shopping-assistant source;
- existing Dognet direct/API/fallback and future CJ token placement;
- click logging redaction on the new path;
- one new daily retention hook;
- Dognet last_click_data1 downstream evidence only.

Unaffected surfaces:

- /go-deal and all other sources;
- Impact Challenge pause lock, autobanner feeds/rotation, offerwall, points,
  votes, rewards, NGO selection/cards/guides and financial truth.

Risk controls:

- legacy regression: exact-source gate plus legacy/new-path matrix;
- identity leak: HMAC subject, token hash only, schema/logging bastion;
- pre-adapter error leak: exact-source unknown-shop branch logs no referer,
  pseudo or IP while preserving legacy diagnostics;
- partial issue: ready rows expire; only built targets mark redirected;
- retry: request-key idempotency and atomic transition;
- stale rows: daily cleanup, marker and central watchdog;
- open redirect: existing shop authority remains sole destination owner;
- wrapped attribution bypass: pre-wrapped Dognet destination fails closed only
  on the new source lane;
- CJ drift: pending provider rejected;
- protected deploy: snapshot, merged-main wrapper, rollback and read-only close;
- click vs money confusion: receipt fixes all economic assertions false;
- existing legacy credential constants are neither touched nor copied.

No equally safe fully additive handler exists: an early parallel /go handler
would duplicate redirect ownership or expose the opaque token through the
legacy click logger. The approved minimal delegation is the smaller protected
change.

## 6. QA evidence

| QA | Method | Expected evidence | Result |
| --- | --- | --- | --- |
| QA-1 correctness | Read full /go resolution, link and log flow plus Sharity 303 contract | Adapter fits before provider construction; no web change | PASS |
| QA-2 regression | Inventory /go, /go-deal, Dognet/CJ, bridge, model and deploy wrapper | Exact source can preserve legacy branches | PASS |
| QA-3 security | Inspect schema/log fields, redirect boundary, option and cron ownership | HMAC/hash only, no URL/public/economic writer | PASS |
| QA-4 operational/docs | Confirm owners, hard-coded watchdog, installed cron and clean worktrees | Two sequential checkpoints required | PASS |

Plan gate:

    node --import tsx scripts/check-codex-dev-plan.ts --plan ABSOLUTE_PLAN

Required ImpactShop validation:

    php -l wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php
    php -l wp-content/mu-plugins/impactshop-boot.php
    php tests/sharity-affiliate-runtime-test.php
    bash tests/sharity-affiliate-runtime-bastion.test.sh
    bash scripts/sharity-affiliate-runtime-bastion-guard.sh
    bash scripts/check-protected-file-touch.sh
    bash scripts/git-health-check.sh
    bash scripts/safe-repo-audit.sh --strict --mode push
    git diff --check

Also run the relevant existing staging and /go smoke suite. AI-agent must run
focused/aggregate Shopping guards, TypeScript, relevant/full regressions,
docsync, memory index, strict audit and git diff check.

## 7. Rollback and observability

Before deploy record protected snapshots, hashes/modes, cron list, table and
option state. Keep activation option 0.

Activation order:

1. merge both repositories;
2. update central watchdog runtime and prove it green;
3. guarded staging deploy, schema, cron, option 1 and smoke;
4. guarded production deploy with option still 0;
5. verify schema/cron/health, then set option 1;
6. one human Arukereso click and fresh report echo.

Rollback:

- option 0 first;
- restore boot from guard snapshot or guarded revert;
- retain additive runtime/cleanup until every retained mapping is purged;
- never drop the table as rollback;
- restore prior watchdog runtime only after the hook is intentionally disabled;
- rerun legacy /go, /go-deal, provider and watchdog smoke.

Observability exposes only counts, status and timestamps. Tokens, pseudos, URLs
and economic values are forbidden.

## 8. Luna implementation chunks

### Chunk 1 — deterministic WP runtime

- Files/interfaces: new MU-plugin and focused tests.
- Preconditions: clean worktree, option off.
- Change: schema, HMAC/token, issue/transition/lookup/purge, cron/health.
- Validation: lint and focused positive/negative tests.
- Done: runtime is testable without route or production.

### Chunk 2 — protected /go delegation

- Files/interfaces: boot and adapter contract.
- Preconditions: Chunk 1 green and change record drafted.
- Change: exact-source fail-closed delegation, provider substitution,
  transition and log redaction.
- Validation: legacy/new-path matrix, lint and protected smoke.
- Done: only Shopping Assistant receives opaque attribution.

### Chunk 3 — ImpactShop maximum bastion and checkpoint

- Files/interfaces: protected model/config/hashes, guards/tests/docs.
- Preconditions: Chunks 1-2 green.
- Change: lock runtime and delegation; record rollback/manual UI.
- Validation: tamper, protected-touch, health/audit, diff/docsync.
- Done: clean ImpactShop checkpoint commit.

### Chunk 4 — central watchdog checkpoint

- Files/interfaces: exact ai-agent files above.
- Preconditions: ImpactShop checkpoint clean; explicit worktree switch.
- Change: one WP-Cron check plus max-bastion/tamper/docs.
- Validation: focused/aggregate/full QA, docsync, diff, strict audit.
- Done: clean ai-agent checkpoint commit.

### Chunk 5 — guarded publication and activation

- Files/interfaces: two branches/PRs, merged mains, guarded deploy/watchdog.
- Preconditions: both checkpoints green, no review blockers, backup/rollback
  and production target identity verified.
- Change: minimum one PR/merge per repo, guarded deploy, option activation and
  one human canary.
- Validation: staging/production smoke, cron/watchdog freshness, report echo,
  physical read-only modes.
- Done: supervised opaque live flow or evidenced option-0 rollback.

## 9. Handoff decision

The plan has no unresolved implementation choice. Operator approval covers the
protected release and two isolated worktrees. Bounded implementation may start.
Production remains mechanically blocked until both merged checkpoints, guarded
deploy evidence, cron/watchdog freshness and manual-click gate exist.
