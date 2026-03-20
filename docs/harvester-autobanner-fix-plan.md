# Harvester + Auto-Banner Fix Plan

**Dátum:** 2026-01-31  
**Státusz:** TERV - Vizsgálat alatt, kód még nem módosítva

---

## Gyors futtatási runbook (SSH)
- SSH: `sharityh@s59.tarhely.com`
- App: `/home/sharityh/app`
- Harvester → Auto‑banner sync:
  - `wp impactshop auto-banner sync`
- Cleanup (whitelist szűrés után):
  - `wp impactshop auto-banner cleanup`
- Ellenőrzés:
  - `wp db query "SELECT COUNT(*) as count, status FROM wp_impactshop_auto_banners GROUP BY status;"`
  - `wp db query "SELECT id, shop_slug, title, status FROM wp_impactshop_auto_banners ORDER BY id DESC LIMIT 5;"`
- DTD/hibás URL purge:
  - `wp db query "DELETE FROM wp_impactshop_auto_banners WHERE banner_url LIKE '%w3.org%' OR banner_url LIKE '%/DTD/%' OR banner_url LIKE '%.dtd%';"`

## 1. Probléma összefoglalás

### 1.1 Auto-banner tele van "hülyeségekkel"
A Gmail Promotions harvester által begyűjtött ajánlatok szűrés nélkül kerülnek be az `impactshop_auto_banners` táblába. Elvileg csak **Dognet és CJ partnerek** ajánlatai kellene, hogy bekerüljenek.

**Érintett komponensek:**
- `wp-content/mu-plugins/impactshop-auto-banner.php` - hook: `impactshop_harvester_offer_saved`
- `wp-content/mu-plugins/impactshop-auto-banner-sync.php` - Google Sheets CSV sync
- `tools/shops_registry.json` - whitelist (102 partner)
- Gmail harvester az `ai-agent` repo-ban (nem ebben a repo-ban)

### 1.2 Link probléma az AdWatch-ban
Ha a felhasználónak **van kiválasztott NGO**, a banner linkeknek NEM Fillout-ra kellene mutatniuk, hanem közvetlenül `/go-deal/{shop}?d1={ngo_slug}&u={encoded_url}` formátumra.

**Jelenlegi viselkedés:**
- `impactshop-ads-watch.js` direktben használja a `banner.banner_url`-t
- A `impact-banners-fillout-rewriter.php` csak `the_content` filterrel működik, JS-ben nincs

---

## 2. Gyökérok elemzés

### 2.1 Harvester szűrő hiánya

**`impactshop-auto-banner.php` (21. sor):**
```php
add_action('impactshop_harvester_offer_saved', 'impactshop_auto_banner_from_offer', 10, 2);
```

Ez a hook **bármilyen** offer-t befogad - nincs whitelist ellenőrzés!

**`impactshop-auto-banner-sync.php`:**
- Közvetlenül a `banners_csv_url`-ből szedi az adatokat
- Nincs partner whitelist szűrés, csak discount >= 10%
- A Google Sheets Banners lapból jön minden, ami ott van

**A szűrés jelenlegi helye:**
- `tools/shops_registry.json` - 102 Dognet/CJ partner
- `ai-agent` repo: Gmail harvester itt használja a whitelistet
- WordPress oldal: **NINCS whitelist szűrés**

### 2.2 Link logika hiánya a JS-ben

**`impactshop-ads-watch.js` (~1974, ~2022 sor):**
```javascript
$banner.find('[data-role=auto-banner-link]').attr('href', banner.banner_url || '#');
```

- A `banner_url` közvetlenül Fillout URL (ha a CSV-ből jön)
- Vagy termék URL (ha az ai-agent harvesterből jön)
- **Nincs logika** a `state.selectedNgo` alapján történő átírásra

**A rewriter PHP működése:**
- `impact-banners-fillout-rewriter.php` a `the_content` filter-rel működik
- Csak server-side HTML-t ír át, JS dinamikus linkekre nincs hatással

---

## 3. Javasolt megoldások

