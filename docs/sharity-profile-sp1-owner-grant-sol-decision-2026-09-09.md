# Sharity profile SP1 — owner-grant security decision

Status: source-reviewed; repo-local protected-source admission approved;
publication and live acceptance pending.

Plan/session identity: `sharity-profile-sp1-20260909`; reviewed historical
checkpoint: `e577270`; publication source commit with identical runtime bytes:
`6f7aa97`.

## Source-continuation approval

Operator approval reference:
`operator-approval:sharity-profile-sp1-source-continuation-20260909`.

This approval is limited to exact-identity, source-only continuation in the
dedicated `impactshop-notes` Sharity Profile worktree and to the repository's
own protected-source admission lane. It does not authorize provider, VPS,
database, staging, production, push, PR or merge effects. The `ai-agent`-only
`worktree-shared-deps.sh check --node-only` command is not applicable here and
must not be run from any sibling repository or worktree.

## Decision

`impactshop_pseudo_id` is an identifier, not authentication. Possession or manual setting of that client-readable cookie must never authorize sign-in-code generation, rotation, nickname/profile mutation, or another security-sensitive account action. WordPress REST nonces remain CSRF defense only; they are not profile-ownership proof for logged-out pseudo users.

The identity service will issue a separate 256-bit random device ownership grant:

- Cookie: `__Host-impactshop_owner`; `Secure`, `HttpOnly`, `Path=/`, no `Domain`, `SameSite=Strict`.
- Server storage: HMAC-SHA-256 of the grant only, in an additive `{$wpdb->prefix}impactshop_identity_device_grants` table.
- Binding: each row binds one grant hash to one HMAC-normalized pseudo ID, with `created_at`, `last_seen_at`, `expires_at`, `revoked_at`, and version fields. Index the grant hash and active grants by pseudo/expiry.
- Lifetime: 365-day sliding inactivity window; explicit logout or account switch revokes the current device grant. Expiry cleanup is request-driven; no cron is required.
- Authorization: one central helper validates the owner cookie, grant status, expiry, and pseudo binding. Client-provided pseudo ID or pseudo cookie alone is never authority.

## Issuance and migration

- New identity: persist the profile and owner-grant row before setting either browser cookie. Failure is fail-closed and must not leave a usable orphan identity.
- Existing v2-owned device: a valid owner grant authorizes code creation or explicit rotation.
- Existing legacy identity: successful pseudo ID + legacy code verification lazily migrates the code hash and mints a new owner grant for that device.
- Existing cookie without a valid owner grant: remains readable for continuity but is `legacy_unverified`; it cannot generate or rotate a code or perform protected mutations.
- Lost legacy code: there is no safe automated recovery because no email, passkey, or verified external identity exists. The UI must say this plainly; pseudo ID alone is insufficient.
- Account switch: revoke the current grant before binding the browser to the newly authenticated profile.

## Protected operations

Code generation/rotation requires all of: valid owner grant, exact pseudo binding, same-origin request validation, CSRF token bound to the owner session, rate limit, and explicit rotation confirmation when a code already exists. The public `identity/refresh-nonce` endpoint and a generic logged-out `wp_rest` nonce cannot satisfy ownership.

The first correction must cover `identity/code/generate`, nickname/profile update, and account-switch handling. Before production acceptance, the central grant check must also protect other profile-mutating points, vote, vacation, saved-offer, push, and reward paths currently trusting `impactshop_pseudo_id` directly. Read-only display may continue to use the pseudo identifier where no confidential data is exposed.

## Rollout and rollback

1. Add the grant table and verifier as backward-compatible, feature-flagged source.
2. Enable grant issuance for new identities and successful legacy-code sign-ins.
3. Validate migration and protected mutations on staging.
4. Enable enforcement only after backup/restore proof and operator approval.

After the first v2 grant or migrated hash is written, rollback may disable new UI/enforcement flags but must retain the v2 verifier and data. Do not drop the table or revert to pseudo-cookie authority.

## Required tests

- Manually setting another pseudo ID without its owner grant cannot create or rotate a code.
- A valid grant paired with a different pseudo ID is rejected.
- New identity creation persists a bound grant before cookies are issued.
- Legacy code sign-in migrates the hash and issues a grant without exposing the plaintext code.
- Unknown pseudo and wrong code return indistinguishable responses; rate limits remain enforced.
- Revoked, expired, replayed, or account-switched grants fail closed.
- Cross-origin mutation and generic logged-out WordPress nonce reuse are rejected.
- Owner tokens never appear in JavaScript, REST bodies, URLs, logs, analytics, or normal HTML.
- Concurrent rotation leaves one active code hash and does not resurrect an old grant.

References:

- WordPress REST cookie authentication is defined for logged-in WordPress users and uses `wp_rest` nonces for CSRF: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- Logged-out WordPress nonce users have user ID `0` unless explicitly customized: https://developer.wordpress.org/reference/hooks/nonce_user_logged_out/
