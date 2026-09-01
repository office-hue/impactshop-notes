# Impi source-owner context — 2026-08-23

## Scope

This checkpoint adds one additive, default-off WordPress MU-plugin:
`impact-community-impi-source.php`. It exposes only a service-authenticated
`GET /impact/v1/internal/impi/circles/{circle_id}/context` projection for the
Impi shadow service. The browser/session Hatás Körök routes remain unchanged.

The operator approved the dedicated Impi-only credential and the pilot names
Tamási and Győztesek Egyesülete. Numeric circle IDs and the secret are runtime
configuration and are intentionally absent from Git. The 30-day value is a
maximum raw-context retention bound; it is not a 30-day activation delay.

## Contract and safety

- `IMPACT_IMPI_COMMUNITY_SOURCE_ENABLED` must be explicitly true; default is
  disabled and the route therefore remains absent in normal runtime.
- `IMPACT_IMPI_COMMUNITY_SOURCE_TOKEN` is a separate, minimum 64-character
  credential compared with `hash_equals`; no legacy Impi/profile credential is
  accepted.
- `X-Sharity-Impi-Request-Id` is required and bounded. No token, body, query,
  actor, membership or profile value is logged.
- Missing or invalid service authorization and request IDs return the same
  `404 context_not_found` response as an unavailable context. Anonymous callers
  therefore cannot distinguish whether the internal route exists.
- The response contains only active circle name/description, shadow mode,
  empty topic allowlist, up to 24 redacted activities and a deterministic
  summary. Author hashes, memberships, points, badges, votes, rewards,
  donations, financial fields and raw metadata never cross the boundary.
- No write SQL, publication route, browser fallback, cron or watchdog hook is
  introduced. The existing ai-agent receipt guard remains the sole scheduler
  owner.

## Validation and rollback

Run:

```bash
php -l wp-content/mu-plugins/impact-community-impi-source.php
php -d assert.exception=1 tests/impact-impi-source-context.test.php
python3 scripts/impact-impi-source-bastion-audit.py
git diff --check
```

Rollback is a guarded removal of this new MU-plugin and its policy/test/docs
files only. Existing `impact-community.php`, identity/profile, points, votes,
reward, donation, Offerwall and VB2026 surfaces are not part of the rollback.
No live secret provisioning or deploy occurred in this checkpoint.

## Production anonymous-disclosure closure — 2026-09-01

The first guarded production activation proved authenticated `200` responses
for pilot circles 276 and 278, but also exposed that unauthenticated requests
returned `401`. The live authority requires anonymous `404`, so the two
authorization failure branches now fail closed as `context_not_found`/`404`.
The route remains GET-only and the token, response schema, pilot allowlist,
redaction, retention and no-writer contracts are unchanged.
