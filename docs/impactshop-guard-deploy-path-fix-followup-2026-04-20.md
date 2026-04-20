# Impact Shop Guard Deploy-Path Fix Follow-Up

## Cél

A guard deploy infrastruktúrát össze kell igazítani a tényleges production kiszolgálási útvonallal, hogy a következő protected deploy már ne incidensúton menjen ki.

## Probléma

- A jelenlegi runbook szerint a production deploy célpath: `/home/sharityh/app`
- A 2026-04-19-es incidens során a publikus asset-egyezés alapján az `/home/sharityh/app-staging` is érintettnek bizonyult
- Ez sérti a `Guard scope and source of truth` és a `Canonical guard workflow path` elvet

## Kötelező korrekciók

1. `.deploy.production.env`
- ellenőrizni kell, hogy a benne lévő `REMOTE_WP_PATH` a tényleges production originre mutat-e

2. `docs/impactshop-deploy.md`
- a runbook csak a valós, auditált deploy pathot nevezheti ki production truth-nak

3. `bin/impactshop-guard-deploy.sh`
- ellenőrizni kell, hogy ugyanazt a pathot használja, mint a dokumentáció és a live site

4. `bin/deploy-wpcontent-map.sh`
- production mapping során explicit ellenőrzés kell a tényleges célpathra

5. post-deploy verifikáció
- az ellenőrzésnek nem elég a HTML route vagy health endpoint
- kötelező legyen:
  - asset URL
  - asset hash
  - `X-ImpactShop-AdsWatch-Version` vagy releváns runtime header

## Kész állapot

Ezt akkor tekintjük lezártnak, ha:

- a dokumentált production path és a live origin egyezik
- a guard deploy ugyanarra a pathra ír
- a post-deploy smoke ezt hash/header szinten is igazolja
- protected incidens esetén nem kell többé két pathra párhuzamos restore

## Kapcsolódó anyagok

- `docs/protected-change-records/2026-04-19-ads-watch-mobile-resize-throttle-hotfix.md`
- `docs/impactshop-production-deploy-path-audit-2026-04-20.md`
- `docs/impactshop-deploy.md`
