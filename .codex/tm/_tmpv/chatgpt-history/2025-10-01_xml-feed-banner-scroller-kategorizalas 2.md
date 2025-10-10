# 2025-10-01: XML Feed Folytatás - Banner Scroller és Kategorizálás

## Beszélgetés Áttekintése
**Főbb célok:**
- Animated logo banner scrollerek implementálása
- Webshop kategorizálás megvalósítása
- Google Sheets alapú adatkezelés átállítás
- Elementor integráció beállítása

**Technikai környezet:**
- WordPress.com hosted (alapcsomag)
- Elementor page builder
- WPCode plugin PHP snippetek
- Google Sheets CSV export
- Fillout form integráció (eM61RLkz6jus)

## Megoldott Funkciók

### 1. Banner Scroller Rendszer
**Funkció:** Végtelen görgetős sáv webshop logókkal és bannerekkel
```php
// Shortcode használat
[impactshop_scroller category="" inject_every="5" speed="30"]
```

**Paraméterek:**
- `category`: csak adott kategória logói (üres = összes)
- `inject_every`: hányadik logó után jöjjön banner
- `speed`: animáció sebessége (másodperc)

**Technikai megvalósítás:**
- CSS animations (`@keyframes impactshop-scroll`)
- Hover pause funkció
- Lazy loading képek (`loading="lazy"`)
- Végtelen loop (2x duplikálás)

### 2. Kategóriás Katalógus
**Funkció:** Tabos kategóriaválasztó + kereső + logórács
```php
// Shortcode használat
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
```

**Jellemzők:**
- Automatikus kategóriafülek generálás
- Valós idejű kereső
- Responsive grid layout
- JavaScript szűrés

### 3. Google Sheets Integráció
**Adatforrások:**
```
Shops CSV: https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv

Banners CSV: https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=0&single=true&output=csv
```

**Shops sheet oszlopok:**
- `name` / `név`: bolt neve
- `category` / `kategória`: kategorizálás
- `logo` / `logo_url` / `kép` / `image`: logó URL
- `shop_slug` / `slug` / `go_slug`: /go/{shop} identifier

**Banners sheet oszlopok:**
- `img` / `image` / `banner` / `kép`: banner kép URL
- `href` / `url` / `link`: céloldal URL
- `label` / `címke` / `title`: banner címke
- `category` / `kategória`: opcicionális kategória szűrés

## Kód Implementáció

### Teljes WPCode PHP Snippet
**Verzió:** CSV-alapú adatkezelés
**Méret:** ~200 sor
**Cache:** 15 perc (transient)

**Főbb komponensek:**
1. **Settings konfiguráció**
   - CSV URL-ek
   - Fillout form URL
   - Cache TTL beállítás

2. **CSV parser**
   - BOM eltávolítás
   - Delimiter detektálás (vessző vs pontosvessző)
   - Ékezetes fejléc normalizálás
   - Asszociatív tömb konverzió

3. **Scroller shortcode**
   - Banner injektálás logika
   - Kategória szűrés
   - Végtelen animáció
   - d1 parameter alapú linkelés

4. **Katalógus shortcode**
   - Tab generálás
   - JavaScript szűrés
   - Responsive grid
   - Keresőfunkció

### Link Logika
**d1 parameter nélkül:**
```
Logo kattintás → Fillout form (?shop=X&amb=Y) → NGO választás → /go/{shop}?d1=Z&amb=Y&src=impactshop
```

**d1 parameter-rel:**
```
Logo kattintás → /go/{shop}?d1=Z&amb=Y&src=impactshop (közvetlen)
```

## Elementor Integráció

### Beállítási Lépések
1. **Shortcode widget használata** (nem Szöveg/HTML)
2. **1 oszlopos szekció** (teljes szélességhez)
3. **Content Width: Full Width**
4. **Columns Gap: No Gap**

### Javasolt Oldal Struktúra
```
Hero szekció (cím, magyarázat)
↓
Felső scroller: [impactshop_scroller inject_every="5" speed="28"]
↓
Kategória katalógus: [impactshop_catalog show_tabs="1" search="1" per_page="200"]
↓
Alsó scroller: [impactshop_scroller inject_every="7" speed="24"]
```

## Hibakeresés és Javítások

### Szintaktikai Hibák
**Probléma:** PHP syntax error a tömb lezárásoknál
**Megoldás:** 
- Minden tömb elem végére vessző
- Tiszta `return [ ... ];` szerkezet
- Rejtett karakterek eltávolítása

