# 93. Beszélgetés összefoglaló: Netflix MU plugin require + deploy

## Áttekintés
A WebP/Lazy változtatások működéséhez a MU pluginban is ugyanazt a kódot kellett használnunk, ezért a fallback shortcode fájlt kiegészítettem egy `require_once` guarddal, majd production/staging környezetre is hotfix deploy ment ki.

## Lépések
- `wp-content/mu-plugins/impactshop-netflix-shortcodes.php` tetején most megpróbálja betölteni az `impactshop-shortcode-pack.php`-t (ABSPATH alatt); ha sikerül, azonnal `return`, így nincs több duplikált kód.
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-netflix-shortcodes.php` → prod + staging rsync + cache flush.
- `wp eval 'echo do_shortcode("[impactshop_netflix max_items=1]");' | rg '<picture'` igazolta, hogy a frontend tényleg `<picture>`-t ad vissza, tehát az új WebP helper fut élőben.

## Állapot
- WebP konverziók már a szerveren vannak (ngo-logos könyvtár WebP párokkal + backup), a rövidkód pedig ugyanabból a forrásból fut, így a slider automatikusan preferálja a `.webp` fájlokat.
