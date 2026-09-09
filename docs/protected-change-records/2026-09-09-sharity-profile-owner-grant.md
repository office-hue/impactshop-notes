# Sharity Profile owner grant — protected change record

Date: 2026-09-09

Status: source-reviewed; publication and live acceptance pending

## Approval and scope

The operator requested a persistent Sharity profile sign-in available across
the site, clearer account creation/sign-in language, stable profile UI and an
ownership review. Existing identity REST routes, shortcode markup and browser
logic must change together; a parallel plugin would duplicate route and cookie
ownership and is therefore less safe than this explicitly approved bounded
legacy touch.

## Protected files touched

- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `wp-content/mu-plugins/impactshop-identity-panel.js`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `docs/bastion-guard-status.md`

The related non-protected runtime touch is
`wp-content/mu-plugins/impactshop-adsense-head.php`, limited to suppressing the
existing AdSense head injection on the `/profil` subtree.

## Coherence and affected functions

Directly affected:

- `/impact/v1/identity/profile`, `/identity/restore` and the new explicit
  `/identity/code/generate` route;
- the `[impactshop_identity_panel]` and compact identity widget UI;
- owner-grant schema installation, issue, verification and revocation;
- portable-code generation, one-time reveal, v2 hash verification and legacy
  migration;
- nickname/profile mutation and account-switch handling;
- `/profil` and its one-time code-reveal child route.

Unchanged and outside SP1 acceptance:

- points, votes, saved offers, vacation, push and reward writers that still
  need a later central owner-grant migration;
- affiliate, VB2026, Impact Challenge, NGO, financial and settlement logic;
- provider, cron, Cronos and watchdog configuration.

## Security and risk analysis

Pseudo ID and a logged-out WordPress nonce are not treated as ownership proof.
The new 256-bit device grant is Secure, HttpOnly and SameSite Strict; only its
hash and the HMAC-bound pseudo identity are stored. Code issuance and profile
mutation require the matching active grant and same-origin request. Account
switch revokes the prior grant before issuing a replacement. Normal profile
responses never expose the portable code, sign-in errors are generic, and
attempts are rate limited.

Primary risks are schema activation failure, cookie/domain mismatch, legacy
code migration regression and stale grants after account switching. Static
contract tests, the completed Terra security re-review and the required staging
browser/DB matrix cover these risks. Full production acceptance remains blocked
while other direct pseudo-cookie mutation routes are not centrally protected.

## Bastion and rollback plan

The existing identity files stay in the maximum protected inventory and their
candidate SHA-256 values are pinned in the repository guard manifest. Source
publication uses the guarded feature-branch lane. Staging and production may
resume only from clean merged `main == origin/main`; production uses three
separate exact-file release identities with pre-state SHA, backup, double CAS,
PHP lint where applicable, atomic replacement and read-only relock.

Rollback uses those exact release IDs and deployed SHA checks. Database rollback
is forward-compatible: deactivate the new source paths but do not drop the
owner-grant table or erase migrated v2 code hashes.

## Smoke scope and manual UI checklist

Required guard tags:

- `route:profil`
- `flow:profile-return-account`
- `flow:profile-return-restore`
- `flow:points-jump`
- `flow:message-popup`
- `flow:legacy-pool-visibility`
- `route:impact-challenge`
- `route:factlens-vb-prod`

After staging activation, manually verify account creation, persistent refresh,
nickname display, pseudo ID/points/votes, existing-account sign-in, wrong-code
generic failure, account switching, one-time code reveal and `/profil` without
layout movement or AdSense. Also verify the listed profile-return, points,
message, legacy-pool, Impact Challenge and FactLens routes for regressions.

No staging browser/DB smoke or production deploy has run yet.
