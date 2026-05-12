# Protected Change Record

- Date: 2026-05-12
- Scope: ImpactShop lang/country selector restore hotfix
- Reason: Production oldalon hiányzik a nyelv/ország selector, mert a kanonikus fájlban nem szerepel a selector blokk.
- Risk: Low/Medium (UI plusz query-arg váltás)
- Rollback: A commit teljes visszavonása (`git revert <commit>`), illetve korábbi action-bar fájl visszaállítása.

## Protected Files Touched

- wp-content/mu-plugins/impactshop-action-bar.php

## Validation Plan

- `php -l wp-content/mu-plugins/impactshop-action-bar.php`
- Ellenőrzés: `/impactshop/?lang=en&country=us` oldalon megjelenik a `sharity-slc` selector.
- Smoke: alap navigáció és action bar működés változatlan.
