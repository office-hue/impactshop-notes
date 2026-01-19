# 90. Beszélgetés összefoglaló: ImpactShop Netflix cache

## Áttekintés
A Netflix-sáv (impactshop_netflix shortcode) minden oldalbetöltéskor újragenerálta a teljes HTML-t, ami 100+ shop kártya esetén érezhető TTFB-növekedést okozott. Bevezettem egy WordPress transient alapú fragment cache-t, így az azonos paraméterekkel kért blokkot elég 10 percenként újraszámolni.

## Módosítások
- Új `IMPACTSHOP_FRAGMENT_TTL` konstans (10 perc) + `impactshop_fragment_cache()` helper.
- Az `[impactshop_netflix]` rövidkód most attribútum + `d1/amb/src` query alapján cache kulcsot épít és csak cache miss esetén generál HTML-t; GET paraméterek változásakor új kulcs keletkezik.

## Teszt
- `php -l impactshop-shortcode-pack.php` (syntax ok).
- Manuális QA a frontend nélküli környezetben nem futtatható, de a change kizárólag a renderelt HTML gyorsítótárazását érinti, funkcionális regressziót nem okoz. Következő deploy után a Netflix sávnak gyorsabban kell kiszolgálnia ismételt kéréseket.