### 3.1 Whitelist szűrő az auto-banner hook-ba (BIZTONSÁGOS)

**Helyszín:** `wp-content/mu-plugins/impactshop-auto-banner.php`

**Javaslat:** Új helper függvény + szűrés a hook-ban

```php
/**
 * Check if shop_slug is a whitelisted Dognet/CJ partner
 * Sources: 1) tools/shops_registry.json (Dognet+CJ), 2) Shops CSV fallback (Dognet only)
 */
function impactshop_is_whitelisted_partner(string $shop_slug): bool
{
    // Statikus cache
    static $whitelist = null;
    
    if ($whitelist === null) {
        $whitelist = [];
        
        // 1. Elsődleges: shops_registry.json (Dognet + CJ partnerek)
        $registry_path = WP_CONTENT_DIR . '/../tools/shops_registry.json';
        if (file_exists($registry_path)) {
            $data = json_decode(file_get_contents($registry_path), true);
            if (is_array($data)) {
                foreach ($data as $shop) {
                    if (!empty($shop['slug'])) {
                        $whitelist[strtolower($shop['slug'])] = true;
                    }
                }
            }
        }
        
        // 2. Fallback: Shops CSV-ből (csak Dognet, ha JSON üres)
        if (empty($whitelist) && function_exists('impactshop_get_shops')) {
            $shops = impactshop_get_shops();
            foreach ($shops as $shop) {
                if (!empty($shop['shop_slug'])) {
                    $whitelist[strtolower($shop['shop_slug'])] = true;
                }
            }
        }
    }
    
    return isset($whitelist[strtolower($shop_slug)]);
}

// Módosított from_offer függvény
function impactshop_auto_banner_from_offer(array $offer, array $context = []): void
{
    // Whitelist ellenőrzés - nem whitelisted = skip (nincs log, egyszerűen eldobjuk)
    $shop_slug = sanitize_text_field((string) ($offer['shop_slug'] ?? ''));
    if ($shop_slug !== '' && !impactshop_is_whitelisted_partner($shop_slug)) {
        return; // Skip nem whitelisted partner
    }
    
    // ... eredeti kód folytatódik
}
```

**Kockázat:** ALACSONY - csak szűr, nem módosít semmit
**Impi hatás:** NINCS - az ai-agent repo külön kódot használ

### 3.2 Auto-banner sync whitelist szűrő (BIZTONSÁGOS)

**Helyszín:** `wp-content/mu-plugins/impactshop-auto-banner-sync.php` (~130 sor körül)

**Javaslat:** A CSV feldolgozás során szűrjünk:

```php
// A foreach cikluson belül, a $slug ellenőrzése után
$slug = trim($cols[$col_slug] ?? '');

// ÚJ: Whitelist ellenőrzés
if ($slug !== '' && function_exists('impactshop_is_whitelisted_partner')) {
    if (!impactshop_is_whitelisted_partner($slug)) {
        $result['skipped']++;
        continue; // Skip nem whitelisted partner
    }
}

// ... eredeti kód
```

**Kockázat:** ALACSONY
**Impi hatás:** NINCS

### 3.3 JS link átírás NGO alapján (KÖZEPES KOCKÁZAT)

**Helyszín:** `wp-content/mu-plugins/impactshop-ads-watch.js`

**Javaslat:** Új helper függvény a link átírásra

