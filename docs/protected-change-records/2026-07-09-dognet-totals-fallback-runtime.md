# 2026-07-09 Dognet Totals Fallback Runtime

## Summary

Second-phase incident recovery after the invalid Dognet conversions probing disable.

The first containment fix stopped the unsupported Dognet endpoint bursts, but the live totals route still returned `502` because the production MU runtime expected a fallback helper family that was not actually loaded.

This change localizes the fallback runtime into the protected totals MU file so the route remains self-sufficient.

## Protected files touched

- `wp-content/mu-plugins/impactshop-rest-totals.php`

## Coherence assessment

### Directly affected runtime lane

- `dognet__status_map(...)`
- `dognet_api_list_conversions_batch(...)`
- `dognet_api_list_conversions_all(...)`
- `impactshop_totals_collect(...)`
- `GET /?rest_route=/impactshop/v1/totals`

### Upstream dependencies

- `dognet_api_request(...)`
- Dognet auth token/runtime configuration already used by the site
- canonical Dognet transaction lane: `POST /raw-transactions/filter`

### Downstream / user-facing surfaces

- any NGO/shop totals widget reading the totals REST payload
- legacy NGO card related summary surfaces
- operator verification flows that compare affiliate totals by NGO/shop group

### Intentionally unchanged

- totals JSON contract
- public aggregation semantics
- invalid `conversions/search` probing remains disabled
- affiliate redirect / tracking behavior

## Risk assessment

### Primary risk removed

- totals route no longer depends on a missing cross-file helper definition at runtime
- public totals route recovers from `502` to `200` without re-enabling invalid Dognet API guesses

### Residual risks

- fallback path still depends on valid Dognet credentials and `raw-transactions/filter` availability
- large date windows can still be heavier than a true dedicated totals endpoint
- the route remains part of the protected identity and points surface family, so future edits must stay tightly scoped

## Decision log

- Chosen approach: localize the fallback helper family inside `impactshop-rest-totals.php`
- Reason: lowest-risk incident recovery that matches the already used canonical Dognet transaction path
- Rejected approach: introducing a new shared runtime dependency during hotfix publish

## Rollback

- git-level: `git revert f7c301bf`
- production file-level: restore backup `impactshop-rest-totals.php.bak-20260709-090656`
- operational note: rollback reintroduces the broken runtime dependency and is only safe if replaced by an equivalent loaded helper implementation

## Smoke checklist

### Runtime/API

- `php -l wp-content/mu-plugins/impactshop-rest-totals.php`
- `curl -sS -D - 'https://app.sharity.hu/?rest_route=/impactshop/v1/totals'`
- `curl -k --resolve app.sharity.hu:443:185.111.89.244 'https://app.sharity.hu/?rest_route=/impactshop/v1/totals'`

### Expected result

- public route returns `200`
- origin-resolved route returns `200`
- response body is JSON with `rows` and `meta.grand`
- no renewed `conversions/search` burst is required for totals retrieval

## Smoke scope / smoke tag

- `route:factlens-vb-prod`
- `route:impact-challenge`
- `route:profil`
- `flow:message-popup`
- `flow:points-jump`
- `flow:legacy-pool-visibility`
- `flow:profile-return-account`
- `flow:profile-return-restore`

## Manual UI checklist

- Open one frontend surface that consumes the shared totals lane
- Confirm no obvious broken totals widget or empty-state regression
- Confirm the route-backed numbers render or fail gracefully without PHP warnings

## Deploy notes

- production deploy completed with backup and permission relock
- backup used for rollback: `impactshop-rest-totals.php.bak-20260709-090656`
- live verification already confirmed public and origin route `200` responses
