# 30. Beszélgetés összefoglaló: Dányi Apró Paták LSE share pass manuális rebuild

## Áttekintés
A user kérésére kézzel legyártottam a Dányi Apró Paták LSE statikus Wallet share passát a jóváhagyott sablonból.

## Fő lépések
- Az `impactshop-share-card-template.pkpass` állományt kitömörítve frissítettem a `pass.json` mezőket (slug: `danyi-apro-patak-lse`, CTA + QR, videós támogatás link, tombola URL, amount/rank becslés a `wp-json/impactshop/v1/totals?group=ngo` alapján).
- Letöltöttem az `adomany.sharity.hu/kampanyok/16284580` OG képét, majd `sips` segítségével legeneráltam a 1x/2x/3x logókat (160×50 → 480×150) és az iconokat (29×29, 58×58), amelyeket bemásoltam a passba.
- Új `manifest.json` + `signature` született a `wallet-pass-downloads/tmp_rebuild/{cert,key,AppleWWDRCAG4}.pem` párossal; a kész csomag `wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse-20251129T144327.pkpass` néven került mentésre (canonical fájl is frissült). Deploy még pending, mert a slug API jóváhagyása hiányzik.

## Következő lépések
- Ha az `impact/v1/ngo-card/danyi-apro-patak-lse` endpoint elérhető lesz, fusd le a szkriptelt rebuildet, hogy az összeg/rang és guardok garantáltan egyezzenek.
- Amint a passot deployolni kell, a szokásos `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` parancsot futtasd prod/staging környezeten, utána `~/bin/impactall` guard.
