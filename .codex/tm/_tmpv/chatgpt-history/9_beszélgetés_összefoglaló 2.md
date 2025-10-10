# 9. beszélgetés - Impact Shop: Automatikus Dognet API integráció és banner rendszer továbbfejlesztése

**Dátum:** 2025-01-11  
**Fő téma:** Automatikus Dognet API bejelentkezés, token kezelés, banner rendszer stabilizálása

## Kulcs fejlesztések

### 1. Automatikus Dognet API bejelentkezés
- **Email:** office@sharity.hu  
- **Jelszó:** cuXsuj-8wenbo-kimnac
- **Token TTL:** 20 óra (biztonsági ráhagyással)
- **401 hiba kezelés:** Automatikus token frissítés
- **Fallback:** Legacy go.dognet.com rendszer

### 2. Összevont snippet architektúra
**Komponensek:**
- Dognet API auto-login és token management
- /go és /go-deal endpointok
- impactshop_scroller, impactshop_catalog, impactshop_diag shortcode-ok
- Banner injektálás és CSS highlighting
- Cache és error handling

### 3. Banner rendszer fejlesztések
**Probléma:** Banner CSV gyakran üres → scroller eltűnik
**Megoldás:** Fallback banner generálás
- Ha Banner CSV üres → automatikus "teszt" bannerek kategóriánként
- Véletlenszerű shop kiválasztás minden kategóriából
- "AKCIÓ" jelvény és kiemelés (100px magasság vs 60px shopok)

### 4. Shortcode funkcionalitás

#### impactshop_scroller
```php
[impactshop_scroller inject_every="3" speed="25"]
```
- Shop logók (60px) + banner injektálás (100px)
- "AKCIÓ" jelvény bannereken
- Fallback: ha nincs banner, generál kategóriánként

#### impactshop_catalog
```php
[impactshop_catalog show_tabs="1" search="1"]
```
- Kategória fülek
- Keresés funkció
- Várható adomány kalkuláció (commission/2)

#### impactshop_diag
```php
[impactshop_diag]
```
- Shop/banner számok
- Hiányzó mezők ellenőrzése

#### impactshop_debug (fejlesztői)
```php
[impactshop_debug]
```
- CSV URL-ek megjelenítése
- Minta banner struktúra

## Technikai architektúra

### Token management
```php
function dognet_get_token($force_refresh = false) {
  // Auto-login logic
  // 20 órás cache
  // 401 retry mechanizmus
}
```

### API request handling
```php
function dognet_api_request($method, $path, $body=null) {
  // Bearer token authentication
  // 401 auto-retry
  // Error handling
}
```

### Link generation flow
1. **API first:** dognet_api_generate_link() Dognet Publisher API-val
2. **Fallback:** Legacy go.dognet.com URL építés
3. **UTM propagation:** sharity, impactshop tags

### CSV konfiguráció
```php
'shops_csv_url'   => '.../gid=0&single=true&output=csv',
'banners_csv_url' => '.../gid=328401803&single=true&output=csv&v=3',
```

## Használat

### Telepítés
1. Snippet beillesztése WPCode-ba
2. Adminként megnyitni: `https://app.sharity.hu/?impactshop_refresh=1`
3. Rewrite rules flush

### Oldal létrehozás
```html
[impactshop_scroller inject_every="2" speed="20"]
[impactshop_catalog show_tabs="1" search="1"]
```

### NGO kód használat
- Menü link: `/akciok/?d1=ZOLDISKOLA&amb=holloko`
- Automatikus redirect: /go/{shop_slug} → Dognet API → céloldal

### Cache frissítés
```php
// banners_csv_url végén
&v=3 → &v=4 (azonnal frissít)
```

## Banner CSV formátum
```csv
img,href,label,category
https://example.com/image.jpg,https://form.fillout.com/t/eM61RLkz6jus?shop=4home&u=...,Őszi kiárusítás – 4home,Otthon
```

## Problémamegoldások

### Banner tűnik-eltűnik jelenség
**Ok:** Üres Banner CSV  
**Megoldás:** Fallback banner generálás kategóriánként

### Token lejárat
**Ok:** 24 órás Dognet token  
**Megoldás:** 20 órás cache + automatikus refresh + 401 retry

### CSS banner kiemelés
**Probléma:** Bannerek nem látszottak akcióként  
**Megoldás:** 
- `.banner-item img {height:100px}` vs `.shop-item img {height:60px}`
- "AKCIÓ" jelvény pozicionálás
- Box-shadow és border-radius

### Namespace XML parsing (Apps Script)
**Probléma:** Banner feed parsing hibák  
**Megoldás:** `pickTextNS()` namespace-agnostic parser

## Teljesítmény optimalizálás

### Cache stratégia
- **Shops CSV:** 15 perc
- **Banners CSV:** 15 perc  
- **Dognet token:** 20 óra
- **Cache-buster:** &v= paraméter

### Error handling
- API timeout: 20 másodperc
- WP_Error propagation
- Graceful fallback minden szinten

## Következő lépések
1. Apps Script banner automation továbbfejlesztése
2. Banner CSV automatikus populálás
3. Performance monitoring
4. A/B testing banner injection frequency

## Kódbázis állapot
- **Konszolidált snippet:** ✅ Minden funkció egyetlen fájlban
- **Auto-login:** ✅ 24/7 működőképes
- **Banner fallback:** ✅ Mindig van mit megjeleníteni
- **Error handling:** ✅ Robosztus hibakezelés
- **Documentation:** ✅ Teljes használati útmutató

**Összesen:** 400+ sor PHP kód, teljes Impact Shop megoldás automatikus Dognet API integrációval és fejlett banner menedzsmenttel.