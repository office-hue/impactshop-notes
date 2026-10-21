# Notes risk-scoped DEV — T1 compatibility source

Status: original source committed; native v3 continuity repair implemented and
independently reviewed, final client 86/86 and legacy fixture PASS. Local repair
checkpoint and central exact T1 path addition precede native publication.
Not a push, merge, installation, activation or production acceptance.

<!-- DEV-DELIVERY-V2-MANIFEST
{
  "schemaVersion": 3,
  "contractId": "dev-v4.plan",
  "planId": "dev-risk-scoped-notes-20261020",
  "repo": "impactshop-notes",
  "selectorId": "dev-governance-source",
  "identity": {
    "taskId": "dev-risk-scoped-notes-20261020",
    "branch": "dev/risk-scoped-notes-20261020",
    "codeBase": "73beb6f4598acdca22e7910d39a0088cd5f38b14",
    "policyCommit": "73beb6f4598acdca22e7910d39a0088cd5f38b14"
  },
  "decision": {
    "state": "approved",
    "summary": "Implement the explicitly requested exceptional central DEV target adapter, retaining old task pins and active v1 policy until staged engine rollout; source only."
  },
  "authorization": {
    "source": "task-request",
    "taskRequestDigest": "43dcac432fc6a8f8abf1960136bf112bdc51a70013d8638705b164ead61ca9e9"
  },
  "scope": {
    "changeImpact": "governance-only",
    "riskTier": "high",
    "releaseProfile": "source-only",
    "changeAllowlist": [
      "scripts/dev-v4-lifecycle-client.mjs",
      "scripts/worktree-continuity-guard.sh",
      "tests/dev-v4-lifecycle-client.test.mjs",
      "tests/dev-v4-admission.test.mjs",
      "docs/dev-plans/dev-risk-scoped-notes-2026-10-20.md",
      "docs/protected-change-records/2026-10-20-dev-risk-scoped-notes.md",
      "docs/continuity/dev/2026-10-20-dev-risk-scoped-notes.md",
      "docs/dev-v4-lifecycle-installed-controller-impactshop-notes-2026-09-27.md",
      "docs/impactshop-governance-system-plan-2026-06-16.md",
      "docs/impactshop-notes-doc-sync-map-2026-06-23.md",
      "docs/bastion-guard-status.md",
      "notes.md",
      "system-status-snapshot.md"
    ],
    "documentationTargets": [
      "docs/dev-v4-lifecycle-installed-controller-impactshop-notes-2026-09-27.md",
      "docs/impactshop-governance-system-plan-2026-06-16.md",
      "docs/impactshop-notes-doc-sync-map-2026-06-23.md",
      "docs/bastion-guard-status.md",
      "docs/protected-change-records/2026-10-20-dev-risk-scoped-notes.md",
      "docs/continuity/dev/2026-10-20-dev-risk-scoped-notes.md",
      "notes.md",
      "system-status-snapshot.md"
    ]
  },
  "validation": {
    "profileId": "affected",
    "recipePolicyCommit": "73beb6f4598acdca22e7910d39a0088cd5f38b14"
  }
}
DEV-DELIVERY-V2-MANIFEST -->

## Native protected source admission

Source-approval reference: `operator-approval:dev-risk-scoped-notes-source-20261020`.
This names Arnold's already approved implementation of this exact Notes T1
package, whose installed task-request digest is in the manifest above. It does
not reuse a commit exception, the central publication exception or a host grant.
Push/PR and merge still need their own current operation authority; the adapter's
`sourceMergeAdmission` is validation evidence, not a provider or deployment permit.

Pre-publication diagnosis on source HEAD `99023e6780aaad6a64d282115eaa35b3178c4a7d`:
the native maximum-bastion read returned `operator-review`, with
`protected-change-record-exact-admission-missing` and
`full-validation-evidence-missing`. No CI was started and no blind repair tried.
The existing source record lacked the schema-1 block that the unchanged native
adapter and CI already require. The exact protected path set is only
`docs/bastion-guard-status.md`, `scripts/dev-v4-lifecycle-client.mjs` and
`scripts/worktree-continuity-guard.sh`.

Repair: connect the existing protected record to this heading and source approval,
with that exact set, the existing rollback and smoke scope; update continuity,
notes and status only. Executable, test, policy, hash-lock and hook bytes stay
unchanged. Commit through native hooks, rebind the same task through the supported
client, then run the native `full-validate` and read-only `bastion` on the resulting
clean commit. These check per-commit lanes and protected-source metadata; they do
not rerun the unchanged 86/86 client suite or authorize a live operation.
The resulting evidence must be real, private and tied to the exact new HEAD/tree;
this document itself does not assert that those checks passed.

