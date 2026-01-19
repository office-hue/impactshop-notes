# 32. Beszélgetés összefoglaló: Dányi pass logócsere

## Áttekintés
A felhasználó jelezte, hogy a Dányi Apró Paták LSE share pass ugyan frissült, de a logók nem cserélődtek. Újraraktam a pkpass-t dedikált vizuálokkal, majd ismét deployoltam és guardot futtattam.

## Fő lépések
- Kicsomagoltam a canonical `impactshop-share-card-danyi-apro-patak-lse.pkpass` fájlt, a kampány OG képéből `sips` segítségével legeneráltam a 1x/2x/3x logókat és ikonokat, majd ezeket bemásoltam a passba.
- Új `manifest.json` + `openssl smime` aláírás készült, a csomag `wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse-20251129T155516.pkpass` néven került elmentésre (canonical fájl frissült).
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` újból deployolta a pass-t prod/stagingre, cache flush-sel.
- `~/bin/impactall` lefutott (production 200/1234 ms, staging hívás most timeoutolt, de ismert redirect-probléma), guard WARN továbbra sincs.

## Következő lépések
- Ha további slughoz kell egyedi logó, ugyanígy kell OG képből előállítani a png-ket, vagy bővítsük a rebuild szkriptet automatizált logóletöltéssel.
