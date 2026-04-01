# 2026-04-01 - JYSK report route restoration

## Summary

Restore the dedicated JYSK campaign report routes that currently return WordPress 404:

- `/jysk-riport/`
- `/jysk-riport/?print=1`
- `/jysk-riport.data.json`

The route is restored through the protected static guide router, using the recovered dedicated JYSK report assets from the restore tree. This is not a generic report shortcode change and does not touch the JYSK vote runtime.

## Protected Files Touched

- `wp-content/mu-plugins/impactshop-ngo-guides.php`
- `wp-content/mu-plugins/impactshop-ngo-guides/jysk-riport.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/jysk-riport.data.json`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-config.sha256`
- `docs/impactshop-guard-hashes.sha256`
- `docs/ai-assistant-canonical-policy.md`
- `docs/impactshop-deploy.md`

## Coherence / Impact

Directly affected:

- static route registration for `/jysk-riport/`
- static asset serving for the dedicated JYSK one-page report
- `?print=1` auto-print mode already embedded in the recovered HTML
- explicit JSON route for the raw JYSK report data package
- explicit canonical guard inventory + digest lock for the JYSK source assets

Not affected:

- JYSK vote runtime (`impactshop-vote-jysk.php`, `.js`)
- Impact Challenge / Offerwall / AyeT runtime
- guide routes unrelated to `jysk-riport`

## Risk

Primary risk:

- guide router regression could break other static guide routes if the route map is malformed

Secondary risk:

- rewrite cache could still point to the old 404 state until deploy-side rewrite flush completes

Mitigation:

- additive route only
- recovered report HTML is self-contained and already includes embedded data
- deploy mapping includes rewrite flush
- post-deploy manual route check on the three JYSK URLs
- guard inventory + hash manifest now lock the source assets explicitly, so later drift cannot silently detach live route state from canonical source control

## Smoke Scope

- `route:jysk-riport`
- `route:jysk-riport-print`
- `route:jysk-riport-data`
- `flow:jysk-report-render`
- `flow:jysk-report-print`
- `flow:jysk-report-json`

## Rollback

Rollback by restoring the pre-deploy snapshot from the guard deploy snapshot id and removing the JYSK route additions from `impactshop-ngo-guides.php` plus the two JYSK report assets:

- `wp-content/mu-plugins/impactshop-ngo-guides.php`
- `wp-content/mu-plugins/impactshop-ngo-guides/jysk-riport.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/jysk-riport.data.json`

If route behavior remains stale after rollback, run rewrite flush again through the standard deploy mapping flow.

## Manual UI Checklist

- open `/jysk-riport/` and verify the dedicated JYSK one-page report renders
- verify historical campaigns such as `Ózd` and `Várpalota` appear in the overview data
- open `/jysk-riport/?print=1` and verify print mode auto-starts
- open `/jysk-riport.data.json` and verify JSON payload is returned with HTTP 200
- verify unrelated guide routes such as `/cegeknek/` and `/rolunk/` still render
