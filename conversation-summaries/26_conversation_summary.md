# 26. Beszélgetés összefoglaló: Manuális workflow-t követő share pass script

## Áttekintés
Újraírtam a share pass rebuild eszközt, hogy pontosan a korábbi Bátor-sablonos kézi folyamatot kövesse, majd ismét legyártottam és deployoltam a problémás slugok pkpass-ait.

## Főbb változások
- `scripts/wallet/rebuild-share-pass.sh <slug> [rendszerüzenet]` mostantól mindig az `impactshop-share-card-base-bator.pkpass`-ból indul, az API-ból csak az értékeket (összeg, rank, slugos CTA, tombola/videó link, Sharity hírek) emeli át, majd manifest + `openssl smime` aláírással készíti el a statikus csomagot. Ha nem adok meg külön szöveget, de az API `announcement.url`-t küld, a script automatikusan létrehozza a "Rendszerüzenet" mezőt (szöveg + URL kombináció); eltérő üzenet második paraméterként adható.
- A szkriptet lefuttattam `bator-tabor-alapitvany`, `mbe`, `csoda-emma-mosolyaert-alapitvany`, `patronus-haz-kozhasznu-nonprofit-kft` és `adamremenye` slugokra; mindegyikhez új timestampelt pkpass + canonical fájl készült a `wallet-pass-downloads/` mappában.
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh ...` frissítette az öt pass-t prod/staging környezeten, cache flush után `~/bin/impactall` továbbra is zöld (baseline WARN változatlan).

## Következő lépések
- Ha újabb slugot kell frissíteni, futtasd a `scripts/wallet/rebuild-share-pass.sh <slug>` szkriptet, majd deployold a pkpass-t (`HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-<slug>.pkpass`). Így garantáltan a sablonnal egyező szerkezet kerül ki minden kártyára.
