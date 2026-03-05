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

| 2026-03-05 | Ledger + card request modulok | Új `mu-plugins/` modulok külön continuity gate-tel integrálva | `mu-plugins/impact-ledger.php`, `mu-plugins/impactshop-card-request.php`, `docs/pr-ledger-card-request-pack-2026-03-05.md` |
