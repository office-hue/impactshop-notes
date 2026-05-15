# JVK Tarcsi dimensions follow-up (2026-05-15)

## Scope
- File: `wp-content/mu-plugins/impactshop-event-auction-widget.php`
- Lot: `tarcsi-daniel-part-iii`
- Change type: protected legacy runtime metadata correction

## Why
- The artist delivered a different final size than the originally agreed one.
- The public auction runtime must show the final delivered dimensions and medium.

## Effective runtime correction
- `description_short`: `Akril, vásznon, 33x88 cm (keretezett méret: 32x102x3 cm).`
- `dimensions`: `33x88 cm (keretezett méret: 32x102x3 cm)`
- `medium`: `Akril, vásznon`

## Continuity note
- PR #140 completed a canonical PR/merge/deploy cycle but did not change the canonical Tarcsi lines in `origin/main`.
- PR #141 is the narrow follow-up carrying the effective runtime correction.
