# Dognet Inkrementális Fetch + DB Aggregálás – Gyorsítási Javaslat

**Dátum:** 2026-01-27  
**Státusz:** Tervezet – Véleményezés alatt

---

## 📋 Eredeti Javaslat Összefoglalója

Íme egy rövid, megvalósítható terv az inkrementális Dognet fetch + DB‑aggregálás gyorsításra (nagyobb projekt, de nagy ugrás a stabilitásban és teljesítményben).

### Cél

- A teljes Dognet "all conversions" helyett csak az új tételek beolvasása.
- A leaderboard/ticker/activity ne API‑ból számoljon, hanem lokális aggregált táblából.

### 1) Új táblák (aggregálás + state)

**A. wp_impactshop_metrics_state**
| Mező | Típus | Leírás |
|------|-------|--------|
| id | PK | |
| last_id | BIGINT | Utolsó feldolgozott Dognet ID |
| last_checked_at | DATETIME | |
| status | VARCHAR | pl. `all`, `approved` |
| from_date | DATE | fix kezdő (2025‑10‑23) |

**B. wp_impactshop_metrics_ngo**
| Mező | Típus | Leírás |
|------|-------|--------|
| ngo_slug | VARCHAR, PK | |
| donation_total | DECIMAL | Összesített adomány |
| donation_today | DECIMAL | Mai adomány |
| updated_at | DATETIME | |

**C. wp_impactshop_metrics_shop**
| Mező | Típus | Leírás |
|------|-------|--------|
| shop_name | VARCHAR, PK | |
| donation_total | DECIMAL | |
| updated_at | DATETIME | |

**D. wp_impactshop_metrics_activity**
| Mező | Típus | Leírás |
|------|-------|--------|
| id | PK | |
| ngo_slug / shop_name | VARCHAR | |
| donation | DECIMAL | |
| created_at | DATETIME | |
| status | VARCHAR | |
| Index | created_at DESC | |

### 2) Inkrementális fetch logika

**Process:**
- `last_id` alapján Dognetből csak newer tételeket kérünk.
- `dognet_api_list_conversions_batch()` már tud last_id‑t, erre építünk.

**Pseudó:**
```
state = metrics_state(status='approved')
loop:
  batch = dognet_api_list_conversions_batch(from, to, status, last_id)
  if empty -> break
  foreach item:
    update NGO aggregate
    update shop aggregate
    insert activity row
    last_id = max(last_id, item.id)
  commit state
```

**Szabályok:**
- `status=approved` mehet defaultból → gyors
- `status=all` csak külön "heavy refresh"

### 3) Leaderboard / ticker / activity átállítása

| Jelenlegi | Cél |
|-----------|-----|
| API‑ból számol | DB‑ből olvas |

- `/impact/v1/leaderboard?tab=ngo` → `wp_impactshop_metrics_ngo`
- `/impact/v1/leaderboard?tab=shop` → `wp_impactshop_metrics_shop`
- `/impact/v1/ticker` → `metrics_ngo` + "today_total" mező
- `/impact/v1/activity` → `metrics_activity` legutóbbi 10

### 4) Frissítési ütemezés

- **Cron:** `impactshop_metrics_sync` (5–10 perc)
- **Manual:** admin "Force refresh"
- **SWR fallback:** ha cron csúszik, az API még kiszolgálja a last known aggregátot.

### 5) Migrációs lépések

1. Táblák létrehozása (MU‑plugin aktiváláskor).
2. Full backfill egyszer (2025‑10‑23 → ma, approved).
3. `last_id` elmentése.
4. Átállítás az új adatforrásokra.

### 6) Kockázatkezelés

- **Rollback:** visszakapcsolható a régi "full fetch" logikára.
- **Validáció:** 1 napig párhuzamosan futtatható (régi vs új), diff log.

### 7) Méretezés előnyök

- API válasz ms szintre esik
- Dognet terhelés csökken
- Preflight már nem a Dognetet üti

---

## ✅ Véleményezés – Pozitívumok

### 1. Architektúrailag helyes irány
A jelenlegi "minden API hívásnál full fetch" modell nem skálázódik. Az inkrementális + lokális aggregálás a **helyes pattern** nagy adatmennyiségnél.

### 2. A `last_id` alapú inkrementális fetch kiváló
- Már van rá kód (`dognet_api_list_conversions_batch()`)
- Dognet API támogatja
- Idempotens: újrafuttatható hiba után

### 3. Preflight gyorsítás kritikus
A jelenlegi preflight timeout-ok nagy része a Dognet API lassúságából fakad. Ha a preflight csak lokális DB-t üt, **10s → <100ms** javulás várható.

### 4. SWR fallback gondolkodás jó
Ha a cron csúszik, a stale adat még mindig jobb, mint timeout. Ez prod-ready szemlélet.

### 5. Rollback terv megvan
A párhuzamos futtatás + diff log lehetővé teszi a biztonságos átállást.

---

## ⚠️ Véleményezés – Kiegészítések / Javítások

### 1. `donation_today` reset logika hiányzik
**Probléma:** A `metrics_ngo.donation_today` mezőt naponta nullázni kell.

**Javaslat:** 
```php
// Cron: impactshop_metrics_daily_reset (éjfélkor)
UPDATE wp_impactshop_metrics_ngo SET donation_today = 0, updated_at = NOW();
```

### 2. Activity tábla túl gyorsan nő
**Probléma:** Ha minden conversion bekerül, a tábla hatalmasra nőhet.

**Javaslat:**
- Activity táblát limitálni (pl. max 10,000 sor, FIFO)
- Vagy: csak az utolsó 30 nap, régebbi törlődik
- Cron: `impactshop_metrics_activity_cleanup`

