# Sharity affiliate runtime — technical handover

Date: 2026-08-19

Package: ImpactShop WordPress checkpoint

Release posture: default-off, not deployed, not activated

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

## Safe release order

1. Merge this ImpactShop checkpoint.
2. Merge the separate ai-agent central-watchdog checkpoint.
3. Run the guarded staging deploy with option `0`.
4. Verify schema, cron registration, health evidence and legacy redirect smoke.
5. Run the guarded production deploy with option `0`.
6. Verify central watchdog, then change the option to exact string `1`.
7. Perform one human Árukereső click and compare the opaque Dognet echo with
   the local correlation mapping.

Do not deploy from this feature worktree and do not automate the affiliate
click.

## Rollback

Disable the option first. Restore the protected boot adapter through the
guarded snapshot/revert lane if necessary, but retain cleanup until every
stored mapping reaches retention expiry. Never drop the table as the first
rollback action.

## Next package

The immediate next checkpoint belongs to the isolated ai-agent VPS worktree:
register the exact WP-Cron hook in the central automation watchdog, add its
focused tamper guard and update ai-agent continuity. After both repositories
are merged, production activation is an operator-controlled release package.
