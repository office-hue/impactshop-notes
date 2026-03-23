# Autobanner Feed Import Update

Date: 2026-03-23

## Purpose

This change adds a direct JSON feed import path for autobanner inventory and raises the legacy source limits so the live autobanner catalog can stay above the 1000-item target.

## Changes

- Added feed-file discovery and import helpers to the autobanner MU plugin.
- Added `impactshop auto-banner import-feed` WP-CLI command.
- Added hourly feed-import scheduling hook.
- Raised Google Sheets autobanner sync limit from `50` to `1000`.
- Raised Dognet autobanner ingest limit from `120` to `1000`.

## Operational result

- WordPress can now import autobanner feed JSON generated outside the old sync-only path.
- The autobanner inventory no longer depends only on the smaller legacy sync limits.

## Follow-up Hardening

- The autobanner runtime now supports per-user rotation via `pseudo_id`, so the same user does not start repeating offers until the active inventory has been exhausted.
- The canonical `Shops` CSV remains the source of truth for Dognet autobanner mapping. Dognet mapping now also accepts `dognet_program_id` / `program_id` in addition to parsing `cid` from `dognet_base`.
- The canonical `banners` CSV keeps every non-empty offer row. Blank rows fall out; rows without `img` stay eligible via shop-logo fallback.
- Production deployment for this follow-up is still blocked by filesystem permissions on `/home/sharityh/app/wp-content/mu-plugins/*.php` (`0444` read-only), so the code is ready but not yet copied to the host.
