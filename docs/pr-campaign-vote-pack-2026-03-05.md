# Campaign Vote Pack (2026-03-05)

## Scope
- Impact Challenge vote purchase Stripe modul (UI + REST + checkout flow).
- Quarter close helper modul az ads-watch szavazási ciklushoz.
- Event donation embeddable widget (külső kampány oldalra).
- Legacy redirect + Dognet autobanner ingest + fast data backup CLI kiegészítés.

## Changed Modules
- `wp-content/mu-plugins/impactshop-vote-purchase.php`
- `wp-content/mu-plugins/impactshop-vote-purchase.js`
- `wp-content/mu-plugins/impactshop-vote-purchase.css`
- `wp-content/mu-plugins/impactshop-ads-watch-quarter.php`
- `wp-content/mu-plugins/impactshop-event-donation-widget.php`
- `wp-content/mu-plugins/impactshop-event-donation-widget.js`
- `wp-content/mu-plugins/impactshop-impactad-redirect.php`
- `wp-content/mu-plugins/impactshop-auto-banner-dognet.php`
- `wp-content/mu-plugins/impactshop-fast-data-backup.php`

## Continuity Evidence
- `docs/bastion-guard-status.md`
- `conversation-summaries/438_conversation_summary.md`
- `system-status-snapshot.md` addendum
- Guard workflow continuity override synced (`.github/workflows/protect-critical-files.yml`)