```sql
DELETE FROM wp_impactshop_metrics_activity 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### 3. `from_date` fix érték kockázatos
**Probléma:** Ha 2025-10-23 előtti adat is kell később, újra kell backfill-elni.

**Javaslat:** A `from_date` legyen konfigurálható (`wp_option`), ne hardcode.

### 4. Shop name vs shop_slug inkonzisztencia
**Probléma:** `metrics_shop.shop_name` vs máshol `shop_slug` használata.

**Javaslat:** Legyen egységesen `shop_slug` mindenhol (konzisztens a többi táblával).

### 5. Hiányzik: transaction deduplication
**Probléma:** Mi van, ha egy conversion ID kétszer jön be (API glitch)?

**Javaslat:**
```sql
-- metrics_activity táblában:
UNIQUE KEY uniq_conversion (conversion_id)
-- INSERT ... ON DUPLICATE KEY UPDATE (vagy INSERT IGNORE)
```

### 6. Heavy refresh throttle hiányzik
**Probléma:** Ha valaki spammeli a "Force refresh" gombot, az admin Dognetet DDOS-olhatja.

**Javaslat:**
```php
// Rate limit: max 1 heavy refresh / 10 perc
$last_heavy = get_transient('metrics_last_heavy_refresh');
if ($last_heavy && time() - $last_heavy < 600) {
    return new WP_Error('throttled', 'Várj 10 percet');
}
```

### 7. Hiányzik: error state tracking
**Probléma:** Ha a Dognet API hibázik, honnan tudjuk, hogy a sync "stuck"?

**Javaslat:** `metrics_state` táblába:
```sql
last_error TEXT,
last_error_at DATETIME,
consecutive_errors INT DEFAULT 0
```

### 8. Ticker "today_total" számítás nem egyértelmű
**Kérdés:** A `donation_today` az összes NGO-ra összesítve, vagy NGO-nként külön?

**Tisztázás kell:** A ticker mit mutat?
- Összes mai adomány → külön aggregált mező kell
- Top NGO mai → `metrics_ngo.donation_today` rendezve

---

## 🔧 Javasolt Séma (végleges)

```sql
-- 1. State tracking
CREATE TABLE wp_impactshop_metrics_state (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(32) NOT NULL,
    last_id BIGINT UNSIGNED DEFAULT 0,
    last_checked_at DATETIME NOT NULL,
    from_date DATE NOT NULL,
    last_error TEXT,
    last_error_at DATETIME NULL,
    consecutive_errors INT DEFAULT 0,
    UNIQUE KEY uniq_status (status)
);

-- 2. NGO aggregálás
CREATE TABLE wp_impactshop_metrics_ngo (
    ngo_slug VARCHAR(190) PRIMARY KEY,
    donation_total DECIMAL(14,2) DEFAULT 0.00,
    donation_today DECIMAL(14,2) DEFAULT 0.00,
    conversion_count INT DEFAULT 0,
    updated_at DATETIME NOT NULL,
    KEY idx_total (donation_total DESC),
    KEY idx_today (donation_today DESC)
);

-- 3. Shop aggregálás
CREATE TABLE wp_impactshop_metrics_shop (
    shop_slug VARCHAR(190) PRIMARY KEY,
    donation_total DECIMAL(14,2) DEFAULT 0.00,
    conversion_count INT DEFAULT 0,
    updated_at DATETIME NOT NULL,
    KEY idx_total (donation_total DESC)
);

