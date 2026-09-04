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
