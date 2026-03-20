# Summary
- The user requested a “save state” handoff before shutdown.
- I updated `notes.md` with the current WIP status on the account points system and next steps.

# Work Completed
- Logged the removal of WooCommerce references from `docs/fiok-pontrendszer-spec.md` (Impact Shop purchase events instead).
- Logged schema preparation in `wp-content/mu-plugins/sharity-points.php` for `pseudo_id` support and dedupe keys.

# In Progress / Next Steps
- Implement pseudo-id-aware awarding in `wp-content/mu-plugins/sharity-points-manager.php` (e.g., `award_points_for_pseudo`, dedupe).
- Add a ledger-based sync MU-plugin + cron to award points from `impact_ledger` (dedupe by purchase/ledger id).
- Keep protected files untouched unless explicitly approved with backup + one-click rollback.

# Notes
- No protected files were edited in this step.
