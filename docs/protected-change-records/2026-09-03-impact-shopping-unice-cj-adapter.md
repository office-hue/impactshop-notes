# Impact Shopping UNice CJ adapter — protected change record

Date: 2026-09-03

Status: source-ready; production evidence pending

## Authorized change

The existing `impactshop-sharity-affiliate-runtime.php` gains one default-off
CJ canary. The existing Dognet filter and `impactshop-boot.php` are unchanged.
The new canary is fixed to partner `unice`, program
`cj-5824323-15487360`, NGO `gyoztesek-egyesulete` and Max Click base
`https://www.tkqlhce.com/click-101302202-15487360`.

## Protected files touched

- `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`
- `scripts/sharity-affiliate-runtime-bastion-guard.sh`
- `docs/impactshop-guard-hashes.json`
- `docs/bastion-guard-status.md`

## Authority and privacy boundary

`POST /wp-json/sharity/v1/shopping/cj-intent` requires both an active
`sw_session_*` bearer and the existing Sharity service credential. It reads
only the canonical active session row and its HMAC `subject_ref`; it does not
reconstruct or expose the pseudo identifier. The exact request fixes partner,
provider, program, NGO, disclosure and v2 snapshot namespace.

The intent table schema v2 stores the canonical subject, trusted classifications
and only hashes of the provider and handoff tokens. A random 256-bit `shp1_`
handoff is returned. Its GET route consumes the row transactionally with
`SELECT ... FOR UPDATE`, then emits HTTP 303 to the fixed CJ path with exactly
one `sid=sat1_...` query parameter. Replay, expiry, tuple drift and wrong
credentials fail closed.

The runtime reports correlation only. Purchase, commission and settlement
remain false, and no financial, NGO Card, point, vote, reward or profile writer
is present. The existing daily retention hook is reused; no cron was added.

## Validation and rollback

The source and mutation bastions pin the exact canary, dual-auth session lane,
schema fields, atomic one-use handoff and sole outbound query. Focused PHP lint,
lifecycle, replay, schema-failure and tamper tests pass.

The repository's complete standalone test set also passes: all shell deployment,
rollback, policy and mutation tests; the seven-test Human Touch Python suite;
the Impi source-context PHP test; and the full affiliate runtime fixture. The
legacy WordPress PHPUnit harness cannot start in this worktree because its
external WordPress test library is not installed; this is an existing
environment prerequisite, not a product failure. The affected runtime has a
hermetic WordPress/DB stub suite, and `git diff --check`, readiness and
continuity guards pass on the final source tree.

Production rollout must use the exact merged-main file release with private
backup, CAS and PHP lint. Initial deployment is option-off. Rollback is the CJ
option `0`, followed by the exact-file rollback artifact if needed. Intent data
and retention cleanup are preserved.

Smoke checklist: PHP lint and deployed SHA/permission checks; schema v2 and
option-off admission; Dognet regression; dual-auth CJ intent rejection without
session or service credential; exact 303 handoff without following the provider
redirect; and post-enable retention/watchdog state.
