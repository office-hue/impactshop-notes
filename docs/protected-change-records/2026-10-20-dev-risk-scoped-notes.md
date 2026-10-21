# Notes DEV T1 protected change record

## Végső natív útvonaljavítás — 2026-10-21

Kifejezett Notes-specifikus különutas forráskiadási jóváhagyás és elkülönített
6.1 Sol/high review alapján a continuity guard tracked módja100755 lett.
A módhiba miatt a valódi hook korábban kihagyta a guardot; a tartalma nem változott.
A közvetlen végrehajtás regressziós tesztje1/1 PASS, az installed pre-push
dirty-push negatív canaryja a várt continuity elutasítást adta. Ez megerősíti,
nem oldja fel a natív védelmet. A pozitív végső canary külön szükséges.
Rollback kizárólag normál guarded source revert; nincs shared hook/host/data
visszaírás. A gépi protectedPaths/rollback/smoke kör változatlan marad.

## Native source-admission binding — 2026-10-21

This block expresses the already approved Notes T1 source scope in the existing
repo-native format. It grants no product deploy, host install or central bypass.
Actual push/PR and merge authority remains separate. Only the native adapter may
produce clean-commit validation evidence; the record is not a PASS receipt.

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "schemaVersion": 1,
  "planRef": "docs/dev-plans/dev-risk-scoped-notes-2026-10-20.md#native-protected-source-admission",
  "operatorApprovalRef": "operator-approval:dev-risk-scoped-notes-source-20261020",
  "protectedPaths": [
    "docs/bastion-guard-status.md",
    "scripts/dev-v4-lifecycle-client.mjs",
    "scripts/worktree-continuity-guard.sh"
  ],
  "rollbackNote": "Revert only the Notes T1 source commits through native guarded Git; preserve task pins, retained engines and all live runtime state.",
  "smokeTags": ["dev-t1", "hermetic", "docs-sync", "deploy:guard-preflight", "deploy:checksum-verify"]
}
<!-- END PROTECTED SOURCE ADMISSION -->

The two `deploy:*` tags are the unchanged native `deploy_guard` group's required
scope labels because this package touches `worktree-continuity-guard.sh`. They
are not a claim that production deployment or remote smoke ran. For this T1
source-only change, evidence is the reviewed guard/client hermetic fixtures,
actual read-only installed continuity preflight, checksum comparison against
the reviewed source, and unchanged deploy entrypoints/hash inventories at the
pinned base. Any future live deploy still requires its own full preflight.

The executable client and native continuity guard are both protected source
surfaces for this adapter. Their reviewed hashes and tests remain those in
continuity; the older text below is historical, not an assertion that the guard
is outside the package. The admission-format repair changes documentation only.

## 2026-10-21 continuity repair addendum

Affected function: native v3 continuity readback through the existing target
client; legacy no-v3 behavior is unchanged. Risk: wrongly granting continuity
for blocked, stale, mismatched or unsafe state. Countermeasures: verified
installed status plus independent Git/pin comparisons, explicit ready policy,
no legacy fallback or synthesized marker, clean push and no publication grant.
Final client suite 86/86; legacy fixture PASS; independent five-axis review
identified the readiness assertion and its regression repair. Exact evidence
and reviewer closeout are in continuity. Bastion-status update follows in its
own guarded commit; no guard/hash inventory or product code changes.
Rollback: guarded source revert only, retaining actual task pins and packages.
Manual UI checklist: N/A (no UI touched). Live/install smoke remains pending.

Authority: existing Arnold exceptional central DEV source task; exact plan
`docs/dev-plans/dev-risk-scoped-notes-2026-10-20.md`. Source only.

## Protected files touched

`scripts/worktree-continuity-guard.sh` adds the bounded v3 dispatch ahead of
unchanged legacy checks. Original bytes at plan anchor `59f81c32708f609f35d389c9616cd48a84152aab`:
SHA-256 `04a31f122fcff1b6829fb9bc970f83b6761654bfe7136fa4911fb9d98908c101`.
The reviewed new guard hash is recorded in continuity and must match when
reapplied for the separate protected commit. No guard policy/inventory changes.

`docs/bastion-guard-status.md` receives an additive source-status entry only.
Executable change is limited to `scripts/dev-v4-lifecycle-client.mjs`, with
private tests and DocSync. No protected-file list, hash, WordPress code, hook
generator, shared hooks, active policy or installed controller change.

Exact preimage retained in Git at base
`73beb6f4598acdca22e7910d39a0088cd5f38b14:docs/bastion-guard-status.md`;
SHA-256 `9ae12a0a072db2fa2f3faa7a5896c447b53705eb169271217037fee64182341d`.
It was read back before any protected status edit. No guard hash is regenerated.

## Coherence, risk and function

Fix repo-accepted descriptor selection for fresh start; preserve bound pins,
native lane/protected guards and v1 compatibility. Independent design/source
review and fixture evidence are recorded in the plan/continuity, not forged
controller receipts. Optional Secretary packet absence is advisory.

## Rollback

Normal guarded revert of this target source package. No host/default/task-pin,
runtime, data or provider mutation is part of T1. Future installation requires
its own tested exact rollback inputs and authority.

## Smoke checklist

Node22 hermetic integration 65/65 before audit repairs, then 12/12 targeted
post-repair cases; source/test syntax and diff hygiene PASS. Independent
five-axis review closed three concrete findings; exact source/test hashes and
the real read-only bound Secretary proof are in continuity. No full final-suite
claim. Native local protected-touch and lane checks run at their own commit
boundaries; protected status finalization is separate from the ops source commit.
Manual UI: N/A, no UI/product path changed. No live smoke or production claim.

The protected-status local preflight passed using the existing guarded input
route: this exact change record, explicit source revert rollback note, and
`dev-t1,hermetic,docs-sync` smoke scope. The protected predicate/guard was not
changed or bypassed. The executable source commit had already passed normal
native hooks and exact staged-tree readback separately.
