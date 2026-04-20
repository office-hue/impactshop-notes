## Összefoglaló
- a 2026-04-19 ads-watch incidens utókövetéseként lezárjuk a production deploy-path auditot
- kimondjuk a kanonikus production runtime truth-ot: `/home/sharityh/app`
- a deploy tooling explicit ellenőrzést kap arra, hogy `public_html` csak entry wrapper és valóban az `app` runtime-ra mutat

## Protected files touched
- `bin/deploy-wpcontent-map.sh`
- `docs/impactshop-deploy.md`

## Touched files
- `bin/post-deploy-activate.sh`
- `docs/impactshop-production-deploy-path-audit-2026-04-20.md`
- `docs/impactshop-guard-deploy-path-fix-followup-2026-04-20.md`
- `notes.md`
- `system-status-snapshot.md`

## Kockázat
- alacsony-közepes
- a módosítás nem a live runtime viselkedését, hanem a deploy/origin ellenőrzési réteget pontosítja
- fő kockázat: túl szigorú origin-check megakaszthat production mapping deployt, ha a wrapper struktúra változik

## Rollback
- git rollback a follow-up branchen vagy revert a merge commitra
- ha az új origin-check tévesen blokkol deployt, ideiglenesen visszaállítható a korábbi `bin/deploy-wpcontent-map.sh`
- operatív rollback smoke:
  - `bash -n bin/deploy-wpcontent-map.sh`
  - `bash -n bin/post-deploy-activate.sh`

## Smoke scope
- `route:impact-challenge`
- `flow:video-start`
- `browser:chrome`
- `browser:webkit`
- `route:impactshop`
- `flow:go-deal`

## Ellenőrzés
- távoli bizonyíték: `/home/sharityh/public_html/index.php` ténylegesen `../app/wp-blog-header.php`-ra mutat
- `.deploy.production.env` továbbra is `/home/sharityh/app` runtime truth-ot nevez meg
- `bash -n` zöld a módosított deploy scriptekre
- publikus HTML és header továbbra is `2.5.66` ads-watch runtime-ot mutat
