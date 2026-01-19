# 201. Beszélgetés összefoglaló: Impi widget ideiglenes lekapcsolása (2025-12-05 21:50)

## Áttekintés
A tartós méretezési problémák miatt a kérés az volt, hogy az Impi lebegő chat egyelőre teljesen tűnjön el az Impact Shop oldalról, és majd holnap térjünk vissza a vizuális finomhangolásra.

## Megoldás
- A `impactshop_impi_render_floating_widget()` függvény elejére került egy korai `return`, így frontenden nem renderelődik a `[impactshop_impi_chat]` shortcode.
- Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` (prod+staging, cache flush).
- Guard ellenőrzés: `~/bin/impactall` (staging 200 / 1216 ms, production 200 / 947 ms; 13/13 PASS).
- A módosítást rögzítettem a `notes.md`-ben („Impi widget ideiglenes lekapcsolása (21:50)”).

## Következő lépések
1. Ha kész a kívánt dizájn, elég eltávolítani a korai `return`-t, majd újra hotfix-szel deployolni + guard futtatni.
