# MU Vendor Bundle Pack (2026-03-05)

## Scope
- `wp-content/mu-plugins/vendor/` third-party dependency bundle külön csomagként felvéve.
- Cél: a maradék dirty dependency payload leválasztása önálló, review-olható PR-ba.

## Changed Modules
- `wp-content/mu-plugins/vendor/**`

## Continuity Evidence
- `docs/bastion-guard-status.md`
- `conversation-summaries/442_conversation_summary.md`
- `system-status-snapshot.md` addendum
- Guard workflow continuity override synced (`.github/workflows/protect-critical-files.yml`)
