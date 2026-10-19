# Impactshop pre-push policy parity R1

Status: `approved-draft`. This is a bounded source-only governance correction.

Plan ID: `impactshop-prepush-policy-parity-r1-20261019`

<!-- DEV-DELIVERY-V2-MANIFEST
{
  "schemaVersion": 3,
  "contractId": "dev-v4.plan",
  "planId": "impactshop-prepush-policy-parity-r1-20261019",
  "repo": "impactshop-notes",
  "selectorId": "dev-governance-source",
  "identity": {
    "taskId": "impactshop-prepush-policy-parity-r1-20261019",
    "branch": "fix/impactshop-prepush-memory-gate-parity-r1-20261019",
    "codeBase": "f6e0e54ba0cb761fdd71b2c3bd3137edc3124a5a",
    "policyCommit": "f6e0e54ba0cb761fdd71b2c3bd3137edc3124a5a"
  },
  "decision": {
    "state": "approved",
    "summary": "Restore parity between the repo-local generated pre-push hook and git-health-check without reintroducing sibling-worktree memory or dependency execution."
  },
  "authorization": {
    "source": "task-request",
    "taskRequestDigest": "65b1e1f2f899319394d0dab4cfa0c15fa4094685e3dafc6eeb0300cc250bc02f"
  },
  "scope": {
    "changeImpact": "governance-only",
    "riskTier": "high",
    "releaseProfile": "source-only",
    "changeAllowlist": [
      "scripts/git-health-check.sh",
      "scripts/install-hooks.sh",
      "tests/install-hooks-policy-parity.test.sh",
      "docs/pr-policy.md",
      "docs/dev-plans/impactshop-prepush-policy-parity-r1-2026-10-19.md",
      "docs/protected-change-records/2026-10-19-impactshop-prepush-policy-parity-r1.md",
      "docs/continuity/dev/2026-10-19-impactshop-prepush-policy-parity-r1.md",
      "docs/impactshop-notes-doc-sync-map-2026-06-23.md",
      "docs/impactshop-governance-system-plan-2026-06-16.md",
      "docs/bastion-guard-status.md",
      "notes.md",
      "system-status-snapshot.md"
    ],
    "documentationTargets": [
      "docs/pr-policy.md",
      "docs/protected-change-records/2026-10-19-impactshop-prepush-policy-parity-r1.md",
      "docs/continuity/dev/2026-10-19-impactshop-prepush-policy-parity-r1.md",
      "docs/impactshop-notes-doc-sync-map-2026-06-23.md",
      "docs/impactshop-governance-system-plan-2026-06-16.md",
      "docs/bastion-guard-status.md",
      "notes.md",
      "system-status-snapshot.md"
    ]
  },
  "validation": {
    "profileId": "affected",
    "recipePolicyCommit": "f6e0e54ba0cb761fdd71b2c3bd3137edc3124a5a"
  }
}
DEV-DELIVERY-V2-MANIFEST -->

## Goal, acceptance and boundaries

The generated `impactshop-notes` pre-push hook has intentionally been
repo-local since the multi-active continuity package: it must not discover or
execute a sibling `ai-agent` checkout, external Node cache or memory command.
`scripts/git-health-check.sh` and `docs/pr-policy.md` still describe the removed
cross-repository memory integration and therefore report a false failure after
a correct hook installation.

The accepted end state is source-only parity:

- `scripts/install-hooks.sh` continues to generate a repo-local pre-push hook;
- `scripts/git-health-check.sh` verifies the required local audit, lane and
  continuity controls and fails if the generated hook regains a sibling memory
  dependency;
- an isolated disposable Git fixture installs the hooks and executes the health
  check without modifying the shared hook directory;
- policy and DocSync documentation describe the same contract;
- no product, WordPress, provider, runtime, credential, shared dependency,
  shared hook, push, PR, merge or deploy mutation occurs in this package.

## Traceability

| Condition | Authority | Operation | State or risk | Resolving file/package | Verification |
| --- | --- | --- | --- | --- | --- |
| Exact task identity | Git and task-start marker | all local writes | verified | this plan | HEAD/tree/capsule readback |
| Governance ownership | base-owned `task-selectors.v1.json` | source edit | plan/bind required | `dev-governance-source` | Phase 0 readback |
| Repo-local hook isolation | multi-active continuity plan and checkpoint | hook generation | verified intended behavior | health check and fixture | negative sibling-dependency assertion |
| Health-check parity | `git-health-check.sh` versus generated hook | local pre-push readiness | currently inconsistent | `scripts/git-health-check.sh` | installed-fixture health PASS |
| Shared hooks/worktrees | user boundary | implementation | hard no-touch boundary | disposable fixture only | shared hook hashes unchanged |
| Product/runtime behavior | task scope | release | not applicable | none | no product/runtime path changed |
| Push/PR/merge | release profile | publication | future boundary | separate publication preflight | not performed here |

