# ChatGPT beszélgetés - Impact Shop Nyereményjáték rendszer
**Dátum**: 2025-10-01 (folytatás)
**Cél**: Vásárláshoz kötött nyereményjáték implementálás
**Status**: Részleges megoldás - WPCode snippet kész, Dognet API integrálásra vár

## Probléma leírása
Impact Shop-ban vásárlók számára nyereményjáték szervezése:
- B verzió választása: belépés CSAK vásárlóknak (jogi megfelelőség biztosítható)
- Dognet 2.0 API integráció szükséges
- WordPress alapú megvalósítás WPCode snippet-tel
- Automatikus sorsjegy generálás jóváhagyott rendelések alapján

## ChatGPT megoldása

### Nyereményjáték rendszer architektúra:

#### 1. **Adatbázis struktúra** (2 tábla):
- `wp_impactshop_raffle_entries` - sorsjegyek tárolása
- `wp_impactshop_totals` - shop × NGO összesítések

#### 2. **Konfigurációs konstansok**:
```php
define('IMPACTSHOP_PROMO_SLUG', 'impactshop-2025-q4');
define('IMPACTSHOP_RAFFLE_START', '2025-10-01 00:00:00');
define('IMPACTSHOP_RAFFLE_END', '2025-12-31 23:59:59');
define('DOGNET_API_BASE', 'https://api.dognet.example/v2');
define('DOGNET_PUBLISHER_ID', 'YOUR_PUBLISHER_ID');
define('DOGNET_API_TOKEN', 'YOUR_SECRET_TOKEN');
```

#### 3. **Automatikus folyamat**:
1. **Cron szinkron** (óránként) - Dognet 2.0 API-ból konverziók lekérése
2. **Deduplication** - (order_id + program_id) kulcson
3. **Sorsjegy generálás** - csak `approved` státusz esetén
4. **Aggregálás** - shop × NGO bontásban összesítés

#### 4. **Sorsjegy szabályok**:
- Alap: 1 jóváhagyott rendelés = 1 sorsjegy
- Opcionális: érték alapú extra esélyek (pl. 20,000 Ft-onként +1)
- Duplikációvédelem: ugyanaz az order_id csak egyszer

#### 5. **Frontend elemek** (shortcode-ok):
- `[impactshop_raffle_count]` - résztvevők száma
- `[impactshop_raffle_totals]` - shop × NGO toplisták

#### 6. **Átlátható sorsolás**:
- Determinisztikus algoritmus nyilvános seed-del
- `impactshop_draw_winner($promo_slug, $public_seed)` függvény
- Reprodukálható eredmények

### Teljes WPCode snippet:
A snippet tartalmazza:
- ✅ Tábla létrehozás és aktiválás
- ✅ Cron job beállítás (óránkénti szinkron)
- ✅ Sorsjegy generálás logika
- ✅ Aggregálás és összesítés
- ✅ Shortcode-ok frontend megjelenítéshez
- ✅ Sorsolási algoritmus
- ⏳ Dognet API integráció (placeholder)

## Tesztelés eredménye
✅ **Kész elemek**:
- Adatbázis struktúra
- Cron szinkronizáció keretrendszer
- Sorsjegy logika
- Frontend shortcode-ok
- Sorsolási mechanizmus

⏳ **Függőben**:
- Dognet 2.0 API végpont specifikáció
- API authentication beállítás
- Konverziós adatok field mapping
- Admin felület sorsoláshoz

## Következő lépések
1. **Dognet 2.0 API adatok** megszerzése:
   - Endpoint URL és auth módszer
   - Response formátum (JSON mezők)
   - Rate limiting és hibakezelés

2. **Promó meta-adatok véglegesítése**:
   - Pontos időszak
   - Nyeremény részletei
   - Játékszabályzat URL

3. **Admin felület** kiegészítése:
   - Sorsolás gomb
   - Nyertes export funkció
   - Statisztikák dashboard

4. **Tesztelés és validálás**:
   - Dognet konverzió szinkron
   - Sorsjegy generálás ellenőrzése
   - Duplikáció védelem tesztelése

## Kapcsolódó fájlok
- [x] WPCode snippet (teljes nyereményjáték rendszer)
- [ ] Dognet API integráció (pending)
- [ ] Admin felület (tervezés alatt)
- [ ] Játékszabályzat és jogi dokumentáció

## GitHub Copilot notes
- **B verzió**: csak vásárlók, jogi megfelelőség biztosítható
- **Technikai integráció**: meglévő Impact Shop rendszerbe illeszthető
- **Skálázhatóság**: shop × NGO bontásban aggregálás
- **Biztonság**: deduplication, approved státusz szűrés
- **Következő**: Dognet API specifikáció és implementálás