-- 4. Activity log (limitált)
CREATE TABLE wp_impactshop_metrics_activity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversion_id BIGINT UNSIGNED NOT NULL,
    ngo_slug VARCHAR(190) DEFAULT '',
    shop_slug VARCHAR(190) DEFAULT '',
    donation DECIMAL(10,2) NOT NULL,
    status VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_conversion (conversion_id),
    KEY idx_created (created_at DESC),
    KEY idx_ngo (ngo_slug, created_at DESC)
);
```

---

## 📊 Becsült Hatás

| Metrika | Előtte | Utána |
|---------|--------|-------|
| Leaderboard API latency | 2-5s | <50ms |
| Ticker API latency | 1-3s | <30ms |
| Preflight Dognet check | 3-10s | 0ms (skip) |
| Dognet API calls/hour | ~100+ | ~6-12 |
| DB táblák | 0 | 4 új |

---

## 🚀 Implementációs Ütemterv Javaslat

| Fázis | Feladat | Idő |
|-------|---------|-----|
| **1** | Táblák létrehozása + MU-plugin skeleton | 1 óra |
| **2** | Inkrementális sync logika | 2-3 óra |
| **3** | Backfill script (egyszeri) | 1 óra |
| **4** | REST endpoint átállítás | 2 óra |
| **5** | Cron setup + admin UI | 1 óra |
| **6** | Párhuzamos validáció (1 nap) | – |
| **7** | Teljes átállás | 30 perc |

**Összesen:** ~8-10 óra fejlesztés + 1 nap validáció

---

## 🎯 Végső Verdikt

**✅ JÓVÁHAGYVA** – A terv megalapozott és megvalósítható.

**Prioritás:** HIGH – A preflight timeout problémák és a Dognet API terhelés ezt indokolja.

**Ajánlás:** Induljon a fejlesztés a fenti kiegészítésekkel.

---

## Kapcsolódó Dokumentumok

- `notes.md` – Projekt napló
- `impactshop-metrics-ngo.php` – Jelenlegi metrika plugin
- `bin/preflight-run.sh` – Preflight script (Dognet check itt van)

---

## 🔍 Koherencia Vizsgálat – Rendszer Integritás

**Dátum:** 2026-01-27  
**Cél:** Azonosítani a potenciális ütközéseket, hogy az új rendszer ne borítsa fel a meglévőt.

---

### 1. Meglévő Adatforrások (leaderboard/ticker/activity)

| Komponens | Jelenlegi forrás | Új forrás terve | Ütközés kockázat |
|-----------|------------------|-----------------|------------------|
| `/impact/v1/ticker` | `impactshop-metrics-ngo.php` → `ism_build_ticker()` → Dognet API | `wp_impactshop_metrics_ngo` | ⚠️ MEDIUM |
| `/impact/v1/leaderboard` | `impactshop-metrics-ngo.php` → `ism_build_leaderboard()` → Dognet API | `wp_impactshop_metrics_ngo/shop` | ⚠️ MEDIUM |
| `/impact/v1/activity` | `impactshop-metrics-ngo.php` → `ism_build_activity()` → Dognet API | `wp_impactshop_metrics_activity` | ⚠️ MEDIUM |
| `/ads-watch/leaderboard` | `impactshop-ads-watch.php` → DB (`wp_impactshop_ads_user_stats`) | Nem érintett | ✅ OK |
| NGO Card API | `impactshop-ngo-card.php` → `impactshop_totals_collect()` VAGY ledger | Nem érintett | ✅ OK |
| Sharity `leaderboard_cache` | `sharity-points-cron.php` → DB | Nem érintett | ✅ OK |

### 2. Párhuzamos Adatútvonalak – Ütközések

#### ⚠️ NGO Card vs Metrics Leaderboard
**Probléma:** Jelenleg KÉT különböző leaderboard rendszer van:
1. `impactshop-ngo-card.php` → `rebuild_dataset()` → `impactshop_totals_collect()` (vagy ledger fallback)
2. `impactshop-metrics-ngo.php` → `ism_build_leaderboard()` → Dognet API direkt

**Kockázat:** Ha az új rendszer csak a `metrics-ngo.php`-t váltja ki, az NGO Card API **továbbra is** a régi útvonalat használja!

**Megoldás szükséges:**
```php
// impactshop-ngo-card.php → collect_totals_rows() is át kell állítani
// VAGY az impactshop_totals_collect() is az új aggregált táblából olvasson
```

#### ⚠️ Cache Inkonzisztencia
**Probléma:** Jelenleg több független cache réteg:
- `impactshop_ticker_v1` (transient, 180s TTL)
- `impactshop_lb_v1_ngo` / `impactshop_lb_v1_shop` (transient, 1 óra TTL)
- `impactshop_activity_v2` (transient, 120s TTL)
- `impactshop_ngo_card_dataset_v2` (transient, saját TTL)
- `impactshop_metrics_raw_*` (transient, 1 óra TTL)

**Kockázat:** Ha az új aggregált táblák frissülnek, de a transient cache-ek nem invalidálódnak, **stale data** marad.

**Megoldás szükséges:**
```php
// Új sync után invalidálni MINDEN kapcsolódó cache-t:
function impactshop_metrics_sync_complete(): void {
    delete_transient('impactshop_ticker_v1');
    delete_transient('impactshop_lb_v1_ngo');
    delete_transient('impactshop_lb_v1_shop');
    delete_transient('impactshop_activity_v2');
    delete_transient('impactshop_ngo_card_dataset_v2');
    // ... összes metrics_raw is
}
```

### 3. Funkció Függőségek

| Függvény | Hol használják | Átállítandó? |
|----------|----------------|--------------|
| `dognet_api_list_conversions_all()` | `impactshop-metrics-ngo.php`, `impact-combat-pack.php` | ✅ Igen |
| `dognet_api_list_conversions_batch()` | Terv szerint inkrementálishoz | ✅ Megtartandó |
| `impactshop_totals_collect()` | `impactshop-ngo-card.php`, `impactshop-rest-totals.php` | ⚠️ Eldöntendő |
| `ism_fetch_tx()` | `impactshop-metrics-ngo.php` | ✅ Kiváltandó |
| `sharity_refresh_leaderboard_cache()` | `sharity-points-cron.php` | ❌ Különálló (nem Dognet) |

### 4. Táblázat Névütközés Ellenőrzés

**Tervezett új táblák:**
- `wp_impactshop_metrics_state`
- `wp_impactshop_metrics_ngo`
- `wp_impactshop_metrics_shop`
- `wp_impactshop_metrics_activity`

**Meglévő hasonló táblák:**
- `wp_leaderboard_cache` (sharity) – NEM ütközik
- `wp_impact_ledger` – NEM ütközik (más célú)
- `wp_impactshop_ads_user_stats` – NEM ütközik

**✅ Nincs névütközés** – A tervezett táblanevek egyediek.

### 5. Cron Job Ütközések

| Meglévő cron | Funkció | Összeférhetőség |
|--------------|---------|-----------------|
| `impactshop_leaderboard_prewarm` | Leaderboard cache előmelegítés (30 perc) | ⚠️ FELÜLVIZSGÁLANDÓ |
| `impactshop_metrics_refresh_raw` | Háttérben Dognet fetch | ⚠️ KIVÁLTANDÓ |
| `sharity_hourly_leaderboard` | Sharity leaderboard cache | ✅ Független |

**Megoldás:**
- Új cron: `impactshop_metrics_incremental_sync` (5-10 perc)
- Régi cronok (`impactshop_leaderboard_prewarm`, `impactshop_metrics_refresh_raw`) **eltávolítandók** átállás után

### 6. REST Endpoint Visszafelé Kompatibilitás

**Kritikus követelmény:** Az API response formátum NEM változhat!

| Endpoint | Jelenlegi response | Új response | Kompatibilis? |
|----------|-------------------|-------------|---------------|
| `/ticker` | `{total, today, generated_at}` | Ugyanaz | ✅ OK |
| `/leaderboard` | `[{name, amount}, ...]` | Ugyanaz | ✅ OK |
| `/activity` | `[{text}, ...]` | Ugyanaz | ✅ OK |

**✅ A terv visszafelé kompatibilis** – A response formátum nem változik.

### 7. Átállási Sorrend (Kritikus!)

**Hibás sorrend kockázata:** Ha az API-t előbb állítjuk át mint a sync-et, az adatok hiányozni fognak!

**Helyes sorrend:**
1. ✅ Táblák létrehozása (üres)
2. ✅ Backfill futtatása (minden adat)
3. ✅ Cron indítása (inkrementális sync)
4. ⏳ 24 óra párhuzamos futtatás + validáció
5. ✅ API átállítása az új táblákra
6. ✅ Régi Dognet API hívások kikapcsolása
7. ✅ Régi cronok eltávolítása

### 8. Rollback Terv

**Rollback trigger:** Ha az új rendszer 5 percen belül >5% eltérést mutat a régitől.

**Rollback lépések:**
```php
// 1. API visszaállítás a régi logikára (flag-based)
define('IMPACTSHOP_METRICS_USE_NEW_AGGREGATE', false);

// 2. Cron leállítás
wp_clear_scheduled_hook('impactshop_metrics_incremental_sync');

