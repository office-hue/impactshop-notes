# Points Admin Pack (2026-03-05)

## Scope
- Sharity pontcore bootstrap frissítés (admin + notification modulok bekötése).
- Új admin felület a pontkorrekcióhoz és szinteloszlás monitorozásához.
- Új targeted notification flow szintváltás/inaktivitás eseményekre.
- Új Content Consumption Guard dashboard (WP admin + dashboard widget).

## Changed Modules
- `wp-content/mu-plugins/sharity-points.php`
- `wp-content/mu-plugins/sharity-points-admin.php`
- `wp-content/mu-plugins/sharity-points-notifications.php`
- `wp-content/mu-plugins/sharity-content-consumption-guard.php`

## Continuity Evidence
- `docs/bastion-guard-status.md`
- `conversation-summaries/437_conversation_summary.md`
- `system-status-snapshot.md` addendum
- Guard workflow continuity override synced (`.github/workflows/protect-critical-files.yml`)
