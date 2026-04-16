## Összefoglaló
- ads-watch debug endpoint hardening: default OFF + admin+nonce + rate limit + redacted response
- Dev clone sandbox write mode: admin-only access guard, noindex headers, sandbox early-return mind 5 write endpointon
- Frontend: writeMode constant from PHP config, X-ImpactShop-Write-Mode header minden non-GET hívásban

## Érintett fájlok
- `wp-content/mu-plugins/impactshop-ads-watch.js`
- `wp-content/mu-plugins/impactshop-ads-watch.php`
- `scripts/hatas-korok-load-memory.sh`
- `AGENTS.md`

## Változások

### Review-fix kiegészítés (2026-04-14)
- `impactshop_ads_watch_get_request_write_mode()` szigorítás:
  - `sandbox` mód csak admin + valid REST nonce esetén engedélyezett
  - `write_mode` query param fallback eltávolítva (header-only)
  - dev clone referer + admin+nonce esetén hiányzó headernél is sandbox fallback
- `allocate_votes` sorrendjavítás: sandbox early-return az `ngo_mismatch` check elé került (dev clone tesztflow unblock)
- `IMPACTSHOP_ADS_WATCH_VERSION` bump: `2.5.65` (stale JS cache ellen)

### Debug endpoint (impactshop-ads-watch.php)
- `IMPACTSHOP_ADS_DEBUG_ENDPOINT_ENABLED = false` konstans (alapértelmezés: letiltva)
- `impactshop_ads_watch_debug_enabled()` — filterable wrapper
- `impactshop_ads_watch_debug_permission()` — `manage_options` + nonce + rate limit
- Debug route csak ha `debug_enabled()` igaz, más esetben nem regisztrálódik

### Dev clone sandbox (impactshop-ads-watch.php)
- `IMPACTSHOP_ADS_DEV_CLONE_SLUG = 'impact-challenge-dev'` konstans
- `IMPACTSHOP_ADS_DEV_CLONE_CAPABILITY = 'manage_options'` konstans
- `impactshop_ads_watch_guard_dev_clone_access()` — `template_redirect` priority 0, nem-admin → wp_die 404
- `impactshop_ads_watch_send_dev_clone_noindex_headers()` — `X-Robots-Tag: noindex, nofollow, noarchive`
- Sandbox early-return (no DB write) in: `view`, `education`, `set_ngo`, `set_auto_vote`, `allocate_votes`
- `wp_localize_script` bővítve: `writeMode`, `devClone` metadata block

### Frontend write mode (impactshop-ads-watch.js)
- `const writeMode` — config értékből, fallback 'production'
- `options.headers['X-ImpactShop-Write-Mode'] = writeMode` — minden non-GET hívásban

### Shell guard (scripts/hatas-korok-load-memory.sh)
- `--confirm-full-sync` kapcsoló kötelező a `--full-sync` futtatáshoz
- `HATAS_KOROK_ALLOW_FULL_SYNC=1` env override lehetőség
- Ismeretlen opció → `exit 1`

## Kockázat
- Alacsony: kizárólag additive/defensive változások
- Production write flow: nem érintett (writeMode = 'production' alapértelmezés)
- Debug endpoint: prod-on tiltva marad hacsak a konstanst vagy a filtert nem írja felül valami

## Backup / Rollback
- Backup: `.codex/backups/post-dev-clone-safety-20260414-121959` (8 fájl, sha256 mind OK)
- Pre-change snapshot: `.codex/backups/pre-dev-clone-safety-20260414-121132` (6 fájl, sha256 OK)
- Rollback: `bash .codex/backups/post-dev-clone-safety-20260414-121959/rollback.sh`
- Op. rollback: az előző stabil állapotra `bash .codex/backups/pre-dev-clone-safety-20260414-121132/rollback.sh`

## Ellenőrzés
- PHP lint: `php -l wp-content/mu-plugins/impactshop-ads-watch.php` → No syntax errors ✅
- JS check: `node --check wp-content/mu-plugins/impactshop-ads-watch.js` → OK ✅
- Shell: `bash -n scripts/hatas-korok-load-memory.sh` → OK ✅
- Prod smoke (deploy után):
  - Anon GET `/impact-challenge-dev` → 404
  - Admin GET `/impact-challenge-dev` → 200 + `X-Robots-Tag: noindex...`
  - POST `/wp-json/impact/v1/ads-watch/view` with `X-ImpactShop-Write-Mode: sandbox` → `{sandbox: true, ...}` (no DB write)
  - GET `/wp-json/impact/v1/ads-watch/debug-rotation` anon → 401/403/404
