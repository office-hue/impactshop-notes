# Expansion NGO + PWA Pack (2026-03-05)

## Scope
- Cégjelző NGO registry integráció (sync + filter + export).
- NGO selector + static NGO guide oldalak.
- PWA shell + web push modul + offline fallback assetek.
- Offerwall extension: AyeT callback stack + internal article quiz provider.

## Changed Modules
- `wp-content/mu-plugins/impactshop-cegjelzo.php`
- `wp-content/mu-plugins/impactshop-ngo-guides.php`
- `wp-content/mu-plugins/impactshop-ngo-guides/*`
- `wp-content/mu-plugins/impactshop-ngo-selector.php`
- `wp-content/mu-plugins/impactshop-ngo-selector.js`
- `wp-content/mu-plugins/impactshop-ngo-selector-data/komarom-esztergom.json`
- `wp-content/mu-plugins/impactshop-pwa.php`
- `wp-content/mu-plugins/impactshop-pwa-push.php`
- `wp-content/mu-plugins/impactshop-ayet-offerwall.php`
- `wp-content/mu-plugins/impactshop-offerwall-article-quiz.php`
- `wp-content/mu-plugins/impactshop-offerwall-article-quiz-data/articles_quiz.json`
- `sw.js`
- `offline.html`

## Continuity Evidence
- `docs/bastion-guard-status.md`
- `conversation-summaries/439_conversation_summary.md`
- `system-status-snapshot.md` addendum
- Guard workflow continuity override synced (`.github/workflows/protect-critical-files.yml`)
