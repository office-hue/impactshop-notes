# Impi source-owner — SOL source release admission

Status: `approved-for-luna`

Date: 2026-08-23

## 1. Scope and non-goals

This package publishes the already completed, default-off Impi source-owner
adapter through one guarded GitHub source chain: one branch push, one PR and one
merge after required checks. It does not deploy the MU-plugin to WordPress,
provision a secret, resolve production circle IDs or activate the route.

Task classification: max-protected source-release admission. The operator
explicitly requested the next coherent SOL package and minimum push/PR/merge
count.

Falsifiable hypothesis: if `origin/main` remains the exact ancestor of the
tested clean candidate, the PR diff contains only the approved additive Impi
source perimeter, local guards pass and GitHub required checks pass on the
exact head, then one normal squash merge can make the source canonical without
changing production, identity or economic state.

Non-goals:

- no WordPress/VPS/Railway/Vercel/Turso deploy or configuration mutation;
- no secret creation, copying, printing or repository storage;
- no profile, identity, membership, points, badges, votes, rewards, donation,
  finance, Offerwall or VB2026 writer change;
- no Impi publication route, autonomous action or human moderation decision;
- no cron/Cronos/watchdog installation or modification;
- no force push, direct main push, broad sync or automatic deploy retry.

## 2. Context and canonical sources

- Dedicated clean worktree:
  `impactshop-notes-impi-source-owner-20260823`.
- Candidate branch: `feat/impi-source-owner-context-20260823`.
- Exact base: `origin/main@bf439f121fa020243f0f04fcae1e7b0dea4b738a`.
- Candidate before this plan: `1ceb76406b876147c3a6dbcd92e8ea237034c852`.
- Source checkpoints: `59ea0bb2`, `f0c291bf`, `1ceb7640`.
- Source contract: `docs/IMPI-SOURCE-OWNER-CONTEXT-2026-08-23.md`.
- Maximum-bastion evidence:
  `docs/protected-change-records/2026-08-23-impi-source-owner-context.md` and
  `scripts/impact-impi-source-bastion-audit.py`.
- Existing `impact-community.php`, profile-return/identity, Offerwall and
  VB2026 files are outside the candidate diff.

## 3. Acceptance criteria

1. The worktree is clean and the named feature branch has exact
   `origin/main@bf439f121...` ancestry with zero commits behind before push.
2. The range diff contains only the additive Impi plugin, non-secret policy,
   tests, bastion and continuity files already reviewed in the source
   checkpoint.
3. PHP lint, hermetic context/redaction fixture, source maximum-bastion audit,
   `git diff --check` and strict push audit pass on the exact candidate.
4. The plugin SHA remains
   `b86c28139796528b19bc17b43c28c283180c3013b493313d127c358eed8e4029`.
5. No committed value resembles the Impi service token; policy contains only
   the variable name and minimum length.
6. Push uses the existing guarded feature-branch path once. No force push and
   no direct main write are allowed.
7. The PR body records scope, risk, additive protected change, validation,
   rollback, affected-function checks and the manual UI checklist.
8. Merge occurs only after the exact remote PR head equals the tested local
   head and every required GitHub check is successful. Pending, skipped where
   required, cancelled or failed checks block merge.
9. After merge, read-only GitHub evidence proves the merged PR and exact main
   ancestry. No deploy command, provider mutation, feature flag, key, circle ID,
   cron or watchdog operation follows automatically from this plan.
10. Source rollback is a normal revert PR of the merge commit. Production
    rollback is not applicable because this package performs no deploy.
11. Docsync/continuity and the source checkpoint remain available in Git
    history; a final local checkpoint records the release result without
    creating a second source PR.

## 4. Design and file-level implementation plan

No runtime implementation file changes are planned. The release candidate is
the existing source checkpoint plus this admission document.

- `docs/IMPI-SOURCE-OWNER-SOL-RELEASE-ADMISSION-2026-08-23.md`: exact source
  base/candidate, security boundary, tests, PR/merge admission and rollback.
- `notes.md` and `docs/impactshop-governance-system-plan-2026-06-16.md`:
  source-release continuity and the repo-local guard contract before the single
  push.
- GitHub branch/PR: one guarded push, one PR, one squash merge after exact-head
  and required-check verification.
- Canonical main: read-only post-merge ancestry/status verification only.

## 5. Risk, coherence, and security review

- Correctness: the adapter is independently testable and strict-schema
  compatible with the current Impi agent. It remains absent unless explicitly
  enabled at runtime.
- Regression: the candidate is additive. It does not modify the existing
  community route file or any protected identity/economic/VB2026 surface.
- Security: the repository contains no secret or numeric production allowlist.
  The exact plugin hash, no-writer/no-publication rules and 64-character token
  contract are guarded.
- Operational coherence: source merge and live activation are separate. This
  package needs no Cronos guard because it adds no scheduler and does not
  activate a runtime; the existing ai-agent receipt watchdog remains the sole
  future supervision owner.

## 6. QA evidence

| QA | Method | Expected evidence | Result |
| --- | --- | --- | --- |
| QA-1 correctness | PHP lint, hermetic projection/redaction fixture, strict agent contract suite | bounded valid context; unsafe values redacted | PASS |
| QA-2 regression | `origin/main...HEAD` path/range inventory | additive Impi/guard/docs only; no legacy runtime touch | PASS |
| QA-3 security | source bastion, exact SHA and secret-pattern review | no writer/fallback/publication/secret; default-off | PASS |
| QA-4 operational/docs | branch ancestry, clean status, continuity and rollback review | zero-behind feature branch; one source-only release chain | PASS |

## 7. Rollback and observability

Before merge, rollback is branch/PR closure. After merge, rollback is one normal
revert PR of the squash merge; force push and direct main reversal are forbidden.
Because no deploy or activation occurs, there is no runtime, database, secret,
cron or provider state to roll back. GitHub PR checks and merge ancestry are the
only release observability in this package.

## 8. Luna implementation chunks

### Chunk 1 — exact candidate admission

- Files and interfaces: this plan and `notes.md`; existing source checkpoint.
- Preconditions: clean dedicated worktree, unchanged exact main ancestry.
- Exact change: record source-release contract and run the complete local gate.
- Validation: targeted/full Impi tests, PHP lint, source bastion, strict audit,
  docsync/continuity and `git diff --check`.
- Done when: one clean exact candidate commit is ready for remote publication.

### Chunk 2 — single guarded GitHub release chain

- Files and interfaces: feature branch and one GitHub PR.
- Preconditions: Chunk 1 green, exact main unchanged, authenticated canonical
  remote, complete PR checklist.
- Exact change: one push, one PR, wait for exact-head required checks, one
  squash merge, read-only post-merge verification.
- Validation: PR head SHA parity, required checks, merged status and main
  ancestry.
- Done when: the source is canonical on main with no production mutation.

## 9. Handoff decision

This plan is implementation-ready and `approved-for-luna`. The exact branch,
base, candidate, file perimeter, security contract, validation, rollback and
remote-write sequence are fixed. The operator's current instruction authorizes
the minimal normal GitHub source-release chain. Live key provisioning,
WordPress deployment and activation remain separate SOL decisions.
