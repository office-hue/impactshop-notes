# JVK auction 11-lot emergency restore (2026-05-16)

## Scope
- File: `wp-content/mu-plugins/impactshop-event-auction-widget.php`
- Purpose: restore the JVK auction lot list from 9 back to 11 items

## Findings
- Local worktree had regressed further to 7 lots.
- Canonical `origin/main` and live public payload showed 9 lots.
- Commit `0734ba80` contains the previously added lot 10 and lot 11 content.

## Restored lots
- Lot 10: `balla-gemma-ecoprint-selyemsal-nyaklac`
- Lot 11: `kocsis-katica-weiler-peter-dedikalt-konyv`

## Continuity
- This is an urgent event-live restore lane based on the dedicated JVK auction memory and repository history.