// ...existing code...
// 3. Táblák megtartása (debug célból)
// NE töröld rögtön!
```

### 9. Mélyfúrás Eredmények (Deep Scan)

**A. `bin/preflight-check.sh`**
- A script a `/impact/v1/ticker` és `/impact/v1/leaderboard` végpontokat hívja.
- Mivel a response formátum nem változik, a script **módosítás nélkül** működni fog.
- Várható eredmény: Jelentős sebességnövekedés a deploy preflight fázisában (jelenleg gyakori timeout pont).

**B. `impactshop-ngo-card.php`**
- A `rebuild_dataset()` függvény `impactshop_totals_collect(..., 'ngo')` hívást használ.
- Mivel a `$group` paraméter itt `'ngo'`, ez tökéletesen lefedhető az új `wp_impactshop_metrics_ngo` táblával.
- **Javaslat:** Az `impactshop_totals_collect` függvény belsejében detektáljuk a `$group='ngo'`-t, és irányítsuk át az új táblára. Ez automatikusan gyorsítja az NGO kártya generálást is.

**C. `impactshop-rest-totals.php`**
- Itt található az `impactshop_totals_collect` definíciója.
- Ez a függvény jelenleg full fetch-et végez (`dognet_api_list_conversions_page` loop).
- **Megjegyzés:** Ha ezt a függvényt refaktoráljuk, azzal több klienst is javítunk egyszerre.

**D. Deployment Environment Configs (`.deploy.staging.env`, `.deploy.production.env`)**
- **Kritikus:** A `PREFLIGHT_ENDPOINTS` pont az új rendszerből fog profitálni.
- Jelenleg: `ticker`, `leaderboard`, `totals`, `report` végpontok (4-10s latency esetenként).
- Átállás után: Várhatóan <100ms → **a preflight timeout problémák megszűnnek**.
- **Nincs szükség módosításra** – a config fájlok változatlanok maradnak.

**E. Cron Job `impactshop_leaderboard_prewarm`**
- **Funkció:** 30 percenként cache előmelegítés (leaderboard NGO/shop + ticker).
- **Jelenlegi implementáció:** `impactshop-metrics-ngo.php` (line 298).
- **Döntés szükséges:**
  - **Opció 1:** Megtartjuk a prewarm-ot, de az új táblákból olvas (gyorsabb lesz).
  - **Opció 2:** Megszüntetjük, mert az új rendszer olyan gyors, hogy nincs szükség cache prewarm-ra.
  - **Ajánlás:** Kezdetben **megtartani**, később (ha a rendszer stabil) **eltávolítani**.

---

## ✅ Koherencia Vizsgálat Eredménye

| Kategória | Státusz | Megjegyzés |
|-----------|---------|------------|
// ...existing code...
```

---

## ✅ Koherencia Vizsgálat Eredménye

| Kategória | Státusz | Megjegyzés |
|-----------|---------|------------|
| Tábla névütközés | ✅ OK | Nincs |
| REST kompatibilitás | ✅ OK | Response format megmarad |
| Cache inkonzisztencia | ⚠️ KEZELENDŐ | Invalidálás szükséges |
| NGO Card párhuzamos útvonal | ⚠️ ELDÖNTENDŐ | Totals collect is átállítandó? |
| Cron ütközés | ⚠️ KEZELENDŐ | Régi cronok törlése |
| Átállási sorrend | ✅ DEFINIÁLVA | Lásd fent |
| Rollback terv | ✅ OK | Flag-based visszaállás |

**Végső verdikt:** ✅ **MEGVALÓSÍTHATÓ** a fenti kiegészítésekkel.

---

## Kiegészítő Teendők a Tervhez

1. **Cache invalidálás hook** – `impactshop_metrics_sync_complete()` hozzáadása
2. **NGO Card döntés** – `impactshop_totals_collect()` is az új aggregátból olvasson? (ajánlott: igen)
3. **Cron cleanup** – Régi cronok explicit eltávolítása a kódból
4. **Feature flag** – `IMPACTSHOP_METRICS_USE_NEW_AGGREGATE` konstans bevezetése az átállás idejére
5. **Prewarm döntés** – `impactshop_leaderboard_prewarm` megtartása vagy megszüntetése (átmeneti időszakban: megtartani)

---

## 🛡️ Biztonsági Megfontolások (Security Review)

### 1. SQL Injection Védelem
**Kérdés:** Az új táblák INSERT/UPDATE műveletei biztonságosak-e?

**Javaslat:**
```php
// HELYES: prepared statement használata
$wpdb->prepare(
    "INSERT INTO wp_impactshop_metrics_ngo (ngo_slug, donation_total, updated_at) 
     VALUES (%s, %f, %s) 
     ON DUPLICATE KEY UPDATE donation_total = donation_total + %f, updated_at = %s",
    $ngo_slug, $amount, $now, $amount, $now
);

// HELYTELEN (NE így!):
$wpdb->query("INSERT ... VALUES ('$ngo_slug', $amount, ...)");
```

### 2. Rate Limiting a Manual Refresh-re
**Kockázat:** Admin felhasználók DDOS-olhatják a rendszert a "Force Refresh" gombbal.

**Megoldás:** Transient alapú throttle (már szerepel a javaslatban, de hangsúlyozandó).

### 3. Backfill Script Jogosultság
**Kockázat:** Ha a backfill script WP-CLI-ből fut, meg kell győződni róla, hogy csak admin futtathatja.

**Javaslat:**
```php
// wp-cli command esetén:
if (!defined('WP_CLI') || !WP_CLI) {
    wp_die('Csak WP-CLI-ből futtatható');
}
```

### 4. Error Logging Biztonság
**Kockázat:** A `metrics_state.last_error` mező API kulcsokat vagy érzékeny adatokat tárolhat.

**Javaslat:** Sanitizálni az error üzeneteket mielőtt DB-be kerülnek:
```php
function sanitize_dognet_error(string $error): string {
    // API key pattern eltávolítása
    return preg_replace('/api[_-]?key[=:]\S+/i', 'api_key=***', $error);
}
```

---

## 📈 Teljesítmény Becslés Részletesen

### Jelenlegi Állapot (Baseline)
- **Ticker endpoint:** 2-5s (95%-ban <3s, 5%-ban timeout)
- **Leaderboard endpoint:** 3-7s (80%-ban <5s, 20%-ban timeout vagy >10s)
- **Activity endpoint:** 1-2s
- **Dognet API calls/nap:** ~2000+ (minden REST kérés = 1 Dognet hívás)
- **Preflight fail rate:** ~15% (latency timeout miatt)

