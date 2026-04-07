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
- **Legacy entrypoint**: `bin/deploy.sh` deprekált wrapper, ami már csak a guardolt deployra delegál.
- **Uncommitted changes**: **block**. Kivétel csak külön engedéllyel.
- **Target útvonalak**: `.deploy.staging.env` + `.deploy.production.env` az igazság.
- **Protected Impact Challenge files**: deploy előtt célzott backup kötelező, deploy után fizikai read-only visszazárás kötelező.
- **Guard deploy hibaállapot**: ha a wrapper maga hibás root/branch kötés vagy hiányzó guard-infra miatt nem használható, ezt nem-kanonikus incidensként kell kezelni; ilyenkor csak szűk, auditált restore mehet pontos fájllistával, backup + rollback + flush/smoke + live verifikáció mellett, és utána külön javítani kell a guard deploy pathot.
- **Canonical baseline**: Impact Challenge deploy esetén a referenciaállapot a `docs/impact-challenge-canonical-baseline.md`; ettől való eltérés csak explicit jóváhagyással vihető ki.
- **Guide rendszer**: `impactshop-ngo-guides.php` és `wp-content/mu-plugins/impactshop-ngo-guides/**` teljes subtree csak explicit engedéllyel deployolható; guide tartalmat felülíró automatika vagy hallgatólagos sync tiltott.
- **JYSK riport**: `/jysk-riport/`, a `/jysk-riport/?print=1` nézet és a `/jysk-riport.data.json` a guide-rendszer max-védett része; deploynál külön ellenőrizni kell a route render, print render és JSON payload folytonosságát.
- **JYSK source inventory**: a route csak akkor tekinthető teljesen kanonikusnak, ha a forrásfájlok (`impactshop-ngo-guides.php`, `jysk-riport.html`, `jysk-riport.data.json`) a guard-config és hash-manifest alatt is rögzítve vannak; pusztán live restore nem elég.
- **Legacy touch**: ha védett meglévő Impact Challenge fájlt módosítasz, a deploy naplóban szerepelnie kell az explicit engedélynek és az indoknak.
- **Protected-file change review**: deploy előtt kötelező a koherencia vizsgálat, kockázatelemzés, érintett funkciólista és a kézi UI checklist megléte.
- **CI/local parity**: protected lane csak akkor tekinthető lezártnak, ha a lokális guard és a GitHub oldali guard ugyanazt a protected modellt értelmezi.
- **Paired env continuity**: ha protected runtime env pár változik, a deploy scope-nak a staging és production env fájlt együtt kell tartalmaznia.
- **Review-fix recheck**: review-javítás után deploy vagy merge előtt kötelező egy új teljes guard/check kör és a nyitott review threadek rendezése.
- **Empty-cache hardening**: harmadik fél inventory/cache integrációnál tartós üres cache csak külön indokkal maradhat; alapértelmezésként forced refresh, retry vagy rövid TTL szükséges.

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
- Protected-file deploy vagy review-fix után kötelező ellenőrizni, hogy a GitHub oldali guard és a lokális guard ugyanarra a lane-re ugyanazt mondja.

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
- A kézi restore nem válik kanonikussá attól, hogy sikeres volt; ha guard hiba miatt incidensúton kellett kimenni, azt külön dokumentálni kell és külön helyre kell hozni a guard deploy infrastruktúrát.
