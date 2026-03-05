# Offerwall Core Pack (2026-03-05)

## Scope
- Offerwall engine és UI bővítés (`impactshop-offerwall.php`, `impactshop-offerwall.js`).
- Internal survey submit endpoint + token validation + shortcode útvonal (`impactshop-offerwall-survey.php`).
- Survey question mapping bővítés (`question_mapping.csv`).

## Changed Modules
- `wp-content/mu-plugins/impactshop-offerwall.php`
- `wp-content/mu-plugins/impactshop-offerwall.js`
- `wp-content/mu-plugins/impactshop-offerwall-survey.php`
- `wp-content/mu-plugins/impactshop-offerwall-survey-data/question_mapping.csv`

## Continuity Evidence
- `conversation-summaries/435_conversation_summary.md`
- `system-status-snapshot.md` addendum
- Guard workflow continuity override synced (`.github/workflows/protect-critical-files.yml`)
