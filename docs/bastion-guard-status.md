# Bastion Guard Status

Last updated: 2026-03-05

## Purpose
Ez a fájl a kötelező evidencianapló minden új modulhoz tartozó bástya/guard kiterjesztéshez.

## Kötelező szabály
- Minden új modul (`wp-content/mu-plugins/`, `wp-content/plugins/`, `mu-plugins/`, `scripts/`, `bin/`) esetén ebben a fájlban is frissítést kell rögzíteni ugyanabban a change setben.
- A strict pre-push audit ezt automatikusan ellenőrzi.

## Kiterjesztési napló
| Dátum | Modul | Guard kiterjesztés | Evidencia |
| --- | --- | --- | --- |
| 2026-03-05 | Baseline policy bevezetés | Safe repo audit kötelezővé teszi az új modulok bastion frissítését | `scripts/safe-repo-audit.sh` |

| 2026-03-05 | Pre-push hook módosítás | Push-range audit (`--mode push`) bekapcsolva, dirty worktree miatti false block megszűnik | `scripts/install-hooks.sh`, `scripts/safe-repo-audit.sh` |

| 2026-03-05 | Identity panel nickname flow | Mentés után azonnali greeting + herowall szinkron, plusz fallback névfeloldás a Legacy API-ban | `wp-content/mu-plugins/impactshop-identity-panel.php`, `impactshop-identity-panel.js`, `impact-gamification.php` |

| 2026-03-05 | Vote purchase + quarter modulok | Új szavazatvásárlási és quarter-close modulok guard evidence-hez kötve | `wp-content/mu-plugins/impactshop-vote-purchase.php`, `wp-content/mu-plugins/impactshop-ads-watch-quarter.php`, `docs/pr-campaign-vote-pack-2026-03-05.md` |
| 2026-03-05 | Event donation + campaign utility modulok | Külső embed donation flow és utility modulok (redirect/dognet/backup) bastion naplózva | `wp-content/mu-plugins/impactshop-event-donation-widget.php`, `wp-content/mu-plugins/impactshop-impactad-redirect.php`, `wp-content/mu-plugins/impactshop-auto-banner-dognet.php`, `wp-content/mu-plugins/impactshop-fast-data-backup.php` |
| 2026-03-05 | Sharity Points admin+notification modulok | Új MU modulokhoz kötelező continuity evidence + strict audit gate fenntartás | `wp-content/mu-plugins/sharity-points-admin.php`, `wp-content/mu-plugins/sharity-points-notifications.php`, `docs/pr-points-admin-pack-2026-03-05.md` |
| 2026-03-05 | Content Consumption Guard dashboard | Új guard modul dokumentált bastion kiterjesztéssel, snapshot/summary kapcsolattal | `wp-content/mu-plugins/sharity-content-consumption-guard.php`, `conversation-summaries/437_conversation_summary.md`, `system-status-snapshot.md` |
