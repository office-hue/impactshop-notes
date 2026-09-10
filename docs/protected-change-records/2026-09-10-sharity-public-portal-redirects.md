# Sharity public portal redirects — protected change record

Date: 2026-09-10

Status: production-accepted

## Approval and scope

The operator explicitly requested both retired donation-portal URLs to redirect
to `https://sharity.hu/` and instructed Codex to continue. The linked Sol plan
records the exact live pre-state, package identity and approval reference.

## Protected files touched

- `wp-content/mu-plugins/impactshop-sharity-public-portal-redirects.php`
- `docs/impactshop-protected-files.json`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-guard-config.sha256`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `docs/bastion-guard-status.md`

## Coherence and affected functions

Directly affected:

- `template_redirect`, priority `1`;
- only the two named `app.sharity.hu` GET/HEAD routes;
- the protected inventory/digest and `public_portal_redirects` smoke group.

Unchanged:

- `impactshop-ngo-guides.php` and its guide/report/static asset subtree;
- the two legacy WordPress page records;
- the donation and auction embed/API/runtime;
- admin, REST, AJAX, callback, profile, identity-return, points, votes, rewards,
  affiliate, settlement, imports, sync, cron, Cronos and watchdog.

## Security, risk and rollback

The destination is a fixed constant and no request-derived value reaches the
Location header. Exact host/path/method allowlists and protected-surface
exclusions keep the change narrow. A `302` avoids creating a new permanent
browser cache commitment while the earlier 301 may still remain in old browser
caches.

Production is exact-file CAS only from merged exact main, expected-before
`absent`, with remote backup/manifest, PHP lint, atomic install and `0444`
relock. Rollback removes only the new file using the release ID and deployed
SHA; the unchanged legacy redirect resumes. There is no database rollback.

## Functional and manual smoke scope

Automated and live checks:

- both portal paths, with and without trailing slash;
- query input is discarded from Location;
- exact destination status and final `200`;
- wrong host/path, POST, admin, REST and AJAX do not redirect;
- existing NGO guide, JYSK report, Impact Challenge and profile routes remain
  unchanged/readable;
- remote SHA and `0444` mode match the exact release receipt.

Manual UI checklist:

1. Open both original URLs in a private Safari/Chrome window.
2. Confirm the final address is exactly `https://sharity.hu/` and the public
   Sharity home page renders.
3. Confirm there is no intermediate auction/Jövőnk Vize screen.
4. If a normal old tab still opens the auction embed, clear that site's cached
   redirect; the previous server response was a cacheable 301.

## Production acceptance

- Source: PR #192, merge `7e6f3c8094cc4060324b011e6e4f14d3323c6016`.
- Guard-hash parity repair: PR #193, exact deployed main
  `77e345ebfdcfb3331e443e3af31876b77699a123`.
- Release ID: `sharity-portals-20260910-77e345eb`.
- Deployed SHA-256:
  `b525a44e6efc7b00e915be5f06b16bdddb8585545f70567e8ac390ba5f82c7b9`.
- Remote release manifest reports `deployed` and mode `0444`; the MU-plugin
  parent remains `0555`.
- Both exact portal URLs return `302` with `Location: https://sharity.hu/` and
  resolve to final `200`; a query input is discarded.
- `/ngo-guides/`, `/jysk-riport/`, `/impact-challenge/` and `/profil/` each
  remained `200` after release.
- Rollback remains exact release-ID and deployed-SHA bound; no database or
  shared runtime rollback is required.

## Protected source admission

This manifest covers every protected endpoint in the exact
`origin/main..HEAD` candidate. Full validation is private base/HEAD/tree-bound
evidence and grants no automatic provider or production authority.

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "operatorApprovalRef": "operator-approval:sharity-public-portal-redirects-20260910",
  "planRef": "docs/sharity-public-portal-redirects-sol-plan-2026-09-10.md#sharity-public-portal-redirects",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "docs/impactshop-guard-config.json",
    "docs/impactshop-guard-config.sha256",
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "docs/impactshop-protected-files.json",
    "wp-content/mu-plugins/impactshop-sharity-public-portal-redirects.php"
  ],
  "rollbackNote": "revert the exact redirect checkpoint and remove only the additive MU-plugin through the release-ID and deployed-SHA bound rollback",
  "schemaVersion": 1,
  "smokeTags": [
    "route:adomany-automata-portal-1",
    "route:adomany-automata-portal-2",
    "destination:sharity-public-home",
    "route:ngo-guides",
    "route:jysk-riport",
    "route:impact-challenge",
    "route:profil",
    "deploy:guard-preflight",
    "deploy:checksum-verify"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->