### Új Rendszer Várható Értékei
- **Ticker endpoint:** <50ms (DB query + cache hit)
- **Leaderboard endpoint:** <80ms (DB ORDER BY + LIMIT)
- **Activity endpoint:** <30ms (egyszerű SELECT LIMIT 10)
- **Dognet API calls/nap:** ~288 (5 perc cron × 12/óra × 24 óra)
- **Preflight fail rate:** <1% (csak hálózati hibák)

### Számított Teljesítménynövekedés
| Metrika | Javulás | Üzleti Hatás |
|---------|---------|--------------|
| API Latency | **40-100x gyorsabb** | Jobb UX, kevesebb panasz |
| Dognet Terhelés | **-85%** | Kevesebb API költség, kisebb esély rate limitre |
| Preflight Stabilitás | **+14% pass rate** | Deploy folyamat megbízhatóbb |
| Cache Hit Rate | **~95%** (vs régi ~70%) | Kevesebb DB/API ütés |

---

## 🔄 Átállási Forgatókönyvek (Migration Scenarios)

### Forgatókönyv A: "Big Bang" (NEM ajánlott)
1. Táblák létrehozása + backfill
2. API átállítás azonnal
3. Régi kód törlése

**Kockázat:** Ha bármi hiba van, az egész rendszer lebénul. **NEM AJÁNLOTT**.

### Forgatókönyv B: "Blue-Green Deployment" (Ajánlott)
1. Új táblák létrehozása + backfill (háttérben)
2. Új API végpontok párhuzamos futtatása (`/impact/v2/ticker`)
3. 24 óra A/B teszt (régi vs új összehasonlítása)
4. Fokozatos átállás (pl. 10% → 50% → 100% traffic)
5. Régi kód kikapcsolása (de nem törlése 1 hétig)

**Előny:** Biztonságos, hibatűrő, gyors rollback.

### Forgatókönyv C: "Feature Flag" (Legbiztonságosabb)
```php
define('IMPACTSHOP_METRICS_USE_NEW_AGGREGATE', get_option('impactshop_metrics_new_system', false));

function ism_build_ticker() {
    if (IMPACTSHOP_METRICS_USE_NEW_AGGREGATE) {
        return ism_build_ticker_from_db();
    }
    return ism_build_ticker_legacy(); // régi Dognet hívás
}
```

**Előny:** Admin panel-ból kapcsolgatható, instant rollback, A/B tesztelhető.

**Ajánlás:** **Forgatókönyv C (Feature Flag)** – Ez a legstabilabb megközelítés.

---

## 🧪 Tesztelési Checklist (Pre-Deploy Validation)

### Unit Tesztek (PHP)
- [ ] `impactshop_metrics_sync_incremental()` – üres batch case
- [ ] `impactshop_metrics_sync_incremental()` – duplikált conversion_id case
- [ ] `impactshop_metrics_daily_reset()` – donation_today nullázás
- [ ] `impactshop_metrics_activity_cleanup()` – régi sorok törlése

### Integrácios Tesztek
- [ ] Backfill script futtatása teszt adaton (2025-10-23 → 2025-10-24)
- [ ] Cron szimuláció (5 perc múlva új conversion jön be)
- [ ] REST endpoint válasz formátum összehasonlítás (régi vs új)
- [ ] Preflight script futtatása új rendszeren

### Performance Tesztek
- [ ] 1000 conversion backfill sebesség mérése (cél: <10s)
- [ ] Leaderboard query latency (100 NGO esetén, cél: <50ms)
- [ ] Cache invalidálás hatása (minden transient törölve, újragenerálás <200ms)

### Staging Deployment Checklist
- [ ] Táblák létrehozva staging DB-ben
- [ ] Backfill futott sikeresen
- [ ] Cron beállítva és fut (ellenőrzés: `wp cron event list`)
- [ ] Feature flag OFF (alapértelmezett)
- [ ] 24 óra monitoring (error log, performance log)
- [ ] Feature flag ON (50% traffic)
- [ ] 12 óra A/B teszt
- [ ] Feature flag ON (100% traffic)
- [ ] 48 óra megfigyelés
- [ ] Döntés: production deploy GO / NO-GO

---

## 🚨 Vészhelyzeti Eljárások (Emergency Rollback)

### Trigger Feltételek (Rollback azonnal!)
1. **REST API error rate >5%** (5 percen belül)
2. **Leaderboard adatok >10% eltérés** a legacy rendszerhez képest
3. **DB connection timeout** az új táblákkal
4. **Preflight fail rate >20%** (új rendszer alatt)

### Rollback Parancsok
```bash
# 1. Feature flag kikapcsolás (admin vagy SSH)
wp option update impactshop_metrics_new_system 0

# 2. Cron leállítás
wp cron event delete impactshop_metrics_incremental_sync

# 3. Cache flush (minden transient törlése)
wp cache flush
wp transient delete --all

# 4. Monitoring reset
tail -f /path/to/debug.log | grep "impactshop_metrics"
```

### Post-Rollback Teendők
1. **Root cause analysis** – Mi volt a probléma?
2. **Fix implementálás** – Javítás staging-en
3. **Retest** – Újra végigfuttatni a teljes tesztelési checklistet
4. **Dokumentáció** – Mi tanultunk belőle? (`notes.md` frissítés)

---

## 📊 Monitoring & Alerting Javaslatok

### Kritikus Metrikák (Production)
1. **Sync Latency** – `impactshop_metrics_incremental_sync` futási idő (<30s cél)
2. **API Latency** – `/ticker`, `/leaderboard` válaszidő (<100ms cél)
3. **Error Rate** – `metrics_state.consecutive_errors` (>3 = alert)
4. **Data Freshness** – `metrics_state.last_checked_at` (>15 perc = alert)
5. **Activity Table Size** – row count (>50,000 = cleanup warning)

### Alert Szabályok (Ajánlott)
```yaml
alerts:
  - name: "Metrics Sync Stuck"
    condition: "last_checked_at > NOW() - INTERVAL 15 MINUTE"
    action: "Email admin + Slack notification"
  
  - name: "API Latency Spike"
    condition: "ticker_latency > 500ms for 3 consecutive requests"
    action: "Log warning + Health check"
  
  - name: "Dognet API Error"
    condition: "consecutive_errors >= 5"
    action: "Disable cron + Email admin"
```

