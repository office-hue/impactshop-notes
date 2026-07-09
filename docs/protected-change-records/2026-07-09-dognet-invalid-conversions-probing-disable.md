# 2026-07-09 Dognet Invalid Conversions Probing Disable

## Summary

Incident containment hotfix for unsupported Dognet conversions endpoints that were generating repeated `404` / `405` bursts from the live WordPress runtime.

Scope is intentionally narrow:

- disable legacy page-oriented Dognet conversions probing
- preserve existing totals REST contract
- fall through to the already-used canonical `raw-transactions/filter`-based conversion fetch path

## Files touched

- `wp-content/mu-plugins/impactshop-rest-totals.php`

## Coherence assessment

### Directly affected runtime lane

- `dognet_api_list_conversions_page(...)`
- `impactshop_totals_collect(...)`
- `GET /wp-json/impactshop/v1/totals`

### Upstream dependencies

- `dognet_api_request(...)`
- `dognet_api_list_conversions_all(...)`
- Dognet publisher API authentication/token lane
- canonical Dognet transaction fetch path: `POST /raw-transactions/filter`

### Downstream / user-facing surfaces

- NGO/shop totals widgets or reports consuming `/wp-json/impactshop/v1/totals`
- any internal operator screen or frontend card that reads the totals REST payload

### Intentionally unchanged

- totals response shape
- aggregation logic
- NGO/shop grouping semantics
- Dognet auth/login logic
- affiliate redirect or deeplink flows

## Risk assessment

### Primary risk removed

- live runtime no longer sends repeated unsupported requests to:
  - `POST /publisher/conversions/search`
  - `POST /conversions/search`
  - `GET /publisher/conversions`
  - `GET /conversions`

### New / residual risks

- if `dognet_api_list_conversions_all(...)` is absent or broken at runtime, totals route can still return `502`
- fallback path may be heavier than a true page API, but is safer than invalid probing during incident response
- cached totals may mask immediate verification unless cache is bypassed or allowed to expire

## Decision log

- Chosen approach: approved legacy touch in a single existing MU runtime file
- Reason: fastest safe way to stop live partner-facing noise without introducing speculative Dognet endpoint variants
- Rejected approach: trying additional undocumented Dognet conversions endpoints during incident response

## Rollback

- git-level: `git revert <commit>`
- file-level: restore previous `wp-content/mu-plugins/impactshop-rest-totals.php`
- operational note: rollback would re-enable the invalid endpoint probing and is therefore not recommended unless paired with a corrected API implementation

## Required verification

### Before deploy

- `php -l wp-content/mu-plugins/impactshop-rest-totals.php`
- `bash scripts/git-health-check.sh`

### After deploy

- call `GET /wp-json/impactshop/v1/totals?...` on a representative date range
- confirm route still returns rows/meta or, if failing, a single contained fallback error instead of endpoint bursts
- verify logs or partner confirmation that `conversions/search` traffic stopped

## Manual UI checklist

- Open a frontend or admin screen that shows NGO/shop donation totals
- Check that totals still render or at minimum fail gracefully without PHP warnings
- Compare one known NGO/shop aggregate with pre-hotfix expectation
- Watch for empty totals blocks, repeated loading spinners, or 502-backed broken widgets

## Deploy notes

- Deploy through `bin/impactshop-guard-deploy.sh`
- Use an explicit incident reason in the guard deploy command
- After production deploy, ask Dognet to confirm the burst has ceased or verify from access/application logs
