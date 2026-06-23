# Protected Change Record

- Date: 2026-06-23
- Scope: VB2026 profile-return protected inventory cleanup
- Reason: A guard inventory egy nem letezo `impactshop-factlens-identity-bridge.php` MU-plugint vart protected truthkent, emiatt a canonical `main` deploy-preflight fail-closed modon megallt. A valos source-side runtime jelenleg az `impactshop-identity-panel.php` + `impactshop-identity-panel.js` par, ezert az inventoryt es a continuity szoveget ehhez kellett visszaigazitani.
- Risk: Low-medium (protected inventory es continuity truth modositas, runtime PHP/JS logika valtoztatas nelkul)
- Rollback: A commit teljes visszavonasa (`git revert <commit>`), vagy a megelőző repo-tracked `docs/impactshop-guard-config.json`, `docs/impactshop-protected-files.json`, `notes.md`, `system-status-snapshot.md`, `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md` allapot visszaallitasa.

## Protected Files Touched

- `docs/impactshop-guard-config.json`
- `docs/impactshop-protected-files.json`

## Continuity Files Touched

- `notes.md`
- `system-status-snapshot.md`
- `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md`

## Validation Plan

- `node -e "JSON.parse(require('fs').readFileSync('docs/impactshop-guard-config.json','utf8')); JSON.parse(require('fs').readFileSync('docs/impactshop-protected-files.json','utf8')); console.log('json-ok')"`
- `git diff --check`
- `rg -n 'impactshop-factlens-identity-bridge\\.php' notes.md system-status-snapshot.md docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md docs/impactshop-protected-files.json docs/impactshop-guard-config.json -S`
- deploy-preflight expectation a `main` parity worktree-ben: a phantom file miatti `hiányzó védett fájl` hiba megszunik, es a guard a kovetkezo valos gate-re lep tovabb

## Smoke Scope

- `deploy:guard-preflight`
- `deploy:checksum-verify`
