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

| 2026-03-05 | NGO registry + selector modulok | Cégjelző sync és NGO selector/guides modulok guard evidence-hez kötve | `wp-content/mu-plugins/impactshop-cegjelzo.php`, `wp-content/mu-plugins/impactshop-ngo-selector.php`, `wp-content/mu-plugins/impactshop-ngo-guides.php` |
| 2026-03-05 | PWA + offerwall extension modulok | PWA shell/push + AyeT/article-quiz modulok bastion naplózással és continuity csomaggal | `wp-content/mu-plugins/impactshop-pwa.php`, `wp-content/mu-plugins/impactshop-pwa-push.php`, `wp-content/mu-plugins/impactshop-ayet-offerwall.php`, `wp-content/mu-plugins/impactshop-offerwall-article-quiz.php` |