```javascript
/**
 * Transform banner URL based on selected NGO
 * - Ha nincs NGO: eredeti URL (Fillout)
 * - Ha van NGO: /go-deal/{shop}?d1={ngo}&u={base64_url}
 */
function transformBannerUrl(bannerUrl, shopSlug, ngoSlug) {
    if (!ngoSlug || ngoSlug === '') {
        return bannerUrl; // Fillout marad
    }
    
    if (!bannerUrl || bannerUrl === '#') {
        return bannerUrl;
    }
    
    // Extract target URL from Fillout if present
    let targetUrl = bannerUrl;
    try {
        const url = new URL(bannerUrl);
        if (url.hostname.includes('fillout.com')) {
            // Fillout URL - extract 'u' parameter (base64)
            const uParam = url.searchParams.get('u');
            if (uParam) {
                targetUrl = atob(uParam);
            }
        }
    } catch (e) {
        // Not a valid URL, use as-is
    }
    
    // Build /go-deal URL
    const shop = shopSlug || 'unknown';
    const base = window.location.origin + '/go-deal/' + encodeURIComponent(shop);
    const params = new URLSearchParams({
        d1: ngoSlug,
        u: btoa(targetUrl)
    });
    
    return base + '?' + params.toString();
}

// Használat a showAutoBanner és loadAutoBanner függvényekben:
const finalUrl = transformBannerUrl(
    banner.banner_url || '', 
    banner.shop_slug || '', 
    state.selectedNgo ? state.selectedNgo.slug : ''
);
$banner.find('[data-role=auto-banner-link]').attr('href', finalUrl);
```

**Kockázat:** KÖZEPES - tesztelni kell a különböző URL formátumokat
**Impi hatás:** NINCS

---

## 4. Impi AI Agent hatásvizsgálat

### 4.1 Jelenlegi architektúra

```
ai-agent repo (külön)
├── tools/gmail/promotions-runner.ts  # Gmail harvester
├── tools/shops_registry.json         # Whitelist (102 partner)
├── apps/ai-agent-core/               # Core logic
└── tmp/ingest/                       # Output files

impactshop-notes repo (ez)
├── tools/shops_registry.json         # Másolat/szinkronban
├── wp-content/mu-plugins/            # WordPress kód
└── docs/                             # Dokumentáció
```

### 4.2 Szűrési pontok

| Komponens | Szűrés helye | Státusz |
|-----------|-------------|---------|
| Gmail harvester (ai-agent) | shops_registry.json | ✅ OK |
| Árukereső Playwright (ai-agent) | shops_registry.json | ✅ OK |
| Google Sheets Patrol (Code.gs) | Shops lap tartalma | ✅ OK (ha a lap helyes) |
| auto-banner-sync.php | NINCS | ❌ HIÁNYZIK |
| impactshop_harvester_offer_saved hook | NINCS | ❌ HIÁNYZIK |

### 4.3 Impi tudásbázis érintettsége

A javasolt változtatások **NEM érintik** az Impi AI agent-et:
- A `tools/shops_registry.json` nem módosul
- Az ai-agent repo kódja nem változik
- Csak WordPress szintű szűrés kerül be

---

## 5. Link logika részletes terv

### 5.1 Jelenlegi flow

```
1. Banner adat jön (CSV vagy harvester)
   └─> banner_url = Fillout URL VAGY direkt termék URL

2. JS megjelenít
   └─> <a href="{banner_url}"> közvetlenül

3. Kattintás
   └─> Fillout megnyílik, nincs NGO tracking
```

### 5.2 Javasolt flow

```
1. Banner adat jön (CSV vagy harvester)
   └─> banner_url = Fillout URL VAGY direkt termék URL
   └─> shop_slug = partner azonosító

2. JS megjelenít
   └─> Ha state.selectedNgo létezik:
       │   └─> transformBannerUrl() → /go-deal/{shop}?d1={ngo}&u={encoded}
       └─> Ha nincs NGO:
           └─> Eredeti banner_url (Fillout)

3. Kattintás
   └─> /go-deal handler generálja az affiliate linket NGO-val
```

### 5.3 Szükséges módosítások

1. **`impactshop-ads-watch.js`** - `transformBannerUrl()` helper + használat
2. **`impactshop-ads-watch.js`** - `showAutoBanner()` és `loadAutoBanner()` módosítás

---

## 6. Implementációs sorrend

### 6.1 Fázis 1: Whitelist szűrő (ALACSONY KOCKÁZAT)

1. `impactshop_is_whitelisted_partner()` helper létrehozása
2. `impactshop_auto_banner_from_offer()` szűrés hozzáadása
3. `impactshop_auto_banner_sync_run()` szűrés hozzáadása
4. Tesztelés: WP-CLI `wp impactshop auto-banner sync`
5. Deploy: csak CSS/JS után, vagy külön

