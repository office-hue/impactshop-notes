## 2026-03-31 20:22 CET - AyeT surveywall runtime lane
- Clean runtime worktree branch: `fix/ayet-surveywall-runtime`.
- Restored AyeT surveywall as a dedicated survey source on adslot `25740` with
  profile hash `b970533bbaf884d085d7c0e6734da1c2`.
- Kept the existing AyeT offerwall/game inventory on adslot `25643`.
- Runtime changes limited to:
  - `.deploy.production.env`
  - `.deploy.staging.env`
  - `wp-content/mu-plugins/impactshop-ayet-offerwall.php`
  - `wp-content/mu-plugins/impactshop-offerwall.php`
  - `wp-content/mu-plugins/impactshop-offerwall.js`
- Protected continuity recorded in `docs/protected-change-records/2026-03-31-ayet-surveywall-restoration.md`.
