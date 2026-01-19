# 106. Beszélgetés összefoglaló: KPI fragment cache hotfix deploy

## Áttekintés
A KPI fallback shortcódok új fragment cache rétegét kellett kitolni production + staging környezetre, majd ellenőrizni, hogy a /impactshop oldal mindkét nézetben rendben betölt.

## Megfigyelések
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impact-combat-pack.php` lefutott (prod + staging rsync, transient purge, cache flush; PHP 8.3.27 vs 8.4.14 mismatch csak warning).
- `wp eval 'wp_set_current_user(1); echo apply_filters("the_content", get_post_field("post_content", 16348));'` és az anonim `curl --http1.1 https://app.sharity.hu/impactshop/` egyaránt 200-as HTML-t adott.
- `[impact_ticker]` rövidkód WP-CLI-ből futtatva rendben működött, az új HTML fragment cache ennek során is felépült (Redisben tárolódik, ezért `wp transient list` nem mutatja).

## Következő lépések
1. Ha másik KPI blokkot módosítunk, ugyanígy kell lefuttatni a hotfix szinkront és a két ellenőrző parancsot.
2. Amennyiben fragment jelenlétét kell bizonyítani, használjuk a `scripts/diagnostics/fragment-probe.php` szkriptet.
