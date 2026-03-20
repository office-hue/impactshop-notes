# AyeT Offerwall – UX/UI élmény & Jutalmazási rendszer terv

> **Státusz:** FINAL v1.3
> **Utolsó frissítés:** 2026-02-16
> **Előzmény:** `AYET_OFFERWALL_INTEGRATION.md`, `AYET-OFFERWALL-MIGRATION-PLAN.md`

---

## Állapot mentés (2026-02-16)
- AyeT offerwall UI átállítva **offers módra** (nem iframe) + általános tájékoztató: `impactshop-notes/wp-content/mu-plugins/impactshop-offerwall.js`.
- Új REST endpoint: `/impact/v1/offerwall/offers/{provider}` + provider `mode` mező a configban: `impactshop-notes/wp-content/mu-plugins/impactshop-offerwall.php`.
- Offerwall API fetch + HU‑only + casino tiltás + cache (2 perc): `impactshop-notes/wp-content/mu-plugins/impactshop-ayet-offerwall.php`.
- **Kötelező beállítás**: `AYET_OFFERWALL_ADSLOT` env/wp-config nélkül üres lista jön vissza.
- **Kint van** staging + prod guardos deploy (2026-02-16).

## 0. Jelenlegi helyzet összefoglalása

### Pont/szavazat rendszer (jelenlegi, kódból)

| Tevékenység | Pont | Szavazat | Forrás |
|---|---|---|---|
| Normál reklámvideó | 1 | 1 | `IMPACTSHOP_ADS_POINTS_REGULAR` / `VOTES_REGULAR` |
| Szponzor videó | 5 | 5 | `IMPACTSHOP_ADS_POINTS_SPONSOR` / `VOTES_SPONSOR` |
| Banner megtekintés | 1 | 1 | `IMPACTSHOP_ADS_POINTS_BANNER` / `VOTES_BANNER` |
| Edukációs videó (30s interval) | 5 | 5 | `IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL` / `VOTES_PER_INTERVAL` |
| IMA reklám kattintás | 5 | 5 | JS hardcoded |
| Belső survey | 10 | 10 | `points_multiplier=1.0`, `votes_multiplier=1.0`, payout=1 |

### AyeT callback-ből kapott adatok
- `payout_usd` – a tényleges publisher bevétel USD-ben
- `currency_amount` – virtuális valuta összeg (az AyeT dashboard-ban beállított konverziós ráta alapján)
- `offer_name` – feladat neve
- `event_name` – CPE task event neve
- `task_name` – CPE task felhasználói neve

### Jelenlegi AyeT reward formula (AYET_OFFERWALL_INTEGRATION.md)
```
pont     = payout_usd × 100 × points_multiplier   (default multiplier = 1.0)
szavazat = payout_usd × 10  × votes_multiplier    (default multiplier = 1.0)
```
**Példa:** $0.50 payout → 50 pont + 5 szavazat

### Probléma
1. A user NEM látja a feladatokat/feltételeket indulás előtt (csak provider-szintű switcher van, iframe-be lépés után derül ki)
2. A pont/szavazat arány nincs kalibrálva a meglévő belső rendszerhez
3. Nincs UX a feladat nehézsége, időigénye, „new user only" státusza stb. megjelenítésére

---

## 1. Jutalmazási rendszer – Kalibrált modell

### 1.1 Alapelv: Idő-alapú normalizáció

A cél: **1 perc aktív felhasználói idő ≈ állandó jutalom**, függetlenül a tevékenység típusától. Így a user mindig tisztán érti: „több idő = több pont + szavazat", és nincs se túl könnyű, se túl nehéz.

**Referencia: belső rendszer**
- 1 reklámvideó ≈ 15-30 sec → **1 pont + 1 szavazat**
- 1 edukációs videó interval (30s) → **5 pont + 5 szavazat**
- 1 belső survey kitöltés ≈ 2-3 perc → **10 pont + 10 szavazat**

**Átlag: ~3-5 pont/perc + ~3-5 szavazat/perc**

### 1.2 AyeT Offerwall jutalom kalibrálás

Az AyeT feladatok jellemzően 3 kategóriába esnek:

| Kategória | Tipikus payout | Tipikus idő | Példa |
|---|---|---|---|
| **Könnyű** (survey, signup, egyszerű CPA) | $0.10–$0.50 | 1–5 perc | „Regisztrálj és erősítsd meg az email címed" |
| **Közepes** (app install + play, trial) | $0.50–$2.00 | 5–30 perc | „Töltsd le az appot és érj el 5-ös szintet" |
| **Nehéz** (CPE multi-task, reach level 20+) | $2.00–$15.00+ | 30 perc – napok | „Érj el 20-as szintet a játékban 14 napon belül" |

#### Javasolt formula

```
pont     = ceil(payout_usd × 50)    # $0.50 → 25 pont, $2 → 100 pont, $10 → 500 pont
szavazat = ceil(payout_usd × 10)    # $0.50 → 5 szavazat, $2 → 20 szavazat, $10 → 100 szavazat
```

#### Összehasonlítás a belső rendszerrel

| Tevékenység | Becsült idő | Pont | Szavazat | Pont/perc | Szavazat/perc |
|---|---|---|---|---|---|
| Reklámvideó (1 db) | ~0.5 perc | 1 | 1 | 2 | 2 |
| Edukációs videó (2 perc) | ~2 perc | 20 | 20 | 10 | 10 |
| Belső survey | ~3 perc | 10 | 10 | 3.3 | 3.3 |
| AyeT: könnyű ($0.30) | ~3 perc | 15 | 3 | 5 | 1 |
| AyeT: közepes ($1.50) | ~15 perc | 75 | 15 | 5 | 1 |
| AyeT: nehéz ($5.00) | ~60 perc | 250 | 50 | 4.2 | 0.8 |
| AyeT: nagyon nehéz ($10) | ~180 perc+ | 500 | 100 | 2.8 | 0.6 |

**Értékelés:** Az AyeT feladatok pont/perc aránya **alacsonyabb** mint a belső rendszeré → a user nem tudja „kizsákmányolni" az AyeT-et a belső feladatok helyett, de mégis **értelmes jutalom** jár a befektetett időért. A szavazat/perc szándékosan alacsonyabb, mert az AyeT feladatok nem közvetlenül NGO-kapcsolódók.

> **🔧 Konfigurálható konstansok:**
> ```php
> define('AYET_POINTS_MULTIPLIER', 50);   // pont = ceil(payout_usd × ez)
> define('AYET_VOTES_MULTIPLIER', 10);    // szavazat = ceil(payout_usd × ez)
> define('AYET_MIN_POINTS', 5);           // minimum jutalom küszöb
> define('AYET_MIN_VOTES', 1);            // minimum szavazat küszöb
> define('AYET_MAX_POINTS_PER_TX', 2000); // cap / tranzakció (abuse védelem)
> define('AYET_MAX_VOTES_PER_TX', 500);   // cap / tranzakció
> ```

**Konzisztencia (végleges):** A jutalomszámítás **csak** backend oldalon történik (`ceil` + min/max cap). A frontend **nem számol**, kizárólag a backend által visszaadott `total_points` / `total_votes` értékeket jeleníti meg, így nincs kerekítési eltérés.

### 1.3 CPE Multi-task: részleges jutalom

CPE (Cost Per Engagement) offerek esetén az AyeT **task-onként** küld callback-et (`event_name`, `task_name`, `payout_usd`). Ez azt jelenti, hogy a user **fokozatosan** kap jutalmat, nem kell az egész offert befejezni.

**UX:** Minden részleges teljesítésnél toast üzenet: „+25 pont ✓ — Következő lépés: Érj el 10-es szintet"

### 1.4 Chargeback (visszavonás) kezelés

Ha `is_chargeback=1`:
- Pont és szavazat **visszavonása** (negatív ledger bejegyzés)
- Targeted üzenet a pseudo_id-hoz (meglévő üzenetrendszer)
- **UX:** Toast: „Egy korábbi feladat jóváírása visszavonásra került. Pontjaid korrigálva."

---

## 2. UX/UI terv – Feladat kártya rendszer

### 2.1 Architektúra: Offerwall API alapú listázás

Az AyeT **Offerwall API**-t használjuk (nem iframe-et), így teljes kontrollt kapunk a megjelenítés felett.

**API endpoint:**
```
GET https://www.ayetstudios.com/offers/offerwall_api/{ADSLOT_ID}
    ?external_identifier={pseudo_id}
    &user_agent={UA}
    &client_hints={...}
    &include_cpe=true
    &language=hu
    &num_offers=30
    &offer_sorting=ecpm
```

**Válasz kulcsmezői (offerenként):**

