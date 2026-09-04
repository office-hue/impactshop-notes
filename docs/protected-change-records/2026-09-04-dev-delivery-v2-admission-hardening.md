# DEV delivery v2 admission hardening — protected change record

## Protected files touched

- `.github/workflows/ci.yml`
- `docs/bastion-guard-status.md`

The corresponding adapter, classifier, policy fixtures and continuity files are
the same guarded source-admission lane.  No WordPress, legacy PHP exact-release
engine, product runtime, provider configuration or remote target changes.

## Rollback plan

Before source merge, revert the exact candidate commit(s), re-run the
repo-local full-validation and confirm that no private candidate evidence is
reused across a different base, HEAD or tree.  There is no deploy rollback:
automatic provider/product deploy remains false.

## Smoke checklist

- `deploy:guard-preflight`
- `deploy:checksum-verify`
- Run `bash tests/dev-delivery-v2-adapter.test.sh` and
  `bash tests/dev-context-policy-guard.test.sh`.
- Verify the CI candidate `baseSha`, `headSha`, `treeSha` and
  `candidateTreeSha` are identical to the checked-out candidate.

## Protected source admission

The machine-readable block is the exact source-only admission scope for this
candidate. It covers every protected old/new path endpoint in the base-to-HEAD
diff. Full validation remains evidence only, the protected classification and
operator-review decision remain visible, and deploy-class candidates can never
receive source admission from this adapter.

<!-- BEGIN PROTECTED SOURCE ADMISSION -->
{
  "operatorApprovalRef": "operator-approval:dev-delivery-v2-p0-fix3-20260904",
  "planRef": "docs/impactshop-governance-system-plan-2026-06-16.md#2026-09-04-dev-delivery-v2-p0-convergence",
  "protectedPaths": [
    ".github/workflows/ci.yml",
    "config/dev-delivery-v2-impact-policy.json",
    "config/dev-delivery-v2-target-contract.json",
    "docs/bastion-guard-status.md",
    "scripts/dev-context-policy-guard.sh",
    "scripts/dev-delivery-v2-adapter.sh"
  ],
  "rollbackNote": "revert the exact candidate commits and discard private candidate evidence before source merge",
  "schemaVersion": 1,
  "smokeTags": [
    "deploy:guard-preflight",
    "deploy:checksum-verify"
  ]
}
<!-- END PROTECTED SOURCE ADMISSION -->
