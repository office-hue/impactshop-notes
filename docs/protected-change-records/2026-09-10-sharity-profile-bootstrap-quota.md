# Sharity profile bootstrap/quota — protected change record

Date: 2026-09-11

Status: source-only Luna checkpoint; no publication or live activation

## Scope and candidate binding

This publication candidate is the complete source-bearing range from
`origin/main@dd0a19eecfdeb021ed312b5a836f14ff64af0a6e` through
`f3a5fb3e27e840de07c14c570eeef7bf789718b7` on branch
`fix/sharity-profile-bootstrap-owner-grant-luna-20260910`. The exact candidate
base is `dd0a19eecfdeb021ed312b5a836f14ff64af0a6e`; the earlier remediation
checkpoint within that range is `9df5c1269f9a6cc9f7ad9924518676bda064df32`.
The exact source-bearing head/tree are `f3a5fb3e27e840de07c14c570eeef7bf789718b7` /
`9505d904e68bb5c49ddaa1c5aa3e4a72a2c25fd5`. The earlier `9df5c126` source
checkpoint is included in that full range. Governance-only follow-up commit
`6cf300bb547da540e0107ada9d015fd11499520f` (tree
`f1d89cdea782d58476be448e696d83905849e02f`) is identified separately; this
record's correction is also governance-only and does not invent a
self-referential final head. No sibling worktree, shared dependency, provider,
database, VPS or runtime state is used.

The runtime scope is exactly two protected files: `impactshop-boot.php` and
`impactshop-identity-panel.php`. The guard-hash and bastion metadata paths below
are the required integrity companions for those two runtime files.

## Protected files touched

- `docs/bastion-guard-status.md`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `wp-content/mu-plugins/impactshop-boot.php`
- `wp-content/mu-plugins/impactshop-identity-panel.php`

## Invariants and risk

- Query REST (`rest_route`) and pretty REST under a subdirectory never trigger
  the shared init/HTML automatic cookie-touch bootstrap; the earlier
  `impact_pseudo_id` query compatibility branch remains first and unchanged in
  behavior. This exclusion does not disable the public
  `GET /impact/v1/identity/profile` handler: when that handler has no cookie,
  it intentionally owns exactly one common owner issuance and therefore uses
  the same prune/quota path as other public automatic issuers.
- Each issuance transaction prunes only expired `state='pending'` rows,
  bounded to 64 and indexed by state/expiry. Active and nonexpired pending
  rows are preserved.
- Public automatic issuance locks at most 257 eligible pending rows and refuses
  at 256, committing successful pruning without creating a token, cookie or
  request-global issuance marker. Recovery restore explicitly bypasses only
  this public quota while retaining common pruning and supersession.
- The bounded locking read accounts for overlapping concurrent issuers under
  the normal InnoDB isolation contract. A non-default isolation level remains
  a staging verification gate; this is bounded concurrency control rather than
  an absolute cross-isolation ceiling claim. No IP, forwarded-header trust,
  cron or watchdog state is introduced.

## Rollback and smoke scope

Rollback is a source-only revert of this exact checkpoint before publication;
retain the v2 grant table/verifier and do not perform live schema rollback.

Smoke tags:

- `route:profil`
- `route:impactshop`
- `flow:owner-grant-two-request-activation`
- `flow:owner-grant-pending-quota`
- `flow:owner-grant-prune-compensation`
- `runtime:identity-cookie-touch`
- `runtime:rest-query-exclusion`
- `flow:legacy-pool-visibility`
- `flow:message-popup`
- `flow:points-jump`
- `flow:profile-return-account`
- `flow:profile-return-restore`
- `flow:go-deal`
- `flow:saved-offers-open`
- `route:factlens-vb-prod`
- `route:impact-challenge`
- `browser:chrome`
- `browser:webkit`

No push, PR, merge, deployment, database/schema execution, OPcache, cron or
watchdog action is part of this record. Staging schema/browser/API acceptance
remains pending.

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/sharity-profile-summary-live-closure-luna-continuity-20260910.md#protected-source-admission",
  "operatorApprovalRef": "operator-approval:sharity-profile-summary-live-closure-20260910",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "docs/impactshop-guard-hashes.json",
    "docs/impactshop-guard-hashes.sha256",
    "wp-content/mu-plugins/impactshop-boot.php",
    "wp-content/mu-plugins/impactshop-identity-panel.php"
  ],
  "rollbackNote": "Revert this exact source checkpoint before publication; retain the v2 grant table and verifier and perform no live schema rollback.",
  "smokeTags": [
    "route:profil",
    "route:impactshop",
    "flow:owner-grant-two-request-activation",
    "flow:owner-grant-pending-quota",
    "flow:owner-grant-prune-compensation",
    "runtime:identity-cookie-touch",
    "runtime:rest-query-exclusion",
    "flow:legacy-pool-visibility",
    "flow:message-popup",
    "flow:points-jump",
    "flow:profile-return-account",
    "flow:profile-return-restore",
    "flow:go-deal",
    "flow:saved-offers-open",
    "route:factlens-vb-prod",
    "route:impact-challenge",
    "browser:chrome",
    "browser:webkit"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->