| Mező | Típus | Használat az UI-ban |
|---|---|---|
| `offer_name` | string | Feladat neve |
| `payout_usd` | float | Jutalom számítás alapja |
| `currency_amount` | float | Virtuális valuta összeg (megjelenítés) |
| `introduction` | string | Rövid leírás/bevezető |
| `rules_requirements` | string | Feltételek, követelmények |
| `offer_status` | string | `new` / `started` / `in_progress` |
| `offer_status_days_left` | int/null | Hátralévő napok |
| `max_conversion_time` | int | Max idő (napokban) |
| `tracking_link` | string | Kattintás URL |
| `icon_url` / `icon_large` | string | Ikon |
| `video_url` | string | Videó preview (opcionális) |
| `categories` | array | `games`, `shopping`, `finance` stb. |
| `tags` | array | Kategória + task címkék |
| `store_id` | string | App Store / Play Store ID |
| `rating` | float | App értékelés |
| `screenshots` | array | App képernyőképek |
| `offer_complexity` | string | Nehézség jelzés |
| `cpe_instructions` | array | CPE multi-task lépések |
| `cpe_instructions[].task_name` | string | Lépés neve |
| `cpe_instructions[].currency_amount` | float | Lépéshez járó jutalom |
| `cpe_instructions[].type` | string | `regular` / `bonus_task` |
| `cpe_instructions[].status` | string | `available` / `completed` / `unavailable` |
| `cpe_instructions[].remaining_time` | int/null | Hátralévő idő |
| `cpe_instructions[].max_conversion_time` | int | Max idő ehhez a taskhoz |
| `impression_url` | string | Impression tracking pixel |
| `support_url` | string | Támogatás link |
| `kpi` | object | Konverziós mutatók |

### 2.2 Feladat kártya layout

```
┌─────────────────────────────────────────────────────────┐
│ [Ikon]  Feladat neve                        [Nehézség]  │
│         ───────────                         ⭐ Könnyű   │
│                                                         │
│  📝 Rövid leírás (introduction, max 2 sor)              │
│                                                         │
│  ┌───────────────────────────────────────────────────┐  │
│  │ 🎯 Jutalom:  75 pont  +  15 szavazat             │  │
│  │ ⏱ Becsült idő: ~15 perc                          │  │
│  │ 📅 Határidő: 14 nap                              │  │
│  └───────────────────────────────────────────────────┘  │
│                                                         │
│  ⓘ  Feltételek ▼ (kinyitható accordion)                │
│  ┌───────────────────────────────────────────────────┐  │
│  │ • Új felhasználó szükséges                        │  │
│  │ • Android 10+ szükséges                           │  │
│  │ • Érd el a 5-ös szintet a játékban                │  │
│  └───────────────────────────────────────────────────┘  │
│                                                         │
│  [Lépések] (CPE multi-task, ha van):                    │
│  ✅ 1. Telepítés          +10 pont  +2 szavazat         │
│  ⬜ 2. Regisztráció       +15 pont  +3 szavazat         │
│  🔒 3. 5-ös szint elérés  +50 pont  +10 szavazat        │
│                                                         │
│              [ 🚀 Feladat indítása ]                    │
│                                                         │
│  📊 4.2 ⭐ · Játékok · 500K+ letöltés                  │
  └─────────────────────────────────────────────────────────┘
```

---

## 9. Koherencia és biztonsági vizsgálat (végleges)

### Koherencia
- AyeT offerlist API → backend transzformáció (`total_points`, `total_votes`, `difficulty`, `cpe_steps`) → frontend kizárólag megjelenít.
- AyeT offers mód **nem** iframe: a provider választó csak akkor jelenik meg, ha több provider aktív.
- HU-only szűrés és casino tiltás szerveroldalon történik, így a kliens nem tudja kikerülni.

### Biztonság
- Postback: HMAC ellenőrzés (`x-ayetstudios-security-hash` / `security_hash`) + rate limit (IP és pseudo).
- IP allowlist támogatott (ha AyeT ad listát, `AYET_ALLOWED_IPS`).
- Napi cap: pont/szavazat/tx limit (abuse védelem).
- Reversal kezelve: pont/szavazat visszavonás + ledger „declined” + célzott üzenet.

### Nyitott admin beállítások
- `AYET_OFFERWALL_ADSLOT` kötelező (env/wp-config).
- AyeT callback URL: `/wp-json/impact/v1/ayet-callback`.

### 2.3 Nehézség jelzés logika

Az `offer_complexity` mező + `payout_usd` + `max_conversion_time` alapján:

```php
function ayet_difficulty_label(array $offer): array {
    $payout = (float) ($offer['payout_usd'] ?? 0);
    $days   = (int) ($offer['max_conversion_time'] ?? 0);
    $tasks  = count($offer['cpe_instructions'] ?? []);

    if ($payout < 0.50 && $days <= 1 && $tasks <= 1) {
        return ['label' => '⭐ Könnyű',   'color' => '#22c55e', 'minutes' => '1–5'];
    }
    if ($payout < 2.00 && $days <= 7 && $tasks <= 3) {
        return ['label' => '⭐⭐ Közepes', 'color' => '#f59e0b', 'minutes' => '5–30'];
    }
    if ($payout < 5.00 && $days <= 14) {
        return ['label' => '⭐⭐⭐ Kihívás', 'color' => '#ef4444', 'minutes' => '30–120'];
    }
    return ['label' => '🏆 Nagykihívás', 'color' => '#7c3aed', 'minutes' => '2+ óra'];
}
```

### 2.4 Szűrők és rendezés

**Szűrő sáv (a Task Hub tetején):**
```
[Összes] [⭐ Könnyű] [⭐⭐ Közepes] [⭐⭐⭐ Kihívás] [🏆 Nagy]
[Legtöbb pont ▼] [Leggyorsabb ▼] [Legújabb ▼]
```

**Rendezési opciók:**
- `payout` (legtöbb pont) – default
- `max_conversion_time` (leggyorsabb)
- `offer_status` (folyamatban lévők elöl)
- `ecpm` (legjobb ár-érték – backend rendezés)

### 2.5 Folyamatban lévő feladatok kiemelése

