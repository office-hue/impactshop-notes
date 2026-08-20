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
- **Mapping prevalidation**: a teljes mapping profil még HTTP/SSH előtt validálódik; csak repo-relatív, whitespace- és traversal-mentes, egyértelmű source/destination fogadható el.
- **Production write lock**: a széles production mapping és az exact-file production írás is fail-closed tiltott, amíg nincs remote backup + CAS/hash + végrehajtható rollback admission.
- **Review-fix recheck**: review-javítás után deploy vagy merge előtt kötelező egy új teljes guard/check kör és a nyitott review threadek rendezése.
- **Empty-cache hardening**: harmadik fél inventory/cache integrációnál tartós üres cache csak külön indokkal maradhat; alapértelmezésként forced refresh, retry vagy rövid TTL szükséges.

## Kulcs helyek
- Staging env: `.deploy.staging.env`
- Production env: `.deploy.production.env`
- Guard wrapper: `bin/impactshop-guard-deploy.sh`
- Mapping deploy: `bin/deploy-wpcontent-map.sh`
- Production runtime truth: `/home/sharityh/app`
- Public entry wrapper: `/home/sharityh/public_html/index.php` → `../app/wp-blog-header.php`

## Staging deploy (guard + mapping)
```bash
IMPACT_ENV=staging IMPACTSHOP_ALLOW_FULL_SCAN=1 \
  bin/impactshop-guard-deploy.sh --staging --non-interactive --auto-approve --reason="<ok>"
```

## Valódi dry-run szerződés

```bash
DRY_RUN=1 IMPACT_ENV=production IMPACTSHOP_ALLOW_FULL_SCAN=1 \
  bin/impactshop-guard-deploy.sh --production --non-interactive --auto-approve --reason="<ok>"
```

`DRY_RUN=1` esetén a wrapper HTTP/SSH read-only preflightot és tételes rsync
szimulációt futtat. Nem hozhat létre távoli könyvtárat, nem futtathat `wp`
cache/cron/rewrite karbantartást, és nem indíthat post-deploy smoke-ot. A már
létező remote célkönyvtárak hiánya fail-closed eredmény; a dry-run nem készíti
elő őket.

A mapping minden rsync előtt ellenőrzi a remote WordPress rootot és a bástya
manifestet. Elsődleges manifest:
`<remote-app-root>/.bastion/protected-hashes.json`. Csak normál, nem symlinkelt,
méretkorlátos, érvényes JSON fogadható el nem üres protected fájl- és SHA-256
térképpel. Hiányzó vagy hibás manifestnél staging és production is blokkol.
Az ellenőrzés a korábbi live baseline szerkezetét validálja; nem írja át a
manifestet, és nem tekinti automatikusan jóváhagyottnak a live-main driftet.

## Exact-file production release preview

A production runtime egyetlen repó által birtokolt könyvtárnak sem tekinthető:
a 2026-08-20-i read-only audit 20 live-only MU-plugin fájlt és hat közös,
tartalmilag eltérő fájlt talált. Ezért valós széles production mapping tiltott.

Egyetlen fájl írásmentes feloldása és rsync-előnézete:

```bash
DRY_RUN=1 \
IMPACTSHOP_DEPLOY_FILE="wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php" \
IMPACT_ENV=production IMPACTSHOP_ALLOW_FULL_SCAN=1 \
  bin/impactshop-guard-deploy.sh --production --non-interactive --auto-approve \
  --reason="<jóváhagyott exact-file dry-run>"
```

Az exact scope pontosan egy normál, nem symlinkelt, fizikailag is az aktív
repógyökér alatt lévő fájl lehet. Pontosan egy mapping roothoz kell tartoznia.
Az exact ág minden `--delete*` rsync opciót eltávolít, `--checksum` ellenőrzést
ad, nem hoz létre távoli könyvtárat, és nem érinti a sibling fájlokat.

Valós production futás jelenleg exact scope-pal is blokkol. A kapu csak külön
védett csomagban nyitható meg remote backup, compare-and-swap/hash ellenőrzés,
post-write `0444` visszazárás és végrehajtható rollback után.

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

## Rollback truth

A guard deploy snapshot jelenleg lokális forrás-snapshot. Nem bizonyít távoli
runtime backupot, és a korábban hivatkozott `bin/impactshop-guard-rollback.sh`
nem létezik. Emiatt production írás nem nyitható meg pusztán a lokális snapshot
azonosítójával. Valós deployhoz előre rögzített remote backup, eredeti SHA-256,
jogosultság és ténylegesen futtatható rollback útvonal szükséges.

## Megjegyzések
- SSH host/user a `.deploy.*.env` fájlokban. A távoli parancsoknál szükség esetén `ssh -t` használható.
- `public_html` productionön entry wrapper, nem a kanonikus WP runtime gyökér; a guard/deploy path igazsága az `/home/sharityh/app`.
- Preflight figyelmeztetés (pl. `totals` lassú) deploy-t nem blokkol, de érdemes monitorozni.
- MP4/asset fájlok ne kerüljenek deployba, ha nem része a mappingnek.
- Kézi utóellenőrzéshez továbbra is használható: `bin/post-deploy-checklist.sh`, ami már tartalmazza a Hatás Körök smoke-ot is.
- A kézi restore nem válik kanonikussá attól, hogy sikeres volt; ha guard hiba miatt incidensúton kellett kimenni, azt külön dokumentálni kell és külön helyre kell hozni a guard deploy infrastruktúrát.

## 2026-05-14 - adomany-automata redirect protected change record

### Protected files touched
- wp-content/mu-plugins/impactshop-ngo-guides.php

### Rollback
- `git revert 15de3677` ezen a branchen, vagy szerveren backup restore:
  `cp ~/impactshop-ngo-guides.php.bak-20260514 ~/app/wp-content/mu-plugins/impactshop-ngo-guides.php`

### Smoke
- `route:jysk-riport`
- `route:ngo-guides`
- `flow:guide-route-render`
- `flow:guide-print-mode`
- `flow:guide-data-json`
- `deploy:guard-preflight`
- `deploy:checksum-verify`
