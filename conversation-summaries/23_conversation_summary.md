# 23. Beszélgetés összefoglaló: Négy NGO wallet pass újragenerálása

## Áttekintés
A Bátor Tábor, az MBE, a Csoda Emma Mosolyáért és a Patrónus Ház Apple Wallet megosztási kártyáit újraépítettem az új szabály szerint: kötelező slugos CTA blokk + az API-ból érkező announcement kerül a `sharity_news` mezőbe, külön `announcement` mezőt csak akkor tartunk meg, ha eltérő üzenetet kell kommunikálni (most mindnél elhagytuk).

## Főbb változások
- Mind a négy slugra letöltöttem a legfrissebb `/wallet-pass` csomagot, módosítottam a `pass.json`-t (CTA sorrend, tombola/videó linkek, sharity_news), új `serialNumber`-t generáltam, majd manifest + `openssl smime` aláírással `impactshop-share-card-<slug>.pkpass` néven mentettem.
- Az új fejek timestampelt backupot kaptak a `wallet-pass-downloads/` mappában, a canonical fájlok frissültek (`bator-tabor-alapitvany`, `mbe`, `csoda-emma-mosolyaert-alapitvany`, `patronus-haz-kozhasznu-nonprofit-kft`).
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh` egyszerre vitte fel a négy pkpass-t prod/staging környezetre, cache flush után `~/bin/impactall` lefutott (200/200, baseline WARN maradt).

## Következő lépések
- Ha bármelyik NGO külön rendszerüzenetet szeretne kommunikálni, csak akkor adjunk vissza `announcement` mezőt – egyébként maradjon kizárólag a `sharity_news` blokk az API announcementtel.