Ha `offer_status = "started"` vagy `"in_progress"`:
- A kártya tetején **kék sáv**: „Folyamatban – X nap van hátra"
- CPE lépéseknél látszik, melyik van kész (✅) és melyik következik (⬜)
- A lista tetejére kerül (sticky section: „Aktív feladataid")

### 2.6 Impression tracking

Minden megjelenített offer kártyánál betöltjük az `impression_url`-t:
```html
<img src="{impression_url}" style="width:1px;height:1px;border:0;" loading="lazy" alt="" />
```
Ez javítja az eCPM-et és az AyeT által ajánlott offerek minőségét.

---

## 3. Task Hub UX – Integrálás a meglévő rendszerbe

### 3.1 Provider tab-ok (Feladatok szekción belül)

```
┌─────────────────────────────────────────────────┐
│ Feladatok                                       │
├──────────┬──────────┬──────────┬────────────────┤
│ 📋 Kvíz  │ 📊 Kérdőív│ 🎁 Offerwall│ 📋 Aktívak  │
│  (belső) │  (belső) │  (AyeT)  │  (összesített)│
└──────────┴──────────┴──────────┴────────────────┘
```

- **Kvíz** – meglévő cikk kvíz
- **Kérdőív** – meglévő belső survey
- **Offerwall** – AyeT Offerwall API kártya lista
- **Aktívak** – összesített nézet a folyamatban lévő feladatokról (minden providerből)

### 3.2 Offerwall tab UX flow

1. Tab kattintás → Offerwall API hívás (kliens-oldali fetch, pseudo_id-vel)
2. Loading skeleton (3 kártya placeholder)
3. Kártya lista renderelése + impression tracking pixel betöltése
4. Kártya kattintás → részletek accordion kinyit
5. „Feladat indítása" gomb → `tracking_link` megnyitása:
   - Desktop: új tab (`target="_blank" rel="noopener noreferrer"`)
   - Mobil: külső böngésző link (stabilabb tracking)
6. Callback érkezik → pont/szavazat jóváírás → toast értesítés

### 3.3 Jutalom kommunikáció a kártyán

**Mindig jól láthatóan:**
```
🎯 Jutalom: 75 pont + 15 szavazat
```

**CPE multi-task esetén lépésenként is:**
```
Lépés 1: Telepítés        → +10 pont  +2 szavazat
Lépés 2: Regisztráció     → +15 pont  +3 szavazat  
Lépés 3: 5-ös szint       → +50 pont  +10 szavazat
─────────────────────────────────────────────────
Összesen:                    75 pont   15 szavazat
```

### 3.4 Hibakezelés és edge case-ek

| Eset | UX válasz |
|---|---|
| API nem elérhető / timeout | „A feladatok betöltése nem sikerült. Próbáld újra!" + retry gomb |
| 0 elérhető offer | „Jelenleg nincs elérhető feladat a régiódban. Nézz vissza később!" |
| User AdBlock-ot használ | Baráti üzenet (nem agresszív) |
| Offer lejárt (kártya kattintás után) | „Ez a feladat már nem elérhető." + lista frissítés |
| Rate limit (429) | Exponential backoff + „Túl sok kérés, kérlek várj..." |

---

## 4. Részletes jutalom szabályok

### 4.1 AyeT Currency beállítás (Dashboard)

Az AyeT dashboardon be kell állítani:
- **Currency name:** „Impact Pont"
- **Conversion rate:** `50` (1 USD = 50 pont → `currency_amount = payout_usd × 50`)

Így a `currency_amount` közvetlenül **pont értékben** érkezik a callback-ben. A szavazatot mi számítjuk:
```php
$points = max(AYET_MIN_POINTS, min(AYET_MAX_POINTS_PER_TX, (int) ceil($currency_amount)));
$votes  = max(AYET_MIN_VOTES, min(AYET_MAX_VOTES_PER_TX, (int) ceil($payout_usd * AYET_VOTES_MULTIPLIER)));
```

### 4.2 Callback pont-szavazat összefoglaló

| payout_usd | currency_amount (×50) | Pont | Szavazat |
|---|---|---|---|
| $0.10 | 5 | 5 | 1 |
| $0.20 | 10 | 10 | 2 |
| $0.50 | 25 | 25 | 5 |
| $1.00 | 50 | 50 | 10 |
| $2.00 | 100 | 100 | 20 |
| $5.00 | 250 | 250 | 50 |
| $10.00 | 500 | 500 | 100 |
| $15.00 | 750 | 750 | 150 |

### 4.3 Koherencia a belső rendszerrel

| Összehasonlítás | Pont/perc | Szavazat/perc |
|---|---|---|
| Reklámvideó (15 sec) | ~4 | ~4 |
| Edukációs videó (30 sec) | ~10 | ~10 |
| Belső survey (~3 perc) | ~3.3 | ~3.3 |
| **AyeT könnyű ($0.30, ~3 min)** | **~5** | **~1** |
| **AyeT közepes ($1.50, ~15 min)** | **~5** | **~1** |
| **AyeT nehéz ($5, ~60 min)** | **~4** | **~0.8** |

> **Konklúzió:**
> - **Pont/perc** hasonló a belső rendszerhez (3-5 pont/perc) → a user nem érez nagy aránytalanságot.
> - **Szavazat/perc** szándékosan alacsonyabb AyeT-nél (~1 vs ~3-4), mert:
>   - A szavazat közvetlenül befolyásolja az NGO donation pool-t
>   - Az AyeT feladatok nem Sharity-specifikus aktivitások
>   - Megelőzi a donation pool szavazat-inflációját
> - A **pont** bőkezűbb, mert az badge/szint rendszerhez kell → motiváció fenntartása

### 4.4 Napi/heti cap (opcionális, abuse védelem)

```php
define('AYET_DAILY_POINTS_CAP', 2000);    // max 2000 pont/nap/pseudo_id
define('AYET_DAILY_VOTES_CAP', 200);      // max 200 szavazat/nap/pseudo_id
define('AYET_DAILY_TX_CAP', 50);          // max 50 tranzakció/nap/pseudo_id
```

Ha a cap-et eléri → pont/szavazat nem kerül jóváírásra, de a ledger bejegyzés megtörténik `status='capped'` jelzéssel.

**Ledger karbantartás (végleges):** A `status='capped'` rekordok 90 nap után archiválandók vagy törlendők (havi cron / guard), mert nem képviselnek valódi jutalmat, és feleslegesen növelik az adatbázist.

---

## 5. Toast és visszajelzés rendszer

### 5.1 Toast típusok

| Esemény | Toast szöveg | Ikon |
|---|---|---|
| Feladat teljesítve | „🎉 +{pont} pont +{szavazat} szavazat – {offer_name}" | confetti |
| CPE részleges | „✓ +{pont} pont – Következő: {next_task_name}" | check |
| Chargeback | „⚠️ Feladat jóváírás visszavonva. Pontjaid korrigálva." | warning |
| Cap elérve | „ℹ️ Napi pontlimit elérve. Holnap újra gyűjthetsz!" | info |

### 5.2 Összesített jutalom panel

A feladatok tab tetején mindig látszik:
```
┌─────────────────────────────────────────┐
│  Ma gyűjtöttél:  125 pont + 18 szavazat │
│  Összes AyeT:    1,250 pont + 210 szav. │
└─────────────────────────────────────────┘
```

---

## 6. Implementációs fájlok és lépések

### 6.1 Szükséges fájlok

```
wp-content/mu-plugins/
├── sharity-ayet-callback.php          # Callback handler (HMAC+IP+ledger+reward)
├── sharity-ayet-offerwall.php         # Offerwall API proxy + shortcode
├── sharity-ayet-offerwall.js          # Kliens: API fetch + kártya render + szűrők
├── sharity-ayet-offerwall.css         # Kártya stílusok (dark theme, glassmorphism)
└── impact-ledger-migration-unified.php # Ledger séma bővítés
```

### 6.2 AyeT Dashboard beállítások

1. **Placement:** Website – `app.sharity.hu`
2. **AdSlot:** Offerwall API típus
3. **Currency name:** „Impact Pont"
4. **Conversion rate:** `50` (1 USD = 50 Impact Pont)
5. **Callback URL:**
   ```
   https://app.sharity.hu/wp-json/impact/v1/ayet-callback
     ?transaction_id={transaction_id}
     &payout_usd={payout_usd}
     &currency_amount={currency_amount}
     &external_identifier={external_identifier}
     &offer_name={offer_name}
     &offer_id={offer_id}
     &event_name={event_name}
     &task_name={task_name}
     &is_chargeback={is_chargeback}
   ```
6. **HMAC:** Bekapcsolva
7. **Reversal callbacks:** Bekapcsolva
8. **IP Whitelist:** A 6 callback IP beállítva szerveren

### 6.3 Implementációs sorrend

| # | Lépés | Fájl | Prioritás |
|---|---|---|---|
| 1 | Callback handler implementálás | `sharity-ayet-callback.php` | P0 |
| 2 | Ledger séma bővítés | `impact-ledger-migration-unified.php` | P0 |
| 3 | Offerwall API proxy (szerverre) | `sharity-ayet-offerwall.php` | P1 |
| 4 | Kártya render JS + CSS | `sharity-ayet-offerwall.js/.css` | P1 |
| 5 | Task Hub provider tab integráció | `impactshop-ads-watch.php/.js` | P1 |
| 6 | AyeT Dashboard konfiguráció | Dashboard | P1 |
| 7 | Staging teszt + smoke | — | P2 |
| 8 | Production deploy + monitoring | — | P2 |

---

## 7. Biztonsági szempontok

### 7.1 API proxy (szerver-oldali)

Az Offerwall API hívást **szerveren** végezzük (nem közvetlenül a kliensről), így:
- Az `AYET_API_KEY` nem kerül a böngészőbe
- IP-t a szerver küldi (pontosabb geolokáció/device match)
- Rate limit a mi oldalunkon is szabályozható

**REST endpoint:** `GET /wp-json/impact/v1/ayet-offers`
- Input: pseudo_id (cookie-ból), user_agent, client_hints
- Output: transzformált offer lista (pont/szavazat előre kiszámítva, tracking linkek megőrizve)

### 7.2 Tracking link biztonság

- `rel="noopener noreferrer"` minden AyeT linken
- CSP header frissítés: `frame-src` / `connect-src` bővítés ayetstudios.com domainre
- HTTPS kötelező

---

## 8. Monitorozás és KPI-k

| KPI | Leírás | Cél |
|---|---|---|
| `ayet_offers_loaded` | Sikeres API hívások / nap | >90% success rate |
| `ayet_offers_clicked` | Tracking link kattintások | Konverzió tracking |
| `ayet_callbacks_ok` | Sikeres callback-ek | HMAC pass rate >99% |
| `ayet_points_awarded` | Kiosztott pontok összesen | Trendkövetés |
| `ayet_votes_awarded` | Kiosztott szavazatok összesen | Donation pool hatás |
| `ayet_chargebacks` | Visszavonások | <5% |
| `ayet_daily_cap_hits` | Napi cap elérések | Abuse indikátor |

---

## 9. Döntések (végleges)

| # | Kérdés | Döntés | Indoklás |
|---|---|---|---|
| 1 | Multiplier értékek | **pont: ×50, szavazat: ×10** | 3-5 pont/perc megfelel a belső rendszernek; szavazat alacsonyabb → donation pool védelem. 4 hét után KPI review, ±20%-os finomhangolás megengedett. |
| 2 | Surveywall külön tab? | **Nem, egységes Offerwall tab** | Az AyeT Offerwall API már tartalmazza a survey típusú offereket is, külön tab feleslegesen bonyolítja a UX-et. A szűrőkkel kategorizálható (📋 Kérdőív szűrő). |
| 3 | Impression tracking | **Kötelező, automatikus** | Minden megjelenített kártyánál 1×1 pixel betöltés. Javítja az eCPM-et → jobb offerek → több bevétel. |
| 4 | Napi cap értékek | **2000 pont / 200 szavazat / 50 tx** | Konzervative start. 1 hónap után log elemzés: ha <5% user éri el → cap lazítás. Ha >10% → abuse vizsgálat. |
| 5 | Reward Status API | **P1 — beépül az offer kártyába** | A CPE lépés-státuszok kritikusak a UX-hez. Az API `GET /rest/v1/userSupport/get_reward_status` polling (5 perces cache) az Offerwall tab megnyitásakor. |
| 6 | `offer_complexity` használata | **Igen, kombinált heurisztika** | Ha az AyeT API küldi → elsődleges. Ha nincs → fallback a saját `payout+days+tasks` heurisztikára. Mindkettő leképeződik a 4 nehézségi szintre. |

---

## 10. CPE multi-task – részletes UX

### 10.1 CPE kártya megjelenítés

CPE (Cost Per Engagement) offerek több lépésből állnak, amelyek mindegyikéért külön jutalom jár. A kártyán a lépések **stepper** formában jelennek meg:

```
┌─────────────────────────────────────────────────────────┐
│ [Ikon]  Raid: Shadow Legends            ⭐⭐⭐ Kihívás │
│                                                         │
│  📝 Töltsd le az appot és teljesítsd a lépéseket        │
│                                                         │
│  ┌───────────────────────────────────────────────────┐  │
│  │ 🎯 Összjutalom:  250 pont  +  50 szavazat        │  │
│  │ ⏱ ~60 perc  ·  📅 14 nap határidő                │  │
│  └───────────────────────────────────────────────────┘  │
│                                                         │
│  Lépések:                                               │
│  ┌─────────────────────────────────────────────────┐    │
│  │ ✅  1. Telepítés + regisztráció                  │    │
│  │     +10 pont  +2 szavazat       [Kész ✓]        │    │
│  ├─────────────────────────────────────────────────┤    │
│  │ 🔵  2. Érj el 5-ös szintet                      │    │
│  │     +40 pont  +8 szavazat    [Folyamatban…]     │    │
│  │     ⏳ 12 nap van hátra                          │    │
│  ├─────────────────────────────────────────────────┤    │
│  │ 🔒  3. Érj el 15-ös szintet                     │    │
│  │     +100 pont  +20 szavazat     [Zárolva]       │    │
│  ├─────────────────────────────────────────────────┤    │
│  │ ⭐  4. BÓNUSZ: Érj el 25-ös szintet             │    │
│  │     +100 pont  +20 szavazat    [Bónusz lépés]   │    │
│  └─────────────────────────────────────────────────┘    │
│                                                         │
│  ┌─ Feltételek ──────────────────────────────────────┐  │
│  │ • Csak új felhasználók                            │  │
│  │ • iOS 14+ vagy Android 10+                        │  │
│  └───────────────────────────────────────────────────┘  │
│                                                         │
│              [ 🚀 Feladat indítása ]                    │
│              vagy ha folyamatban:                        │
│              [ ▶️ Folytatás az appban ]                  │
└─────────────────────────────────────────────────────────┘
```

### 10.2 CPE lépés státusz ikonok

| AyeT `status` | Ikon | Szín | Magyar label |
|---|---|---|---|
| `completed` | ✅ | `#22c55e` (zöld) | Kész |
| `available` (és offer started) | 🔵 | `#3b82f6` (kék) | Folyamatban |
| `available` (offer nem started) | ⬜ | `#94a3b8` (szürke) | Elérhető |
| `unavailable` | 🔒 | `#475569` (sötétszürke) | Zárolva |
| `bonus_task` típus | ⭐ | `#f59e0b` (arany) | Bónusz lépés |

### 10.3 CPE jutalom számítás

Minden CPE lépéshez az AyeT API küldi a `currency_amount`-ot (ami a konverziós ráta ×50 alapján már pont-értékben van). A kártyán **lépésenként** megjelenítjük:

```php
foreach ($offer['cpe_instructions'] as $step) {
    $step_points = max(AYET_MIN_POINTS, (int) ceil($step['currency_amount']));
    $step_votes  = max(AYET_MIN_VOTES, (int) ceil($step['payout_usd'] * AYET_VOTES_MULTIPLIER));
    // render: "{step.task_name} → +{step_points} pont +{step_votes} szavazat"
}
```

### 10.4 Reward Status API polling

Az Offerwall tab megnyitásakor + 5 percenként a kliens meghívja a szerver-proxy-t:

```
GET /wp-json/impact/v1/offerwall/reward-status
```

A szerver:
1. Lekéri az AyeT Reward Status API-t: `GET https://www.ayetstudios.com/rest/v1/userSupport/get_reward_status?placementId={ADSLOT}&externalIdentifier={pseudo_id}`
2. Cache: 5 perc (transient)
3. Visszaadja a CPE lépés-státuszokat offer-enként
4. A kliens merge-öli az Offerwall API válasszal → kártyán a lépések státusza frissül

---

## 11. Nehézségi szint – végleges logika

### 11.1 Kombinált heurisztika

```php
function impactshop_ayet_difficulty(array $offer): array {
    // 1. Ha az AyeT API küldi az offer_complexity-t → elsődleges
    $api_complexity = strtolower(trim((string) ($offer['offer_complexity'] ?? '')));
    if ($api_complexity !== '') {
        $map = [
            'easy'   => ['tier' => 1, 'label' => '⭐ Könnyű',      'color' => '#22c55e', 'est' => '1–5 perc'],
            'medium' => ['tier' => 2, 'label' => '⭐⭐ Közepes',    'color' => '#f59e0b', 'est' => '5–30 perc'],
            'hard'   => ['tier' => 3, 'label' => '⭐⭐⭐ Kihívás',  'color' => '#ef4444', 'est' => '30–120 perc'],
        ];
        if (isset($map[$api_complexity])) {
            return $map[$api_complexity];
        }
    }

    // 2. Fallback: payout + max_conversion_time + task count heurisztika
    $payout = (float) ($offer['payout_usd'] ?? 0);
    $days   = (int)   ($offer['max_conversion_time'] ?? 0);
    $tasks  = count($offer['cpe_instructions'] ?? []);

    if ($payout < 0.50 && $days <= 1 && $tasks <= 1) {
        return ['tier' => 1, 'label' => '⭐ Könnyű',      'color' => '#22c55e', 'est' => '1–5 perc'];
    }
    if ($payout < 2.00 && $days <= 7 && $tasks <= 3) {
        return ['tier' => 2, 'label' => '⭐⭐ Közepes',    'color' => '#f59e0b', 'est' => '5–30 perc'];
    }
    if ($payout < 5.00 && $days <= 14) {
        return ['tier' => 3, 'label' => '⭐⭐⭐ Kihívás',  'color' => '#ef4444', 'est' => '30–120 perc'];
    }
    return ['tier' => 4, 'label' => '🏆 Nagykihívás', 'color' => '#7c3aed', 'est' => '2+ óra'];
}
```

### 11.2 Szűrő logika

A szűrő gombok a `tier` értéken dolgoznak (1–4). „Összes" = nincs szűrés.

---

## 12. Toast és valós idejű visszajelzés – részletes megvalósítás

### 12.1 Toast megjelenítés mechanizmus

A toast egy 4 másodpercig látható, jobb alsó sarokba animált elem. A callback-ből eredő jóváírás **nem** érkezik valós időben a kliensre (nincs WebSocket a shared hosting-on), ezért két mechanizmus:

**A) Offerwall tab polling (passzív):**
- Minden 60 másodpercben a kliens meghívja `/offerwall/history`-t
- Ha új elem jelent meg az utolsó lekérdezés óta → toast megjelenítés
- Előny: egyszerű, nincs extra infra

