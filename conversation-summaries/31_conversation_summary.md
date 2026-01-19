# 31. Beszélgetés összefoglaló: Dányi Apró Paták LSE share pass jóváhagyás + deploy

## Áttekintés
Jóváhagytam a Dányi Apró Paták LSE slugot prod/staging környezeten, hivatalosan újrageneráltam a share pass-t az API-ból, majd hotfix-sync segítségével deployoltam.

## Fő lépések
- `ssh sharityh@cp40.ezit.hu` → `/usr/local/bin/wp --path=/home/sharityh/app impactshop ngo-card approve --slug=danyi-apro-patak-lse --name='Dányi Apró Paták LSE'` (majd ugyanez `app-staging`), így a `/wp-json/impact/v1/ngo-card/danyi-apro-patak-lse` endpoint élő adatot ad (24 Ft, rank 10, rising badge).
- `scripts/wallet/rebuild-share-pass.sh danyi-apro-patak-lse` legyártotta a `wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse-20251129T144651.pkpass` csomagot, amely már az API announcement + CTA linket tükrözi.
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` feltolta a pkpass-t prod/stagingre és cache flush-ölte a környezeteket.
- `~/bin/impactall` lefutott (staging 200/1881 ms, production 200/1363 ms), minden guard PASS maradt.

## Következő lépések
- Ha a slug statjai változnak, időszakosan futtasd újra a rebuild szkriptet, hogy a statikus share pass is az API-val maradjon szinkronban.
