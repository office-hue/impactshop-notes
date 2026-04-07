# Protected Change Record (DRAFT)

Date: 2026-04-06
Owner: bujdosoarnold
Status: Draft (planning only, no runtime change yet)

## Scope

### Protected files touched

- `impactshop-ads-watch.js` — JS sponsor CTA lifecycle fix (7 surgical changes)
- `impactshop-ads-watch.php` — version bump 2.5.49 → 2.5.56

Target files only:
- wp-content/mu-plugins/impactshop-ads-watch.js
- wp-content/mu-plugins/impactshop-ads-watch.php

Out of scope (must not be touched in this change):
- impactshop-offerwall.php
- impactshop-offerwall.js
- impactshop-ayet-offerwall.php
- impactshop-action-bar.php
- zzz-impactshop-ui-lock.php
- impactshop-pwa.php
- sw.js
- deploy/guard/env files

## Incident Context

Current production workaround is sponsor video disable, because sponsor CTA flow can freeze UI.

Known-good reference snapshot:
- ../output/ic-working-2.5.55-20260406/impactshop-ads-watch.js
- ../output/ic-working-2.5.55-20260406/impactshop-ads-watch.php

Current runtime differs from snapshot in key areas:
- JS external navigation lifecycle handling is not equivalent for sponsor CTA return.
- PHP includes schema 9 + auto-vote support not present in 2.5.55 snapshot.

Therefore full file rollback is unsafe. Forward-fix is required.

## Root Cause Hypothesis

Sponsor CTA path lost the stronger external-navigation lifecycle semantics from snapshot:
- source-aware external navigation state
- sponsor-specific cleanup on return
- resilient fallback when external open/visibility behavior differs by browser/PWA mode

This allows sticky sponsor UI state and freeze-like behavior on return paths.

## Proposed Safe Fix

1. JS: restore sponsor lifecycle semantics from snapshot
- Keep source context for external nav (sponsor_cta vs other)
- Reintroduce sponsor-specific return cleanup:
  - hideSponsorCta()
  - hideVideoInfoPanel()
- Preserve timeout/fallback behavior for blocked popups and no-visibility-change paths

2. JS: keep current auto-vote functionality unchanged
- keep state.autoVote behavior
- keep localStorage persistence
- keep set-auto-vote API calls

3. PHP: keep schema 9 and auto-vote endpoints/helpers unchanged
- keep IMPACTSHOP_ADS_WATCH_SCHEMA_VERSION = 9
- keep /ads-watch/set-auto-vote route
- keep auto_vote_enabled read/write helpers

4. Versioning
- bump plugin version to 2.5.56 in impactshop-ads-watch.php after merge is complete

## Verification Plan (must pass before sponsor re-enable)

Required smoke tags:
- route:impact-challenge
- flow:video-start
- flow:cta-click
- flow:reward-accumulation
- browser:webkit
- browser:chrome

Additional incident checks:
- sponsor CTA click does not freeze UI
- sponsor return hides sponsor CTA/panel as expected
- popup-blocked case recovers without stuck overlay
- reward counters are not duplicated/regressed
- auto-vote toggle persists and still works after sponsor flow

Suggested script:
- scripts/impact-challenge-ui-smoke.sh

## Rollback Plan

If regression appears:
1. Revert single forward-fix commit
2. Keep sponsor video disabled
3. Re-run smoke with current workaround baseline
4. Compare against snapshot files for the exact failing subpath

## Commit Gate Metadata Template

Use only when actually committing (not now):

BASTION_OVERRIDE=1
BASTION_CHANGE_RECORD=docs/protected-change-records/2026-04-06-ads-watch-sponsor-freeze-forward-fix-draft.md
BASTION_ROLLBACK_NOTE="git revert <commit>; sponsor video remains disabled until smoke green"
BASTION_SMOKE_TAGS="route:impact-challenge,flow:video-start,flow:cta-click,flow:reward-accumulation,browser:webkit,browser:chrome"

## Non-Goals

- No broad cleanup of all protected files in this step
- No deploy/guard/env updates in this step
- No UI redesign, no offerwall rewrite