**B) Service Worker push (jövő, P3):**
- Ha a PWA service worker bekapcsol → Web Push notification a callback handler-ből
- Előny: a user más oldalon is értesül

### 12.2 Toast design

```
┌──────────────────────────────────────────┐
│ 🎉  +75 pont  +15 szavazat              │
│     Raid: Shadow Legends – 5-ös szint   │
│     ──────────────────────── [×]         │
└──────────────────────────────────────────┘
```

Animáció: slideInRight → 4s → slideOutRight. Max 3 toast egymás felett.

### 12.3 Összesítő panel az Offerwall tab tetején

```html
<div class="offerwall-stats">
  <div class="offerwall-stat">
    <span class="offerwall-stat-value" data-role="stat-today-points">0</span>
    <span class="offerwall-stat-label">pont ma</span>
  </div>
  <div class="offerwall-stat">
    <span class="offerwall-stat-value" data-role="stat-today-votes">0</span>
    <span class="offerwall-stat-label">szavazat ma</span>
  </div>
  <div class="offerwall-stat">
    <span class="offerwall-stat-value" data-role="stat-active-tasks">0</span>
    <span class="offerwall-stat-label">aktív feladat</span>
  </div>
</div>
```

A stat-ok a `/offerwall/history` válaszból számolódnak (ma = `created_at` mai dátum).

