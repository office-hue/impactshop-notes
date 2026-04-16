# Protected Change Record: prod-uncommitted-sync

**Date:** 2026-04-16
**Author:** Arnold (via Copilot QA)
**Branch:** hotfix/prod-uncommitted-sync

## Protected files touched

- `impactshop-boot.php` — remove retired CJ helpers (isb_is_retired_shop, isb_parse_csv_assoc, etc.)
- `impactshop-offerwall.php` — __return_true → require_pseudo_id (3 endpoints), CPX HMAC
- `scripts/safe-repo-audit.sh` — add SAFE_REPO_AUDIT_ALLOW_REMOTE_WRITE bypass for false-positive remote-write patterns in docs/notes

## Non-protected files (same commit)

- `impact-community.php` — dev-clone sandbox (/hatas-korok-dev/ route)
- `impactshop-netflix-shortcodes.php` — SECURITY: Dognet creds → env file

## Risk assessment

Functional risk: ZERO — these exact bytes already run in production (MD5 verified via SSH).
This commit only records existing production state in git. No deployment needed.

## Rollback

```bash
git revert <commit-sha>
# No production rollback needed — prod already runs this code
```

## Smoke checklist

- [ ] `~/bin/impactall` after merge to main
- [ ] Verify boot.php CJ endpoint still works
- [ ] Verify offerwall callback endpoints respond
