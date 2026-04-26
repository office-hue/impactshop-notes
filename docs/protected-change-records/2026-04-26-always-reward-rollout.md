# Protected Change Record - 2026-04-26 - Always Reward Rollout

## Protected Files Touched
- `wp-content/mu-plugins/impactshop-ads-watch.php`
- `wp-content/mu-plugins/impactshop-click-tracking.php`
- `wp-content/mu-plugins/impactshop-always-reward-flag.php.off`

## Change Type
- Impact Challenge protected perimeter touch (approved legacy modification).
- Additive switch behavior through default-off feature flag.

## Approval Context
- Explicit user approval provided in-session for protected deploy path and flag activation.
- Bastion deploy gate variables used:
  - `IMPACTSHOP_BASTION_WRITE_ALLOW=YES`
  - `IMPACTSHOP_BASTION_WRITE_APPROVAL=Arnold approved always-reward flag activation 2026-04-26`
  - `IMPACTSHOP_BASTION_ALLOW_PATHS=wp-content/mu-plugins/impactshop-always-reward-flag.php`

## Coherence Analysis
- Ads-view dedupe and CTA dedupe logic now share a single feature-flag semantic: default legacy, optional instance-based dedupe when enabled.
- CTA tracking now mirrors ads-watch sandbox behavior for dev-clone testing, avoiding lane mismatch where click endpoint could still write while ads endpoint stayed sandboxed.
- Default runtime behavior remains unchanged when flag is absent or disabled.

## Risk Analysis
- Main risk: unintended global activation if active flag file is deployed to production.
- Main mitigation: default-off constant and `.off` toggle file pattern in repo; activation requires explicit file enable.
- Operational risk: protected path deploy failures due to bastion lock.
- Mitigation: bastion-approved deploy path with automatic backups and generated rollback script.

## Affected Functions
- `impactshop_ads_watch_is_always_reward_enabled()`
- `impactshop_ads_watch_build_cta_dedupe()`
- `impactshop_ads_watch_view()`
- `impactshop_click_tracking_is_always_reward_enabled()`
- `impactshop_click_tracking_is_sandbox_request()`
- `impactshop_click_tracking_build_instance_dedupe()`
- `impactshop_click_tracking_handle()`

## Deploy Notes
- Deploy executed with bastion approval on production and staging.
- Automatic bastion backups/manifests created for both targets.
- Rollback script generated:
  - `.codex/reports/hotfix-sync/rollback_20260426T091837Z.sh`
- Cache flush executed on both environments.

## Validation Notes
- Production file checks confirmed:
  - `IMPACTSHOP_ADS_WATCH_VERSION=2.5.66`
  - always-reward default constant present and false
  - click tracking sandbox helper present
  - active flag file present on server after activation step
- CTA mismatch query after activation window returned 0 (`affected_users_after_flag=0`, `missed_rewards_after_flag=0`).

## Smoke Scope
- Runtime smoke tags used for this protected touch: `impact-challenge,ads-watch,cta,api,ui`.
- Post-deploy check targets:
  - production ads-watch runtime response
  - click tracking reward parity
  - flag-on behavior confirmation

## Manual UI Checklist
- Open production page with cache-buster query parameter.
- Verify repeated CTA clicks continue to award in always-reward mode.
- Verify repeated ad views continue to award in always-reward mode.
- Verify dev-clone requests remain sandboxed (no write side effects).
- Verify points and votes counters update consistently in identity panel.
- Verify no 4xx/5xx regressions on key endpoints.

## Rollback
- Use generated rollback script from hotfix report folder.
- Disable active flag file in production if immediate behavior rollback is required.