---

## 13. AyeT Dashboard beállítások – végleges konfiguráció

### 13.1 Kötelező beállítások

| Beállítás | Érték | Megjegyzés |
|---|---|---|
| Placement típus | Website | `app.sharity.hu` |
| AdSlot típus | Offerwall API | Nem iframe! |
| Currency name | Impact Pont | |
| Conversion rate | 50 | 1 USD = 50 Impact Pont |
| HMAC signature | ✅ Bekapcsolva | `AYET_API_KEY` = HMAC secret |
| Reversal callbacks | ✅ Bekapcsolva | `is_chargeback=1` vagy `reversal=1` |
| CPE campaigns | ✅ Include | `include_cpe=true` query param |

### 13.2 Callback URL (végleges)

```
https://app.sharity.hu/wp-json/impact/v1/ayet-callback?transaction_id={transaction_id}&payout_usd={payout_usd}&currency_amount={currency_amount}&external_identifier={external_identifier}&offer_name={offer_name}&offer_id={offer_id}&event_name={event_name}&task_name={task_name}&task_uuid={task_uuid}&is_chargeback={is_chargeback}
```

> `task_uuid` hozzáadva a CPE dedup-hoz.

### 13.3 IP Whitelist (callback szerverre)

```
51.79.101.241
158.69.185.134
158.69.185.154
35.165.166.40
35.166.159.131
52.40.3.140
```

Ezeket a `AYET_ALLOWED_IPS` konstansban és/vagy Cloudflare WAF-ban is engedélyezni kell.

**IP lista karbantartás (végleges):** Negyedévente kötelező felülvizsgálni az AyeT IP listát a hivatalos dokumentáció alapján. A HMAC aláírás az elsődleges védelem, az IP lista csak másodlagos védvonal.

---

## 14. Implementációs checklist

### Fázis 1 – Backend (P0, ~2 nap)

- [ ] `impactshop-ayet-offerwall.php` → `fetch_offers()`: `include_cpe=true`, `language=hu`, `num_offers=30`, `offer_sorting=ecpm` query params hozzáadása
- [ ] `impactshop-ayet-offerwall.php` → `fetch_offers()`: teljes offer adat visszaadása (icon, introduction, rules_requirements, cpe_instructions, offer_complexity, rating, max_conversion_time, payout_usd, currency_amount, impression_url, offer_status, offer_status_days_left, categories)
- [ ] `impactshop-ayet-offerwall.php` → `fetch_offers()`: nehézségi szint számítás + pont/szavazat előszámítás az offer adatra
- [ ] `impactshop-ayet-offerwall.php` → `calculate_points()` / `calculate_votes()`: átírás ×50/×10 multiplierekre + min/max cap
- [ ] `impactshop-ayet-offerwall.php` → napi cap logika: `AYET_DAILY_POINTS_CAP` / `AYET_DAILY_VOTES_CAP` / `AYET_DAILY_TX_CAP`
- [ ] `impactshop-offerwall.php` → Reward Status API proxy endpoint: `GET /offerwall/reward-status`
- [ ] `impactshop-offerwall.php` → Összesítő stat endpoint: `GET /offerwall/stats` (ma gyűjtött pont/szavazat/aktív feladatok)
- [ ] AyeT Dashboard: currency rate = 50, HMAC bekapcsolva, callback URL frissítés (`task_uuid` hozzáadva)

### Fázis 2 – Frontend (P1, ~3 nap)

- [ ] `impactshop-offerwall.js` → `renderOffers()`: teljes kártya UI (ikon, nehézség badge, jutalom box, CPE stepper, feltételek accordion, impression pixel)
- [ ] `impactshop-offerwall.js` → szűrő sáv (nehézség gombok + rendezés dropdown)
- [ ] `impactshop-offerwall.js` → skeleton loading (3 placeholder kártya)
- [ ] `impactshop-offerwall.js` → „Aktív feladataid" szekció (offer_status=started/in_progress → lista tetejére)
- [ ] `impactshop-offerwall.js` → összesítő stat panel az Offerwall tab tetején
- [ ] `impactshop-offerwall.js` → toast rendszer (history polling + új elem detekció)
- [ ] `impactshop-offerwall.js` → Reward Status API polling (5 perc) + CPE lépés-státusz merge
- [ ] `impactshop-offerwall.php` → inline CSS: kártya redesign, nehézség badge színek, jutalom box, CPE stepper, szűrő sáv, toast, skeleton, active badge, responsive

### Fázis 3 – Teszt & deploy (P2, ~1 nap)

- [ ] Staging deploy: `bash scripts/hotfix-sync.sh <files> --dry-run`
- [ ] Manuális QA: offer betöltés, kártya megjelenítés, szűrők, tracking link, callback szimuláció
- [ ] `~/bin/impactall` → guard pass
- [ ] Production deploy
- [ ] 1 hét monitorozás: guard-events.log + KPI-k (§8 táblázat)
- [ ] 4 hét review: multiplier finomhangolás, cap adjustments

---

## 15. Mobil UX – részletes terv

### 15.1 Responsive kártya layout

| Képernyőszélesség | Kártya grid | Kártyaszélesség |
|---|---|---|
| > 1024px (desktop) | 3 oszlop | `minmax(280px, 1fr)` |
| 641–1024px (tablet) | 2 oszlop | `minmax(260px, 1fr)` |
| ≤ 640px (mobil) | 1 oszlop | teljes szélesség |

### 15.2 Mobil-specifikus UX adaptációk

**Kártya interakciók:**
- Desktop: hover → enyhe kiemelés (`translateY(-2px)`, shadow)
- Mobil: **nincs hover effekt**, tap → instant visual feedback (`:active` → `scale(0.98)` + háttérszín váltás)
- Hosszú kártyák (CPE stepper 4+ lépés): összecsukva jelennek meg, „Mutasd mind (5 lépés)" gombbal kinyitható

**Szűrő sáv:**
- Mobil: **horizontálisan görgethető** (overflow-x: auto, scrollbar hidden), nem törik több sorra
- A „Rendezés" dropdown teljes szélességű mobil modal-ként nyílik meg (az apró select nem fingerfriendly)

**Toast pozíció:**
- Desktop: jobb alsó sarok
- Mobil: **felső középre** helyezve (a thumb zone-ból kilógó jobb-alsó nem ideális kicsi kijelzőn)
- Szélességi korlát: `max-width: calc(100vw - 32px)`

**Tracking link megnyitás:**
- Desktop: `window.open(url, '_blank')` — új tab
- Mobil: `window.location.href = url` — **ugyanabban a tab-ban** navigál, mert a mobil böngészők popup-blockerrel blokkolhatják a `window.open`-t. A user az Offerwall-ra a böngésző back gombbal tér vissza.
- Alternatíva mobilon: `<a href="..." target="_blank">` link elem, ami megbízhatóbb mint a JS `window.open`
- iOS-en a "Swipe Back" gyakran újratölti az oldalt, ezért a scroll pozíciót `sessionStorage`-ba mentjük kilépés előtt, és visszatéréskor visszaállítjuk (ha az Offerwall tab aktív), hogy a lista ne ugorjon az elejére.

### 15.3 Touch target minimum méretek

| Elem | Min. touch target | Megjegyzés |
|---|---|---|
| Szűrő gomb | 44×36px | Apple HIG minimum |
| „Feladat indítása" CTA | 48px magas, teljes kártya szél. | Jól megkülönböztethető |
| Toast „×" bezáró | 44×44px | Könnyű bezárás |
| Feltételek accordion | 44px magas tap terület | A `<summary>` elem teljes szélessége |
| Rendezés dropdown | 44px magas | |