### 6.2 Fázis 2: Link logika (KÖZEPES KOCKÁZAT)

1. `transformBannerUrl()` JS helper létrehozása
2. `showAutoBanner()` és `loadAutoBanner()` módosítása
3. Tesztelés: NGO kiválasztva / nincs kiválasztva esetek
4. Deploy: CSS/JS-sel együtt

---

## 7. Rollback terv

### 7.1 Whitelist szűrő

Ha problémát okoz:
- Kommenteld ki a szűrő sort `impactshop_auto_banner_from_offer()`-ben
- A meglévő bannerek maradnak
- Nincs adatvesztés

### 7.2 Link logika

Ha problémát okoz:
- `transformBannerUrl()` return-jét cseréld `return bannerUrl;`-ra
- Visszaáll az eredeti viselkedés

---

## 8. Koherencia ellenőrzés

### 8.1 Fájl függőségek

| Fájl | Függőség | Ellenőrzés |
|------|---------|------------|
| impactshop-auto-banner.php | shops_registry.json VAGY impactshop_get_shops() | ✅ Fallback van |
| impactshop-auto-banner-sync.php | impactshop_is_whitelisted_partner() | ✅ function_exists guard |
| impactshop-ads-watch.js | state.selectedNgo | ✅ Már létezik |
| impactshop-ads-watch.js | banner.shop_slug | ✅ Már létezik az adatban |

### 8.2 Nem érintett komponensek

- ✅ `Impi Tudásbázis/` - dokumentáció, nincs kód
- ✅ `ai-agent repo` - külön projekt
- ✅ `impact-banners-fillout-rewriter.php` - marad, server-side használatra
- ✅ Google Sheets Code.gs - nem módosul

### 8.3 Potenciális konfliktusok

| Szcenárió | Kockázat | Megoldás |
|-----------|---------|----------|
| shops_registry.json üres vagy hiányzik | KÖZEPES | Fallback impactshop_get_shops()-ra |
| NGO slug speciális karaktereket tartalmaz | ALACSONY | encodeURIComponent használat |
| Fillout URL formátum változik | ALACSONY | Try-catch a parse-olásban |

---

## 9. Döntési pontok (VÁLASZOLVA: 2026-01-31)

| Kérdés | Döntés | Megjegyzés |
|--------|--------|------------|
| **shops_registry.json szinkronizálás** | ✅ Automatikus | Cron vagy deploy hook-kal szinkronizálódik |
| **Whitelist forrása** | ✅ Mindkettő (JSON + Shops CSV fallback) | JSON elsődleges (Dognet+CJ), CSV fallback (csak Dognet) |
| **Log szint** | ✅ Nincs részletes log | Elutasított ajánlatok törlődnek, nincs review szükség |
| **Immediate cleanup** | ✅ Automatikus | Deploy után script törli a nem-whitelisted bannereket |

---

## 10. Automatikus cleanup terv

### 10.1 Meglévő rossz bannerek törlése

A whitelist szűrő bevezetésekor automatikusan töröljük a meglévő nem-whitelisted bannereket:

**Helyszín:** `wp-content/mu-plugins/impactshop-auto-banner.php` - új cleanup függvény

```php
/**
 * Remove existing banners that don't match the whitelist
 * Called once after deploying the whitelist filter
 */
function impactshop_auto_banner_cleanup_non_whitelisted(): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_auto_banners';
    
    $result = [
        'checked' => 0,
        'deleted' => 0,
        'kept' => 0,
    ];
    
    // Get all banners
    $banners = $wpdb->get_results("SELECT id, shop_slug, title FROM {$table}", ARRAY_A);
    
    foreach ($banners as $banner) {
        $result['checked']++;
        $shop_slug = $banner['shop_slug'] ?? '';
        
        // Remove sync: prefix if present
        if (strpos($shop_slug, 'sync:') === 0) {
            $shop_slug = substr($shop_slug, 5);
        }
        
        // Check whitelist
        if ($shop_slug === '' || impactshop_is_whitelisted_partner($shop_slug)) {
            $result['kept']++;
            continue;
        }
        
        // Delete non-whitelisted
        $wpdb->delete($table, ['id' => $banner['id']], ['%d']);
        $result['deleted']++;
    }
    
    return $result;
}
```

