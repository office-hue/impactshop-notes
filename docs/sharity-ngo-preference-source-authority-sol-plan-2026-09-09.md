# Sharity NGO preference Package B — source authority Sol decision

Status: `re-qa-approved-for-sol-source-publication`; source-only publication,
activation and deployment remain separate.

Operator approval for this exact source-only publication instruction:
`operator-approval:sharity-ngo-preference-source-publication-20260910`.

Plan/session ID: `sharity-ngo-preference-source-authority-20260909`

Identity: `impactshop-notes`, branch
`feat/sharity-ngo-preference-source-authority-terra-20260909`, exact base
`origin/main@073f2854d4e4bc01ad928636125b7a18dc43efa0`.

## Scope

Package B may add a default-off WordPress source adapter that owns authenticated
subject derivation, opaque web sessions, profile/context NGO preferences, current
catalog validation, compare-and-set updates, and audit records. It must provide the
private source endpoints required by the later `ai-agent` BFF package.

This decision grants no push, PR, merge, staging, production, database migration,
secret provisioning, provider, cron, or watchdog authority. Shopping, Offerwall,
Commitments, VB2026 activity writers, and UI integration remain later packages.

## Existing authority and protected perimeter

- `impactshop_identity_owner_authorized()` is the only admitted browser profile
  ownership proof. `impactshop_pseudo_id` and WordPress nonces are not identity.
- The `__Host-impactshop_owner` cookie is host-only and cannot be forwarded to a
  different Sharity host. The later BFF therefore needs an authorization-code
  exchange; browser-supplied profile IDs are forbidden.
- `sharity_ngo_catalog.sharity_ngo_id` is the numeric NGO identifier. Master active
  state and the separate global preference policy must be checked at every
  preference write and resolve; campaign selectability is not global authority.
- VB2026 selection, the legacy slug selector, votes, points/rewards/fund state, NGO
  Card, and Hatás Körök remain separate truth sources.
- Existing protected runtime files are not edited. The implementation must be an
  additive disabled module plus tests and mandatory protected/bastion records.

## Authentication decision

The source adapter uses an OAuth-style, first-party authorization-code bridge:

1. A browser navigates to a source-origin authorization endpoint with an exact
   allowlisted `client_id`, `redirect_uri`, opaque `state`, and PKCE challenge.
2. The source validates HTTPS, the current pseudo cookie, the bound unrevoked owner
   grant, same-origin intent, and a purpose-bound CSRF token.
3. The source stores only a hash of a 256-bit one-time code, bound to the owner-grant
   hash, subject key version, client, redirect URI, PKCE challenge, and a maximum
   60-second expiry. It redirects only to the exact registered BFF callback.
4. The BFF exchanges the code server-to-server using its confidential client secret
   and PKCE verifier. The exchange is atomic and single-use.
5. Only the BFF receives a 256-bit opaque bearer token. The browser receives it only
   as the BFF's Secure, HttpOnly, SameSite cookie; source redirects and JavaScript
   never contain the bearer.
6. Bearer sessions expire after 15 minutes, have no refresh token in v1, and are
   revoked when their linked owner grant is revoked or no longer matches.

Authorization codes may appear only in the single callback request. Source/BFF logs
must redact query strings and request bodies on these routes. State is verified by
the BFF; code replay, redirect mismatch, PKCE mismatch, or client mismatch returns an
indistinguishable denial and consumes no session.

### Exact issuer-origin policy

The authorization-code issuer has one canonical source origin per environment: the
normalized origin tuple derived from `home_url('/')` (scheme, host, and effective
port). It must be HTTPS. Production and staging each use their own exact origin;
there is no shared multi-host list, suffix match, wildcard, or production/staging
fallback.

Authorization is split into a non-mutating GET and an issuing POST. GET validates
the exact registered BFF client and redirect URI and establishes the authorization
intent. POST may issue a code only when the `Origin` header exactly equals the
canonical source origin and a purpose-bound, one-time CSRF token matches the current
owner grant and immutable request parameters. A missing `Origin` fails closed; the
issuer does not fall back to `Referer`.

The additive module must implement a new, narrowly scoped exact-origin check. It
must not use `impactshop_identity_request_same_origin()` as its sole origin or CSRF
control because that helper intentionally accepts multiple Sharity hosts. BFF
callbacks use a separate per-environment registry of exact `(client_id,
redirect_uri)` pairs; callback registration never authorizes a browser origin.

## Subject and key decision

The preference subject is `v1:` plus a base64url HMAC-SHA-256 of the normalized
pseudo ID using a dedicated source-held key. It is not the owner-grant hash salt and
is never derived in the browser or BFF. Raw pseudo/profile identifiers are forbidden
in new preference/session/audit tables, responses, public health, and logs.

Missing keys fail closed. Key provisioning and rotation are separate Sol operations.
A future rotation must use explicit dual-read/single-write key versions and a
measured migration; silently changing the key is forbidden.

## Data authority

The additive module owns five logical tables:

- web authorization codes: hashed code, owner-grant hash, subject, client/redirect,
  PKCE challenge, expiry and consumed timestamp;
- web sessions: hashed bearer, subject, owner-grant hash, scope, issued/expiry and
  revoked timestamps;
- global preference catalog policy: numeric NGO ID, global selection flag, policy
  state/version/hash and timestamps, independent of campaign flags;