### 15.4 Teljesítmény mobilon

- **Lazy load:** Az offer ikonok (`icon_url`) `loading="lazy"` attribútummal töltődnek
- **Offer limit:** Mobilon max 15 offer renderelés (desktop: 30), a többi „Mutass többet" gombbal elérhető — csökkenti a DOM méretét
- **Impression pixel:** `loading="lazy"` → csak viewport-ba kerüléskor töltődik

---

## 16. Cache stratégia

### 16.1 Szerver-oldali cache rétegek

| Adat | Tároltípus | TTL | Invalidáció |
|---|---|---|---|
| Offerwall API válasz | WP transient (`ayet_offers_{pseudo_id}`) | **2 perc** | Automatikus lejárat. Manuális: `wp transient delete ayet_offers_*` |
| Reward Status API válasz | WP transient (`ayet_rstatus_{pseudo_id}`) | **5 perc** | Automatikus lejárat |
| Összesítő stat (pont/szavazat ma) | WP transient (`ayet_stats_{pseudo_id}`) | **1 perc** | Automatikus lejárat. Invalidálódik callback-nél is (delete transient). |
| Provider config | PHP `const` / WP option | Állandó | Kézi admin módosítás |

### 16.2 Kliens-oldali cache

- Az Offerwall tab megnyitásakor **mindig** friss API hívás történik (nincs localStorage cache az offerlistára)
- A szűrő és rendezés állapot **session-szintű** (JS változó), nem persistálódik — egyszerűbb, kevesebb edge case
- History polling eredménye: az utolsó ismert `transaction_id` eltárolódik memóriában → csak az újakat mutatja toast-ként

### 16.3 CDN / Cloudflare

- Az offer API proxy endpointok (`/offerwall/offers`, `/offerwall/reward-status`, `/offerwall/stats`) **NEM cache-elhetők CDN szinten** (user-specifikus adatok)
- Cloudflare Page Rule: `*app.sharity.hu/wp-json/impact/v1/offerwall/*` → Cache Level: Bypass
- A statikus kártya CSS/JS fájlok viszont cache-elhetők (filemtime-based versioning query string)

---

## 17. Frontend analitika – event tracking

### 17.1 Követendő események

Az analitikát a meglévő `impactshop_track_event()` JS helper-en keresztül küldjük (ez már implementálva van a belső rendszerben). Az alábbi események szükségesek az Offerwall hatékonyság méréséhez:

| Event neve | Trigger | Adatok | Cél |
|---|---|---|---|
| `offerwall_tab_open` | Offerwall tab megnyitás | `provider`, `pseudo_id` | Tab használati gyakoriság |
| `offerwall_offers_loaded` | Sikeres offer lista betöltés | `count`, `provider`, `load_time_ms` | API megbízhatóság, válaszidő |
| `offerwall_offers_error` | API hiba | `error_type`, `status_code` | Hibaarány monitorozás |
| `offerwall_offer_impression` | Kártya viewport-ba kerül | `offer_id`, `difficulty_tier`, `payout_usd` | Offer láthatóság |
| `offerwall_offer_click` | „Feladat indítása" kattintás | `offer_id`, `offer_name`, `difficulty_tier`, `payout_usd` | CTR számítás |
| `offerwall_filter_change` | Szűrő gomb kattintás | `filter_type` (difficulty tier / all) | Legnépszerűbb szűrő |
| `offerwall_sort_change` | Rendezés váltás | `sort_by` | Preferált rendezés |
| `offerwall_toast_shown` | Toast megjelenítés | `toast_type` (success/warning/info), `offer_id` | Jutalom visszajelzés tracking |
| `offerwall_cpe_step_view` | CPE stepper kinyitás | `offer_id`, `total_steps`, `completed_steps` | CPE engagement |
| `offerwall_rules_expand` | Feltételek accordion kinyitás | `offer_id` | Offer részletek érdeklődés |
| `offerwall_load_more` | „Mutass többet" kattintás (mobil) | `current_count`, `total_count` | Engagement mélység |

### 17.2 Aggregált KPI levezetés

Az event-ekből az alábbi KPI-k számolhatók:

- **CTR** = `offerwall_offer_click` / `offerwall_offer_impression` → offer-szintű konverziós arány
- **Tab engagement** = `offerwall_offers_loaded` / `offerwall_tab_open` → hány megnyitásból lesz tényleges böngészés
- **Szűrő preferencia** = `offerwall_filter_change` group by `filter_type` → melyik nehézségi szintet keresik legtöbben
- **CPE engagement depth** = `offerwall_cpe_step_view` rate → mennyire érdeklik a multi-step feladatok

### 17.3 Adatgyűjtés módja

- A `impactshop_track_event(name, data)` helper `POST /wp-json/impact/v1/analytics`-re küld (ez a meglévő analitika endpoint)
- Beacon API (`navigator.sendBeacon`) használata a page unload / tab váltás esetekre (ne vesszen el az event)
- **Nincs harmadik fél tracking** (GA, Mixpanel stb.) — saját rendszer, GDPR-barát
- Rate limit: max 10 event/perc/pseudo_id (abuse védelem)

---

## 18. Offer kártya interakciós állapotok

### 18.1 Állapot mátrix