### 10.2 WP-CLI parancs a cleanup-hoz

```php
// WP-CLI command for cleanup
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('impactshop auto-banner cleanup', function($args, $assoc_args) {
        WP_CLI::log('Cleaning up non-whitelisted banners...');
        $result = impactshop_auto_banner_cleanup_non_whitelisted();
        WP_CLI::success(sprintf(
            'Cleanup complete: %d checked, %d deleted, %d kept',
            $result['checked'],
            $result['deleted'],
            $result['kept']
        ));
    });
}
```

### 10.3 Egyszer futó cleanup (deploy után)

```php
// One-time cleanup on plugin load (can be removed after first successful run)
add_action('init', function() {
    $cleanup_done = get_option('impactshop_auto_banner_cleanup_v1', false);
    if (!$cleanup_done && function_exists('impactshop_is_whitelisted_partner')) {
        $result = impactshop_auto_banner_cleanup_non_whitelisted();
        update_option('impactshop_auto_banner_cleanup_v1', [
            'done_at' => current_time('mysql'),
            'result' => $result,
        ]);
    }
}, 99);
```

---

## 11. Frissített implementációs terv

### 11.1 Fázis 1: Whitelist szűrő + Cleanup (ALACSONY KOCKÁZAT)

1. `impactshop_is_whitelisted_partner()` helper létrehozása
   - JSON elsődleges (tools/shops_registry.json)
   - Shops CSV fallback (impactshop_get_shops())
2. `impactshop_auto_banner_from_offer()` szűrés hozzáadása (nincs log, csak skip)
3. `impactshop_auto_banner_sync_run()` szűrés hozzáadása
4. `impactshop_auto_banner_cleanup_non_whitelisted()` függvény
5. WP-CLI `wp impactshop auto-banner cleanup` parancs
6. Egyszer futó cleanup hook (option-nel jelölve)
7. Tesztelés: WP-CLI `wp impactshop auto-banner cleanup --dry-run` (ha van)
8. Deploy

### 11.2 Fázis 2: Link logika (KÖZEPES KOCKÁZAT)

1. `transformBannerUrl()` JS helper létrehozása
2. `showAutoBanner()` és `loadAutoBanner()` módosítása
3. Tesztelés: NGO kiválasztva / nincs kiválasztva esetek
4. Deploy: CSS/JS-sel együtt

---

## 12. Biztonsági vizsgálat (2026-01-31)

### 12.1 PHP kód biztonság ✅ RENDBEN

| Szempont | Státusz | Megjegyzés |
|----------|---------|------------|
| **SQL Injection** | ✅ OK | A `impactshop_auto_banner_from_offer()` wpdb prepare-t használ, formátumokkal (`%s`, `%d`) |
| **XSS** | ✅ OK | `sanitize_text_field()`, `esc_url_raw()` használat minden bemenetnél |
| **File access** | ✅ OK | JSON olvasás `WP_CONTENT_DIR` relatív, nincs user-input path |
| **Function exists guard** | ✅ OK | `function_exists('impactshop_get_shops')` fallback |

**Javasolt whitelist kód (impactshop_is_whitelisted_partner):**
- Statikus cache használ → nincs ismételt file read
- `strtolower()` normalizálás → case-insensitive
- JSON decode error handling implicit (false → üres array)

### 12.2 JS kód biztonság ✅ RENDBEN

| Szempont | Státusz | Megjegyzés |
|----------|---------|------------|
| **XSS** | ✅ OK | `escapeHtml()` helper létezik és használva van |
| **Unsafe redirect** | ✅ OK | `/go-deal` URL építés `encodeURIComponent()` és `btoa()` használ |
| **Open redirect** | ✅ OK | Mindig `window.location.origin`-hez relatív, nem külső URL |
| **URL parse error** | ✅ OK | Try-catch van a Fillout URL extract körül |