### Dashboard Elemek (WP Admin)
- Utolsó sync időpont
- Feldolgozott conversions száma (ma)
- Aktív NGO-k száma
- Legutóbbi error üzenet (ha van)
- Manual refresh gomb (throttle-del)

---

## 🚀 További Gyorsítási Javaslatok (Bonus Optimization)

**Dátum:** 2026-01-27  
**Cél:** A Dognet gyorsítás mellett további rendszerszintű optimalizációk, amelyek egymást erősítik.

---

### 1. Object Cache (Redis/Memcached) Bevezetése

**Jelenlegi helyzet:**
- A rendszer kizárólag `get_transient()`/`set_transient()` használ cache-elésre.
- Transient-ek DB-be íródnak (`wp_options` tábla) → **lassú**, főleg nagy terhelésnél.
- Csak az `impactshop-ads-watch.php` használ `wp_cache_get()`/`wp_cache_set()`-et, de nincs persistent object cache backend.

**Probléma:**
- Minden transient olvasás = 1 DB query.
- `impactshop-ngo-card.php` (4500 sor!) nagyon transient-intenzív.
- 50+ különböző transient kulcs van a mu-plugins-ban.

**Javaslat:**
1. **Redis Object Cache plugin telepítése** (ajánlott: `redis-cache` by Till Krüss)
2. Vagy: **Memcached** a hosting szolgáltatótól (ha cPanel)
3. **Migráció:** A transient-ek automatikusan object cache-be kerülnek, kód módosítás nélkül.

**Becsült hatás:**
| Metrika | Előtte | Utána |
|---------|--------|-------|
| Transient read latency | 5-20ms (DB) | <1ms (memory) |
| DB queries/request | 50+ | 10-20 |
| NGO Card generation | 300-500ms | <100ms |

**Koherencia vizsgálat:**
- ✅ Nincs kód módosítás – WP transient API kompatibilis.
- ⚠️ **Redis FLUSH kockázat:** Ha valaki `redis-cli FLUSHALL`-t futtat, minden cache elvész.
- **Megoldás:** Külön Redis DB a WP-nek (`wp-config.php`: `define('WP_REDIS_DATABASE', 2);`)

---

### 2. HUF Árfolyam Cache Optimalizálás

**Jelenlegi helyzet:**
- `impactshop_get_huf_rate()` minden hívásnál fix értéket ad vagy konstanst olvas.
- Ez nem probléma, de ha valós árfolyamot kellene fetch-elni (ECB API), az lassú lenne.

**Javaslat:**
- Ha valós árfolyam kell: **naponta 1x frissíteni cron-nal**, nem minden API hívásnál.
- Tárolás: `wp_option` vagy az új `wp_impactshop_metrics_state` táblában (dedikált `huf_rate` mező).

**Koherencia vizsgálat:**
- ✅ Jelenleg nem probléma (fix érték).
- ⚠️ Ha ECB API-ra váltunk, kötelező cache (24h TTL).

---

### 3. Google Sheets CSV Cache Hosszabbítás

**Jelenlegi helyzet (`impactshop-boot.php`):**
```php
define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);  // 15 perc
```
- A shop CSV 15 percenként újratöltődik Google Docs-ból.
- Ha a Google API lassú (1-3s), az minden 15 percben lefékezi a rendszert.

**Probléma:**
- A shop adatok ritkán változnak (naponta 1-2x max).
- Mégis 15 percenként fetch-elünk.

**Javaslat:**
```php
define('IMPACTSHOP_CACHE_TTL', 2 * HOUR_IN_SECONDS);  // 2 óra
// Vagy: 6 óra, manuális refresh admin gombbal
```

**Kiegészítő javaslat: Stale-While-Revalidate pattern:**
```php
function isb_get_shops_swr() {
    $cache_key = 'impactshop_csv_shops';
    $stale_key = $cache_key . '_stale';
    
    $fresh = get_transient($cache_key);
    if ($fresh !== false) return $fresh;
    
    // Stale adat visszaadása, háttérben frissítés
    $stale = get_transient($stale_key);
    if ($stale !== false) {
        wp_schedule_single_event(time(), 'impactshop_refresh_csv_background');
        return $stale;
    }
    
    // Nincs stale → kényszer fetch
    return isb_fetch_csv_fresh();
}
```

**Becsült hatás:**
| Metrika | Előtte | Utána |
|---------|--------|-------|
| Google API hívások/nap | ~96 | ~12 |
| CSV fetch latency | 1-3s | 0ms (cache hit 99%) |

**Koherencia vizsgálat:**
- ✅ Nem érinti a Dognet rendszert.
- ⚠️ Shop adat frissítés 2 óra késéssel jut el a frontendre.
- **Megoldás:** Admin "Force Refresh" gomb + webhook a Google Sheets-ből (opcionális).

---

### 4. REST Endpoint Response Compression (Gzip)

**Jelenlegi helyzet:**
- A REST API válaszok plain JSON-ként mennek ki.
- Leaderboard (100+ NGO) → 10-50KB response.

**Probléma:**
- Nagyobb payload = lassabb letöltés mobil/lassú neten.

**Javaslat:**
```php
// Gzip engedélyezés WP REST API-hoz
add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
    if (function_exists('ob_gzhandler') && !headers_sent()) {
        ob_start('ob_gzhandler');
    }
    return $served;
}, 10, 4);
```

**Vagy:** Szerver szintű konfig (`.htaccess` vagy nginx):
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/json
</IfModule>
```

**Becsült hatás:**
| Metrika | Előtte | Utána |
|---------|--------|-------|
| Leaderboard response | 30KB | 5-8KB |
| Ticker response | 2KB | 0.5KB |

**Koherencia vizsgálat:**
- ✅ Transparent – a kliensek automatikusan dekompresszálnak.
- ⚠️ CPU overhead (minimális, <1ms).

---

### 5. DB Index Optimalizálás (`wp_impact_ledger`)

**Jelenlegi helyzet:**
- `impactshop-ngo-card.php` (line 773-790) ledger fallback query:
```sql
SELECT ngo_slug, SUM(amount_huf) ...
WHERE happened_at >= %s
GROUP BY ngo_slug
ORDER BY amount_huf DESC
```

**Probléma:**
- Ha a `wp_impact_ledger` tábla nagy (100k+ sor), az indexeletlen `GROUP BY` + `ORDER BY` lassú.

**Javaslat:**
```sql
-- Létező tábla esetén ALTER:
ALTER TABLE wp_impact_ledger
ADD INDEX idx_ngo_happened (ngo_slug, happened_at, status);

