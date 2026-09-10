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

## Terra re-QA of Luna remediation

The remediation adds useful owner-grant, subject, PKCE, route metadata, table-name,
and CAS helpers, but it does not close functional QA. Status remains
`luna-remediation-required`.

### QA-L3 — one-time and preference mutation integrity remains incomplete

- CSRF records are keyed by the raw browser token rather than a token hash.
- A live authorization code is consumed only after all client/redirect/PKCE checks
  pass. An invalid verifier can therefore be retried against the same code; the
  exchange is not atomic single-use on an attempted redemption. The issue helper
  also accepts an arbitrary non-empty code rather than a bounded 256-bit code
  representation.
- CAS accepts any non-empty supplied revision and NGO ID. It does not require the
  current catalog revision or a `selectable` result, so it cannot return the
  required stale-revision or `selection_required` outcome.
- Idempotency records only the key and storage key, so the same key with different
  request material is treated as a replay. Audit records omit before/after NGO,
  revision, and outcome fields required for an append-only decision trace.
- The five names and route metadata are useful declarations, but there is still no
  schema descriptor or fail-closed handler contract connecting auth, code/session,
  and preference operations to those declared endpoints.

### Required bounded Luna correction

Within the existing allowlist, change the pure contracts and tests to prove:

1. hashed CSRF storage, bounded code representation, and atomic consume-on-attempt
   code redemption for wrong PKCE/client/redirect as well as replay/expiry;
2. a catalog-aware write gate accepting the current revision and selection result,
   returning `stale_revision` or `selection_required` before mutation;
3. request-fingerprinted idempotency with a stable prior result only for an equal
   request, plus append-only audit tuples containing before/after NGO, versions,
   revisions, outcome, and time; and
4. pure schema/endpoint descriptors that declare the five records and connect each
   endpoint to a fail-closed handler contract without registering or activating any
   WordPress route.

The PHP execution blocker remains separate: no lint or hermetic PHP evidence can be
claimed until a PHP-capable environment is explicitly admitted. No Sol decision is
needed for these corrections.

## Terra re-QA after integrity hardening

The hardening closes the raw-token, attempted-redemption, stale-revision, and
request-fingerprint findings. Functional QA remains blocked by the missing PHP
runtime, and the following source-contract gaps keep status at
`luna-remediation-required`.

### QA-L4 — authority binding and exact data contract remain incomplete

- `sharity_ngo_pref_issue_code()` validates only the callback URI shape; it does
  not receive or enforce the exact `(client_id, redirect_uri)` registry. The prior
  allowlist helper is therefore disconnected from code issuance.
- Redemption verifies PKCE but has no confidential-BFF admission input/contract.
  A future endpoint could invoke it for an unauthenticated caller.
- Preference mutation accepts arbitrary scope and a caller-supplied `selectable`
  string instead of deriving it from the master row plus global policy. It cannot
  enforce the exact permitted scopes or make the source the selection authority.
- Table descriptors contain names and write modes only, not the required record
  keys/columns. The audit tuple still has one `catalog_revision` rather than explicit
  before/after revisions required by the approved decision.

### Required bounded Luna correction

Within the existing allowlist, add and test:

1. exact registry admission during code issue and a fail-closed confidential-BFF
   admission parameter during code redemption;
2. an exact scope allowlist and a mutation entry point that derives selection from
   master/policy input rather than trusting a `selectable` string;
3. schema descriptors with the unique/key fields for all five logical records and
   audit `before_revision` plus `after_revision`; and
4. negative fixtures for unregistered callback, unauthenticated BFF, wrong scope,
   inactive/missing policy, and the amended audit shape.

No activation, real client/secret, policy data, schema execution, provider, deploy,
or protected runtime touch is permitted. PHP lint and hermetic execution remain a
separate environment admission blocker.
