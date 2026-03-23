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
