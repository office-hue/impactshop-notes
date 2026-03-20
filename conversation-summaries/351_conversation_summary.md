# Summary
- Ran staging deploy via `impactctl deploy` with full scan enabled; preflight OK before/after, cache + rewrite flush executed.
- Ran production deploy via `impactctl deploy` with full scan enabled; preflight before had one slow activity endpoint warning (3.14s), preflight after OK; cache + rewrite flush executed.
- Updated `notes.md` with deploy outcomes and warning.

# Pending
- Offerwall detailed implementation plan from `docs/offerwall-integration-plan.md`.
- Ads Watch Opus P0–P3 work status audit (if any gaps) and remaining tasks.
- JYSK /jysk-2/ scroll + text fix still flagged as protected-file update (backup + rollback required).
