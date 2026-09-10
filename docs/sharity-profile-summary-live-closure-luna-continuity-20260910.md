# Sharity profile summary live closure — Luna checkpoint A

## Checkpoint B — owner-grant and policy closure

The same clean worktree/branch continued to checkpoint B. The grant table is
an idempotent v2 `dbDelta` migration with `pending|active` state,
`activated_at`, `supersedes_grant_hash` and lifecycle indexes. New grants are
256-bit `random_bytes(32)` values stored only as HMAC-SHA256 hashes, remain
pending for ten minutes, and activate only on a later GET with both cookies.
Activation, superseded-grant revocation and readback use one captured UTC
clock and a transaction; active renewal couples the owner and pseudo cookies
for 365 days and compensates cookie failures by restoring the prior DB expiry.

`000-impactshop-owner-policy.php` is the first-loaded central policy layer.
Its machine-readable registry covers the exact current pseudo-backed mutator
and private-read route set (`owner_required`), the restore exchange
(`access_code_exchange`), admin and service classifications, and explicit
public/read-only exclusions. Existing endpoint nonce, webhook, service and
admin checks remain authoritative. Runtime introspection checks route, method,
callback and policy tuples; DB/policy failure or safe-disable denies owner
mutation. The token_get_all inventory performs an exact two-way source/registry
comparison and rejects unclassified routes. No cron/watchdog was added.

The action-bar account target now uses `home_url('/profil/#impactshop-account-top')`;
the legacy `#impactshop-account` resolver remains for old links. Exact-origin
checks compare scheme, host and effective port against `home_url`; forwarded
host headers and cross-host Sharity aliases are not trusted.

Checkpoint B evidence: PHP lint, A static contracts, exact owner-policy
inventory and `git diff --check` PASS. No provider, SSH, database execution,
OPcache, deployment, push, PR or merge was performed. Remaining gates are
staging backup/schema/browser/API E2E and runtime/provider worker proof.

Date: 2026-09-10
Plan ID: `sharity-profile-summary-live-closure-20260910`
Worktree: `impactshop-notes-feat-sharity-profile-summary-live-closure-luna-20260910`
Branch: `feat/sharity-profile-summary-live-closure-luna-20260910`
Base: `d39349a3dedad8ebda597c2d531fd2e498268990`

## Scope completed

- Full and compact identity panels expose nickname/fallback, pseudo ID, level/
  points summary and current spendable-votes state.
- `GET /impact/v1/identity/profile` has additive `votes_available` and
  `identity_state` fields. The vote read is a direct, current-pseudo,
  SELECT-only query; malformed, missing and negative values normalize to zero.
- The profile route family has a shared path classifier, private no-store
  response/page headers and `Vary: Cookie`. Repository AdSense, known Site Kit
  AdSense paths and exact Elementor AdSense widgets are suppressed at their
  producers; no rendered HTML regex is used.
- Failed owner/pseudo cookie issuance returns `unavailable` without exposing a
  newly generated pseudo. Non-active states keep mutation controls disabled in
  the profile UI. Existing owner grants and the remaining pseudo mutators are
  intentionally outside this checkpoint.

## Coherence and risk record

Affected route and hooks: identity profile REST GET/POST, profile/compact
shortcodes, profile `template_redirect` headers, REST post-dispatch headers,
AdSense `wp_head`, Site Kit filters and Elementor widget `before_render`.

Unaffected: vote/points writers, owner-grant schema migration, central policy
registry, cross-host SSO, provider/runtime deployment, cron and watchdog.

Primary residual risks are unverified WordPress/Site Kit hook names in the live
environment, Elementor widget-name drift, and the existing cross-module
pseudo-mutator set awaiting checkpoint B. These require staging browser/API
E2E; this commit has no live authority.

## Evidence

- `php -l` identity and AdSense MU-plugins: PASS.
- `node --check wp-content/mu-plugins/impactshop-identity-panel.js`: PASS.
- `python3 tests/impactshop-identity-profile-v2-static.test.py`: PASS.
- `python3 tests/impactshop-profile-summary-checkpoint-a.test.py`: PASS.
- `git diff --check`: PASS.
- No push, PR, merge, staging, production, remote write, schema execution or
  provider operation.

## Manual UI handoff after staging

1. Open `/profil/` with two different cookie jars and verify no shared profile
   HTML or REST payload, no Google ad script/slot/widget, and stable layout.
2. Verify full and compact panels show nickname fallback, pseudo, level/points,
   and the canonical `/profil/#impactshop-account-top` action.
3. Verify active, legacy read-only, expired/revoked and unavailable states.
4. Verify a negative/malformed/missing vote row renders zero without creating a
   row, and a different pseudo's balance is never returned.