- NGO preferences: `(subject, scope_key)` unique, numeric NGO ID, catalog revision,
  monotonic version and timestamps, where `scope_key` is `profile_default` or one
  of `impact_shopping`, `offerwall`, `commitments`, `vb2026`;
- append-only preference audit: operation ID, subject, scope, before/after numeric
  NGO/version/revision, outcome, timestamp and non-sensitive actor class.

No raw token, authorization code, client secret, pseudo ID, slug, provider ID,
financial value, vote, point, or reward is stored in these tables.

Every mutation requires an idempotency key and expected version. The source performs
catalog validation and compare-and-set in one transaction; conflict returns `409`
with the current non-sensitive version. Preference changes are prospective only.
Activity owners must persist immutable `(ngo_id, catalog_revision)` snapshots; this
service never rewrites historical attribution.

## Global preference catalog policy and revision

Global NGO preference eligibility is a new truth source, separate from every
campaign. The additive module owns a `sharity_ngo_preference_catalog_policy` table
keyed by numeric `sharity_ngo_id`, with global `allow_user_selection`, policy state,
policy version/hash, and update timestamp. It joins the master
`sharity_ngo_catalog` only for the current numeric row, master active state, and
source row identity. It never reads `sharity_ngo_campaign_flags` to decide global
preference eligibility.

The same global eligible set applies to `profile_default`, `impact_shopping`,
`offerwall`, `commitments`, and `vb2026`; a scope changes the stored preference, not
which NGO is globally selectable. An empty or uninitialized policy exposes zero
selectable NGOs and resolves to `selection_required`. No row is populated or
promoted implicitly from VB2026, another campaign, the legacy selector, or a slug.
Initial policy population and activation are a later explicit Sol data operation,
outside this source-only package; the disabled module may define schema/routines but
must not execute a live migration.

The source catalog revision is a versioned SHA-256 digest of sorted normalized
tuples containing numeric NGO ID, master active state, master source-row hash, and
global policy selectability, state, and version. Campaign fields are excluded. The
digest algorithm/version is fixed in code and recorded with the preference. A write
must supply the current revision and fail with `409` if stale. Inactive, deleted, or
globally unselectable choices return `selection_required`; no fallback, legacy slug
promotion, campaign promotion, or VB2026 implicit promotion is allowed.

## Source endpoints

All routes are under `/wp-json/sharity/v1` and return `Cache-Control: private,
no-store`. Error bodies never distinguish unknown subject, invalid token, revoked
grant, or expired session beyond a common authorization failure.

- authorization-code issue and token exchange endpoints as defined above;
- `GET /identity/web-session/summary` — bearer-only minimal subject state;
- `POST /identity/web-session/revoke` — bearer or authenticated BFF client;
- `GET /ngo-preferences` — bearer-only default, overrides, effective states and
  current catalog revision;
- `PUT /ngo-preferences/{scope_key}` — bearer-only CAS mutation with numeric NGO ID,
  current revision, expected version, and idempotency key.

CORS is not an authentication mechanism. Token exchange and preference APIs are
server-to-server/BFF paths; browser-origin mutation is rejected.

## Fail-closed rollout and rollback

The first implementation is `impactshop-sharity-ngo-preference-source.php.off`.
It may contain schema and route code but WordPress cannot load it. Tests must prove
the `.off` state, exact route/auth contracts, no provider calls, no legacy writer
reuse, and no protected-file changes. Enabling, schema execution, secret injection,
staging, or production requires a later Sol release decision with backup/restore and
browser/database E2E evidence.

Rollback before activation is file removal/revert. After any schema or v1 subject is
written, rollback may disable routes but must retain tables, verifier, audit, subject
key version, and data; destructive schema rollback is forbidden.

## Required implementation evidence

1. Static and PHP syntax tests for the disabled additive module.
2. Tests for owner-grant mismatch/revocation, exact redirect allowlist, PKCE, code
   replay/expiry, bearer expiry/revocation, and response/log redaction.
3. Tests for exact scopes, numeric ID, master-active/global-policy-selectable state,
   empty policy, campaign independence, revision mismatch, CAS conflict, idempotent
   retry, audit append, and no fallback.
4. Protected inventory/digest parity proving no existing protected runtime file
   changed; update `docs/bastion-guard-status.md` and a protected change record for
   the new perimeter module.
5. Repo-local full validation, continuity, `git diff --check`, and a clean checkpoint.

## Handoff

Next model: `gpt-5.6-terra`, high reasoning. Terra must independently audit this
revision. The two prior blockers now have explicit decisions: a single exact
HTTPS issuer origin per environment with a purpose-bound one-time CSRF token, and a
new global preference catalog policy independent of campaign flags. Terra must
verify those decisions against the existing owner-grant and catalog source, define
the exact file allowlist and test matrix, and approve Luna only if no protected-file
edit or unresolved security/data decision remains.

## Source-publication CI reconciliation

The current-main reconciliation exposed a CI-only false block: after a full
checkout the workflow fetched the already present PR base with `--depth=1`, which
created a shallow boundary and made the commit-lane guard inspect unrelated old
history. The bounded publication correction removes that shallow refetch and only
fetches the exact base when the commit is genuinely absent. It changes no required
job name, admission rule, runtime, provider or deploy authority.
