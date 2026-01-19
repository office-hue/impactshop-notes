# 105. Beszélgetés összefoglaló: Impact KPI shortcódok fragment cache-e

## Áttekintés
A kérés szerint a korábban javasolt „biztonságos gyorsítások” közül implementáltam a fallback KPI shortcódok (ticker / leaderboard / activity) fragment cache rétegét az `impact-combat-pack` MU pluginben.

## Megfigyelések
- Új helper: `ims_fragment_cache()` + `IMS_FRAGMENT_TTL` (5 perc) az `impact-combat-pack.php` elején; a helper md5-elt kulccsal tárolja a HTML-t, és csak akkor ment, ha a callback `cacheable=true` (default). API hiba esetén a rövidkód továbbra is visszaadja a „Nincs adat” panelt, de nem kerül cache-be.
- `[impact_ticker]`: kulcs `impact_ticker_shortcode`, cache misskor meghívja a `/wp-json/impact/v1/ticker` végpontot, majd a teljes KPI blokkot eltárolja.
- `[impact_leaderboard]`: kulcs `impact_leaderboard_{tab}`, így az NGO és SHOP fül külön fragmentbe kerül; üres lista esetén nem cache-el.
- `[impact_activity]`: kulcs `impact_activity`, az aktivitás lista HTML-je kerül transientbe, csak akkor ment, ha legalább egy elem van.
- `php -l wp-content/mu-plugins/impact-combat-pack.php` futtatva (OK), további funkcionális tesztet nem igényelt, mert kizárólag cache-réteg adódott hozzá.

## Következő lépések
1. Ha új KPI blokkot vagy designvariánst vezetünk be, érdemes ugyanígy `ims_fragment_cache` alá tenni, hogy Woocommerce/Elementor oldalak se gyártsanak többlet REST hívást.
2. Amennyiben mélyebb JSON-szintű cache szükséges, a helper kibővíthető úgy, hogy adat- és HTML-szinten is visszaadja az eredményt (pl. debug célra).
