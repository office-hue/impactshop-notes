# 2026-08-19 Sharity affiliate runtime

## Summary

The approved SOL package introduces a default-off, privacy-preserving
correlation runtime for Shopping Assistant redirects. The existing `/go`
handler remains the only redirect and partner-link owner. For the exact
`src=shopping-assistant` lane it replaces raw Dognet attribution values with
one opaque `sat1` token and keeps the NGO plus HMAC-pseudonymized user mapping
inside WordPress.

This change does not create or modify reward, points, vote, donation,
commission, callback, reconciliation or settlement writers. CJ remains
fail-closed until separate verified provider proof is approved.

## Protected files touched

- `wp-content/mu-plugins/impactshop-boot.php`
- `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`
- `scripts/sharity-affiliate-runtime-bastion-guard.sh`
- `docs/impactshop-protected-files.json`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-guard-config.sha256`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`

## Coherence assessment

Directly affected functions and hooks:

- `isb_handle_go(...)`, only behind exact `src=shopping-assistant`;
- `impactshop_sharity_affiliate_prepare` filter;
- `impactshop_sharity_affiliate_mark_redirected` filter;
- `impactshop_sharity_affiliate_correlate(...)` internal lookup;
- `impactshop_sharity_affiliate_retention_cleanup` daily WP-Cron hook.

Intentionally unchanged:

- non-Shopping-Assistant `/go` and every `/go-deal` request;
- partner catalog/feed truth and destination resolution;
- Dognet campaign, ad-channel and deeplink ownership;
- CJ link construction outside the new lane;
- NGO vote truth, profile writer, points, rewards and financial settlement.

The additive runtime loads after `impactshop-boot.php`, but WordPress loads all
MU-plugins before `template_redirect`; therefore its filters are registered
before the existing handler runs. No parallel redirect owner is introduced.

## Security and data assessment

- Runtime defaults off and requires option value exactly `1`.
- The table stores provider-token hash, request-key hash and HMAC subject; it
  stores no raw pseudo, raw provider token, destination URL, IP, browser,
  credential, session or economic outcome.
- Ready intents expire after 15 minutes and are one-time transitioned.
- Redirected mappings are retained for at most 45 days and purged daily.
- Correlation output explicitly keeps purchase, commission and settlement
  assertions false.
- Invalid input, disabled runtime, missing schema/key and invalid transition
  fail closed without a legacy fallback on the exact new source lane.
- Unknown-shop errors on the exact new source log sanitized shop/source only;
  referer, pseudo and IP remain available solely to unchanged legacy logging.
- Pre-wrapped Dognet destinations fail closed on the exact new lane, preventing
  inherited `d1/data5` from bypassing the opaque token contract; the legacy
  passthrough branch remains unchanged.
- The new module and adapter contract are protected by a source guard and a
  mutation test that proves source-gate, raw-schema and transition tampering
  are rejected.
- The digest preflight exposed 27 pre-existing stale manifest values. Before
  refresh, every affected path was proven clean against this worktree's
  `HEAD`/canonical `origin/main` base; regeneration refused any unrelated
  dirty protected path. The resulting manifest verifies all 145 retained
  locks, including the two legacy manifest-only protections, without changing
  their source files.

## Risk assessment

- Primary regression risk: unintended legacy `/go` drift. Control: exact
  source gate and preservation of original values on every other source.
- Primary privacy risk: raw pseudo or token leakage. Control: HMAC/hash-only
  schema, blank new-path `data5`, and blank click-log pseudo/SID.
- Primary attribution risk: a click being interpreted as money. Control:
  correlation-only result with all economic flags fixed false.
- Operational risk: stale mappings. Control: daily cleanup plus a separately
  checkpointed central watchdog check with a 36-hour warning threshold.
- Residual rollout risk: activation before both repository checkpoints are
  merged. Control: production option remains `0` until guarded deploy and
  post-deploy cron/schema verification pass.
- Digest-baseline risk: the refresh could otherwise bless unrelated worktree
  edits. Control: the generator aborted on every dirty protected file except
  the four explicitly approved package artifacts, then full-manifest SHA-256
  parity and both checksum files were verified.

## Rollback

1. Set `impactshop_sharity_affiliate_runtime_enabled` to `0` immediately.
2. Guarded-revert the boot adapter or restore it from the deploy snapshot.
3. Keep the additive runtime and cleanup hook present until retained mappings
   have expired; do not drop the table as an emergency rollback.
4. Re-run legacy `/go`, `/go-deal`, Dognet and central watchdog checks.
5. Restore the previous watchdog registration only after the hook is
   intentionally disabled.

## Smoke checklist

Mandatory smoke tags:

- `route:impactshop`
- `flow:saved-offers-open`
- `flow:go-deal`
- `browser:webkit`
- `browser:chrome`
- `deploy:guard-preflight`
- `deploy:checksum-verify`

Before deploy:

- PHP lint for both MU-plugin files;
- focused lifecycle test and bastion mutation test;
- protected-touch, git-health, strict audit and `git diff --check`;
- verify runtime option stays `0`.

After guarded staging/production deploy:

- verify schema and exact cron hook without reading sensitive row data;
- verify non-Shopping `/go` and `/go-deal` remain unchanged;
- with option `1`, run one human Árukereső canary click;
- verify Dognet echo carries an opaque `sat1` value and local mapping resolves
  the selected NGO plus HMAC subject;
- never claim the click proves commission or settlement.

## Manual UI checklist

- Open the Shopping Assistant with an Árukereső URL and confirm partner match.
- Start the purchase once and confirm a normal redirect to the intended page.
- Confirm no pseudo or selected NGO slug appears in the provider-facing
  attribution parameters for the new lane.
- Open the ImpactShop saved-offers surface and a `/go-deal` link to confirm
  existing behavior.
- Repeat in Chrome and WebKit/Safari.

## Deploy notes

- Deploy only from reviewed, merged `main` through the canonical guarded
  wrapper with snapshot and rollback evidence.
- The runtime file may be deployed while disabled; activation is a separate
  option change after schema, cron and central watchdog verification.
- Affiliate clicks are human-only canaries; automation must not follow the
  revenue-bearing partner URL.
