# Impact Shop deploy runbook

## Cél
Biztonságos, ismételhető staging + production deploy a bástyavédelem mellett.

## Alapelv
- Teljes repo-scan/rsync **tiltott** külön jóváhagyás nélkül.
- Védett fájlok módosítása csak indoklással, snapshot + egykattintásos rollbackkel.
- Deploy előtt ellenőrizd a working tree tisztaságát.

## Deploy policy (gyors, kötelező minimum)
- **Deploy parancs**: mindig `bin/impactshop-guard-deploy.sh` (runbook szerint).
- **Uncommitted changes**: **block**. Kivétel csak külön engedéllyel.
- **Target útvonalak**: `.deploy.staging.env` + `.deploy.production.env` az igazság.

## Kulcs helyek
- Staging env: `.deploy.staging.env`
- Production env: `.deploy.production.env`
- Guard wrapper: `bin/impactshop-guard-deploy.sh`
- Mapping deploy: `bin/deploy-wpcontent-map.sh`

## Staging deploy (guard + mapping)
```bash
IMPACT_ENV=staging IMPACTSHOP_ALLOW_FULL_SCAN=1 \
  bin/impactshop-guard-deploy.sh --staging --non-interactive --auto-approve --reason="<ok>"
```

## Production deploy (guard + mapping)
```bash
IMPACT_ENV=production IMPACTSHOP_ALLOW_FULL_SCAN=1 \
  bin/impactshop-guard-deploy.sh --production --non-interactive --auto-approve --reason="<ok>"
```

Production mapping deploy után a `bin/deploy-wpcontent-map.sh` automatikusan lefuttatja a `scripts/hatas-korok-post-deploy-smoke.sh` read-only ellenőrzést, ha a script elérhető és a `PREFLIGHT_BASE_URL` be van állítva.

## Quick rollback (guard snapshot)
A deploy kimenetében megjelenik a snapshot azonosító. Visszaállítás:
```bash
bin/impactshop-guard-rollback.sh deploy-YYYYMMDD-HHMMSS
```

## Megjegyzések
- SSH host/user a `.deploy.*.env` fájlokban. A távoli parancsoknál szükség esetén `ssh -t` használható.
- Preflight figyelmeztetés (pl. `totals` lassú) deploy-t nem blokkol, de érdemes monitorozni.
- MP4/asset fájlok ne kerüljenek deployba, ha nem része a mappingnek.
- Kézi utóellenőrzéshez továbbra is használható: `bin/post-deploy-checklist.sh`, ami már tartalmazza a Hatás Körök smoke-ot is.
