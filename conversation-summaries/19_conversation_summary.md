# 19. Beszélgetés összefoglaló: Ádám Reménye share pass + impactall

## Áttekintés
Újrageneráltam az `impactshop-share-card-adamremenye.pkpass` fájlt a CTA és `sharity_news` guard szabályok szerint, majd hotfix-szinkronnal feltöltöttem prod/staging környezetre és lefuttattam az impactall ellenőrzést.

## Főbb változások
- A pass.json frissítve: a `storeCard.backFields` első eleme a slugos Impact Shop linket tartalmazó CTA anchor, a `sharity_news` + `announcement` mezők az API `announcement.text` értékét tükrözik, a QR/barcode is az új URL-t mutatja, új `serialNumber` generálva.
- Új manifest és `signature` készült a `wallet-pass-downloads/tmp_rebuild/{cert,key,AppleWWDRCAG4}.pem` tanúsítványokkal, majd `wallet-pass-downloads/impactshop-share-card-adamremenye.pkpass` felülírva + timestamp backup mentve.
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-adamremenye.pkpass` lefutott; mindkét környezeten cache flush futott, végül `~/bin/impactall` igazolta, hogy a guard zöld (csak a baseline figyelmeztetés maradt).

## Következő lépések
- Pótolni kell a `impactshop-baseline-2025-11-02.md` hiányzó baseline dokumentumot, hogy az impactall riport tiszta legyen.