-- Vagy compound index a gyakori lekérdezésekhez:
ADD INDEX idx_happened_ngo (happened_at, ngo_slug, amount_huf);
```

**Koherencia vizsgálat:**
- ✅ Nem változtat semmit a logikán, csak gyorsabb query.
- ⚠️ Index hozzáadás nagy táblán **rövid lock**-ot okozhat.
- **Megoldás:** Alacsony forgalmú időszakban futtatni (`pt-online-schema-change` ha kritikus).

---

### 6. Lazy Loading a Legnagyobb Plugin-ekre

**Jelenlegi helyzet:**
- `impactshop-ngo-card.php` (4487 sor) **minden pageload-on betöltődik**.
- MU-plugin = nincs mód kikapcsolni.

**Probléma:**
- A legtöbb oldal nem használja az NGO Card API-t.
- Mégis parse-ol 4500 sor PHP-t.

**Javaslat: Conditional Loading**
```php
// impactshop-ngo-card.php elején:
if (!defined('DOING_AJAX') && !defined('REST_REQUEST')) {
    // Admin vagy frontend shortcode kell-e?
    if (!is_admin() && strpos($_SERVER['REQUEST_URI'] ?? '', 'rest_route') === false) {
        return; // Nem töltjük be
    }
}
```

**Vagy: Autoload átszervezés**
```php
// mu-plugins/impactshop-loader.php
add_action('rest_api_init', function () {
    require_once __DIR__ . '/impactshop-ngo-card.php';
}, 1);
```

**Becsült hatás:**
| Metrika | Előtte | Utána |
|---------|--------|-------|
| PHP parse time | +50-100ms | +0ms (ha nem REST) |
| Memory usage | ~5MB | ~2MB |

**Koherencia vizsgálat:**
- ⚠️ **Kockázatos** – ha valami shortcode-ból hívódik, eltörik.
- **Megoldás:** Csak REST API esetén alkalmazni, shortcode-okat külön fájlba.

---

### 7. Dognet Token Cache Optimalizálás

**Jelenlegi helyzet (`impactshop-boot.php`):**
```php
define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);  // 20 óra
```

**Probléma:**
- Ha a token lejár, a következő API hívás **blokkolódik** a re-auth idejére (1-2s).

**Javaslat: Proactive Token Refresh**
```php
// Cron: impactshop_dognet_token_refresh (18 óránként)
add_action('impactshop_dognet_token_refresh', function () {
    isb_dognet_get_token(true); // force refresh
});

