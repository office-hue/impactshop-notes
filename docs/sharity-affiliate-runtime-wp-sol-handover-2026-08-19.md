# Sharity affiliate runtime — technical handover

Date: 2026-08-19

Package: ImpactShop WordPress checkpoint

Release posture: runtime and opaque boot adapter active in production; human
Dognet echo canary pending

## What is implemented

The existing `/go` route has one narrowly gated integration point for
`src=shopping-assistant`. A new MU-plugin issues an opaque `sat1` Dognet
attribution token, stores only its SHA-256 hash, and keeps the selected NGO and
an HMAC-only subject reference locally. A 15-minute intent is idempotent while
ready, can transition to redirected exactly once, and remains correlatable for
45 days. A daily WP-Cron purges expired mappings.

Legacy requests keep their current NGO/pseudo attribution behavior. The new
lane supports Dognet only; CJ is deliberately rejected until its provider
contract is separately verified and approved. A URL already wrapped by
Dognet is also rejected on the new lane, so inherited `d1/data5` values cannot
bypass the opaque attribution contract; users should submit the merchant URL.

## Runtime contract

- activation option: `impactshop_sharity_affiliate_runtime_enabled`
- schema option: `impactshop_sharity_affiliate_schema_version`
- cleanup evidence: `impactshop_sharity_affiliate_last_cleanup`
- cron hook: `impactshop_sharity_affiliate_retention_cleanup`
- table: `${wpdb->prefix}impactshop_affiliate_intents`
- token: `sat1_` plus 43 base64url characters
- subject: `hmac-sha256:` plus 64 lowercase hex characters

The internal correlator returns mapping metadata only. It cannot confirm a
purchase, commission or settlement, and it writes no financial state.

## Completed release evidence

- production release: `20260820T094433Z-87fe5d3ac628-98513d73`
- deployed SHA-256:
  `4347dded2ad009b5fe793836b57bbb163f3ffe94e55c0ed6dedeff93e0ef4859`
- target mode `0444`; max-protected parent restored to `0555`
- activation option missing, cleanup cron absent, affiliate table absent
- PHP lint, five public Impact endpoints and Shopping Assistant HTTP 200 green

## 2026-08-21 opaque boot-adapter cutover

- The production boot was still legacy SHA-256
  `cccc3f4147c0d849a4d53bf1567150c94e1493afda88686b6709d89f2136b56f`
  even though the runtime, schema and cleanup were active. That blob matched
  historical commit `3fa415ae`; the complete main diff was only the reviewed
  three-hunk Shopping adapter.
- Exact release `20260821T145250Z-1716e6fc2761-6892b1d3` installed merged-main
  boot SHA-256
  `e05a538fe4fdc5ca7af4220e03e3924cd4090f0d2ca5adf7c2f355cad545ba06`.
  Remote backup/CAS, PHP lint, target `0444`, parent `0555` and rollback inspect
  all passed.
- The central watchdog runtime now preserves the prior branch and runs exact
  ai-agent main from
  `ops/vb2026-transition-runtime-sharity-shopping-sat1-20260821`; rollback
  crontab is `central-watchdog.20260821T144748Z.crontab`.
- Postactivation admission returned `ADMITTED` before and after release. The
  global watchdog still has unrelated non-Shopping failures, exposed as a
  warning; the affiliate retention lane has no blocker.
- Five public Impact endpoints and the Shopping Assistant returned HTTP 200.
  No automated affiliate click was performed.

## Remaining safe validation order

1. Perform one human Árukereső product click from the Shopping Assistant.
2. Verify `last_click_data1` has `sat1_` shape and no raw pseudo/data5 is
   exposed.
3. Resolve that exact token through the internal correlator and verify the
   selected NGO plus HMAC subject only; keep every economic flag false.
4. Run one ordinary legacy `/go-deal` smoke in Chrome and Safari/WebKit.

Do not automate the affiliate click.

## Rollback

Disable the option first. Restore the protected boot adapter through the
guarded snapshot/revert lane if necessary, but retain cleanup until every
stored mapping reaches retention expiry. Never drop the table as the first
rollback action.

The current boot-adapter rollback is:

```text
bin/impactshop-guard-rollback.sh --production --apply --release-id=20260821T145250Z-1716e6fc2761-6892b1d3 --expected-deployed-sha=e05a538fe4fdc5ca7af4220e03e3924cd4090f0d2ca5adf7c2f355cad545ba06
```

## Next package

After the human Árukereső echo proves the opaque path, activate one newly
admitted Dognet partner through the same exact provider-neutral adapter. That
package must bind one reviewed program/deeplink tuple and keep CJ as a separate
proof/admission lane; it must not reopen raw pseudo/data5 attribution.
## 2026-09-03 UNice CJ canary extension

The runtime schema advances to v2 with nullable one-use handoff hash, authority
snapshot and disclosure columns. Existing Dognet issuance and redirect marking
remain unchanged. One separately gated CJ adapter reads the existing canonical
web-session table under dual authentication, stores its HMAC subject and the
trusted Győztesek Egyesülete mapping, and creates a 256-bit one-use Sharity
handoff. The handoff redirects only to the pinned UNice CJ Max Click path with
one opaque `sid`.

The new option `impactshop_sharity_affiliate_cj_canary_enabled` defaults to
`0`; production enablement and postactivation admission are separate. No new
cron, provider poll, financial writer or NGO Card projection is introduced.
Canonical evidence:
`docs/protected-change-records/2026-09-03-impact-shopping-unice-cj-adapter.md`.
