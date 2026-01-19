# 22. Beszélgetés összefoglaló: Ádám Reménye pass – Sharity hírek marad, rendszerüzenet törölve

## Áttekintés
Újrageneráltam az `impactshop-share-card-adamremenye.pkpass` fájlt úgy, hogy a hátlapon csak a kötelező `sharity_news` mező maradjon, azonos tartalmú `announcement` blokk nélkül.

## Főbb lépések
- Kibontottam a legutóbbi pass-t, frissítettem a `backFields` tömböt (CTA + tombola + videó + sharity_news), kihagytam a felesleges `announcement` mezőt, és új `serialNumber`-t állítottam be.
- Új `manifest.json` és `signature` készült a meglévő Apple Wallet tanúsítványokkal, majd a csomagot timestampelt néven és canonical fájlként is elmentettem a `wallet-pass-downloads` mappába.
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-adamremenye.pkpass` deployolta a pass-t prod/staging környezetre, cache flush után `~/bin/impactall` lefutott (csak a baseline hiány WARN maradt).

## Következő lépések
- Ha új rendszerüzenetet kell kommunikálni, külön `announcement` mezőt csak akkor adj hozzá, ha szövege eltér a Sharity hírektől; ellenkező esetben hagyd ki, hogy a pass hátlapja letisztult maradjon.