### Elementor Megjelenítési Problémák
**Probléma:** Tartalom "egy oldalra tömörül"
**Megoldás:**
- 1 oszlopos szekció használata (ne 3 oszlopos)
- Full Width beállítás
- Shortcode widget (nem Szöveg widget)

### CSV Adatforrás Konfúzió
**Probléma:** "Honnan vette a logókat?" - fix kód vs. Google Sheets
**Megoldás:** Teljes átállás CSV-alapú adatkezelésre

## Teljesítmény Optimalizálás

### Cache Stratégia
- **CSV cache:** 15 perc transient
- **Kép lazy loading:** `loading="lazy"`
- **Async dekódolás:** `decoding="async"`

### Animáció Optimalizálás
- **Hover pause:** `animation-play-state: paused`
- **Transform használat:** `translateX()` GPU-optimalizált
- **Végtelen loop:** content duplikálás

## Konfiguráció Beállítások

### Fillout Form Beállítás
**URL:** `https://fillout.com/t/eM61RLkz6jus`
**Rejtett mezők:**
- `shop` (prefill paraméterből)
- `amb` (prefill paraméterből) 
- `ngo_code` (számított mező)

**On submit redirect:**
```
/go/@shop?d1=@ngo_code&amb=@amb&src=impactshop&utm_source=sharity&utm_medium=impactshop&utm_campaign=@shop
```

### Redirection Plugin Szabály
**Pattern:** `/go/([a-z0-9\-\_]+)$`
**Target:** Dognet deeplink template
**Query params:** d1, amb, src továbbadás

## Tesztelési Forgatókönyvek

### 1. Üres Sheet Teszt
- Shops sheet üres → "Nincs megjeleníthető partner"
- Fokozatos feltöltés tesztelése

### 2. Banner Injektálás Teszt
- inject_every="4" → 4 logó után 1 banner
- Kategória-specifikus banner szűrés

### 3. Kategória Szűrés Teszt
- Scroller category="Divat" → csak Divat kategória
- Banner category matching teszt

### 4. Link Logika Teszt
- URL ?d1=TESZT → közvetlen /go/ link
- URL d1 nélkül → Fillout átirányítás

## Jövőbeli Fejlesztési Lehetőségek

### URL Parameter Támogatás
```
?cat=Divat%20%26%20Egészség&q=vision
→ előre szűrt katalógus
```

### Kategória→Banner Mapping
- Automatikus kategória-specifikus banner kiválasztás
- Banner rotation strategy

### Swipe/Touch Interakció
- Mobile swipe to pause
- Touch-friendly banner navigation

## Eredmények és Státusz

### ✅ Működőképes Funkciók
- Animated logo scroller bannerekkel
- Kategóriás katalógus tabokkal és keresővel
- Google Sheets CSV integráció
- Elementor shortcode beillesztés
- Cache-elt adatbetöltés

### ⚠️ Konfigurálandó Elemek
- Fillout form URL csere (`https://fillout.com/IDE-AZ-URL`)
- Shops CSV adatok feltöltése
- Banners CSV banner lista feltöltése

### 🔄 Folyamatos Fejlesztés
- Banner rotation optimalizálás
- Kategória struktúra finomhangolás
- Teljesítmény monitoring

## Technikai Specifikációk

### Kompatibilitás
- **WordPress:** 5.0+
- **PHP:** 7.4+
- **Plugins:** WPCode, Redirection, Elementor
- **Browsers:** Modern browsers (CSS animations)

### Fájlméretek
- **PHP snippet:** ~200 sor
- **CSS inline:** ~20 sor
- **JavaScript inline:** ~40 sor
- **Cache footprint:** ~50KB/15perc

## Megjegyzések
- Dognet XML feedek (`https://feed.arukereso.com/dognet_*`) jelenleg nem használtak
- Banner képek WordPress médiatárból ajánlott (stabilabb URL)
- Kategória nevek egységesítése fontos (ékezetek, szóközök)
- Mobile responsiveness beépített (grid auto-fill)

---

**Utolsó módosítás:** 2025-10-01  
**Fejlesztési fázis:** Banner scroll és kategorizálás implementálva, CSV integráció kész  
**Következő lépés:** Google Sheets adatok feltöltése és Fillout URL konfiguráció