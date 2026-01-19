# 35. Beszélgetés összefoglaló: Dányi pass ikon@3x javítás

## Áttekintés
A manuális share passból hiányzott az `icon@3x.png`, ezért Safariban nem nyílt meg. Újracsomagoltam a Bátor-sablont úgy, hogy minden retina méret bekerüljön.

## Fő lépések
- Újra kitömörítettem a `impactshop-share-card-base-bator.pkpass` fájlt, frissítettem a `pass.json`-t az API adataival, majd az OG képből 1×/2×/3× logókat és 29/58/87 px ikonokat készítettem (`sips`).
- Az összes ikon/logó hash bekerült az új `manifest.json`-ba, `openssl smime`-mel új `signature` készült.
- Az így kapott `impactshop-share-card-danyi-apro-patak-lse-20251129T161236.pkpass`-t deployoltam `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh …` paranccsal prod+stagingre, majd `~/bin/impactall` lefutott (staging 200/1842 ms, production 200/1223 ms).
- A pass most már tartalmazza az `icon@3x.png` fájlt, így iOS Walletben is érvényesnek számít.

## Következő lépések
- A manuális workflow dokumentációját egészítsük ki az `icon@3x` generálásával, hogy legközelebb se maradjon le.
