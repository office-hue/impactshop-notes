# Offerwall hotfix - 2026-03-23

## Scope
- Restored the survey provider chooser on the `Kérdőív` tab so `Sharity`, `CPX Research`, and `AyeT` are visible again.
- Restored CPX/AyeT survey rendering in the shared offerwall flow.
- Kept the article quiz token fallback fix so rewardable internal quiz starts remain valid even if the query token is lost.
- Added the hardened WordPress email proxy MU plugin for signed internal email sending.

## Validation
- Production `impact-challenge` HTML contains the survey chooser and both external survey containers.
- Production article quiz page renders the signed `data-quiz-token` fallback.
- Production email proxy test returned HTTP 200 with `{"sent":true,"count":1}`.

## Deploy and rollback
- Production deploy happened by targeted MU-plugin copy after server-side backup.
- Rollback path is restoring the backed-up MU-plugin files from the pre-deploy backup directory on production.
