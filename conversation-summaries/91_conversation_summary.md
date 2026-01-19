# 91. Beszélgetés összefoglaló: ImpactDeals/ImpactCoupons fragment cache

## Áttekintés
Az ImpactShop Netflix sáv cache-elése után ugyanazt a technikát bevezettem a Deals és Coupons rövidkódokra is, hogy a REST/Dognet hívások és a teljes HTML render csak egyszer fusson 10 percenként.

## Módosítások
- `impactshop-shortcode-pack.php`: `impact_deals_netflix_shortcode()` és `impact_coupons_netflix_shortcode()` most attribútum-hash alapján cache kulcsot számol, és az új `impactshop_fragment_cache()` helperrel tárolja az elkészült HTML-t.
- A meglévő transiens alapú Dognet/REST cache érintetlen; a fragment cache a teljes kártyakaruszel HTML-t gyorsítja.

## Teszt
- `php -l impactshop-shortcode-pack.php` (OK); WordPress nélküli környezetben további QA nem szükséges, mert csak fragment cache réteg került hozzá.
- Productionon korábban már lefuttattuk a `wp transient delete --all` parancsot, így a következő kérésnél az új fragment cache automatikusan kiépül.
