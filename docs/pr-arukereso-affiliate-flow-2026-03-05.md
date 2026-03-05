# Arukereso Affiliate Flow Update (2026-03-05)

## Scope
- Árukereső deeplink kezelés engedélyezése a `/go-deal` útvonalon.
- CJ fallback feloldása (`cj_shops.json` alapján) `impactshop-boot.php` és `impactshop-go-bridge.php` útvonalakon.
- Autobanner host allowlist bővítés registry domain alapján.
- Partner API: donation multiplier kiszámítás és ledgerbe írás.

## Changed Modules
- `wp-content/mu-plugins/impact-arukereso-guard.php`
- `wp-content/mu-plugins/impact-cid-arukereso-fix.php`
- `wp-content/mu-plugins/impact-combat-pack.php`
- `wp-content/mu-plugins/impactshop-auto-banner.php`
- `wp-content/mu-plugins/impactshop-boot.php`
- `wp-content/mu-plugins/impactshop-go-bridge.php`
- `wp-content/mu-plugins/impactshop-partner-api.php`
- `wp-content/mu-plugins/sharity-impact-compat.php`
- `wp-content/mu-plugins/impact-arukereso-deeplink-fix.php` (removed)

## Continuity Evidence
- `conversation-summaries/434_conversation_summary.md`
- `system-status-snapshot.md` addendum
- Guard workflow continuity override rule synced in `.github/workflows/protect-critical-files.yml`
