# Impact Community — Sprint 1–16 Implementáció Log

**Dátum:** 2026-03-24
**Branch:** `feat/impact-community-sprint1`
**PR:** [#73](https://github.com/office-hue/impactshop-notes/pull/73)
**DB verzió:** IC_DB_VERSION 1.3.7

## Modulok

- 22 DB tábla (ic_circles, ic_memberships, ic_posts, ic_missions stb.)
- 47+ REST endpoint (`/wp-json/impact/v1/...`)
- 15+ cron (napi stats, heti data retention, tombola ritual stb.)

## Post-implementation Audit (2026-03-24)

11 SQL séma-inkonzisztencia javítva:

| Tábla | Hibás oszlop | Helyes |
|-------|-------------|--------|
| ic_memberships | `status='active'` | `is_active=1` |
| ic_memberships | `user_id` | `pid_hash` |
| ic_posts | `deleted_at` | `is_deleted` |
| ic_sprints | `closed_at` | `status+ends_at` |
| tombola buy | `ic_votes` tábla | `ic_posts` |
| ic_moderation_actions | `action_type` | `action` |
| ic_ngo_accounts | `admin_hash` lookup | `ic_ngo_guard()` Bearer |
| ic_reports | `pid_hash` | `reporter_hash` |
| ic_reports | `updated_at` | `reviewed_at` |
| ic_mission_completions | `completed_at` | `created_at` |
| ic_circle_leaderboard | `snapshot_date` | `updated_at` |

PHP lint: OK. SQL injection védelem: minden `$wpdb->prepare()`. XSS: `esc_html()`.
