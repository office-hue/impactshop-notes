# Protected Change Record: ads-watch.php Clear-Site-Data removal

**Dátum:** 2026-04-16
**Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.php`
**Érintett guard fájl:** `docs/impactshop-guard-hashes.json`
**Változás típusa:** Header sor kikommentelése (1 sor) + guard hash frissítés

## Mi változott

- `header('Clear-Site-Data: "cache"', false);` → kikommentelve
- Sor: 4096

## Miért

A `Clear-Site-Data: "cache"` header a Cloudflare `override_origin` cache rule-lal kombinálva jQuery cached-404 kaszkádot okozott:
1. Facebook bot hit `jquery.min.js` → origin 404 → CF cached 24h
2. `Clear-Site-Data` header minden IC page loadnál törölte a böngésző cache-t
3. Böngésző újra letöltötte jQuery-t CF-ről → cached 404 → "jQuery is not defined" → IC teljesen leállt

## Kiegészítő fix

- CF cache rules #3 és #4 frissítve `status_code_ttl: [{400-499: 0}, {500-599: 0}]` kiegészítéssel (ruleset version 7)
- Ez megakadályozza, hogy a CF bármilyen 4xx/5xx választ cache-eljen a jövőben

## Deploy

- Manuális: `ssh chmod 644 → scp → ssh chmod 444` (szerveren 444 védelem visszaállítva)
- Staging + production egyaránt

## Rollback

- Server backup: `~/impactshop-ads-watch.php.bak-20260416` (s59)
- Bastion backup: `._backup20260416T135655Z_production_bastion.tgz`
- Rollback script: `.codex/reports/hotfix-sync/rollback_20260416T135655Z.sh`

## Smoke / verifikáció

- `impactall` guard suite: 7/8 PASS, 1 WARN (non-blocking: workspace-backup stale)
- IC page: HTTP 200
- jQuery: HTTP 200, cf-cache-status: HIT
- ads-watch.js: HTTP 200
- Clear-Site-Data header: REMOVED OK (verified via curl)
- Guard hash frissítve: `fb3299a5` → `31190b72`