The central finite scope extension is now merged as PR1702, source commit
`48cac5e319c2748d21397a6c5ecf2c1ccd3022f4`; this updates the historical pending
scope notes below, without migrating Notes policy/engine pins or granting a
central exception to this repo.

## Goal and existing source authority

Arnold explicitly requested continued implementation of the central DEV change
on its exceptional separate source route. This is the Notes target portion of
that existing task, not a product/WordPress task or a new central framework.
The manifest carries the real installed controller task-request digest, not a
manually invented approval. Code writes require a clean committed plan anchor
and successful supported `bind`; maintenance-docs alone does not authorize them.

Parent source: `office-hue/ai-agent`, branch `dev/risk-scoped-20261020`, candidate
`d329593dfd831c97dd3b505eb1d70cbae8f88df8`, tree
`68186498f3280e34a7d390b56d568ef3fa300919`. Parent plan:
`docs/dev-plans/dev-central-exclusive-write-surfaces-risk-scoped-20261020.md`.
This target starts at Notes commit `73beb6f4598acdca22e7910d39a0088cd5f38b14`,
tree `0f7aff058cc3f2579dabc7580cd470ea49794682`. No moving-main synchronization
is needed for these local source edits.

## Smallest functional T1 change, file by file

1. `scripts/dev-v4-lifecycle-client.mjs`: preserve the existing installed fixed
   dispatcher and its verification, argument allowlist and timeout. A bound
   task delegates engine selection to the dispatcher, preserving its exact pin;
   no installed-default replacement or manual capsule/state edit. For unbound
   `start`, require the canonical primary main checkout, resolve the locally
   known `refs/remotes/origin/main` once without fetching, and select the exact
   accepted retained-engine canonical descriptor from that committed policy,
   never HEAD or candidate working-tree JSON. Bound reads must not depend on
   current candidate contract contents. Resolve real Git metadata paths; an
   unsafe state or orphan lifecycle directory is not an unbound task. Reject ambiguous,
   missing or unsafe identities, stale-policy mismatch, descriptor conflicts,
   malformed output and unknown/duplicate arguments. The historical controller
   fetches internally and revalidates against its actual fetched base before
   creating a worktree/state. It has no expected-base argument: this adapter
   selects compatibility, not proof that the two snapshots are identical.
   Incompatible drift must fail closed there. Actual starts need a new narrow
   quiet window; this local source package runs no real start. Do not probe multiple
   engines or retry with the global default. Expose only `context --render` as
   the additional read-only Secretary view, retaining refresh/consume syntax.
   Errors must not echo arbitrary controller output or credentials. Existing
   test root/data-root seams are not new public arbitrary-command authority.
2. `tests/dev-v4-lifecycle-client.test.mjs`: hermetic temporary Git/dispatcher
   fixtures prove unbound selection despite mismatching default, candidate
   policy non-authority, exact source/digest, bound-pin preservation, unsafe or
   ambiguous state/descriptor refusal, origin mismatch, narrow context render,
   duplicate/unknown argument refusal and sanitized malformed output. Fixtures
   must not fetch, use real installed state, call models, install hooks or touch
   sibling worktrees. Validate the dispatcher argv, not invented controller
   success. Synthetic responses are fixture evidence only.
3. `tests/dev-v4-admission.test.mjs`: register/import the focused fixture so the
   existing CI suite covers it; do not change workflows or unrelated product
   assertions. Do not make host PHP a dependency of the focused new tests.
4. Installed-controller guide: document descriptor selection, bound-task
   behavior, exact recovery instructions, Secretary absent/unavailable versus
   retrieved state, and T1/T2 separation.
5. Governance plan, DocSync map, Bastion status, notes and status snapshot:
   cross-link this exact plan and protected record; record only actually run
   tests and audit findings, never imply installation or completed rollout.
6. Protected record and continuity: preserve identity, goal, authority limits,
   failure ledger, rollback, audit findings and the next unblocked operation.

No active root policy, selector, guard hash, shared hook or wrapper changes are
part of this bounded T1. Hook `requirements` coupling is deferred to compatible
T2 source rather than introducing an old-model blocker. Dormant policy copies
are omitted: the controller reads five existing root policies, so inert JSON
would not activate routing and would add maintenance without functionality.

## Controls, recovery and cost

