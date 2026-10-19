# Protected change record — pre-push policy parity R1

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/dev-plans/impactshop-prepush-policy-parity-r1-2026-10-19.md",
  "operatorApprovalRef": "operator-approval:impactshop-prepush-policy-parity-r1-20261019",
  "protectedPaths": [
    "scripts/git-health-check.sh",
    "docs/pr-policy.md",
    "docs/bastion-guard-status.md"
  ],
  "rollbackNote": "Revert the single source merge. The package changes no shared hook, product, provider, runtime, credential or production state.",
  "smokeTags": [
    "governance:hook-policy-parity",
    "governance:repo-local-isolation",
    "governance:sibling-memory-denied"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->

## Protected files touched and scope

The protected files touched are `scripts/git-health-check.sh`,
`docs/pr-policy.md` and `docs/bastion-guard-status.md`. The executable change is
limited to the local pre-push health-policy check; the two documents keep its
authority and evidence contract synchronized.

## Coherence and affected behavior

The generated pre-push hook has intentionally been repo-local since the
multi-active continuity package. `scripts/install-hooks.sh` already implements
that contract and remains unchanged. This change aligns
`scripts/git-health-check.sh` and `docs/pr-policy.md` with the generator:

- strict push audit, safe-repo audit and worktree continuity remain required;
- `Memory-ID` remains commit-message metadata;
- sibling `ai-agent` discovery and external `memory:*` execution are forbidden;
- the health check fails if those forbidden patterns reappear.

The fail-closed matcher also rejects direct Node/package-manager execution in
the generated hook and direct `../ai-agent` or `/ai-agent/` path references, so
the boundary is not limited to one `npm --prefix` spelling.

No WordPress, point, affiliate, NGO, provider or deployment behavior is
affected.

## Risk analysis and controls

The relevant risk is a false-green parity check that misses a restored
cross-repository dependency, or a fixture that writes into the real shared hook
directory. The disposable test therefore owns an absolute `core.hooksPath`,
asserts all generated hooks remain below it, compares a bounded source/shared
hook snapshot before and after, requires an untouched-hook health PASS, and
requires independent `npm`, `pnpm` and direct `node` sibling cases to produce
the exact fail-closed diagnostic.

The fixture never executes the generated pre-push hook and performs no network,
push, provider, build, deploy or runtime action.

## Evidence and post-merge checks

- `bash -n scripts/git-health-check.sh scripts/install-hooks.sh tests/install-hooks-policy-parity.test.sh`: PASS;
- `bash tests/install-hooks-policy-parity.test.sh`: PASS;
- positive result: `WARN=0 FAIL=0`;
- negative result: `WARN=0 FAIL=1` with the exact sibling-memory diagnostic;
- `scripts/install-hooks.sh`: inspected, unchanged;
- final protected-touch, affected-check, candidate review and publication
  evidence remain required before merge.

There is no manual UI checklist: this package has no user interface or runtime
surface. After merge, a new disposable fixture run is sufficient; installing
hooks into a shared checkout is explicitly not part of acceptance.

## Rollback

Rollback is a normal Git revert PR for this source package. No live or data
rollback exists because the package performs no external mutation.
