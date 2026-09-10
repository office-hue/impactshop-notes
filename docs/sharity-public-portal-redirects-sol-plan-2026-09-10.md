# Sharity public portal redirects — Sol plan

Date: 2026-09-10

Status: implementation-ready; source publication and live acceptance pending

## Sharity public portal redirects

The operator explicitly requested that these two live URLs lead to the public
Sharity home page and then instructed Codex to continue:

- `https://app.sharity.hu/adomany-automata-portal-1/`
- `https://app.sharity.hu/adomany-automata-portal-2/`
- destination: `https://sharity.hu/`

Approval reference:
`operator-approval:sharity-public-portal-redirects-20260910`.

Package identity:

- repository: `office-hue/impactshop-notes`;
- worktree: `/Users/bujdosoarnold/Developer/GitHub/.worktrees/impactshop-notes-feat-redirect-donation-portals-20260910`;
- branch: `feat/redirect-donation-portals-to-sharity-20260910`;
- base: `origin/main@67d066aef5f2f3bc4ca6d440cdccd8ae822aace9`;
- base tree: `30e810cb4cc17f61b3587f7a022b4a54c65bcba4`.

## Verified pre-state and ownership

Read-only live evidence showed both routes returning WordPress `301` to
`https://app.sharity.hu/?impact_event_auction_embed=1&slug=jovonkvize-2026`.
WordPress REST identifies the published legacy pages as post IDs `14937` and
`14997`. The current redirect owner is the protected
`impactshop-ngo-guides.php`; its local and production SHA-256 is
`0162ed8bc6a575c5638423e6f42e1081e3c1573ace64394ce19ddd1935683f85`.

## Coherence, risk and implementation decision

The existing guide owner also serves unrelated NGO guides, reports, static
assets and JSON routes. Editing it would enlarge rollback and regression scope.
The implementation is therefore a new additive MU-plugin at
`template_redirect` priority `1`, ahead of the legacy priority `10` handler.

The handler is fail-closed:

- exact production host `app.sharity.hu` only;
- exact two paths, with optional trailing slash;
- GET and HEAD only;
- admin, REST and AJAX excluded;
- fixed, query-free destination;
- temporary `302` plus no-cache headers for reversible cutover.

No cookie, pseudo ID, query parameter, campaign value, donation, auction,
identity, point, vote, reward, affiliate or settlement data enters the redirect.
No database/schema, cron, Cronos or watchdog change is needed.

Primary risk is an overly broad route match. Exact positive and negative runtime
fixtures cover host, path, method and protected request surfaces. The existing
guide source must remain byte-identical to `origin/main`.

## Release and rollback

Source publication uses one feature push, one PR and one green-only squash
merge. Production release is permitted only from clean `main == origin/main`
through the repo-owned exact-file CAS path with expected pre-state `absent`.
The release must create one `0444` file under the existing `0555` MU-plugin
directory, with backup/manifest and PHP lint evidence.

Rollback is the exact release-ID and deployed-SHA bound rollback command. For a
new-file install it removes only the additive plugin; the unchanged legacy
handler immediately resumes the previous 301 behavior. No data rollback exists.

## Required verification

- PHP syntax and focused runtime matrix;
- protected inventory/config/hash/checksum parity;
- protected-touch, commit-lane, full validation and maximum bastion;
- necessary repository tests, `git diff --check`, docsync and continuity;
- staging/pre-release no-write evidence where the route host boundary permits;
- after production: both routes return exact query-free `302` to
  `https://sharity.hu/`, the destination returns `200`, unrelated guide and
  Impact surfaces remain available, and remote mode/hash match the release;
- manual browser check in a private window because an earlier `301` may be
  cached by existing browsers.
