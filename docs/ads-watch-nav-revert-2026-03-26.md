# Ads Watch nav revert

Date: 2026-03-26

## Problem

The Ads Watch page regressed after the 8-icon floating nav rewrite. Compared with the previously stable state, mobile lost the expected start-button layout and desktop reported visual freeze / darkened player state around the player area.

The accessible Time Machine backup on disk does not expose a separate `18:12` snapshot, but the available backup plus git history show that the main structural change in this area was the 4-button to 8-icon nav rewrite introduced on 2026-03-26.

## Change

- reverted the Ads Watch floating nav HTML from the 8-icon layout back to the earlier 4-button layout
- restored the earlier Ads Watch nav CSS geometry and spacing
- kept the later Safari external-tab recovery JS fixes in place
- bumped `IMPACTSHOP_ADS_WATCH_VERSION` to `2.5.32`

## Verification

- `php -l wp-content/mu-plugins/impactshop-ads-watch.php`
- live HTML references `impactshop-ads-watch.css?ver=2.5.32`
- live mobile Playwright snapshot shows:
  - 4-button Ads Watch nav
  - `Reklám megtekintése` button visible
