# Impact Shop donation points selector R1 — continuity

- task/plan: `impactshop-donation-points-selector-r1-20261014`
- branch: `feat/impactshop-donation-points-selector-r1-20261014`
- worktree: `/Users/bujdosoarnold/Developer/GitHub/.worktrees/impactshop-notes-feat-impactshop-donation-points-selector-r1-20261014`
- code base / policy commit: `e9bfb75a3b9e47e7f9a948597ca36b883c9e25a7`
- plan anchor: `1f6aecf397aa26cf02803c9705fa94524812f656`
- plan-anchor tree: `e9699c1e258583e9861d153c0899a0b6aebf9512`
- lifecycle selector: `dev-governance-source`
- lifecycle status after bind: `implementation-ready`
- current capsule before the required-check correction:
  `18e5156a35702d908895a5a4324fc0c90a0287c290156d1549daa7edd7c93b47`
- retained engine source: `8d4db0d08520f5f61eebb7cd7ccd26dcff6380ad`
- retained engine digest: `8487bcc2a112612c42a009afabd5cb8ae2eefc5dcd32801cdfb6b173adb87502`
- planning package: `c261f205e3f1b581d9cae23988498387f6967e909139efde43306e782b537443`
- context/memory: `retrieved`, 8 results, consumed; current publish packet
  digest `904a7f...`; no required fact missing.
- active operations package/manifest:
  `d1265ecf6ccdc26e1f9f79c4ef4e9c8d66eee9fe0af6ff0439b57c5243f98c25` /
  `f690fc73e6b6c3b954ec904bd881281814a2971c0a1736340cabe67184221767`.
- external mutations: push/PR/merge/build/deploy `2/1/0/0/0`; PR
  `office-hue/impactshop-notes#219` is open.

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
- `plan_change_impact_underclassified`: attempt 1, resolved by changing the
  plan manifest from `governance-only` to the base-policy-derived `protected`;
  no source scope changed.
- `canonical_recipe_checks_invalid`: attempt 1, resolved by replacing the
  unregistered custom test profile with the base-owned `affected` profile.
- rebind route: one malformed manual SHA produced `git_rev_parse_failed`, then
  the correct SHA showed `legacy_options_require_legacy_state`; diagnosed and
  resolved through the supported existing-state `reconcile --plan-anchor`
  path, with no state edit or bypass.
- final source review found `mergedDonationPointsFixture` cloning the already
  merged candidate, so its synthetic merge became a no-op after commit. The
  first repair pins immutable pre-selector base `e9bfb75...`, copies the two
  selector policies plus admission script, commits the synthetic merged base,
  and updates its fixture-only `origin/main`; affected suite PASS 20/20.
- the first two post-fix test observations hit the 30-second caller ceiling
  without a result. Diagnosis showed no test failure; the same command with an
  explicit longer orchestrator budget completed PASS 20/20 in 28.3 seconds.
- `context_next_refresh_only`: attempt 1, resolved by using `--next` only on
  refresh and consuming the current packet without it.
- `github_api_slurp_jq_incompatible`: attempt 1, resolved by separating GitHub
  pagination from the local JSON projection; no mutation occurred.
- `vercel_personal_scope_cli_invalid`: attempt 1, resolved by an unscoped API
  readback. The only accessible team and all four returned projects were
  fully enumerated; every Git link was null.
- `protected_touch_override_missing`: attempt 1 stopped before remote mutation.
  The same push passed with the change-record, rollback and exact smoke-tag
  bindings already documented in this package.
- `protected-change-record-plan-ref-invalid`: attempt 1 on required GitHub
  check `validate`. Diagnosis: the schema-v1 manifest requires a `#fragment`
  and requires its operator-approval identity to resolve in that plan. This
  correction adds the canonical heading fragment and approval identity; it
  does not change selector scope or live authority.
- `merged-selector-positive-blocked-ci`: attempt 1 on the corrected candidate.
  The protected-record correction passed, but the combined Ubuntu CI fixture
  reached the real `php-runtime` probe and blocked because the hardened
  validator rejects the runner's non-exact `/usr/bin/php` resolution. The
  production validator remains unchanged. The exact-path fixture now removes
  only `php-runtime` from its synthetic base policies, while the preceding
  static contract still requires it on the real selector. Exact combined CI
  command PASS 24/24 locally with Node 22.23.2.

## Next exact step

Reconcile the amended plan, align its anchor evidence, record fresh
candidate-bound correctness and security review, and freeze the CI-hermeticity
repair. Both previously authorized pushes have been used; publishing this new
repair requires one explicit additional corrective-push authority. No product
task may start from this selector until the governance merge is read back on
exact main.

Status at this checkpoint: source-ready pending the CI-hermeticity correction,
current review record and corrective-push authority; source candidate only;
source-merged=false; release-ready=false; deployed=false;
user-visible=false; production-accepted=false.
