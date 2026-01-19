# 104. Beszélgetés összefoglaló: Állandó fragment diagnosztika script

## Áttekintés
Az ideiglenes fragment ellenőrzések helyett egy tartós, verziókezelt diagnosztikai szkriptet készítettem, amely a Netflix/Deals/Coupons rövidkódok fragment cache kulcsait tudja újraszámolni és ellenőrizni.

## Megfigyelések
- Új fájl: `scripts/diagnostics/fragment-probe.php`. A szkript `wp eval-file scripts/diagnostics/fragment-probe.php type=netflix atts="max_items=2" query="d1=bator" preview=200` parancs formában futtatható, és automatikusan előállítja a megfelelő `impactshop_fragment_<hash>` kulcsot.
- Támogatott type értékek: `netflix`, `deals`, `coupons`, valamint `raw` (közvetlen kulcs ellenőrzés). Az `atts` és `query` paraméterek URL query formátumú stringek, a `preview` flaggel pedig szabályozható, mennyi HTML-részlet jelenjen meg.
- A script fallback módon biztosítja a `str_ends_with` függvényt, így PHP 7/8 kompatibilis, és automatikusan használja az `impactshop_q()` segédet a d1/amb/src alapértékekhez.

## Következő lépések
1. Ha új fragment típus kerül bevezetésre, itt egyszerűen bővíthető további `case` ággal.
2. A diag útmutatót érdemes linkelni a belső runbookokban, hogy guard WARN esetén egyből tudjuk futtatni.