Hard boundaries remain: foreign repo/worktree, branch/origin/task/base mismatch,
unsafe files, authority/integrity/CAS, native protected/lane guards, actual
security/data-loss/live risk, and existing machine prohibitions. Resolve missing
facts with the supported controller/source repair; do not disable the guard.
Optional memory/context absence and unrelated registered checks are visible
advisories, not fabricated PASS or reasons for global repair/rerun. No actual
5.6 model execution or false 5.6 receipt: use available 6.1 Sol/high for bounded
source/security review and 6 Astra/high only for concrete unresolved complexity.
The old typed review route may remain unverified; exceptional source evidence
does not claim normal lifecycle publication readiness.

Failure ledger: the original default-engine Notes bootstrap refusal was
diagnosed as repo/default mismatch, then resolved once using the accepted
canonical descriptor and supported task-start. New local failure identifiers
start with zero failed repairs. After two failed repairs of one identifier,
write diagnosis plus a short repair plan before another fix. No blind retries.

Rollback: revert this target source commit through normal guarded Git; no task
pin, installed package, global default, runtime, provider or data change needs
reversal. Do not use source revert as a substitute for a future host rollback.

## Five-axis acceptance and validation

- Operability: deterministic private fixture success and one read-only bound
  task/context handoff, without installed mutation or default fallback.
- Functionality: reproduce the mismatch and fix exact descriptor forwarding;
  bound pins and old v1 consumers remain unchanged.
- Coherence: code/test/CI registration/DocSync/Secretary status agree; T1 is
  source compatibility, never final risk-scoped activation.
- Security: committed authority, fixed dispatcher, exact identity, refusal of
  unsafe/ambiguous paths and no raw-output leakage; no gate bypass.
- Risk: private fixtures, bounded subprocesses, no network/shared-hook/provider
  writes; complete source and later host rollback are separate.

Run focused Node22 tests and syntax check, `git diff --check`, native
protected-touch/lane checks applicable to the exact staged paths, and a bounded
independent source review. Reuse unchanged original sequential fresh plan-audit
evidence; review this concrete target design separately, incorporate findings
before implementation, then verify the changed candidate. The existing broad
installed-CLI test is host dependent and is not a substitute for hermetic tests.
PHP/WordPress/browser/provider suites are N/A to this no-product delta, not PASS.
Manual UI checklist: no UI, browser or user-data path is modified; no live smoke
is claimed. Bastion sources and native guards are not weakened or replaced.

## Publication through activation: explicit remaining prerequisites

1. Commit the required documentation identity anchor using native hooks, then
   supported controller bind/readback to `dev-governance-source`; no remote action.
2. Implement only the allowlist, correct independent review findings, run only
   invalidated tests, DocSync and guarded source commit on this branch.
3. Before any push/PR: load current release-operations policy, inspect actual
   provider trigger prestate, exact candidate/check evidence and authorization;
   preserve native hooks. Current source authorization is not a provider permit.
   Source-ready, pushed, PR, merged, installed and accepted are distinct states.
4. Request a new narrow main quiet window only at actual integration/merge.
   Verify exact current prestate/CAS/required checks and target merge witness;
   stop and release the window after bounded main work or unrelated blocker.
5. Complete central S/P plus Notes/Hub/Factlens T1 source roles: four repository
   merge witnesses cover five roles. No unnecessary new publisher framework.
6. Assemble the exact staged release-set. Separately authorized Air install,
   engine-only canary and rollback readiness, then Mini install/canary, must
   precede any routing-schema-2 root cutover. This is ENGINE-STAGE only.
7. Separately approved T2 target-root source changes use fresh source/witness,
   grant and CAS; T1 rights do not authorize them. Preserve predecessor pins.
   Old engines parse only v1; premature root v2 would break fresh task starts.
8. After T2, verify actual target behavior and safe rollback on Air then Mini.
   Only these readbacks establish target activation/acceptance. Never call
   dormant files or the first engine-only canary final activation.

No quiet window is active during this local source work. No paid generation,
dependency install, CI trigger, provider build/deploy or host scan is authorized
by this document. Secretary read-only context may be absent; this continuity
document is not a consumed packet receipt.

## Bounded target design review

Independent `/root/source_review_risk_scope` (actual 6.1 Sol/high) reviewed plan
SHA-256 `b227609ff500b92da474dfb1e3577b55ceae7534a4cef2a93c30e7ca65f37fe5`
and found no material blocker on the five required axes before anchor/bind.
The concrete design observations are incorporated: real Git metadata,
candidate-independent bound reads, local-main selection versus controller
fetched-base compatibility, no fallback, unchanged T1 root policy/hook gates.
Its protected-preimage requirement is recorded in the protected change record.
This is plan review, not implementation acceptance or a typed old-model receipt.