| Állapot | Vizuális jelzés | CTA gomb | Trigger |
|---|---|---|---|
| **Alapértelmezett** (nem started) | Sötét kártya (#111827) | „🚀 Feladat indítása" (kék, #2563eb) | Offer lista betöltéskor |
| **Hover** (csak desktop) | Enyhe kiemelés, árnyék, translateY(-2px) | — | Egér ráhúzás |
| **Active / pressed** | `scale(0.98)`, háttér kissé világosabb | — | Egér kattintás / tap |
| **Folyamatban** (`offer_status=started`) | Kék szegély (`border: 1px solid rgba(59,130,246,.5)`), kék sáv felül | „▶️ Folytatás" (zöld, #16a34a) | Reward Status API / Offerwall API status |
| **Teljesítve** (CPE összes lépés kész) | Zöld szegély, ✅ badge | „✅ Teljesítve" (disabled, szürke) | Reward Status merge |
| **Lejárt** (`offer_status_days_left=0`) | Halványított (opacity: 0.5) | „⏰ Lejárt" (disabled, piros) | API válasz |
| **Loading** (skeleton) | Pulzáló szürke blokkok | — | API hívás közben |
| **Hiba** (offer nem elérhető) | — | „Nem elérhető" (disabled) | API error / offer eltűnt listából |

### 18.2 CTA gomb állapotváltás flow

```
[Alapértelmezett: "🚀 Feladat indítása"]
        │ kattintás
        ▼
[Loading: "⏳ Megnyitás..." (2 sec delay)]
        │ tracking link megnyílt
        ▼
[Folyamatban: "▶️ Folytatás az appban"]
        │ összes CPE lépés completed
        ▼
[Teljesítve: "✅ Teljesítve" (disabled)]
```

A „Loading" állapot azért kell, mert a tracking link redirect chain 1-2 sec-ig tarthat. Vizuális feedback a user-nek, hogy történik valami.

### 18.3 Kártya kinyitás / összecsukás

- **CPE lépések**: alapból **láthatóak** ha ≤3 lépés, **összecsukva** ha >3 lépés
- **Feltételek**: mindig **összecsukva** (`<details>` elem), kattintásra nyílik
- **Intro szöveg**: max 2 sor, utána `…` (CSS `-webkit-line-clamp: 2`)
- A kártya **nem bővül modálissá** — minden információ a kártyán belül marad, vertikálisan gördíthető

---

## 19. Lokalizáció

### 19.1 Jelenlegi nyelvi stratégia

Az ImpactShop kizárólag **magyar nyelvű** (app.sharity.hu). Az AyeT API `language=hu` paramétert kap, így a legtöbb offer neve és leírása magyarul érkezik (ha az advertiser biztosít fordítást). Ha nem → az eredeti (általában angol) szöveg jelenik meg.

### 19.2 Hardcoded magyar stringek (terv)

Az alábbi stringek az offerwall JS/PHP-ben hardcode-olva jelennek meg (nem WordPress i18n, mert a MU plugin nem fordítható a standard `__()` rendszerrel shared hosting-on):

| Kulcs | Magyar szöveg | Kontextus |
|---|---|---|
| `difficulty_1` | ⭐ Könnyű | Nehézség badge |
| `difficulty_2` | ⭐⭐ Közepes | Nehézség badge |
| `difficulty_3` | ⭐⭐⭐ Kihívás | Nehézség badge |
| `difficulty_4` | 🏆 Nagykihívás | Nehézség badge |
| `reward_label` | 🎯 Jutalom | Jutalom box cím |
| `est_time` | ⏱ Becsült idő | Jutalom box |
| `deadline` | 📅 Határidő | Jutalom box |
| `points_unit` | pont | Jutalom szöveg |
| `votes_unit` | szavazat | Jutalom szöveg |
| `cta_start` | 🚀 Feladat indítása | CTA gomb |
| `cta_continue` | ▶️ Folytatás | CTA ha folyamatban |
| `cta_done` | ✅ Teljesítve | CTA ha kész |
| `cta_expired` | ⏰ Lejárt | CTA ha lejárt |
| `cta_loading` | ⏳ Megnyitás... | CTA loading state |
| `filter_all` | Összes | Szűrő gomb |
| `sort_payout` | Legtöbb pont | Rendezés |
| `sort_time` | Leggyorsabb | Rendezés |
| `sort_status` | Aktívak elöl | Rendezés |
| `steps_label` | Lépések | CPE stepper cím |
| `steps_show_all` | Mutasd mind ({n} lépés) | Összecsukott CPE |
| `rules_label` | ⓘ Feltételek | Accordion trigger |
| `toast_reward` | 🎉 +{pont} pont +{szav} szavazat — {offer} | Toast success |
| `toast_cpe` | ✓ +{pont} pont — Következő: {next} | Toast CPE részleges |
| `toast_chargeback` | ⚠️ Jóváírás visszavonva. Pontjaid korrigálva. | Toast chargeback |
| `toast_capped` | ℹ️ Napi pontlimit elérve. Holnap újra! | Toast cap elérve |
| `stat_today_points` | pont ma | Stat panel label |
| `stat_today_votes` | szavazat ma | Stat panel label |
| `stat_active` | aktív feladat | Stat panel label |
| `status_in_progress` | Folyamatban | Aktív badge |
| `status_days_left` | {n} nap van hátra | Aktív badge kiegészítés |
| `loading_text` | Feladatok betöltése... | Skeleton alt szöveg |
| `error_load` | A feladatok betöltése nem sikerült. | API hiba |
| `error_retry` | Próbáld újra | Retry gomb |
| `empty_offers` | Jelenleg nincs elérhető feladat. Nézz vissza később! | 0 offer |
| `load_more` | Mutass többet | Mobil paginálás |

### 19.3 Jövőbeli többnyelvűség (ha szükséges)

Ha az ImpactShop többnyelvűvé válna:
1. A string-ek kiemelendők egy `impactshop_offerwall_strings($locale)` függvénybe
2. Az AyeT API `language` paramétere a WordPress locale-ből képződne (`get_locale()`)
3. A kártyákon megjelenített stringek a PHP-ből inline JS változóként kerülnének a frontenddre (`wp_localize_script` helyett inline `<script>` a MU plugin korlátai miatt)

---

## 20. Edge case-ek és error recovery – kibővített terv

### 20.1 Hálózati hibák

| Hiba típus | Detekció | Recovery |
|---|---|---|
| API timeout (>5 sec) | `fetch()` AbortController | Retry 1× automatikusan, utána error UI + retry gomb |
| HTTP 429 (rate limit) | Response status | Exponential backoff: 2s → 4s → 8s, max 3 retry. UI: „Túl sok kérés, kérlek várj..." |
| HTTP 5xx (szerver hiba) | Response status | Retry 1× (2s + random jitter 0–1s). Ha ismétlődik → error UI, naplózás |

| Hálózat offline | `navigator.onLine` check + fetch catch | „Nincs internetkapcsolat. Ellenőrizd a hálózatot." — online event-re auto-retry |
| CORS hiba | Fetch error (opaque response) | Nem szabadna előfordulnia (szerver proxy), de ha igen → error log + generic error UI |

### 20.2 Adatintegritási edge case-ek

| Eset | Kezelés |
|---|---|
| Callback érkezik ismeretlen `pseudo_id`-vel | Ledger bejegyzés `status='orphan'`, pont/szavazat NEM kerül kiosztásra. Admin alertben megjelenik. |
| Duplikált `transaction_id` callback | A callback handler `INSERT … ON DUPLICATE KEY UPDATE` logikával kezeli → idempotens, nincs dupla jóváírás. |
| CPE callback task sorrendben (3. lépés jön a 2. előtt) | Minden lépés önállóan kerül feldolgozásra, nincs sorrend-függőség. A kártyán a Reward Status API-ból jön a helyes állapot. |
| Chargeback callback, de a user már elköltötte a pontjait | Negatív egyenleg **megengedett** a ledger-ben. A user pontegyenlege mínuszba mehet ideiglenesen. A következő jóváírásnál „feloldódik". Nem blokkoljuk a felhasználót. |
| Chargeback callback duplikált `transaction_id`-vel | A handler ellenőrzi, hogy az adott tx_id-hez létezik-e már chargeback bejegyzés → ha igen, skip. |
| `payout_usd=0` callback | Pont = `AYET_MIN_POINTS` (5), szavazat = `AYET_MIN_VOTES` (1). A 0-ás payout legális (pl. tesztelés). |
| `currency_amount` és `payout_usd × 50` eltérés | A `currency_amount`-ot fogadjuk el (az AyeT dashboard konfig az irányadó). Logolunk ha >10% eltérés → admin alert. |

### 20.3 UI edge case-ek

| Eset | Kezelés |
|---|---|
| Offer ikon URL 404 | `<img onerror>` → CSS placeholder (emoji fallback: 🎮 games, 📋 survey, 📱 app, 🎁 default) |
| Offer név túl hosszú (>60 karakter) | CSS `text-overflow: ellipsis` + `title` attribútum a teljes névvel |
| CPE stepper >8 lépéssel | Az első 3 lépés látható, utána „Mutasd mind (N lépés)" gomb |
| Offer lista >30 elem (nem szabadna, de…) | Kliens-oldali limit: max 30 kártya renderelés |
| `introduction` mező üres | Az intro szekció nem jelenik meg (conditional render) |
| `rules_requirements` mező üres | A „Feltételek" accordion nem jelenik meg |
| `cpe_instructions` üres tömb (nem CPE offer) | A stepper szekció nem jelenik meg, a kártya egyszerűbb layout-ot kap |
| `offer_status_days_left` negatív szám | Kezeljük mint 0 → „Lejárt" állapot |
| Két azonos `offer_id` a listában | Az első előfordulás jelenik meg, a másodikat kiszűrjük (JS `Set`-tel dedup) |
| `impression_url` üres | Nem töltünk be pixelt (conditional render) |

### 20.4 Pseudo ID kezelés

| Eset | Kezelés |
|---|---|
| Nincs `impactshop_pseudo_id` cookie | Új pseudo_id generálás (`base36, 12 char`) + cookie set (`SameSite=Lax, path=/, max-age=1 év`) |
| Cookie lejárt vagy törlődött | Új pseudo_id → az offer státuszok elvesznek (AyeT oldalon az `external_identifier` nem matchel). Nincs jó megoldás shared hosting-on auth nélkül. |
| Böngésző 3rd party cookie block | A cookie 1st party (`app.sharity.hu` domain) → nincs probléma |
| Incognito mód | Új pseudo_id minden session-ben → offer státusz nem marad meg. Ez elfogadható korlátozás. |

---

## 21. CSS design token-ek és vizuális rendszer

### 21.1 Színpaletta

| Token neve | Érték | Használat |
|---|---|---|
| `--ow-bg` | `#0f172a` | Offerwall konténer háttér |
| `--ow-card-bg` | `#111827` | Kártya háttér |
| `--ow-card-hover-bg` | `#1a2236` | Kártya hover háttér |
| `--ow-text` | `#f8fafc` | Elsődleges szöveg |
| `--ow-text-muted` | `#94a3b8` | Másodlagos szöveg |
| `--ow-text-subtle` | `#64748b` | Meta szöveg |
| `--ow-border` | `rgba(148,163,184,.2)` | Kártya/szekció szegélyek |
| `--ow-primary` | `#2563eb` | CTA gomb, aktív szűrő |
| `--ow-primary-hover` | `#1d4ed8` | CTA hover |
| `--ow-success` | `#22c55e` | Pont szám, kész státusz |
| `--ow-success-light` | `#4ade80` | Jutalom kiemelés |
| `--ow-success-bg` | `rgba(34,197,94,.08)` | Jutalom box háttér |
| `--ow-warning` | `#f59e0b` | Közepes nehézség, bónusz |
| `--ow-danger` | `#ef4444` | Kihívás nehézség |
| `--ow-epic` | `#7c3aed` | Nagykihívás nehézség |
| `--ow-info` | `#3b82f6` | Folyamatban badge |
| `--ow-info-bg` | `rgba(59,130,246,.15)` | Aktív badge háttér |

### 21.2 Tipográfia

| Elem | Méret | Súly | Szín |
|---|---|---|---|
| Offerwall cím (h3) | 20px | 600 | `--ow-text` |
| Kártya offer név | 14px | 600 | `--ow-text` |
| Nehézség badge | 10px | 600 | `#fff` (badge bg-n) |
| Intro szöveg | 12px | 400 | `--ow-text-muted` |
| Jutalom szám | 13px | 700 | `--ow-success-light` |
| Jutalom label | 12px | 400 | `#a7f3d0` |
| CPE lépés név | 12px | 400 | `--ow-text` (var. státusz szerint) |
| CPE jutalom | 11px | 400 | `--ow-success` |
| Meta szöveg | 11px | 400 | `--ow-text-subtle` |
| CTA gomb | 13px | 600 | `#fff` |
| Stat szám | 22px | 700 | `--ow-success` |
| Stat label | 11px | 400 | `--ow-text-muted` |
| Szűrő gomb | 12px | 400 | `--ow-text-muted` / `#fff` (active) |
| Toast szöveg | 13px | 400 | `--ow-text` |

### 21.3 Border radius értékek

| Elem | Radius |
|---|---|
| Offerwall konténer | 20px |
| Kártya | 16px |
| Jutalom box | 10px |
| CTA gomb | 10px |
| Szűrő gomb (pill) | 999px |
| Nehézség badge (pill) | 999px |
| Offer ikon | 12px |
| Toast | 12px |
| Stat panel item | 12px |

### 21.4 Árnyékok és effektek

| Elem | Effekt |
|---|---|
| Kártya hover | `box-shadow: 0 8px 24px rgba(0,0,0,.3)` + `translateY(-2px)` |
| Toast | `box-shadow: 0 8px 24px rgba(0,0,0,.4)` |
| Close gomb (modal) | `box-shadow: 0 8px 18px rgba(15,23,42,.4)` |
| Jutalom box | Semiátlátszó háttér (glassmorphism-lite): `background: rgba(34,197,94,.08)` + `border: 1px solid rgba(34,197,94,.2)` |
| Aktív badge | `background: rgba(59,130,246,.15)` — nincs shadow, subtilis |

---

## 22. Teljesítmény budget

### 22.1 Célértékek

| Metrika | Cél | Mérés módja |
|---|---|---|
| Offerwall tab → kártyák megjelenése | <800ms | `performance.mark()` JS-ben: tab click → utolsó kártya renderelve |
| API proxy válaszidő (szerver) | <500ms (p95) | Szerver-oldali log (`dur` mező) |
| AyeT Offerwall API válaszidő | <1000ms (p95) | Szerver-oldali log (upstream response time) |
| DOM elemek (offerwall szekció) | <500 elem | 30 kártya × ~15 elem/kártya ≈ 450 |
| JS bundle méret (inline) | <15 KB gzip | A JS inline a shortcode-ban |
| CSS méret (inline) | <5 KB gzip | A CSS inline `<style>` tag |
| Impression pixel-ek | Max 30 (1×1) | Az offer limittel kontrollálva |

### 22.2 Optimalizálási stratégiák

- **Nem használunk framework-öt** (React, Vue stb.) — vanilla JS + template literal → minimális bundle méret
- **Skeleton-first rendering**: a skeleton azonnal megjelenik, a tényleges kártyák utána pattannak be → perceived performance javul
- **Debounce**: szűrő/rendezés változásnál 150ms debounce a re-render előtt
- **Virtual rendering (jövő, P3)**: Ha >30 offer lenne (jelenleg nem), IntersectionObserver-rel lazy renderelnénk a viewport-on kívüli kártyákat

---

## 23. Tesztelési terv

### 23.1 Manuális tesztelési checklist (staging)

**Offer betöltés:**
- [ ] Offerwall tab megnyitás → skeleton megjelenik → kártyák betöltődnek
- [ ] Üres offer lista kezelése (szűréssel vagy nulla offer-rel)
- [ ] API hiba szimuláció (szerver leállítás) → error UI + retry gomb
- [ ] Lassú API válasz (>3 sec) → skeleton nem ugrál

**Kártya megjelenítés:**
- [ ] Offer ikon betöltődik, fallback működik (404-es ikon esetén emoji)
- [ ] CPE stepper ≤3 lépésnél nyitva, >3-nál összecsukva
- [ ] Hosszú offer név ellipsis-szel jelenik meg
- [ ] Nehézség badge helyes szín és label
- [ ] Jutalom box helyes pont/szavazat szám
- [ ] Feltételek accordion nyit/zár
- [ ] Impression pixel betöltődik (Network tab-ban ellenőrzés)

**Szűrők és rendezés:**
- [ ] „Összes" szűrő mutatja az összes offert
- [ ] Nehézség szűrő helyes kártyákat mutatja
- [ ] Rendezés: pont (csökkenő), idő (növekvő), aktívak elöl
- [ ] Szűrő + rendezés kombinálva is helyesen működik

**Tracking link:**
- [ ] Desktop: új tab nyílik
- [ ] Mobil: navigáció az URL-re (back gombbal visszatér)
- [ ] CTA gomb „Loading" állapotba vált kattintáskor

**Toast:**
- [ ] Callback érkezésekor toast megjelenik (max 60 sec polling delay)
- [ ] Toast 4 sec után eltűnik (slideOut animáció)
- [ ] Max 3 toast egyszerre

**Responsive:**
- [ ] 640px alatti kijelzőn 1 oszlopos kártya grid
- [ ] Szűrő sáv horizontálisan görgethető mobilon
- [ ] Touch target-ek legalább 44px magasak

**Jutalom:**
- [ ] Callback: pont = ceil(payout_usd × 50), szavazat = ceil(payout_usd × 10)
- [ ] Minimum: 5 pont, 1 szavazat
- [ ] Maximum per tx: 2000 pont, 500 szavazat
- [ ] Napi cap: 2000 pont / 200 szavazat / 50 tx → cap utáni callback `status='capped'`
- [ ] Chargeback: negatív ledger bejegyzés + toast

### 23.2 Automatizálható tesztek (jövő)

| Teszt típus | Leírás | Prioritás |
|---|---|---|
| PHP unit test: `calculate_points()` | Input/output párok a §4.2 táblázatból | P1 |
| PHP unit test: `calculate_votes()` | Input/output párok | P1 |
| PHP unit test: `ayet_difficulty()` | Tier mapping heurisztika edge case-ek | P1 |
| PHP unit test: daily cap logika | Cap elérés + capped státusz | P1 |
| PHP integration: callback handler | HMAC validáció + ledger bejegyzés + pont jóváírás | P2 |
| JS visual regression (Percy) | Kártya snapshot összehasonlítás | P3 |

---

## 24. Kockázatok és mitigáció

| Kockázat | Valószínűség | Hatás | Mitigáció |
|---|---|---|---|
| AyeT API downtime | Közepes | Üres offer lista → rossz UX | Graceful fallback UI + retry. Statikus „offline offerek" lista nem éri meg a karbantartási költséget. |
| Alacsony eCPM (kevés/rossz offer a HU régióban) | Magas | Kevés releváns feladat | `offer_sorting=ecpm` + rendszeres AyeT account manager egyeztetés. Ha tartósan <5 offer → „Hamarosan több feladat érkezik" placeholder. |
| Abuse: botok töltik ki az offereket | Alacsony | Hamis pont/szavazat jóváírás | AyeT saját fraud detection + napi cap + HMAC validáció. Monitoring: ha 1 pseudo_id >20 tx/nap → admin alert. |
| Chargeback arány >10% | Alacsony | Negatív egyenlegek, user elégedetlenség | AyeT dashboard-on offer quality szűrés. Ha tartósan magas → adott offer_id blacklist. |
| Shared hosting lassulás (API proxy) | Közepes | Lassú kártya betöltés | 2 perc szerver cache. Ha kritikus → Cloudflare Worker-be lehetne mozgatni az API proxy-t (P3). |
| Pseudo_id cookie elvesztése | Közepes | Offer státuszok elvesznek | Elfogadható kompromisszum auth nélkül. Jövőben: optional login → pseudo_id kötés user accounthoz. |

---

> **Dokumentum státusz: FINAL v1.2**
> Frissítve: 2026-02-16
> Hozzáadott szekciók: §15–§24 (Mobil UX, Cache stratégia, Frontend analitika, Interakciós állapotok, Lokalizáció, Edge case-ek, Design token-ek, Teljesítmény budget, Tesztelési terv, Kockázatok)
