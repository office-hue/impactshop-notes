# Bastion Guard Status

Last updated: 2026-03-25

## Purpose
Ez a fájl a kötelező evidencianapló minden új modulhoz tartozó bástya/guard kiterjesztéshez.

## Kötelező szabály
- Minden új modul (`wp-content/mu-plugins/`, `wp-content/plugins/`, `mu-plugins/`, `scripts/`, `bin/`) esetén ebben a fájlban is frissítést kell rögzíteni ugyanabban a change setben.
- A strict pre-push audit ezt automatikusan ellenőrzi.

## Kiterjesztési napló
| Dátum | Modul | Guard kiterjesztés | Evidencia |
| --- | --- | --- | --- |
| 2026-03-25 | sharity-meghatalmazas-adomanyigazolas.pdf | Statikus PDF, nincs futtatható kód. Csak olvasható (444). PHP-ból file_exists ellenőrzéssel csatolja a cert emailhez. | `impactshop-event-donation-widget.php` v1.5.2 |

| 2026-03-05 | Baseline policy bevezetés | Safe repo audit kötelezővé teszi az új modulok bastion frissítését | `scripts/safe-repo-audit.sh` |

| 2026-03-05 | Pre-push hook módosítás | Push-range audit (`--mode push`) bekapcsolva, dirty worktree miatti false block megszűnik | `scripts/install-hooks.sh`, `scripts/safe-repo-audit.sh` |

| 2026-03-05 | Identity panel nickname flow | Mentés után azonnali greeting + herowall szinkron, plusz fallback névfeloldás a Legacy API-ban | `wp-content/mu-plugins/impactshop-identity-panel.php`, `impactshop-identity-panel.js`, `impact-gamification.php` |

| 2026-03-05 | Vote purchase + quarter modulok | Új szavazatvásárlási és quarter-close modulok guard evidence-hez kötve | `wp-content/mu-plugins/impactshop-vote-purchase.php`, `wp-content/mu-plugins/impactshop-ads-watch-quarter.php`, `docs/pr-campaign-vote-pack-2026-03-05.md` |
| 2026-03-05 | Event donation + campaign utility modulok | Külső embed donation flow és utility modulok (redirect/dognet/backup) bastion naplózva | `wp-content/mu-plugins/impactshop-event-donation-widget.php`, `wp-content/mu-plugins/impactshop-impactad-redirect.php`, `wp-content/mu-plugins/impactshop-auto-banner-dognet.php`, `wp-content/mu-plugins/impactshop-fast-data-backup.php` |
| 2026-03-05 | Sharity Points admin+notification modulok | Új MU modulokhoz kötelező continuity evidence + strict audit gate fenntartás | `wp-content/mu-plugins/sharity-points-admin.php`, `wp-content/mu-plugins/sharity-points-notifications.php`, `docs/pr-points-admin-pack-2026-03-05.md` |
| 2026-03-05 | Content Consumption Guard dashboard | Új guard modul dokumentált bastion kiterjesztéssel, snapshot/summary kapcsolattal | `wp-content/mu-plugins/sharity-content-consumption-guard.php`, `conversation-summaries/437_conversation_summary.md`, `system-status-snapshot.md` |

| 2026-03-05 | NGO registry + selector modulok | Cégjelző sync és NGO selector/guides modulok guard evidence-hez kötve | `wp-content/mu-plugins/impactshop-cegjelzo.php`, `wp-content/mu-plugins/impactshop-ngo-selector.php`, `wp-content/mu-plugins/impactshop-ngo-guides.php` |
| 2026-03-05 | PWA + offerwall extension modulok | PWA shell/push + AyeT/article-quiz modulok bastion naplózással és continuity csomaggal | `wp-content/mu-plugins/impactshop-pwa.php`, `wp-content/mu-plugins/impactshop-pwa-push.php`, `wp-content/mu-plugins/impactshop-ayet-offerwall.php`, `wp-content/mu-plugins/impactshop-offerwall-article-quiz.php` |

| 2026-03-05 | Ledger + card request modulok | Új `mu-plugins/` modulok külön continuity gate-tel integrálva | `mu-plugins/impact-ledger.php`, `mu-plugins/impactshop-card-request.php`, `docs/pr-ledger-card-request-pack-2026-03-05.md` |

| 2026-03-05 | Asset + placeholder modulcsomag | MU image és fallback placeholder állományok is continuity gate alá vonva | `wp-content/mu-plugins/image/*`, `wp-content/mu-plugins/impact-arukereso-deeplink-fix.php.off`, `docs/pr-module-assets-pack-2026-03-05.md` |

| 2026-03-05 | MU vendor dependency bundle | Nagy third-party csomag külön PR-ban, continuity guard evidence kötelezően csatolva | `wp-content/mu-plugins/vendor/**`, `docs/pr-mu-vendor-bundle-pack-2026-03-05.md` |
| 2026-03-20 | Miele Jövőnk Vize gála widget | Új widget modulok: dev + éles (jovonkvize) JS, PHP Stripe limit 3.5M, guard evidence: system-status-snapshot.md 2026-03-20 | `wp-content/mu-plugins/impactshop-event-donation-widget-dev.js`, `wp-content/mu-plugins/impactshop-event-donation-widget-jovonkvize.js`, `wp-content/mu-plugins/impactshop-event-donation-widget.php` |
| 2026-03-24 | Impact Community (Hatás Körök) Sprint 1+2 | Két új MU modul: backend (`impact-community.php`) + SPA frontend (`impact-community-app.php`). Feature flag: `IMPACT_COMMUNITY_ENABLED`. DB: 7 tábla, REST: 11 endpoint, route: `/hatas-korok/` priority 4. Guard: nincsen külön guard script — a meglévő impactall `mu-plugins parity` guard figyeli a jelenlétet. | `wp-content/mu-plugins/impact-community.php`, `wp-content/mu-plugins/impact-community-app.php` |
