# Sharity NGO preference Package B — Terra post-Luna QA

Status: `luna-remediation-required`; not runtime-eligible.

Reviewed identity: `impactshop-notes`, branch
`feat/sharity-ngo-preference-source-authority-terra-20260909`, Luna source commit
`b9c19461fe8d0bbf1e955c87dc327100f84ac5f0`, tree
`65797bc51f6b15f87db1fe14ac45b000a0c394ac`.

## Evidence reviewed

- The new `.php.off` adapter and both new tests.
- Python static contract test: PASS.
- PHP lint and hermetic PHP contract test: not executed because this environment
  has no `php` executable. No dependency installation or alternative runtime is
  authorized or was attempted.

## Blocking implementation findings

### QA-L1 — adapter is a helper skeleton, not the approved source contract

The file contains useful pure helpers and route-name strings, but it does not
implement the source authority described in the approved plan. In particular it
has no owner-grant authorization call, versioned HMAC subject derivation, hashed
one-time authorization-code issue/redeem path, PKCE verifier validation, exact
source-route handlers, session/code revocation storage contract, or effective
`Cache-Control` response behavior. The callback registry accepts exact strings but
does not independently validate a secure callback URI shape.

It also has no preference storage contract for expected-version CAS, idempotency
replay, append-only audit, or the five planned logical tables. A route list and a
catalog digest alone cannot safely service the later BFF package. The `.off` suffix
limits present runtime risk but does not complete the requested source capability.

### QA-L2 — tests do not prove the required contract

The static test checks strings only. The PHP test has useful fixtures for origin,
CSRF, bearer, and catalog helpers, but it cannot exercise the missing code/PKCE,
subject, callback-shape, CAS/idempotency/audit, or route/auth behavior. Its result
is currently unavailable because PHP is absent. Therefore the successful Python
test does not establish the required source-adapter behavior.

## Required bounded Luna remediation

The existing Luna allowlist remains sufficient. Keep the module `.php.off` and
add the missing pure/route contract functions and hermetic tests for:

1. owner-grant admission boundary, versioned HMAC subject derivation, exact HTTPS
   callback URI validation, and code/PKCE issue-and-single-use redemption;
2. expiry/revocation-safe code and bearer records plus no-store error/response
   contract;
3. global-policy catalog resolution plus expected-version CAS, idempotency replay,
   and append-only audit contracts; and
4. the declared five-table/schema routine contract without executing schema or
   registering/activating runtime routes.

No existing MU-plugin, real client/secret/key, policy data, schema execution,
activation, provider, deploy, or protected-file expansion is admitted. This is a
bounded implementation correction, not a new Sol architecture decision. After the
correction, PHP lint and the hermetic PHP test still require a PHP-capable admitted
environment before Terra can give functional QA approval.