## Implementation closure

The bounded independent five-axis review found three medium issues and then
verified all three fixes: Git replacement objects cannot override committed
policy, safe native JSON reason codes remain actionable without raw disclosure,
and linked fixture metadata cannot redirect writes through ambient Git state.
Final client SHA-256
`6bbffde88ed921d4e439d77bee6a3a07204f9a1b046f12439b76cc3b3be2db04`;
test `f26f3ad207348f4b69138bb096fbc742779e7aed0ed49344a229a1c38904c1e3`.
No material issue remains across the five axes. Evidence is 65/65 before
those source repairs plus 12/12 targeted post-repair cases; no full final-suite
claim. Syntax/diff checks pass. Final new-client real `context --render`
verified engine and `writes:false`, packet absent, lifecycle state unchanged.
Exact evidence and audit/repair ledger are in the continuity document.

Native lane requires separate ops source and protected Bastion-status commits.
That source finalization does not publish or activate the package. Hub/Factlens
task bootstrap is the next main-dependent operation and requires a NEW narrow
quiet window; the former Notes bootstrap confirmation was already released.

Native ops source commit completed with normal hooks:
`abd48f5bc3491c8c9f9df211804800b276358e16`, tree
`0b3c11b588d494660c0cce5420ac1305c3c373d4`; tree readback matched the exact
staged candidate. The separate protected status checkpoint contains no new
executable/policy change. No push/PR/merge/install/activation occurred.

## T1 publication repair — native v3 continuity, 2026-10-21

Failure `notes-native-continuity-v3-marker-gap`: the real installed v3 task is
verified, but the native push guard requires missing schema2 marker/decision
files. No blind repair was attempted. Independent review rejected legacy
`worktree-task-start --resume`: it would mislabel HEAD as origin/main and assign
maintenance-docs, hiding the real source delta. Do not run it or forge a marker.

The approved DEV implementation/release scope requires this compatibility repair
before Notes T1 can publish. Base-owned dev-governance-source already owns both
scripts and tests. This manifest adds exactly one source path, not selector
authority. The central T1 exact target path anchor must separately add this path
before the expanded target can be admitted into the release set.

1. Existing lifecycle client: add only `continuity --mode local|push`. It calls
   the fixed installed dispatcher `status`, never a candidate engine,
   and verifies own safe v3 state before/after the read. Preserve actual task,
   branch, repo, base/codeBase/policyCommit, plan anchor, HEAD/tree, engine pin and
   selector. Compare the installed observation to both the local Git identity
   and pinned state; reject malformed/stale/foreign/unsafe/pending state. Require
   `implementation-ready` policy on this bounded continuity route: `current`
   alone proves identity, not policy readiness. This does not change the separate
   planning/status/context routes or authorize publication. Optional
   Secretary packet absence is not a failure. Push requires a clean worktree.
   Existing conflicting legacy marker is rejected, never overwritten. No marker,
   receipt, state, journal, coordinator, network/provider or shared-hook write.
   Output means continuity only, explicitly not publication/activation authority.
2. Native continuity guard: if its own v3 namespace exists (including unsafe or
   orphan state), call that exact read-only route with the real repo and mode;
   never fall back on an error. Clear the existing test-only data-root override.
   Worktrees with no v3 namespace retain the unchanged legacy path and checks.
3. Existing lifecycle-client tests and registered admission entrypoint: hermetic
   valid v3, wrong task/repo/branch/pin/base/head/tree, dirty push, malformed or
   unavailable dispatcher, changed state during read, conflicting legacy marker,
   orphan/symlink state, and wrapper dispatch/no-fallback/legacy parity. No real
   controller fixture calls, fetches, installs or shared-hook changes.
4. DOCSYNC: installed-controller guide, governance, map, protected record,
   continuity, notes, status and Bastion reflect the exact repair and actual
   checks. Review all five axes before a source checkpoint; keep old test facts
   historical. Central and FactLens are merged, Notes/Hub not yet published.

Rollback is a normal source revert, preserving task pins and all packages. This
repair does not activate root v2, waive native protected guards, authorize a
provider action or export the central publication exception. Native context
success does not claim normal lifecycle publication readiness. After committing,
use supported same-task reconcile/bind as needed; no manual state changes.
