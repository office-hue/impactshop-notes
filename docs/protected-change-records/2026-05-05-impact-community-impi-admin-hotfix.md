# 2026-05-05 Impact Community Impi Admin Hotfix

## Összefoglaló
- Restore NGO admin Impi route wiring and keep Hataskorok admin panel operational.
- Commit intent is limited to:
  - wp-content/mu-plugins/impact-community-app.php
  - wp-content/mu-plugins/impact-community.php

## Protected files touched
- wp-content/mu-plugins/impactshop-ads-watch.php
- wp-content/mu-plugins/impactshop-ngo-guides.php
- wp-content/mu-plugins/impactshop-ngo-guides/hatas-korok-en.html
- wp-content/mu-plugins/impactshop-offerwall.php

## Megjegyzés
- A fenti protected fájlok dirty worktree-ben voltak jelen, de ebbe a commitba nem kerülnek bele.

## Rollback
- Use existing per-file production backups created by guarded remote write.
- For commit-level rollback: git revert <commit>

## Smoke checks
- GET /wp-json/impact/v1/auth/status
- GET /wp-json/impact/v1/ngo/admin/mine
- POST /wp-json/impact/v1/ngo/admin/impi/review (ask mode)
- Hataskorok NGO admin panel Impi buttons render and execute without rest_no_route