## File-level work

1. `scripts/git-health-check.sh`
   - remove the obsolete required `memory:gate` and advisory
     `memory:sync-pr` text checks;
   - add a fail-closed check for the forbidden sibling-memory patterns already
     excluded by the multi-active continuity contract;
   - retain strict audit, safe-audit and worktree-continuity checks unchanged.
2. `tests/install-hooks-policy-parity.test.sh`
   - create a disposable repository with a private hooks directory;
   - set a fixture-owned absolute `core.hooksPath`, assert every generated hook
     resolves below that directory, and compare a bounded outside-fixture
     filesystem snapshot before and after installation;
   - copy only the required repo-local scripts and policy fixtures;
   - run `install-hooks.sh`, inspect the generated hook, then execute
     `git-health-check.sh` and require the untouched hook to return zero with a
     zero-failure summary;
   - append a representative forbidden `AI_AGENT_REPO` plus
     `npm --prefix ... memory:gate` command to that fixture hook, rerun the same
     health check, require nonzero exit and the exact repo-isolation FAIL
     diagnostic;
   - prove both outcomes without network or shared hook mutation.
3. `docs/pr-policy.md` and the DocSync/continuity records
   - remove the stale claim that repo-local hooks execute sibling memory jobs;
   - preserve `Memory-ID` commit-message compatibility as metadata only;
   - record rollback as a normal Git revert of this source package.

`scripts/install-hooks.sh` is in the allowlist for parity inspection and only
changes if the fixture exposes a real generator defect. No modification is
planned merely to create a diff.

## Failure and recovery

The historical lifecycle start failure
`retained_engine_not_accepted_by_repository_policy` reached two failed attempts.
It was subsequently fixed in the distributed central dispatcher at source
`94ee04a309843ccec991fa55fd7f8c01a6124e90`; dispatcher digest
`9b4e1d4e7b6c9869ccaf3323fd34c7971e2b87666dcef80b56fb62d6ae384109`
was read back locally. The canonical task was then started successfully from
the primary checkout with the repo-accepted retained descriptor, without
editing policy or lifecycle state. The source package does not modify that
central DEV fix.

The first bind attempt after the canonical restart returned
`bound_plan_authority_mismatch` once. Readback showed that only the previous
fallback task-request digest differed from the canonical lifecycle digest.
This plan now binds the controller-projected digest
`65b1e1f2f899319394d0dab4cfa0c15fa4094685e3dafc6eeb0300cc250bc02f`;
no product scope, allowlist or acceptance condition changed.

If the fixture fails twice with the same identifier, stop for a focused cause
analysis and short repair plan. Test artifacts remain under a private temporary
directory and are removed on exit. Rollback is an exact source revert; no
runtime or data rollback exists.

## Validation and completion

Applicable checks:

- `bash -n scripts/git-health-check.sh scripts/install-hooks.sh tests/install-hooks-policy-parity.test.sh`;
- `bash tests/install-hooks-policy-parity.test.sh`;
- the existing multi-active continuity fixture only if the implementation
  changes `install-hooks.sh` or invalidates its isolation contract;
- base-owned Phase 0 admission for the exact changed paths;
- protected-touch/commit-lane checks selected by the repository;
- `git diff --check` and DocSync/continuity readback.

Unrelated product, PHP, provider, staging and full WordPress suites are visible
risk-review advisories but not applicable to this shell-policy-only delta.
There is no runtime availability, latency, capacity or cost envelope because
the package changes only local developer checks; the measurable acceptance is
deterministic fixture completion with no network and zero shared-hook changes.

The source candidate may later use one push, one PR and one merge only after
the current release-operations profile, provider-proof, exact candidate CAS,
actual PR-body and required-check readbacks. This plan grants none of those
external operations.

## Planning review

The first independent Terra/medium read-only review was bound to draft digest
`719b1332202a78ad4b85962bce73150c816e038da2107a8572750a587875408a`
and returned `REVISE`. Its three findings are incorporated above: fixture-owned
`core.hooksPath` plus outside-write proof, an explicit untouched-hook PASS, and
an injected sibling-memory command that must produce an exact nonzero isolation
FAIL. The review performed no test, edit, provider or external mutation.

The follow-up independent review verified revised draft digest
`21845b43ace89ad6961af69551ee6e1ca4b339b99661fc051765d22c6069d73c`
and returned `PASS` with no remaining P0/P1 planning issue. This is planning
evidence only; implementation and test results remain unclaimed.

After canonical lifecycle restart, an identity-only follow-up review verified
plan digest `91b0a718d86922fedf1b4fcee24d63585fe61d3705f4bc5c1a8ff5cdc3555d47`
and returned `PASS`. It confirmed that the canonical task-request digest update
does not change the allowlist, source-only boundary, fixture acceptance or
hook-isolation requirements. No P0/P1 issue remains.
