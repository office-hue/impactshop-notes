# Protected Change Record — Jövőnk Vize widget ticket-mix parity

Date: 2026-04-28
Repo: impactshop-notes
Branch: feat/jovonkvize-widget-ticket-mix-20260428
Approval: explicit user approval in session from Arnold

## Scope

- `wp-content/mu-plugins/impactshop-event-donation-widget.php`
- `wp-content/mu-plugins/impactshop-event-donation-widget-dev.js`
- `wp-content/mu-plugins/impactshop-event-donation-widget-dev-1.7.2.js`
- `wp-content/mu-plugins/impactshop-event-donation-widget-jovonkvize.js`
- `wp-content/mu-plugins/impactshop-event-donation-widget-jovonkvize-1.7.2.js`
- `wp-content/mu-plugins/impactshop-event-donation-widget.js`
- `docs/jovonkvize-widget-dev-embed.html`
- `docs/jovonkvize-widget-prod-embed.html`

## Why Legacy Touch Was Needed

- The final requirement was strict dev/live parity with separate URLs and identical behavior.
- This could not be solved purely additively because the existing live widget files still served the old single-ticket logic.
- To make the public embed correct, the legacy live widget files had to be updated to the same finalized logic as the dev widget.

## Coherence Analysis

- Dev and live widgets now share the same ticket-mix, package-plus-extra-ticket, and summary behavior.
- Backend checkout payload, storage, Stripe metadata, buyer email, admin email, and stats now all understand `regular_ticket_count` and `supporter_ticket_count`.
- The schema path was hardened so both production and staging can self-heal older table layouts before runtime use.
- Versioned physical filenames were introduced for cache-bypass without collapsing dev/live separation.

## Risk Analysis

- Risk: production MU plugin double-loading could fatal on helper redeclaration.
  - Mitigation: new helper functions are wrapped in `function_exists` guards.
- Risk: older DB tables may miss new ticket columns.
  - Mitigation: explicit column backfill in `impactshop_event_donation_ensure_ticket_mix_columns()`.
- Risk: public page may keep serving stale widget JS from cache.
  - Mitigation: versioned physical file URLs `*-1.7.2.js` documented for both dev and live.
- Risk: package and extra tickets may drift apart in total calculation.
  - Mitigation: single `refreshComputedTotals()` path recalculates both amount and ticket total.

## Affected Functions To Verify

- `impactshop_event_donation_ensure_schema()`
- `impactshop_event_donation_ensure_ticket_mix_columns()`
- `impactshop_event_donation_checkout()`
- `impactshop_event_donation_create_checkout_session()`
- `impactshop_event_donation_send_buyer_confirmation()`
- `impactshop_event_donation_send_transaction_notification()`
- `refreshComputedTotals()` in widget JS
- `recalculateTicketMix()` in widget JS

## Deploy And Rollback

- Deploy path used during session: bastion-approved `scripts/hotfix-sync.sh` to production and staging.
- Documented rollback artifact: `.codex/reports/hotfix-sync/rollback_20260428T093909Z.sh`
- Emergency fallback: redeploy previous known-good widget/PHP versions from the hotfix backup manifests created by the same deploy run.

## Manual UI Checklist

- Live embed renders from `impactshop-event-donation-widget-jovonkvize-1.7.2.js`.
- Dev embed renders from `impactshop-event-donation-widget-dev-1.7.2.js`.
- Package selection still opens package ticket selector.
- Extra alapjegy and támogatói jegy can be combined.
- Package tickets and extra tickets add together in both ticket count and payable amount.
- Summary block shows `Jegyek összesen`, `Jegybontás`, and `Fizetendő végösszeg`.
- Checkout request contains separate regular/supporter ticket counts.
- Buyer/admin confirmation email includes ticket breakdown.