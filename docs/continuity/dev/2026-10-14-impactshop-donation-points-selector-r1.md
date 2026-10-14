# Impact Shop donation points selector R1 — continuity

- task/plan: `impactshop-donation-points-selector-r1-20261014`
- branch: `feat/impactshop-donation-points-selector-r1-20261014`
- worktree: `/Users/bujdosoarnold/Developer/GitHub/.worktrees/impactshop-notes-feat-impactshop-donation-points-selector-r1-20261014`
- code base / policy commit: `e9bfb75a3b9e47e7f9a948597ca36b883c9e25a7`
- plan anchor: `5fae56542d86c7b3c600ea25055887ffb72b75df`
- plan-anchor tree: `c8056e385d142bfdff378e017412b53bd6fb419f`
- lifecycle selector: `dev-governance-source`
- lifecycle status after bind: `implementation-ready`
- capsule: `3327f6bd351307f6a90828f5dfbdd4670a8c32883356681f190ecdd9bafe49b9`
- retained engine source: `8d4db0d08520f5f61eebb7cd7ccd26dcff6380ad`
- retained engine digest: `8487bcc2a112612c42a009afabd5cb8ae2eefc5dcd32801cdfb6b173adb87502`
- planning package: `c261f205e3f1b581d9cae23988498387f6967e909139efde43306e782b537443`
- context/memory: `retrieved`, 8 results, consumed; digest `f9d037ca0eb86d0d94f185dcf23195dcf8427509c9a4ae919b748870542ce3fe`
- external mutations: push/PR/merge/build/deploy `0/0/0/0/0`

## Implemented candidate

The candidate registers the exact, product-specific
`impactshop-donation-points-source-r1` selector in both repo-owned policies.
The two entries are identical except for the Stage-B-only risk ceiling. Their
46-path inventory contains 14 product source paths, 13 targeted test paths and
19 contract/guard/continuity paths. It has no broad root or wildcard.

The CI-owned `tests/dev-v4-admission.test.mjs` now proves exact policy parity,
Plan-ID enforcement, pre-merge self-authority denial, merged-base admission and
file-prefix-collision rejection. The positive fixture creates an actually
listed future product file and stages it. Both an untracked and a staged-only
suffix collision are rejected. Current result: PASS, 20/20. The installed
lifecycle shell test remains unchanged and local-only.

## Failure ledger

- `task-start-unknown-flag`: attempt 1, resolved with installed exact syntax.
- `retained_engine_not_accepted_by_repository_policy`: two task-start attempts
  diagnosed and resolved by the repo-accepted immutable retained engine; no
  state edit or bypass.
- `bound_plan_manifest_missing_or_ambiguous`: attempt 1 before bind, resolved.
- `context_artifact_posture_invalid`: attempt 1 before bind, resolved.
- lifecycle shell observation of
  `retained_engine_not_accepted_by_repository_policy`: attempt 1 in test
  context; candidate-specific shell change removed after diagnosis. This is not
  a product-selector failure and was not reported as PASS.
- first frozen-candidate Sol/high review: `REVISE`, P1 exact-file prefix
  collision. Plan amended and rebound; `writeSurfaceMatch=exact` opt-in plus
  positive listed-file and negative `.attacker.php` fixtures implemented.
- `exact-selector-positive-file-missing`: attempt 1; fixture assumption was
  wrong because the future product file is not on main. Repaired by creating
  the allowlisted file in the hermetic fixture; rerun PASS 20/20.
- first verify-fix result: `still_vulnerable`; staged-only paths were absent
  from the legacy inventory. The second scoped fix adds index paths only for
  exact-mode selectors and extends the fixture to the staged-only bypass;
  rerun PASS 20/20. Second verify-fix on tree
  `57533da1aebe7f027983ba49b295e0b90a74e59e`: `fixed`.
- handbook URL: unavailable/404, advisory; repo-local policy and verified
  planning package are authoritative.

## Next exact step

Freeze the documentation-complete diff, bind the completed verify-fix evidence
to the final tree, then create the candidate commits and checkpoint. Before any push,
load the active release-operations package and perform the current source
publication prestate. No product task may start from this selector until the
governance merge is read back on exact main.

Status: source candidate only; source-ready pending final checkpoint;
source-merged=false; release-ready=false; deployed=false;
user-visible=false; production-accepted=false.
