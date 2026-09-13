# Protected change record — Sharity NGO preference v4.4 G2

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/sharity-ngo-preference-v4-4-g2-governance-terra-plan-2026-09-13.md#8-luna-implementation-chunks",
  "operatorApprovalRef": "operator-approval:sharity-ngo-preference-v4-4-g2-20260913",
  "protectedPaths": [
    "config/dev-v4/activation-policy.v1.json",
    "config/dev-v4/repo-capabilities.v2.json",
    "docs/bastion-guard-status.md",
    "scripts/dev-v4-admission.mjs",
    "scripts/worktree-task-start.sh"
  ],
  "rollbackNote": "Revert the single G2 governance merge; no runtime, provider, schema, secret, or remote state was changed.",
  "smokeTags": [
    "governance:phase0",
    "capability:php",
    "capsule:identity",
    "deploy:guard-preflight",
    "deploy:checksum-verify"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->

## Scope and protected files touched

G2 changes governance only: the activation policy, capability registry,
base-owned admission evaluator, canonical task-start capsule writer, their tests,
and the required documentation/continuity records. The protected paths are:

- `config/dev-v4/activation-policy.v1.json`
- `config/dev-v4/repo-capabilities.v2.json`
- `docs/bastion-guard-status.md`
- `scripts/dev-v4-admission.mjs`
- `scripts/worktree-task-start.sh`

No `wp-content/` file, owner-grant runtime, provider adapter, database, schema,
secret, deployment, VPS, scheduler, or production flag is changed.

## Coherence and risk analysis

The two future selectors are exact-file allowlists for the S4 profile consumer
and the S5 additive activity adapter. They require a plan ID and a dynamically
verified PHP capability. The candidate cannot use its own new selectors or
capability declarations: admission reads policy and capability data from the
immutable capsule base and blocks if either active control script differs from
that base.

The main regression risks are widening a selector, trusting PATH or candidate
self-attestation, accepting an unsafe/stale PHP executable receipt, or weakening
existing selectors. Tests pin the existing selectors, reject broad roots and
wildcards, bind the capsule to repo/path/branch/HEAD/tree, and cover PHP binary,
version, extension, lint, fixture, identity, scope, digest, and TTL failures.

Affected functions and interfaces:

- `marker()` validates the canonical capsule and narrowly accepts a matching
  legacy `current.branch` during transition.
- `validatePhpBinary()`, `validatePhpObservation()`,
  `evaluatePhpCapability()`, and `validatePhpReceipt()` implement the dynamic,
  base-bound PHP decision.
- `admit()` validates the capability schema, detects active-control drift, and
  enforces required capabilities and exact write surfaces.
- `scripts/worktree-task-start.sh` writes canonical top-level branch identity
  and `current={head,tree,recorded_at}` without embedding DocSync authority.
- The S4/S5 activation-policy entries expose only their named future files.

## Smoke and manual checklist

G2 smoke scope is `governance:phase0`, `capability:php`, `capsule:identity`,
`deploy:guard-preflight`, and `deploy:checksum-verify`. The two deploy-guard
tags are required because the task starter is protected by that smoke group;
they validate the guard itself and do not authorize or perform a deployment.
Automated coverage includes clean/fresh admission, private
context isolation, active-control drift, selector regression, and the secure PHP
probe. Because G2 has no UI or runtime, browser smoke is not applicable here.
S4 must separately verify keyboard operation, focus return/trapping, visible
labels, screen-reader state, profile-panel layout, error announcements, and the
existing 2x4 quick-menu layout before any profile UI release.

## Rollback

Rollback is a protected Git revert of the single G2 governance merge, followed
by exact-main readback. No data or remote rollback applies because the package
does not mutate provider, deployment, WordPress, VPS, registry, schema, secret,
cron, watchdog, or production state.

## Evidence state

- Base: `origin/main@f18bb61e09a5e0dade3e5b5cae9b5a84865f4a2c`
- Base tree: `a7f075f5f5d8c498b935d2a5577916d365184f88`
- Candidate content digest (all G2 paths except this record and the continuity
  record, using `git diff --binary` against the fixed base):
  `5fb059da2b23b5c43fe61f20244a87a6d07027b8201190acec7b95b5ce3436ae`
- State: source-only, not deployed, not activated; external mutation count `0`.
