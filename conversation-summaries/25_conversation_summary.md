# 25. Beszélgetés összefoglaló: Share pass rebuild szkript + négyes deploy

## Áttekintés
A manuális pkpass workflow-t teljesen automatizáltam egy új szkripttel, majd lefuttattam Bátor Tábor, MBE, Csoda Emma és Patrónus Ház slugokra, végül élesbe toltam a friss csomagokat.

## Fő lépések
- Elkészült a `scripts/wallet/rebuild-share-pass.sh` segéd: a Bátor-sablont használva API-adatokat húz be, kitölti a slugos CTA blokkot, a tombola/videó linkeket, a `sharity_news` mezőt és újra-aláírja a pass-t (manifest + openssl smime). Az output a `wallet-pass-downloads/impactshop-share-card-<slug>-<ts>.pkpass` fájl.
- A szkriptet lefuttattam a négy kritikus slugra (`bator-tabor-alapitvany`, `mbe`, `csoda-emma-mosolyaert-alapitvany`, `patronus-haz-kozhasznu-nonprofit-kft`), ezzel egységes, slugos CTA-t használó passok készültek.
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh ...` deployolta mind a négy pkpass-t prod/staging környezetre, cache flush után `~/bin/impactall` lefutott (csak a baseline WARN maradt).

## Következő lépések
- Ha új slugot kell frissíteni, futtasd a `scripts/wallet/rebuild-share-pass.sh <slug>` szkriptet, majd a hotfix-syncet; így garantáltan a sablonnak megfelelő, slugos CTA-t és API announcementet tartalmazó pass kerül ki.
