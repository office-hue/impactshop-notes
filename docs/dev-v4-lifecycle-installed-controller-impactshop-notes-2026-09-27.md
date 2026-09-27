# Impactshop notes installed-controller lifecycle — 2026-09-27

## Scope and continuity

This source-only continuation keeps the exact remote-main base and the linked
worktree marker binding correction. The new target-local bundle is limited to
`office-hue/impactshop-notes`; it does not copy the central policy wholesale.

## Authorization and boundary

`operator-approval:dev-v4-linked-worktree-fix-20260927` covers the source-only
adapter and lifecycle contracts. The installed controller is selected by its
verified loader posture and one exact retained engine digest. Product,
provider, build, deploy, VPS, runtime and secret changes remain out of scope.

## Contract decisions

- `normal-source-short-brief` is the normal source selector and accepts an
  adapter-supplied short brief.
- `dev-governance-source` is reserved for DEV and governance surfaces.
- Governance start remains `planning-only`; a plan is bound only through the
  narrow adapter route `bind --task-id <id> --plan-anchor <40hex>` (with an
  exact policy commit only when required).
- `status`, `resume`, context refresh and context consume are read-only
  lifecycle operations; legacy Stage B remains compatible and optional.
- The disposable smoke runs installed CLI start, status, resume, context
  refresh and consume, plus negative engine, origin and governance checks.

## Evidence

`tests/dev-v4-lifecycle-installed-cli.test.sh` passed with the primary tree
hash unchanged and covered planning-only governance start, committed plan
anchor binding, false anchor rejection and extra argument rejection.
`bash -n scripts/worktree-task-start.sh`, `node --check
scripts/dev-v4-lifecycle-client.mjs`, and `git diff --check` are required
focused checks.
