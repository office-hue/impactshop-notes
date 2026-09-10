# Sharity profile summary live closure — Luna checkpoints A–B

## Checkpoint B — owner-grant and policy closure

The same clean worktree/branch continued to checkpoint B. The grant table is
an idempotent v2 `dbDelta` migration with an explicit InnoDB requirement and
`pending|active` state,
`activated_at`, `supersedes_grant_hash` and lifecycle indexes. New grants are
256-bit `random_bytes(32)` values stored only as HMAC-SHA256 hashes, remain
pending for ten minutes, and activate only on a later GET with both cookies.
Activation, superseded-grant revocation and readback use one captured UTC
clock and a transaction; both replacement cookie headers are queued and
verified first, then active renewal couples the owner and pseudo cookies for
365 days. Commit/readback failures expire queued replacement cookies and
safe-disable the policy. A newly issued token is never copied into `$_COOKIE`,
so same-request responses remain `binding_pending`.

`000-impactshop-owner-policy.php` is the first-loaded central policy layer.
Its machine-readable registry covers the exact current pseudo-backed mutator
and private-read route set (`owner_required`), the restore exchange
(`access_code_exchange`), admin and service classifications, and explicit
public/read-only exclusions. Existing endpoint nonce, webhook, service and
admin checks remain authoritative. Runtime introspection checks route, method,
callback and policy tuples for every registry entry; DB/policy failure or
safe-disable denies owner mutation. VB2026 uses explicit pre-auth intent and
owner-or-existing-Bearer-service modes; native endpoint controls remain
authoritative. The token_get_all inventory performs an exact two-way
source/registry/callback comparison and rejects unclassified routes, duplicate
classifications and callback drift; its negative tamper fixtures prove both
fail-closed paths. No cron/watchdog was added.

The action-bar account target now uses `home_url('/profil/#impactshop-account-top')`;
the legacy `#impactshop-account` resolver remains for old links. Exact-origin
checks compare scheme, host and effective port against `home_url`; forwarded
host headers and cross-host Sharity aliases are not trusted.

Checkpoint B remediation evidence: PHP lint, JS syntax, A static contracts,
owner-policy inventory, Luna owner remediation contracts and `git diff --check`
PASS. No provider, SSH, database execution,
OPcache, deployment, push, PR or merge was performed. Remaining gates are
staging backup/schema/browser/API E2E and runtime/provider worker proof.

Date: 2026-09-10
Plan ID: `sharity-profile-summary-live-closure-20260910`
Worktree: `impactshop-notes-feat-sharity-profile-summary-live-closure-luna-20260910`
Branch: `feat/sharity-profile-summary-live-closure-luna-20260910`
Base: `d39349a3dedad8ebda597c2d531fd2e498268990`

## Remediation checkpoint

- Same-request pending activation was removed: server cookies are the sole
  browser authority and issued-this-request state stays `binding_pending`.
- `binding_pending` and `unavailable` responses expose no profile values;
  `invalid_or_revoked` exposes only the pseudo ID, while active and legacy
  read-only visibility follows the approved state model.
- Restore captures only a current valid grant hash as
  `supersedes_grant_hash`; the prior grant remains active until later
  transactional activation succeeds.
- The canonical policy manifest now carries exact callback identities and
  source/policy tuples, including negative callback/method drift checks.
- VB2026 selection-intent remains pre-auth; select-ngo and completion accept a
  valid owner browser binding or the existing exact Bearer service path.
- Grant mutation requires actual InnoDB readback. MyISAM/unknown/missing
  storage is safe-disabled without implicit conversion; live staging migration
  remains an operator prerequisite.

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
  the profile UI. The central owner registry now covers the complete inspected
  pseudo-backed route set; unrelated service routes remain under their native
  authentication.

## Coherence and risk record

Affected route and hooks: identity profile REST GET/POST, profile/compact
shortcodes, profile `template_redirect` headers, REST post-dispatch headers,
AdSense `wp_head`, Site Kit filters and Elementor widget `before_render`.

Unaffected: vote/points business logic, cross-host SSO, provider/runtime
deployment, cron and watchdog.

Primary residual risks are unverified WordPress/Site Kit hook names in the live
environment, Elementor widget-name drift, and the existing cross-module
pseudo-mutator set awaiting checkpoint B. These require staging browser/API
E2E; this commit has no live authority.

## Evidence

- `php -l` identity and AdSense MU-plugins: PASS.
- `node --check wp-content/mu-plugins/impactshop-identity-panel.js`: PASS.
- `python3 tests/impactshop-identity-profile-v2-static.test.py`: PASS.
- `python3 tests/impactshop-profile-summary-checkpoint-a.test.py`: PASS.
- owner-policy duplicate and callback-drift tamper fixtures: PASS.
- `git diff --check`: PASS.
- Worktree-local marker and task-start decision: identity match, `allowed`.
- The shared active-worktree continuity pointer was owned by a parallel chat,
  so its single-active pointer check was not overwritten in this checkpoint.
- No push, PR, merge, staging, production, remote write, schema execution or
  provider operation.
- The complete protected candidate is bound by
  `docs/protected-change-records/2026-09-10-sharity-profile-summary-live-closure.md`;
  checkpoint-specific records remain narrower historical evidence.

## Manual UI handoff after staging

1. Open `/profil/` with two different cookie jars and verify no shared profile
   HTML or REST payload, no Google ad script/slot/widget, and stable layout.
2. Verify full and compact panels show nickname fallback, pseudo, level/points,
   and the canonical `/profil/#impactshop-account-top` action.
3. Verify active, legacy read-only, expired/revoked and unavailable states.
4. Verify a negative/malformed/missing vote row renders zero without creating a
   row, and a different pseudo's balance is never returned.
