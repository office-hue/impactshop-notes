# Sharity affiliate runtime — technical handover

Date: 2026-08-19

Package: ImpactShop WordPress checkpoint

Release posture: production deployed, default-off, not activated

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

## Remaining safe activation order

1. Merge the separate ai-agent central-watchdog checkpoint.
2. Approve and set the activation option to exact string `1`.
3. Verify schema creation, cron registration, watchdog freshness and legacy
   redirect smoke.
4. Perform one human Árukereső click and compare the opaque Dognet echo with
   the local correlation mapping.

Do not automate the affiliate click.

## Rollback

Disable the option first. Restore the protected boot adapter through the
guarded snapshot/revert lane if necessary, but retain cleanup until every
stored mapping reaches retention expiry. Never drop the table as the first
rollback action.

Before activation, the exact first-install rollback is:

```text
bin/impactshop-guard-rollback.sh --production --apply --release-id=20260820T094433Z-87fe5d3ac628-98513d73 --expected-deployed-sha=4347dded2ad009b5fe793836b57bbb163f3ffe94e55c0ed6dedeff93e0ef4859
```

## Next package

The immediate next checkpoint belongs to the isolated ai-agent VPS worktree:
register the exact WP-Cron hook in the central automation watchdog, add its
focused tamper guard and update ai-agent continuity. After both repositories
are merged, production activation is an operator-controlled release package.
