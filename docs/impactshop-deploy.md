# Impact Shop deploy runbook

## Cél
Biztonságos, ismételhető staging + production deploy a bástyavédelem mellett.

## Alapelv
- Teljes repo-scan/rsync **tiltott** külön jóváhagyás nélkül.
- Védett fájlok módosítása csak indoklással, snapshot + egykattintásos rollbackkel.
- Deploy előtt ellenőrizd a working tree tisztaságát.
- Impact Challenge esetén a deploy előfeltétele, hogy a módosítás additív új kód legyen, vagy külön jóváhagyott legacy touch.

## Deploy policy (gyors, kötelező minimum)
- **Deploy parancs**: mindig `bin/impactshop-guard-deploy.sh` (runbook szerint).
- **Uncommitted changes**: **block**. Kivétel csak külön engedéllyel.
- **Target útvonalak**: `.deploy.staging.env` + `.deploy.production.env` az igazság.
- **Protected Impact Challenge files**: deploy előtt célzott backup kötelező, deploy után fizikai read-only visszazárás kötelező.
- **Legacy touch**: ha védett meglévő Impact Challenge fájlt módosítasz, a deploy naplóban szerepelnie kell az explicit engedélynek és az indoknak.
- **Protected-file change review**: deploy előtt kötelező a koherencia vizsgálat, kockázatelemzés, érintett funkciólista és a kézi UI checklist megléte.

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

## Merge / push / deploy kapu Impact Challenge esetén

- Push előtt a PR/commit leírásnak egyértelműen tartalmaznia kell, hogy additív új kód vagy jóváhagyott legacy módosítás történt.
- Merge csak akkor mehet, ha a PR body tartalmazza a bástyavédelmi és írásvédettségi megfelelést.
- Deploy csak akkor mehet, ha backup + rollback útvonal rögzített, és a védett fájlok deploy utáni read-only visszaállítása része a lépéssornak.
- Protected-file deploy csak akkor mehet, ha előre rögzített, hogy mely funkciókat kell utána ellenőrizni, és a felhasználónak külön UI checklist készül.

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
