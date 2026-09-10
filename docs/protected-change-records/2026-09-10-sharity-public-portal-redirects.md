# Sharity public portal redirects — protected change record

Date: 2026-09-10

Status: implementation-ready; source publication and live acceptance pending

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
