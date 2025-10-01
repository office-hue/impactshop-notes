# Nyereményjáték Rendszer - Technikai Specifikáció

## Áttekintés
WordPress alapú nyereményjáték rendszer Impact Shop vásárlóknak, B verzió implementációval (csak vásárlók vehetnek részt).

## Rendszer Architektúra

### Adatbázis Táblák

#### `wp_impactshop_raffle_entries`
```sql
- id (PK)
- promo_slug (kampány azonosító)
- entry_uid (hash alapú egyedi azonosító)
- email (vásárló email)
- shop_slug (webshop azonosító)
- ngo_code (NGO kód d1 paraméterből)
- dognet_order_id (rendelés azonosító)
- dognet_program_id (program azonosító)
- dognet_click_id (kattintás azonosító)
- amount (rendelés értéke)
- currency (pénznem)
- state (pending/approved/rejected)
- created_at (létrehozás időpontja)
```

#### `wp_impactshop_totals`
```sql
- id (PK)
- promo_slug (kampány azonosító)
- shop_slug (webshop azonosító)
- ngo_code (NGO kód)
- total_approved (jóváhagyott összeg)
- orders_approved (jóváhagyott rendelések száma)
- total_pending (függőben lévő összeg)
- orders_pending (függőben lévő rendelések száma)
- updated_at (frissítés időpontja)
```

## Konfigurációs Konstansok

```php
define('IMPACTSHOP_PROMO_SLUG', 'impactshop-2025-q4');
define('IMPACTSHOP_RAFFLE_START', '2025-10-01 00:00:00');
define('IMPACTSHOP_RAFFLE_END', '2025-12-31 23:59:59');
define('IMPACTSHOP_TIMEZONE', 'Europe/Budapest');
define('DOGNET_API_BASE', 'https://api.dognet.example/v2');
define('DOGNET_PUBLISHER_ID', 'YOUR_PUBLISHER_ID');
define('DOGNET_API_TOKEN', 'YOUR_SECRET_TOKEN');
define('IMPACTSHOP_TICKETS_PER_APPROVED_ORDER', 1);
```

## Folyamat Leírása

### 1. Automatikus Szinkronizáció (Cron)
- **Gyakoriság**: Óránként
- **Funkció**: Dognet 2.0 API-ból konverziók lekérése
- **Szűrés**: Csak a kampány időszakában történt rendelések
- **Deduplication**: (order_id + program_id) kulcs alapján

### 2. Sorsjegy Generálás
- **Feltétel**: Csak `approved` státuszú rendelések
- **Szabály**: 1 jóváhagyott rendelés = 1 sorsjegy (alapértelmezett)
- **Opcionális**: Érték alapú extra esélyek
- **Védelem**: Egy order_id csak egyszer generálhat sorsjegyet

### 3. Aggregálás
- **Dimenzió**: shop × NGO bontás
- **Státuszok**: pending és approved külön számolva
- **Frissítés**: Valós időben a szinkronizáció során

## Frontend Elemek (Shortcode-ok)

### Résztvevők Száma
```php
[impactshop_raffle_count promo="impactshop-2025-q4"]
// Kimenet: "1,234 sorsjegy"
```

### Shop × NGO Toplisták
```php
[impactshop_raffle_totals promo="impactshop-2025-q4" state="approved" limit="10"]
// Kimenet: HTML lista top 10 shop×NGO kombináció
```

## Sorsolási Algoritmus

### Determinisztikus Sorsolás
```php
function impactshop_draw_winner($promo_slug, $public_seed) {
    // 1. Összes sorsjegy lekérése ID szerint rendezve
    // 2. Nyilvános seed hash generálása
    // 3. Hash alapján index számítása
    // 4. Nyertes visszaadása
}
```

### Nyilvános Seed Példák
- Lottó sorsolás számainak összege + dátum
- Tőzsdeindex záróértéke + promó ID
- Bármely nyilvánosan ellenőrizhető adat

## API Integráció

### Dognet 2.0 Endpoint (Implementálandó)
```php
function impactshop_dognet_fetch_conversions($since_ts, $until_ts) {
    // API hívás Dognet 2.0-hoz
    // Visszatérési érték: array konverziókkal
    // Kötelező mezők: order_id, program_id, merchant_slug, 
    //                 status, amount, currency, d1, click_id, email
}
```

### Szükséges Adatok
- **Endpoint URL**: Dognet 2.0 API konverziós végpont
- **Authentikáció**: API token vagy OAuth
- **Response formátum**: JSON mezőnevek mapping
- **Rate limiting**: Hívási korlátozások figyelembevétele

## Biztonsági Megfontolások

### Adatvédelem
- Email címek hash-elése `entry_uid` generálásához
- GDPR compliance (adatmegőrzési idő, törlési jog)
- Opt-in marketing kommunikációhoz

### Csalás Elleni Védelem
- Order ID duplikáció védelem
- IP alapú korlátozás (opcionális)
- Napi/összesített limitek e-mail/személy szerint
- Státusz alapú szűrés (csak approved)

## Jogi Megfontolások

### B Verzió Követelmények
- Játékszabályzat publikálása
- NAV bejelentés (ha szükséges)
- Nyereményadó kezelése
- Sorsolási jegyzőkönyv vezetése
- Tanúk biztosítása sorsolásnál

### Kötelező Dokumentumok
- Részletes játékszabályzat
- Adatkezelési tájékoztató
- Részvételi feltételek
- Kizárási okok
- Panaszkezelési eljárás

## Tesztelési Checklist

### Funkcionális Tesztek
- [ ] Tábla létrehozás és struktúra
- [ ] Cron job beállítás és futtatás
- [ ] Dognet API integráció
- [ ] Sorsjegy generálás logika
- [ ] Duplikáció védelem
- [ ] Aggregálás pontossága
- [ ] Shortcode-ok megjelenítése
- [ ] Sorsolási algoritmus

### Teljesítmény Tesztek
- [ ] Nagy mennyiségű konverzió kezelése
- [ ] API válaszidő optimalizálás
- [ ] Adatbázis indexek hatékonysága
- [ ] Cron job futási ideje

### Biztonsági Tesztek
- [ ] SQL injection védelem
- [ ] XSS védelem shortcode-okban
- [ ] API token biztonságos tárolása
- [ ] Adathozzáférés korlátozása

## Implementációs Lépések

### 1. Alapok
1. WPCode snippet telepítése
2. Konstansok konfigurálása
3. Táblák létrehozása
4. Cron job aktiválása

### 2. API Integráció
1. Dognet 2.0 API végpont specifikálása
2. Authentikáció beállítása
3. Field mapping implementálása
4. Hibakezelés és logging

### 3. Admin Felület
1. Kampány kezelő dashboard
2. Sorsolás gomb és interface
3. Nyertes export funkció
4. Statisztikák és riportok

### 4. Frontend
1. Shortcode-ok testreszabása
2. CSS styling
3. Responsive megjelenítés
4. Akadálymentesítés

### 5. Tesztelés és Élesítés
1. Staging környezet tesztelés
2. Load testing
3. Jogi review és jóváhagyás
4. Éles környezet deploy
5. Monitoring és logging beállítása