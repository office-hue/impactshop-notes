# 14. Beszélgetés Összefoglaló

**Dátum:** 2025. szeptember 20.
**Téma:** Dognet API Token Kezelési Hibák és Stabil Hitelesítési Rendszer Implementálása

## Főbb Témák

### 1. Dognet API Token Problémák Azonosítása
- **Probléma:** Az Impact Shop WordPress snippet számos Dognet API autentikációs hibával küzdött
- **Kiváltó Okok:**
  - Többféle login endpoint próbálkozás (auth/login, publisher/login, login)
  - Token formátum inkonzisztenciák ("Bearer " előtag kezelése)
  - Cloudflare védelem blokkolja egyes végpontokat
  - Hiányzó token cache invalidation

### 2. Hivatalos API Dokumentáció Szerinti Megoldás
- **Egyetlen Endpoint Stratégia:** Kizárólag `/api/v1/auth/login` használata
- **Token Élettartam:** 24 órás JWT token, minden használattal automatikusan hosszabbodik
- **Cache Stratégia:** WordPress transient rendszer 20 órás TTL-lel (biztonsági ráhagyás)
- **Automatikus Refresh:** 401/403/419 HTTP státuszkód esetén egyszeri token frissítés

### 3. Robusztus Token Normalizálás
```php
function dognet__normalize_token($tok) {
  $tok = trim((string)$tok);
  $tok = preg_replace('~^(?:"|\')?(?:Bearer\s+)?([A-Za-z0-9\-\._]+)(?:"|\')?$~', '$1', $tok);
  return $tok ?: '';
}
```

### 4. Többszintű Fallback Mechanizmus
- **Elsődleges:** JSON payload POST request
- **Másodlagos:** Form-encoded payload fallback
- **Token Kinyerés:** Hierarchikus keresés (token, access_token, data.token, data.access_token)

### 5. Konverziós Riportok API Átállása
- **Probléma:** Hibás `/conversions` endpoint használata (404 hiba)
- **Megoldás:** Átállás `/raw-transactions/filter` végpontra
- **Lapozás:** `last_id` alapú görgetés `page/per_page` helyett
- **Státusz Szűrés:** `rstatus` mező (`A`=approved, `P`=pending, `D`=declined)

### 6. NGO-alapú Szűrési Rendszer
- **Cél:** Specifikus szervezetek riportjainak elkülönítése
- **Implementáció:** `ngo=` paraméter a shortcode-ban és REST endpoint-ban
- **Data1 Intelligens Kiválasztás:**
  ```php
  function impactshop_pick_ngo_from_row($row) {
    // 1. Slug-szerű értékek preferálása
    // 2. Nem-numerikus értékek
    // 3. Fallback az első elérhető értékre
  }
  ```

### 7. Statikus vs. Dinamikus Token Támogatás
- **Dinamikus:** Automatikus login és cache management
- **Statikus:** `DOGNET_API_TOKEN` konstansba írt JWT használata
- **Hibakezelés:** Statikus token esetén explicit hibaüzenetek

### 8. Diagnosztikai Eszközök
- **Token Refresh URL:** `/?impactshop_token=refresh`
- **Részletes Diagnosztika:** `/?impactshop_token=refresh&diag=1`
- **Kimenet:** Sikeres/sikertelen próbálkozások részletes listája

## Technikai Implementáció

### Cache Kulcs Stratégia
```php
$cache_key = sprintf('impactshop_totals_%s_%s_%s_%s_%s', 
  $from, $to, $status, $group, md5(strtolower($filter_ngo)));
```

### API Request Wrapper
```php
function dognet_api_request($method, $path, $body=null) {
  // 1. Token megszerzése
  // 2. Authorization header beállítása
  // 3. 401 esetén automatikus token refresh
  // 4. Retry egyszer új tokennel
}
```

### Raw Transactions Filter
```php
$filter = [
  ['created_at' => ['gte' => $fromDt]],
  ['created_at' => ['lte' => $toDt]],
];
if ($rstatus) $filter[] = ['rstatus' => ['in' => $rstatus]];
```

## Végeredmény

### Stabil Komponensek
1. **Token Management:** 24h cache, automatikus refresh, normalizálás
2. **API Integration:** Hivatalos végpontok, helyes formátumok, robusztus hibakezelés
3. **Reporting System:** NGO-szűrés, státusz-alapú riportok, cache optimalizálás
4. **Diagnostics:** Átlátható hibaüzenetek, részletes endpoint tesztelés

### Támogatott Használati Módok
```php
// Shortcode
[impactshop_report from="2025-09-01" to="2025-09-20" status="pending" group="shop_ngo" ngo="bator-tabor-alapitvany"]

// REST API
/wp-json/impactshop/v1/totals?from=2025-09-01&to=2025-09-20&status=pending&group=shop_ngo&ngo=bator-tabor-alapitvany
```

### Teljesítmény Optimalizálás
- **Last_ID Pagination:** Hatékony nagy adatmennyiség kezelése
- **Intelligent Caching:** Paraméterfüggő cache kulcsok
- **Minimal API Calls:** Batch processzálás 200 rekord/kérés

## Tanulságok

1. **API Dokumentáció Követése:** Hivatalos végpontok használata kritikus a stabilitáshoz
2. **Fallback Stratégiák:** Többszintű hibakezelés biztosítja a működést
3. **Token Normalizálás:** Különböző API válasz formátumok egységes kezelése
4. **Cache Invalidation:** Intelligent cache kulcsok és TTL kezelés
5. **Diagnostics:** Részletes hibaüzenetek felgyorsítják a hibakeresést

Ez a beszélgetés alapvetően átalakította az Impact Shop Dognet API integrációját egy töredékestől egy production-ready, robusztus rendszerré.