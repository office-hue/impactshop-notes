# 98. Beszélgetés összefoglaló: ImpactShop hotfix deploy + verifikáció

## Áttekintés
A cél a frissített shortcode pack + MU plugin kód prod/staging környezetre juttatása és annak ellenőrzése volt, hogy az ImpactShop oldal bejelentkezett admin felhasználóknak is hiba nélkül renderelődik.

## Megfigyelések
- A `dognet_get_token()` hívásából hiányzott a `method` beállítás, így a WordPress GET-ként futtatta a token kérést, majd a JSON body stringre `http_build_query()`-t próbált hívni, ami PHP 8.3 alatt `TypeError`-t dobott („Súlyos hiba” panelt eredményezve admin oldalon).
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh impactshop-shortcode-pack.php wp-content/mu-plugins/impactshop-netflix-shortcodes.php` lefutott: mindkét fájl rsync-e közben PHP-verzió figyelmeztetés jelent meg (prod/staging 8.3.27 vs. lokális 8.4.14), de a script cache flush-sal zárt.
- Verifikáció: `wp eval 'wp_set_current_user(1); echo apply_filters("the_content", get_post_field("post_content", 16348));'` sikeresen renderelte az egész oldalt, `curl --http1.1 https://app.sharity.hu/impactshop/` pedig 200-as státuszt adott (271 kB letöltött HTML, nincs több „Súlyos hiba” panel).

## Következő lépések
1. Figyeld a Dognet API hívások logjait, de a POST token request most már explicit metódust állít, így a TypeError nem térhet vissza.
2. Ha hasonló helper más pluginban is van, futtasd végig ugyanazt az auditot, hogy mindenhol be legyen állítva a `method` és az `Accept` header.
