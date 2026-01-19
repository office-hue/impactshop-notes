# 34. Beszélgetés összefoglaló: Dányi pass manuális rebuild a Bátor sablonból

## Áttekintés
A felhasználó kérésére a Dányi Apró Paták LSE share pass-t teljesen újraépítettem a Bátor Tábor mintából, végigkövetve a manuális workflowt.

## Fő lépések
- A `impactshop-share-card-base-bator.pkpass` sablont kitömörítettem, majd a `/wp-json/impact/v1/ngo-card/danyi-apro-patak-lse` API adatai alapján frissítettem a `pass.json` mezőket (CTA, amount, rank, badge, sharity_news + announcement, barcode, userInfo, serial).
- Az OG képből `sips` segítségével új logó/icon PNG-k készültek (160×50/320×100/480×150 és 29×29/58×58), ezek kerültek be a csomagba.
- Új `manifest.json` + `signature` készült, a friss pkpass `wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse-20251129T160252.pkpass` néven került mentésre (canonical is frissült), majd `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh …` deployolta prod+stagingre.
- `~/bin/impactall` lefutott (staging 200/1650 ms, production 200/1359 ms), minden guard PASS állapotban maradt.

## Következő lépések
- Ha a slug statjai vagy kreatívjai változnak, ugyanezt a sablon → manuális szerkesztés → hotfix → impactall folyamatot kell megismételni.