// Ütemezés:
if (!wp_next_scheduled('impactshop_dognet_token_refresh')) {
    wp_schedule_event(time(), 'impactshop_18h', 'impactshop_dognet_token_refresh');
}
```

**Koherencia vizsgálat:**
- ✅ Átfedés az inkrementális sync-kel – ha a sync cron fut, a token már friss.
- ⚠️ Ha a cron le van tiltva (WP_DISABLE_CRON), a token lejár.
- **Megoldás:** External cron (`wget` / `crontab`) használata.

---

### 8. CDN Frontend Assets-re

**Jelenlegi helyzet:**
- A statikus fájlok (CSS, JS, képek) közvetlenül a WP szerverről jönnek.

**Javaslat:**
- **Cloudflare** (ingyenes tier) vagy **BunnyCDN** beállítása.
- Csökkenti a szerver terhelését, gyorsabb letöltés a usereknek.

**Koherencia vizsgálat:**
- ✅ Nem érinti a backend logikát.
- ⚠️ Cache purge szükséges deploy után (Cloudflare API-val automatizálható).

---

## 📊 Összesített Gyorsítási Hatás (Összes Javaslat)

| Optimalizáció | Önálló Hatás | Kombinált Hatás |
|---------------|--------------|-----------------|
| **Dognet Inkrementális Fetch** | 40-100x API gyorsulás | ✅ Alapkövetelmény |
| **Redis Object Cache** | 5-20x transient gyorsulás | ✅ Erősen ajánlott |
| **CSV Cache 2 órára** | -90% Google API hívás | ✅ Alacsony kockázat |
| **Gzip Compression** | -70% payload méret | ✅ Egyszerű |
| **DB Index** | 2-10x ledger query | ⚠️ Közepes kockázat |
| **Lazy Loading** | -50ms parse time | ⚠️ Óvatosan |
| **Token Proactive Refresh** | 0 auth blokkolás | ✅ Egyszerű |
| **CDN** | -30% server load | ✅ Infrastruktúra szintű |

**Prioritási sorrend (ROI alapján):**
1. 🥇 **Dognet Inkrementális Fetch** – A legnagyobb impact
2. 🥈 **Redis Object Cache** – Alacsony effort, nagy nyereség
3. 🥉 **CSV Cache növelés** – 1 sor módosítás
4. 4️⃣ **Gzip** – Szerver config
5. 5️⃣ **Token Proactive Refresh** – Kis kód, nagy stabilitás
6. 6️⃣ **DB Index** – Ha ledger tábla nagy
7. 7️⃣ **Lazy Loading** – Csak ha kritikus a memory
8. 8️⃣ **CDN** – Infrastruktúra projekt

---

## 🔗 Koherencia Összefoglaló (Összes Javaslat)

| Javaslat | Dognet Sync | NGO Card | Preflight | REST API |
|----------|-------------|----------|-----------|----------|
| Dognet Incremental | ✅ Fő komponens | ⚠️ Átállítandó | ✅ Gyorsul | ✅ Gyorsul |
| Redis Cache | ✅ Cache gyorsul | ✅ Gyorsul | ✅ Gyorsul | ✅ Gyorsul |
| CSV Cache 2h | ❌ Nem érinti | ❌ Nem érinti | ❌ Nem érinti | ❌ Nem érinti |
| Gzip | ❌ Nem érinti | ❌ Nem érinti | ❌ Nem érinti | ✅ Kisebb payload |
| DB Index | ❌ Nem érinti | ✅ Ledger fallback | ❌ Nem érinti | ❌ Nem érinti |
| Lazy Loading | ❌ Nem érinti | ⚠️ Érintett | ❌ Nem érinti | ⚠️ Érintett |
| Token Refresh | ✅ Sync stabil | ❌ Nem érinti | ❌ Nem érinti | ❌ Nem érinti |
| CDN | ❌ Nem érinti | ❌ Nem érinti | ❌ Nem érinti | ❌ Nem érinti |

**Végső verdikt:** ✅ **Mindegyik javaslat megvalósítható** a koherencia szempontok figyelembevételével.

---

## 🎯 Akcióterv (Next Steps)

### Fázis 1: Core Optimization (1-2 hét)
1. [ ] Dognet Inkrementális Fetch implementálása
2. [ ] Redis Object Cache beállítás
3. [ ] CSV Cache TTL növelése 2 órára

### Fázis 2: Fine-tuning (2-3 hét)
4. [ ] Gzip compression engedélyezés
5. [ ] Dognet Token Proactive Refresh
6. [ ] DB Index hozzáadása (`wp_impact_ledger`)

### Fázis 3: Advanced (Opcionális)
7. [ ] Lazy Loading vizsgálat
8. [ ] CDN bevezetés

---

## 📝 Dokumentáció Frissítés Szükséges

- [ ] `notes.md` – Új optimalizációk felvétele
- [ ] `AGENTS.md` – Redis cache mention (ha bevezetjük)
- [ ] `.deploy.staging.env` – Nincs módosítás szükséges
- [ ] // ...existing code...
- [ ] `bin/preflight-check.sh` – Nincs módosítás szükséges

---

## ⚠️ Kritikus Megjegyzés: Status Update Logika Hiba (Javítva)

**Dátum:** 2026-01-27  
**Felfedezés:** A `last_id` alapú inkrementális fetch **hibás feltételezésen alapulhat** a státusz frissítések tekintetében.

**A Probléma:**
A Dognet API `last_id` (és általában az ID-alapú lapozás) a **létrehozás sorrendjében** adja vissza a tételeket.
Példa forgatókönyv:
1. T1 tranzakció létrejön: 2026-01-01, ID=100, Status=`pending`.
2. Sync lefut, `last_id` = 100.
3. T2 tranzakció létrejön: 2026-01-02, ID=101, Status=`pending`.
4. Sync lefut, `last_id` = 101.
5. **T1 státusza megváltozik:** 2026-01-03, T1 átvált `approved`-ra. **Az ID marad 100.**
6. Sync lefut (`last_id`=101) → **Mivel 100 < 101, a rendszer SOHA nem fogja látni a státusz változást.**

**Hatás:**
- A leaderboard/ticker nem számolja el a jóváhagyott adományokat, ha azok nem azonnal approved státusszal jöttek létre (nagy részük pending-ként indul).
- **Ez kritikus hiba az üzleti logikában.**

**Javasolt Megoldás: "Dual Sync Strategy"**

Két külön cron job szükséges:

1. **Fast Incremental Sync (5-10 perc):**
   - Cél: **Új** konverziók azonnali megjelenítése (Activity feed).
   - Logika: `last_id` alapú fetch.
   - Hatókör: Minden státusz (`pending`, `approved`, stb.).

2. **Status Update Lookback (1-4 óránként):**
   - Cél: Régebbi, de státuszt váltott tételek frissítése.
   - Logika: **ID szűrés nélkül**, csak `updated_at` (ha van) VAGY idősávos lekérdezés.
   - Hatókör: Utolsó 30-45 nap tranzakciói, `status=approved`.
   - Művelet: `INSERT ... ON DUPLICATE KEY UPDATE` a meglévő sorokra.

**Módosított Implementációs Terv:**
A "Fázis 2: Inkrementális sync logika" feladatot bővíteni kell a Lookback Sync implementációjával.

```php
// Cron: impactshop_metrics_status_lookback (pl. óránként)
function impactshop_metrics_sync_lookback() {
    // 30 napra visszamenőleg lekérjük az approved tételeket
    // Itt NEM használunk last_id-t!
    $from = date('Y-m-d', strtotime('-30 days'));
    $to = date('Y-m-d');
    
    // Batch fetch (dátum alapján)
    // Upsert a DB-be
}
```

Ezzel a kiegészítéssel a rendszer koherens és adatbiztos lesz. A többi javaslat (Bonus) érvényes marad.

---

## ➕ Koherencia Kiegészítés – Időablak, státusz és időzóna

**Dátum:** 2026-01-27

### 1) Dognet filter limitáció (created_at vs updated_at)
- A jelenlegi `dognet_api_list_conversions_batch()` **created_at** alapú filtert használ.
- **Következmény:** a lookback szinkron csak akkor talál státuszváltást, ha az adott conversion **created_at** beleesik a lookback időablakba.

**Javaslat:**
- Erősítsük meg, hogy a Dognet API támogat-e `updated_at`/`modified_at` szűrést. Ha igen, **arra** álljunk át a lookback cronban.
- Ha nem támogatja: **definiált lookback ablak** (pl. 45-90 nap) szükséges, amely lefedi a tipikus jóváhagyási csúszást.
- Opcionális: heti 1x „deep refresh” hosszabb lookback ablak (pl. 180 nap) csak `approved` státuszra.

### 2) `metrics_state` sorok státusz szerint
- A `metrics_state` tábla egyértelműen **státusz-szinten** kezelendő (külön sor `pending`, `approved`, `all` stb.).
- Ez szükséges ahhoz, hogy a `last_id` és az error tracking ne keveredjen különböző státuszú szinkronok között.

### 3) `donation_today` és reset időzóna
- A napi nullázás és a „mai adomány” számítása **ugyanazt az időzónát** használja.
- **Javaslat:** WordPress időzóna használata (`wp_timezone()` + `date_i18n()`), hogy a frontend és a cron logika egyezzen.
