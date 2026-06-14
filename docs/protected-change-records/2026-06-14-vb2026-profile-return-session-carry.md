# Protected Change Record

- Date: 2026-06-14
- Scope: VB2026 profile-return source lane canonicalization
- Reason: A FactLens `vb-prod` profile-return flow-nak a Sharity source oldalon kulon account-top es restore-fragment deeplinket, valamint save/restore utani target-session-helyreallito visszaterest kell adnia.
- Risk: Medium (protected identity runtime + cross-domain return lane)
- Rollback: A commit teljes visszavonasa (`git revert <commit>`), vagy a megelőző repo-tracked `impactshop-identity-panel.php` / `impactshop-identity-panel.js` allapot visszaallitasa, szukseg eseten a FactLens host oldali rollbackkel egyutt.

## Protected Files Touched

- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `wp-content/mu-plugins/impactshop-identity-panel.js`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-protected-files.json`

## Validation Plan

- `php -l wp-content/mu-plugins/impactshop-identity-panel.php`
- `node --check wp-content/mu-plugins/impactshop-identity-panel.js`
- `node -e "JSON.parse(fs.readFileSync('docs/impactshop-guard-config.json','utf8')); JSON.parse(fs.readFileSync('docs/impactshop-protected-files.json','utf8'));"`
- Smoke scope: `route:profil`, `route:factlens-vb-prod`, `flow:profile-return-account`, `flow:profile-return-restore`
- E2E expectation: account flow a profil tetejere menjen, restore flow a helyreallitas blokkra menjen, es sikeres save/restore utan a FactLens `auth/session` `connected_session` allapotot adjon.