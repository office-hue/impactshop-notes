# 17. Conversation Summary: Staging Host Canonicalisation

## Overview
Reconciled staging domain usage so the WordPress instance and local tooling consistently target `https://app.sharity.hu/impactshop-staging`, preventing accidental redirects back to the production home page.

## Key Updates
- Introduced `wp-content/mu-plugins/impact-staging-host-guard.php` to enforce the canonical staging `home`/`siteurl` values for both web and WP-CLI requests.
- Realigned local env defaults (`.staging_env`, `.deploy.staging.env`, QA scripts, impactctl helpers, Codex refresh) to rely on the `app.sharity.hu` hostname and refreshed the Codex snapshot.

## Follow-Up
- Execute `bin/staging-rest-fix.sh` (or its individual `wp option` + `.htaccess` steps) on the staging server, then retest `https://app.sharity.hu/impactshop-staging/` and related REST endpoints from a clean browser session.
