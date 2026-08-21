# 2026-08-21 Sharity Shopping opaque sat1 production cutover

## Summary

The already reviewed Shopping Assistant adapter in merged ImpactShop main was
released to the exact production `impactshop-boot.php`. The exact
`src=shopping-assistant` lane now asks the active affiliate runtime for one
opaque `sat1` token, sends that token as Dognet `d1`, leaves `data5` blank and
redacts the raw pseudo/SID from click logging. Legacy redirect behavior remains
unchanged for every other source.

This package did not change source algorithms. It closed a production drift:
the live boot was still the legacy Git blob `3fa415ae` while the runtime module,
schema, cleanup and central watchdog were already active.

## Protected files touched and explicit approval

- Protected target: `wp-content/mu-plugins/impactshop-boot.php`.
- Protected continuity file touched in this checkpoint:
  `docs/bastion-guard-status.md`.
- Existing maximum perimeter:
  `scripts/sharity-affiliate-runtime-bastion-guard.sh` and
  `tests/sharity-affiliate-runtime-bastion.test.sh`.
- Operator approval: the next bounded SOL package and protected live release
  were explicitly approved on 2026-08-21.
- No source file was edited in this package. The deployed payload was exact
  merged `origin/main@1716e6fc276184793b4e7b32dfe51fe191ae90d0`.

## Coherence and affected functions

The complete production-to-main diff was three hunks, 61 additions and 17
deletions, all introduced by reviewed commit `13ee81a6`:

- `isb_handle_go(...)`, only when source is exactly `shopping-assistant`;
- `impactshop_sharity_affiliate_prepare` filter call;
- `impactshop_sharity_affiliate_mark_redirected` filter call;
- Dognet generation receives opaque `d1` and blank pseudo/data5;
- Shopping unknown-shop and click logging omit referer/IP/raw pseudo on the new
  source lane.

Unchanged surfaces:

- non-Shopping `/go` and `/go-deal` requests;
- CJ activation, partner feeds, campaign binding and deeplink resolution;
- Ads Watch, Autobanner and saved-offer behavior;
- NGO vote/fund truth, rewards, points, callbacks and settlement writers.

## Risk and security assessment

- Live expected-before SHA matched the historical repo blob exactly; there was
  no unclassified production drift.
- The exact-file deploy handled one file, no sibling and no delete operation.
- Remote backup, compare-and-swap, staged PHP lint, atomic apply and physical
  `0444` target plus `0555` parent closure all passed.
- The runtime stores a provider-token hash and HMAC subject, not raw token,
  pseudo, URL, IP or economic truth. Correlation cannot confirm a purchase,
  commission or settlement.
- A prewrapped Dognet URL fails closed on the Shopping source; no legacy
  attribution fallback is allowed.
- Automated affiliate clicks remained prohibited and were not performed.

## Release and rollback evidence

- Expected-before SHA-256:
  `cccc3f4147c0d849a4d53bf1567150c94e1493afda88686b6709d89f2136b56f`.
- Release ID: `20260821T145250Z-1716e6fc2761-6892b1d3`.
- Deployed SHA-256:
  `e05a538fe4fdc5ca7af4220e03e3924cd4090f0d2ca5adf7c2f355cad545ba06`.
- Live target: owner `sharityh`, mode `0444`; parent mode `0555`.
- Rollback inspect: `ok:true`, phase `deployed`, current SHA equals intended
  SHA and original state is present.

Emergency order:

1. set `impactshop_sharity_affiliate_runtime_enabled` to exact string `0`;
2. retain the table and cleanup hook;
3. after read-only inspect, run only:

```text
bin/impactshop-guard-rollback.sh --production --apply --release-id=20260821T145250Z-1716e6fc2761-6892b1d3 --expected-deployed-sha=e05a538fe4fdc5ca7af4220e03e3924cd4090f0d2ca5adf7c2f355cad545ba06
```

## Validation evidence

- PHP lint: local boot/runtime and remote boot PASS.
- Runtime lifecycle and maximum-bastion mutation suites: PASS.
- Exact release engine, detached preflight, exact mapping and rollback truth:
  PASS.
- Exact-file dry-run: one file, no delete, five Impact endpoints PASS.
- Post-release: boot SHA/modes PASS; prepare and mark-redirected markers each
  occur exactly once.
- Public smoke: five Impact endpoints and `https://sharity.hu/vasarlasi-seged`
  returned HTTP 200.
- Central watchdog moved branch-preservingly to exact ai-agent main with
  crontab backup
  `central-watchdog.20260821T144748Z.crontab`; the affiliate postactivation
  admission returned `ADMITTED` before and after deploy.
- The global watchdog remains `FAIL` because of unrelated legal/FactLens and
  stale automation findings; no affiliate retention signal is present. The
  Shopping admission exposes this as a warning and has no blocker.

## Manual UI checklist

1. Open `https://sharity.hu/vasarlasi-seged` while signed in to the linked
   ImpactShop profile.
2. Paste an exact Árukereső product URL and confirm the green partner result.
3. Accept the affiliate disclosure and click once.
4. Confirm the exact product page opens, not the Árukereső homepage.
5. In the next Dognet report, confirm `last_click_data1` has `sat1_` shape and
   that no raw pseudo or `data5` is exposed.
6. Resolve the token only through the internal correlation owner and confirm
   NGO `gyoztesek-egyesulete` plus an HMAC subject; do not treat it as purchase,
   donation or settlement proof.
7. Also open an ordinary legacy ImpactShop `/go-deal` link and confirm its
   existing behavior did not change.
8. Repeat the visual flow in Chrome and Safari/WebKit.

The human click/report correlation remains operator-owned and was not
automated in this package.
