# 36. Beszélgetés összefoglaló: Manual template + Dányi pass rollback

## Áttekintés
A felhasználó kérésére visszatértem a tegnap esti 100%-ban manuális workflow-hoz: a sablon ismét az Ádám-fájl, a Dányi pass pedig a legelső (14:43-as) kézi build, amit újra deployoltam.

## Fő lépések
- `cp impactshop-share-card-adamremenye.pkpass impactshop-share-card-template.pkpass` – ezzel a manuális rebuild szkript ismét a korábbi mintából dolgozik.
- A `impactshop-share-card-danyi-apro-patak-lse-20251129T144327.pkpass` csomagot visszamásoltam canonical névre, majd `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` feltolta prod+stagingre és cache flush-ölt.
- `~/bin/impactall` lefutott (staging 200/1424 ms, production 200/1339 ms), minden guard PASS maradt.

## Következő lépések
- Ha újra módosítani kell a pass-t, a dokumentációban rögzített manuális workflow szerint járjunk el, hogy elkerüljük a Safari kompatibilitási gondokat.
