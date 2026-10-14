# Protected change record — Impact Shop donation points selector R1

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/dev-plans/impactshop-donation-points-selector-r1-2026-10-14.md#impact-shop-adomanypont-selector-dev-governance-terv",
  "operatorApprovalRef": "operator-approval:impactshop-donation-points-selector-r1-20261014",
  "protectedPaths": [
    "config/dev-v4/task-selectors.v1.json",
    "config/dev-v4/stage-b-activation-policy.v1.json",
    "scripts/dev-v4-admission.mjs",
    "docs/bastion-guard-status.md"
  ],
  "rollbackNote": "Revert the single selector governance merge. No product, database, provider, runtime, scheduler, secret, or production state is changed by this package.",
  "smokeTags": [
    "governance:selector-parity",
    "governance:plan-required",
    "governance:self-authority-denied",
    "governance:write-surface-exact"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->

## Protected files touched and scope

This source-only governance package registers
`impactshop-donation-points-source-r1` in the two repo-owned selector policies.
It permits only the 46 exact product, test and documentation paths already
identified by the audited donation-proportional points plan. There is no
directory prefix, wildcard, provider, deployment, environment, scheduler or
secret path in the selector.

No WordPress product byte is changed. The selector becomes usable only after
this governance source is merged and a new product lifecycle starts from that
merged exact-main base with a bound Plan ID.

## Security and authority boundaries

- lane: `protected`; risk ceiling: `high`; Plan ID required;
- capabilities: `php-runtime`, `git-readback`, `static-node-test`;
- candidate policy cannot self-authorize because admission reads the selector
  from the immutable base Git object;
- all global provider/build/deploy/VPS/runtime/secret/scheduler flags remain
  false;
- the selector grants source write eligibility only, never push, merge,
  provider, release or production authority.

## Evidence

- Plan anchor: `721f4ee5464835c7dc6c5532b93236ef32aaf6e2`;
- plan-anchor tree: `da70d850b881bca98d003b72aaf495baf568aba0`;
- lifecycle capsule before final DocSync closeout:
  `0ae140d24cae5a7749d4ba3f4dfe1dddbeeefc89cec18748e16c6e96f3837ea3`;
- `node --test tests/dev-v4-admission.test.mjs`: PASS, 20/20;
- JSON parse and `git diff --check`: PASS;
- final candidate-bound review: PASS on tree
  `78de9f1e9a5c743ffb27963fa7e6adb1e6d35de1`; the protected policy, script
  and test blobs are byte-identical to the verified fixed candidate;
- lifecycle checkpoint context: `continue`, memory `retrieved` with 8 results;
  its two WIP warnings are recorded advisories, not publication authority.
- the canonical recipe class is `protected` and the base-owned validation
  profile is `affected`; this corrects plan metadata only and does not widen
  the 46-path product selector or any live authority.
- final source review exposed a committed-candidate fixture no-op. The fixed
  fixture now starts from immutable pre-selector base `e9bfb75...`, copies both
  selector policies and the admission script, creates a real synthetic merge,
  and then exercises Plan-ID and exact-path behavior; affected suite PASS
  20/20.

The installed-controller shell smoke was not added to the candidate. Its first
local run stopped before selector discovery on the separately pinned retained
engine policy. The hermetic admission fixtures cover the affected selector
contract without modifying or bypassing that engine boundary.

The first frozen-candidate Sol/high review found one P1 prefix-collision issue:
the legacy matcher treated literal file names as prefixes. The amended plan and
candidate add an explicit `writeSurfaceMatch=exact` opt-in for this selector;
the positive fixture creates a listed future file, while the negative fixture
blocks the same name with `.attacker.php` appended.

That first verification returned `still_vulnerable` because the legacy path
inventory omitted staged-only files. The scoped correction includes the index
only for `writeSurfaceMatch=exact`; the fixture now stages both the legitimate
listed file and, separately, the suffix collision. The affected suite is again
PASS 20/20. The second verify-fix on frozen tree
`57533da1aebe7f027983ba49b295e0b90a74e59e` returned `fixed`; the P1 finding is
closed. This evidence grants no external or live authority.

The first corrected PR run exposed a CI-host dependency in the positive
write-surface fixture: the hardened production PHP probe correctly rejected
the Ubuntu runner's non-exact PHP resolution. The production admission helper
is unchanged. The fixture-only synthetic base now isolates `php-runtime` for
the path-matching scenario, while the real selector contract still requires
it. The exact combined Stage A/Stage B command passes 24/24 locally on Node
22.23.2; remote confirmation remains required.

## Rollback

Rollback is one normal Git revert PR for the governance merge. No data or live
rollback exists because this package performs no external or runtime mutation.
