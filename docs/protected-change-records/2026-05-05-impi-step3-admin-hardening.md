# Impi Step 3: Admin Capability Gating Hardening (2026-05-05)

**Commit:** b6fb7da1  
**Branch:** impi-step3-scoped  
**Files:** wp-content/mu-plugins/impact-community{.php,-app.php}

## Change Summary

Implemented authoritative capability gating for Impi Step 3 (ask/image_generation/marketing_copy modes).

## Risk Assessment

- **Threat Model:** Runtime config check (`IC_IMPI_LEGAL_REVIEW_URL/TOKEN`) now gating modes
- **Fail-Closed:** Default all modes disabled until capability endpoint responds OK
- **Nonce Coherence:** GET requests now send nonce (fixed `method !== 'OPTIONS'`)
- **Security:** Sanitized reason messages (no config key exposure)
- **Rollback:** Revert both files via hotfix; no DB changes

## Validation

- ✅ PHP lint
- ✅ VS Code error check
- ✅ Guard: protected-touch, commit-lane, safe-repo-audit
- ✅ Functionality: capability endpoint + fail-closed state

## Smoke Scope

- Load NGO admin Impi interface → capability check succeeds
- Environment: `IC_IMPI_LEGAL_REVIEW_URL/TOKEN` set → modes enabled
- Environment: `IC_IMPI_LEGAL_REVIEW_URL` unset → modes disabled with reason
- Test network failure scenario (endpoint unreachable) → all modes disabled

## Deployment

Suitable for immediate deployment to staging + production.

---
Protected-Change-ID: impi-step3-admin-hardening