**Javasolt transformBannerUrl() kód:**
- `new URL()` try-catch-ben → invalid URL nem okoz hibát
- `encodeURIComponent(shop)` → path injection védett
- `btoa(targetUrl)` → query param injection védett
- `URLSearchParams` használat → safe query building

### 12.3 Impi AI Agent érintettség ✅ NEM ÉRINTETT

| Komponens | Helye | Kapcsolat |
|-----------|-------|-----------|
| **ai-agent repo** | `~/Documents/GitHub/ai-agent` | Teljesen külön repo |
| **Gmail harvester** | ai-agent repo | Saját whitelist (`shops_registry.json` másolat) |
| **Impi recommend.ts** | ai-agent repo | Nem hívja a WordPress kódot |
| **shops_registry.json** | Mindkét repo | Szinkronban, de nem módosítjuk |

**Következtetés:** A WordPress változtatások NEM érintik az Impi AI agent működését. Az ai-agent repo:
- Külön kódot futtat Node.js-ben
- Saját shops_registry.json másolatot használ
- Nincs közvetlen függőség a WordPress mu-plugins-tól

### 12.4 ImpactShop WordPress működés ✅ NEM SÉRÜL

| Funkció | Érintett? | Magyarázat |
|---------|-----------|------------|
| **/go-deal handler** | ✅ Rendben | Nem módosul, a `u` paraméter base64 decode-olását végzi |
| **Fillout rewriter** | ✅ Rendben | `impact-banners-fillout-rewriter.php` marad server-side-on |
| **Shops CSV** | ✅ Rendben | Fallback, nem módosul |
| **REST API** | ✅ Rendben | `/wp-json/impactshop/v1/auto-banners` nem változik |
| **Cron cleanup** | ✅ Rendben | Már létező expired banner törlés folytatódik |
| **WP-CLI** | ✅ Rendben | Új `wp impactshop auto-banner cleanup` hozzáadás, nem ütközik |

### 12.5 Potenciális kockázatok

| Kockázat | Valószínűség | Hatás | Mitigáció |
|----------|-------------|-------|-----------|
| shops_registry.json üres | Alacsony | Nincs banner szűrés | Fallback `impactshop_get_shops()`-ra |
| Fillout URL formátum változik | Alacsony | Extract fail | Try-catch, eredeti URL marad |
| NGO slug speciális karakter | Alacsony | URL hiba | `encodeURIComponent()` védelem |
| Whitelist túl szűk | Közepes | Legitim banner kiesik | JSON-ban 102 partner van, elég bő |

### 12.6 Végső biztonsági értékelés

**ÖSSZEGZÉS: A tervezett változtatások BIZTONSÁGOSAK**

1. ✅ Nem vezet be SQL injection, XSS, vagy unsafe redirect sebezhetőséget
2. ✅ Nem érinti az Impi AI agent működését
3. ✅ Nem töri el a meglévő ImpactShop funkciókat
4. ✅ Rollback egyszerű (kód kommentelés)
5. ✅ Cleanup egy option flag-gel vezérelt, nem ismétlődik

---

## 13. Gemini Javaslatok és Kiegészítések (2026-01-31)

A biztonsági és koherencia vizsgálat alapján az alábbi kiegészítéseket javaslom beépíteni a fejlesztés során:

### 13.1 Robusztusabb útvonal kezelés
A `WP_CONTENT_DIR . '/../tools/'` útvonal működik a jelenlegi deploy struktúrában (mivel a `tools/` nincs kizárva a rsync-ből), de sérülékeny lehet, ha a szerver konfiguráció változik.
**Javaslat:** Definiálj egy konstanst a fő plugin fájlban, ami felüldefiniálható `wp-config.php`-ból szükség esetén.

```php
if (!defined('IMPACTSHOP_SHOPS_REGISTRY_PATH')) {
    define('IMPACTSHOP_SHOPS_REGISTRY_PATH', WP_CONTENT_DIR . '/../tools/shops_registry.json');
}
```

