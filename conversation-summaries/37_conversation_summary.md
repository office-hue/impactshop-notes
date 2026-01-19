# 37. Beszélgetés összefoglaló: Dányi pass képek eltávolítása

## Áttekintés
A kérés szerint a Dányi Apró Paták LSE share passról le kellett venni a képet, miközben minden más slug maradjon változatlan.

## Fő lépések
- Kicsomagoltam a canonical pkpass-t, majd base64-ből előállított átlátszó PNG-t 29/58/87 px ikonra és 160/320/480 px logóra méreteztem (`sips`), így a pass frontján többé nincs kép.
- Új `manifest.json` + `signature` készült, a friss csomag `impactshop-share-card-danyi-apro-patak-lse-20251129T171754.pkpass` néven került mentésre, és canonical néven is felülírta az előző fájlt.
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` deployolta prod+stagingre, majd `~/bin/impactall` lefutott (staging 200/2656 ms, production 200/1252 ms), minden guard PASS állapotban maradt.

## Következő lépések
- Ha a képet vissza kell hozni, a sablonból (Ádám-mintából) lehet újra generálni a logókat, majd ugyanígy deployolni.
