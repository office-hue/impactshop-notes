# Dognet Invalid Endpoint Hotfix

Date: 2026-07-09

## Incident

Dognet reported repeated request bursts from `https://app.sharity.hu` to unsupported endpoints:

- `POST /api/v1/conversions/search`
- `POST /api/v1/publisher/conversions/search`

Observed effect:

- thousands of `404` / `405` responses
- unnecessary partner-side load
- avoidable noise in Sharity affiliate ingestion paths

## Root cause

The live WordPress MU runtime in [wp-content/mu-plugins/impactshop-rest-totals.php](/Users/bujdosoarnold/Developer/GitHub/.worktrees/impactshop-notes-fix-dognet-invalid-search-endpoints-20260709/wp-content/mu-plugins/impactshop-rest-totals.php:1) attempted a legacy page-oriented conversions probe across these candidates:

- `POST /publisher/conversions/search`
- `POST /conversions/search`
- `GET /publisher/conversions`
- `GET /conversions`

This probing happened inside `dognet_api_list_conversions_page(...)`.

## Hotfix decision

We intentionally do not replace the broken probing with more endpoint guessing.

Instead:

- `dognet_api_list_conversions_page(...)` is fail-closed disabled
- totals aggregation falls through to the existing `dognet_api_list_conversions_all(...)` fallback
- the canonical data path remains the already-used `POST /raw-transactions/filter` transaction lane

## Follow-up fix

After the first production containment deploy, the totals route still returned `502` because the runtime expected `dognet_api_list_conversions_all(...)` to exist, but that helper was not actually defined in the loaded production MU runtime set.

The second-phase fix was:

- embed `dognet__status_map(...)`
- embed `dognet_api_list_conversions_batch(...)`
- embed `dognet_api_list_conversions_all(...)`

directly into [wp-content/mu-plugins/impactshop-rest-totals.php](/Users/bujdosoarnold/Developer/GitHub/.worktrees/impactshop-notes-deploy-dognet-hotfix-20260709/wp-content/mu-plugins/impactshop-rest-totals.php:1)

This made the totals route self-sufficient again while still keeping the invalid page-oriented endpoint probing disabled.

## Why this is the safest fix

- Stops the exact invalid endpoint pattern Dognet reported
- Reuses an already implemented and known-working transaction fetch path
- Avoids shipping another speculative Dognet API interpretation during incident response
- Keeps NGO totals behavior additive and localized to the Dognet fetch lane

## Risk notes

- If `dognet_api_list_conversions_all(...)` is unavailable at runtime, totals aggregation can still fail
- The fallback path may be heavier than true page APIs, but it is materially safer than continuing invalid bursts
- This is an incident containment fix, not a full Dognet API redesign

## Verification checklist

- `php -l wp-content/mu-plugins/impactshop-rest-totals.php`
- runtime smoke against the affected totals route
- post-deploy log check: invalid `conversions/search` requests stop
- optionally confirm with Dognet after deploy that the burst ended
- public + origin totals route returns `200` JSON after the fallback helper localization
