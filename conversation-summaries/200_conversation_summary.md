# 200. Beszélgetés összefoglaló: Impi kártya szélesség + header fix (2025-12-05 21:47)

## Áttekintés
A kérés szerint kiderítettem, miért nem megfelelő a lebegő Impi chat kártya méretezése: a korábbi patch csak a mini Impi méretét csökkentette, miközben a teljes buborék továbbra is 320 px-re volt korlátozva, és sablon-CSS felülírta a vízszintes elrendezést.

## Megoldás
- Az `impactshop-impi-chat.php` inline CSS-ében a konténer szélessége most `min(340px, calc(100% - 32px))`, így desktopon a kért 340 px-es limit érvényesül, mobilon továbbra is teljes szélességre vált.
- A `.chat-dock` blokkot erősebb (unique) flex beállításokkal láttam el (`display:flex!important; flex-direction:row!important; flex-wrap:nowrap; gap:12px;`), így az Impi animáció és a headline egymás mellett marad akkor is, ha más CSS befolyásolná.
- Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` (prod+staging, cache flush) + `~/bin/impactall` (staging 200 / 1114 ms, production 200 / 1118 ms; 13/13 PASS).
- A változásról bejegyzés készült a `notes.md`-ben („Impi kártya szélesség + header fix (21:47)”).

## Következő lépések
1. Ha további méret- vagy layout-igény érkezik, elég az inline CSS-t finomhangolni ugyanebben a MU plugin fájlban, majd ugyanígy hotfix deploy + guard futtatás.