### 13.2 JS Base64 védelem
A `atob()` függvény kivételt dob, ha a string nem érvényes Base64. Bár a `try-catch` blokk védi a `transformBannerUrl` függvényt, érdemes a `btoa()` hívásnál is figyelni, hogy a `targetUrl` nem null-e.
**Javaslat:** Extra null check a `base64_url` generálás előtt.

### 13.3 Telemetria és Megfigyelhetőség
Jelenleg a szűrés "néma" (return void). Hibakereséshez hasznos lenne tudni, ha a rendszer tömegesen dob el bannereket (pl. hibás whitelist miatt).
**Javaslat:** `do_action` hook elhelyezése a szűrés pontján.

```php
if ($shop_slug !== '' && !impactshop_is_whitelisted_partner($shop_slug)) {
    do_action('impactshop_auto_banner_rejected', $shop_slug, $offer);
    return;
}
```
Így később egy egyszerű diagnosztikai pluginnal naplózhatóvá válik a működés anélkül, hogy a core kódot módosítani kellene.

### 13.4 Admin visszajelzés
Ha a `shops_registry.json` nem olvasható, és a rendszer a fallback-re (`impactshop_get_shops`) támaszkodik, az admin felületen (Auto Banner lista) jó lenne egy figyelmeztetés.
**Javaslat:** `admin_notices` hook, ha `impactshop_is_whitelisted_partner` fallback ágon fut (ehhez a `static $source` változót kellene exponerálni vagy ellenőrizni).

### 13.5 Koherencia megerősítés
- A terv összhangban van a `bin/deploy.sh` működésével (tools mappa deployolva van).
- A `impactshop_get_shops()` fallback biztosítja a folytonosságot CJ/egyéb partnereknél, ha a JSON sérült.
- Az Impi AI agenttel való "non-interference" garantált a külön repository és futtatókörnyezet miatt.

---

## 14. Codex javaslatok és kiegészítések (2026-01-31)

Az alábbi pontok kiegészítik a tervet anélkül, hogy jól működő rendszereket érintenének (ImpactShop, Impi AI agent).

### 14.1 Whitelist cache invalidálás
Ha a `shops_registry.json` frissül, érdemes a statikus cache újratöltésére lehetőséget adni.
**Javaslat:** opcionális `impactshop_is_whitelisted_partner(true)` paraméter a cache resethez, vagy külön `impactshop_reset_whitelist_cache()` helper.

### 14.2 `shop_slug` normalizálás a sync-ben
CSV importnál javasolt `sanitize_title()` használat a `trim()` után, hogy a slug formátum egységes legyen.
**Javaslat:** a sync kódban `sanitize_title($slug)` a whitelist ellenőrzés előtt.

### 14.3 Safe base64 (URL-safe) kezelés
A `btoa()` nem URL-safe base64-et ad. Jelenleg működik, de hosszabb URL-eknél lehetnek `+` és `/` karakterek.
**Javaslat:** opcionális URL-safe base64 (replace `+`→`-`, `/`→`_`, trim `=`), és a go-deal oldalon visszaalakítás (ha ez a handler támogatja).

### 14.4 Cleanup dry-run támogatás
A tervben szerepel `--dry-run`, de jelenleg nincs leírva implementációs részlete.
**Javaslat:** egy `if (!empty($assoc_args['dry-run']))` ág, ami csak logolja a törlendő ID-ket.

### 14.5 Jövőbeli QA checklist
**Javaslat:** rövid ellenőrző lista a staging teszt előtt (NGO kiválasztva / nincs kiválasztva, CJ shop, Dognet shop, Fillout URL, direkt termék URL).

---

## 15. Következő lépések

- [x] Terv review és jóváhagyás
- [x] Kérdések megválaszolása (9. pont)
- [x] Biztonsági vizsgálat (12. pont)
- [ ] Fázis 1 implementáció (whitelist szűrő + cleanup)
- [ ] Fázis 1 tesztelés staging-en
- [ ] Fázis 2 implementáció (link logika)
- [ ] Teljes tesztelés
- [ ] Production deploy
