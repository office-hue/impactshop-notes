# 107. Beszélgetés összefoglaló: Fragment prewarm script + futtatás

## Áttekintés
A kérést követve létrehoztam egy verziókezelt shell szkriptet a fragment cache-ek rendszeres előmelegítésére, majd lefuttattam production és staging környezeten is.

## Megfigyelések
- Új fájl: `scripts/impact-fragment-prewarm.sh`. Paraméterezhető (`production|staging|both`), SSH-n keresztül a megfelelő WordPress könyvtárba lép, majd WP-CLI-vel sorban meghívja a fő shortcódokat: `[impact_ticker]`, `[impact_leaderboard tab="ngo"]`, `[impact_leaderboard tab="shop"]`, `[impact_activity]`, `[impactshop_netflix]`, `[impact_deals_netflix]`, `[impact_coupons_netflix]`, `[impactshop_deals_banners]`. Minden lépés STDOUT-ra logolódik.
- Az első futás (`./scripts/impact-fragment-prewarm.sh`) sikeres volt mindkét környezeten; a parancs 0-val tért vissza, és minden shortcode 200-as választ adott, így a fragment transiensek ismét feltöltődtek.

## Következő lépések
1. Ütemezd a szkriptet (pl. `crontab`/GitHub Actions) óránkénti futásra, hogy a fragmentek folyamatosan melegek maradjanak.
2. Ha új fragmentet vezetünk be, add hozzá a szkript végéhez egy további `run_wp` sort.
