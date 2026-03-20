# Offerwall Integráció - Részletes Implementációs Terv
## ImpactShop Ads Watch Rendszer Bővítése

**Készítette:** GitHub Copilot  
**Dátum:** 2026. január 26.  
**Verzió:** 1.0  
**Célrendszer:** `impactshop-ads-watch` v2.3.0  
**Kapcsolódó tervek:**
- `docs/video-content-strategy-plan.md` (edukációs videók, auto-banner, content rotation)

---

## 📋 Executive Summary

Ez a dokumentum egy **nem-destruktív bővítési tervet** tartalmaz az `impactshop-ads-watch` rendszer offerwall funkciókkal való kibővítésére. A terv **nem módosítja** a meglévő videó ad watch funkciókat, hanem **új komponensekkel** egészíti ki a rendszert.

### Célok:
1. ✅ **Offerwall provider integráció** (AdGate, Adjoe, Tapjoy, stb.)
2. ✅ **Meglévő pont/szavazat rendszer újrafelhasználása**
3. ✅ **Postback callback kezelés** signature validációval
4. ✅ **Fraud detection** és rate limiting
5. ✅ **Admin dashboard** statisztikákhoz
6. ✅ **Frontend widget** offerwall iframe-hez

> **Codex 5.2 javaslat:** Az Executive Summary részbe érdemes felvenni egy rövid **policy/GDPR** bekezdést: az offerwall reward‑olás megengedett, de **kötelező** a felhasználói tájékoztatás a külső provider adatkezeléséről és egyértelmű opt‑in/elfogadás (pl. “Elfogadom az offerwall feltételeit”).
> 
> **Codex 5.2 javaslat:** Tegyél ide egy **1 mondatos value‑propot** és egy **mini „Mit kapsz?”** bullet‑sort fiatalos hangnemben (pl. „2–5 perc = instant pontok”, „Nincs rejtett költség”), mert ez az első scroll‑on dől el a belépési arány.

> **GPT 5.2 javaslat:** A „trust” miatt érdemes egy fél mondatban előre megmondani, hogy **nem minden jutalom instant** (pl. „Néha pár órán belül fut be”), és adni egy **„Hol a jutalmam?”** linket/FAQ-t. Ez csökkenti a supportot és a frusztrációt, miközben nem rontja a fiatalos hangulatot.

---

## 🔍 Jelenlegi Rendszer Analízise

### Architektúra (v2.3.0)

#### Backend Komponensek:
```
📦 impactshop-ads-watch.php (1961 sor)
├── 5 adatbázis tábla
│   ├── impactshop_ads_views (videó megtekintések)
│   ├── impactshop_ads_votes (NGO szavazatok)
│   ├── impactshop_ads_user_ngo (user NGO preferencia)
│   ├── impactshop_ads_user_votes (szavazat egyenleg)
│   └── impactshop_ads_user_stats (streak, achievement)
├── 8 REST API endpoint
│   ├── GET  /ads-watch/config
│   ├── GET  /ads-watch/next
│   ├── GET  /ads-watch/status
│   ├── POST /ads-watch/view
│   ├── POST /ads-watch/allocate
│   ├── POST /ads-watch/set-ngo
│   ├── GET  /ads-watch/tally
│   └── GET  /ads-watch/ngos
└── Integráció
    ├── Sharity_Points_Manager (pont kezelés)
    ├── Sharity_Level_Manager (szint/badge súly)
    └── ImpactShop_NGO_Card_API (NGO lista)
```

#### Frontend Komponensek:
```
📦 impactshop-ads-watch.js (993 sor)
├── Google IMA SDK integráció (dinamikus betöltés)
├── Videó player vezérlés
├── NGO modal (kiválasztás/szavazás)
├── Tally display (top 10 + teljes lista)
├── Achievement tracking
├── LocalStorage cache (NGO lista)
└── Retry logic (exponential backoff)
```

#### Jelenlegi Flow:
```
1. User → "Watch Ad" gomb
2. Frontend → GET /ads-watch/next (sponsor vs regular)
3. IMA SDK → Videó lejátszás
4. 5 mp után → POST /ads-watch/view
5. Backend →
   - Dedupe check (dedupe_key)
   - Pont jóváírás (Sharity_Points_Manager)
   - Szavazat egyenleg frissítés (impactshop_ads_votes táblába NEM, hanem impactshop_ads_user_votes-ba)
   - Stats frissítés (streak, total_views)
6. User → NGO kiválasztás + szavazat leadás
7. POST /ads-watch/allocate → szavazat levonás + impactshop_ads_votes insert
```

### Kulcs Adatszerkezetek:

#### `impactshop_ads_views` tábla:
```sql
CREATE TABLE impactshop_ads_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pseudo_id VARCHAR(32) NOT NULL,
    ngo_slug VARCHAR(190) NULL,
    sponsor_id BIGINT UNSIGNED NULL,
    ad_type ENUM('regular','sponsor') DEFAULT 'regular',
    points INT UNSIGNED DEFAULT 0,
    vote_weight INT UNSIGNED DEFAULT 0,
    dedupe_key VARCHAR(191) NOT NULL UNIQUE,  -- Fraud protection
    day_key CHAR(10) NOT NULL,                -- YYYY-MM-DD
    created_at DATETIME,
    -- Indexes optimized for queries
);
```

#### `impactshop_ads_user_votes` tábla:
```sql
CREATE TABLE impactshop_ads_user_votes (
    pseudo_id VARCHAR(32) PRIMARY KEY,
    available_votes INT NOT NULL DEFAULT 0,  -- Egyenleg
    updated_at DATETIME
);
```

#### `impactshop_ads_votes` tábla (leadott szavazatok):
```sql
CREATE TABLE impactshop_ads_votes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pseudo_id VARCHAR(32) NOT NULL,
    ngo_slug VARCHAR(190) NOT NULL,
    vote_weight INT UNSIGNED DEFAULT 0,      -- Badge-alapú súly
    ad_type ENUM('regular','sponsor'),
    day_key CHAR(10),
    created_at DATETIME,
    -- Indexes for tally queries
);
```

### Rate Limiting Rendszer:
```php
function impactshop_ads_watch_rate_limit_check(
    string $key,     // 'ads_watch_view_pseudo:{id}'
    int $limit,      // 60 views/min
    int $window,     // 60 seconds
    bool $increment  // true = consume token
): array {
    // Transient-based token bucket
    // Returns: ['allowed' => bool, 'remaining' => int, 'reset' => timestamp]
}
```

### Pont/Szavazat Jóváírási Logika:
```php
// impactshop_ads_watch_view() függvényből (line ~700)
$points_result = $points_manager->award_points_for_pseudo(
    $pseudo_id,
    $points,
    $ad_type === 'sponsor' ? 'video_sponsor' : 'video_ad',
    'ads_watch',
    ['source_type' => $ad_type, 'ngo_slug' => $ngo_slug],
    'ads_watch:' . $dedupe_key  // Dedupe ID
);

$available_votes = impactshop_ads_watch_add_votes($pseudo_id, $votes_added);
// Frissíti impactshop_ads_user_votes.available_votes-ot
```

---

## 🎯 Offerwall Integráció Tervezése

### Design Principles:
1. **Minimális invázivitás**: Nem módosítjuk a meglévő kódot
2. **Separation of Concerns**: Új fájl (`impactshop-offerwall.php`)
3. **Reuse, Don't Rebuild**: Használjuk a meglévő Sharity API-kat
4. **Provider Agnostic**: Pluggable provider architecture

> **Opus javaslat:** Érdemes bevezetni egy 5. alapelvet: **Graceful Degradation** – ha az offerwall szolgáltatás elérhetetlen (provider API hiba, timeout), a rendszer ne blokkolja a fő videó ad watch funkciót. Implementálj circuit breaker pattern-t, ami 3 egymást követő hiba után 5 percre kikapcsolja az offerwall hívásokat.

---

## 📦 Új Komponensek Specifikációja

### 1. Backend: `impactshop-offerwall.php`

**Elhelyezés:** `/wp-content/mu-plugins/impactshop-offerwall.php`  
**Méret becslés:** ~800 sor  
**Függőségek:** `impactshop-ads-watch.php` (pont/szavazat függvények)

> **Codex 5.2 javaslat:** Mivel ez **MU‑plugin**, a `register_activation_hook()` nem fut. A séma‑migrációt ezért `init`/`muplugins_loaded` alá tervezz (mint az `ads-watch`), külön “MU‑plugin migration note” bekezdéssel a doksiban.

#### Új Adatbázis Tábla:

```sql
CREATE TABLE wp_impactshop_offerwall_completions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pseudo_id VARCHAR(32) NOT NULL,
    provider VARCHAR(64) NOT NULL,              -- 'adgate', 'adjoe', stb.
    offer_id VARCHAR(128) NOT NULL,
    offer_name VARCHAR(255) DEFAULT '',
    offer_type VARCHAR(64) DEFAULT '',          -- 'survey', 'app_install', 'video'
    transaction_id VARCHAR(255) NOT NULL,       -- Provider trx ID (dedupe)
    payout_usd DECIMAL(10,4) DEFAULT 0.0000,   -- Provider payout
    currency VARCHAR(4) DEFAULT 'USD',          -- Payout currency
    points_awarded INT UNSIGNED DEFAULT 0,
    votes_awarded INT UNSIGNED DEFAULT 0,

> **Gemini 3 Pro javaslat:** Érdemes hozzáadni egy `currency` oszlopot is, mivel egyes providereknél (pl. európai székhelyűek) előfordulhat EUR alapú elszámolás, és a konverzió nyomon követése fontos lehet a könyvelés számára.

    user_ip VARCHAR(64) DEFAULT '',
    user_agent TEXT,
    postback_data TEXT,                         -- Full postback JSON
    status VARCHAR(32) DEFAULT 'completed',     -- 'completed', 'pending', 'reversed'
    created_at DATETIME NOT NULL,
    
    UNIQUE KEY dedupe (provider, transaction_id),  -- Fraud protection
    KEY pseudo_id (pseudo_id),
    KEY provider_offer (provider, offer_id),
    KEY created_at (created_at),
    KEY status_created (status, created_at)  -- Opus: reversal és pending query optimalizáláshoz
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

> **Opus javaslat:** A `status_created` összetett index segíti a pending/reversed státuszú tranzakciók gyors lekérdezését admin dashboardon és cron job-oknál. Emellett fontolja meg a `created_at` oszlopra vonatkozó **partícionálást** havi bontásban, ha havi 100k+ completion várható – ez jelentősen gyorsítja a historikus lekérdezéseket és a régi adatok archiválását.
```

**Táblaelemzés:**
- **Dedupe key:** `(provider, transaction_id)` UNIQUE → prevent replay attacks
- **Audit trail:** `postback_data` teljes JSON → forensics
- **Analytics:** `offer_type`, `payout_usd` → ROI tracking

> **GPT 5.2 javaslat:** Hosszabb távra hasznos pár „operációs” mező: `awarded_at` (mikor írtuk jóvá), `reversed_at` (mikor vontuk vissza), és egy `request_id` / `provider_event_id` (ha van) a log-korrelációhoz. Ezek nem kötelezőek, de rengeteget segítenek incidentnél és vitás payout esetén.

#### Új REST API Endpointok:

```php
// 1. Offerwall konfiguráció frontend számára
GET /wp-json/impact/v1/offerwall/config
Response:
{
  "providers": [
    {
      "id": "adgate",
      "name": "AdGate Media",
      "iframe_url": "https://wall.adgaterewards.com/..."
    }
  ],
  "version": "1.0.0"
}

// 2. Postback callback (provider → backend)
POST /wp-json/impact/v1/offerwall/callback/{provider}
Parameters (provider-specific, példa AdGate):
  - user_id: {pseudo_id}
  - transaction_id: "ABC123XYZ"
  - offer_id: "12345"
  - offer_name: "Survey XYZ"
  - amount: "0.50" (USD)
  - sig: "md5_hash"  (signature)
Response:
{
  "success": true,
  "points": 50,
  "votes": 5
}

// 3. User completion history
GET /wp-json/impact/v1/offerwall/history?pseudo_id={id}&limit=50
Response:
{
  "completions": [
    {
      "provider": "adgate",
      "offer_name": "Watch Video",
      "points_awarded": 10,
      "votes_awarded": 1,
      "created_at": "2026-01-26 12:34:56"
    }
  ],
  "total": 127
}

> **Codex 5.2 javaslat:** A `/offerwall/history` endpointhoz javasolt **nonce** vagy **pseudo_id ownership** validáció, különben mások history-ja lekérdezhető. Alternatíva: `pseudo_id=me` és cookie‑ból feloldás, vagy `X-WP-Nonce` kötelezővé tétele.

> **GPT 5.2 javaslat:** A history-hoz tegyél **kemény limit plafont** (pl. max 100), és ha várható nagy forgalom, válts **cursor pagination**-re (`cursor`/`next_cursor`). Válasz fejlécekben érdemes `Cache-Control: no-store`-t adni (user-szintű adat).

// 4. Available offers (optional, ha nem iframe)
GET /wp-json/impact/v1/offerwall/offers?provider={id}
Response:
{
  "success": false,
  "message": "Use iframe integration"
}

// 5. Health check endpoint (Opus javaslat)
GET /wp-json/impact/v1/offerwall/health
Response:
{
  "status": "healthy",
  "providers": {
    "adgate": { "enabled": true, "last_postback": "2026-01-26 12:00:00", "24h_completions": 142 },
    "tapjoy": { "enabled": false, "last_postback": null, "24h_completions": 0 }
  },
  "database": "ok",
  "rate_limit_status": "ok"
}
```

> **Opus javaslat:** A `/health` endpoint kritikus a monitoring és alerting rendszerhez. Ha a `last_postback` időbélyeg több mint 1 óra régi aktív provider esetén, az anomáliát jelezhet (provider API hiba vagy hibás postback konfiguráció). Integrálható Uptime Robot, Datadog vagy egyéb monitoring eszközzel.

#### Signature Validation (Security):

**Provider postback példa (AdGate):**
```
GET /wp-json/impact/v1/offerwall/callback/adgate?
    user_id=abc123&
    transaction_id=TRX999&
    offer_id=1234&
    amount=0.50&
    sig=d41d8cd98f00b204e9800998ecf8427e
```

**Signature számítás:**
```php
function impactshop_offerwall_validate_signature(
    string $provider,
    array $params,
    string $received_signature
): bool {
    $config = impactshop_offerwall_get_provider_config($provider);
    $secret = $config['secret_key'];  // Admin beállítás
    
    // Remove sig param
    unset($params['sig']);
    
    // Sort and concatenate
    ksort($params);
    $query = http_build_query($params);
    $payload = $query . $secret;
    
    // Hash based on provider method
    $method = $config['signature_method'] ?? 'md5';
    $expected = hash($method, $payload);
    
    // Timing-safe comparison
    return hash_equals($expected, $received_signature);
}

> **GPT 5.2 javaslat:** Providerenként legyen dokumentálva a **kanonikalizálás** (URL decode/encode, param-sorrend, space `+` vs `%20`, üres paramok), mert ez a #1 signature mismatch ok. Debughoz érdemes eltenni a `raw_query`-t és a számolt `expected` hash-t (csak debug módban logolva).
```

**Miért fontos?**
- Megakadályozza a **fake postback** támadásokat
- Provider authenticity verification
- Replay attack protection (transaction_id dedupe-val együtt)

> **Codex 5.2 javaslat:** Kiegészítő védelemként hasznos egy **provider IP allowlist** ellenőrzés (több offerwall szolgáltató publikál fix IP listát). Ez plusz réteg a signature mellett.

#### Reward Calculation:

```php
function impactshop_offerwall_calculate_rewards(
    string $provider,
    float $payout_usd
): array {
    $config = impactshop_offerwall_get_provider_config($provider);
    
    // Példa: $0.50 payout
    // Points: $0.50 * 100 * 1.0 multiplier = 50 pont
    // Votes:  $0.50 * 10 * 1.0 multiplier = 5 szavazat
    
    $points = (int) round(
        $payout_usd * 100 * ($config['points_multiplier'] ?? 1.0)
    );
    $votes = (int) round(
        $payout_usd * 10 * ($config['votes_multiplier'] ?? 1.0)
    );
    
    // Minimum reward (even for $0 offers)
    return [
        'points' => max(1, $points),
        'votes'  => max(1, $votes),
    ];
}

> **GPT 5.2 javaslat:** Rögzíts egy rövid „reward policy” táblát a doksiban: milyen **kerekítést** használsz (round/floor/ceil), mi az **abszolút minimum** és mi a **max** (plafon), és hogy „$0 offer” esetén mi történik. Ez később termék- és pénzügyi vitáknál aranyat ér.
```

**Multiplier rationale:**
- Admin beállítható provider-enként
- Lehetővé teszi a bevételi modell finomhangolását
- Példa: AdGate 1.2x, Tapjoy 0.8x (performance alapján)

> **Codex 5.2 javaslat:** Érdemes **napi plafonokat** (max pont/vote per user per day) és **cooldown** szabályt (pl. 1 completion / 2 perc) bevezetni az automatizált abuse visszafogására és a költségmodell stabilizálására.

#### Integration with Existing Systems:

```php
function impactshop_offerwall_award_rewards(
    string $pseudo_id,
    int $points,
    int $votes,
    string $source
): bool {
    // 1. Pont jóváírás (MEGLÉVŐ RENDSZER)
    if (class_exists('Sharity_Points_Manager') && $points > 0) {
        $points_manager = new Sharity_Points_Manager();
        $points_manager->add_points_for_pseudo(
            $pseudo_id,
            $points,
            $source  // 'offerwall_adgate'
        );
    }
    
    // 2. Szavazat egyenleg növelés (MEGLÉVŐ FÜGGVÉNY)
    if ($votes > 0) {
        // Ugyanaz a függvény, amit impactshop_ads_watch_view() is hív
        impactshop_ads_watch_add_votes($pseudo_id, $votes);
        
        // VAGY ha nincs ilyen export, akkor direct DB:
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_ads_user_votes';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO `{$table}` (pseudo_id, available_votes) 
             VALUES (%s, %d)
             ON DUPLICATE KEY UPDATE 
             available_votes = available_votes + VALUES(available_votes)",
            $pseudo_id,
            $votes
        ));
    }
    
    // 3. Action hook (analytics, webhooks)
    do_action('impactshop_offerwall_rewards_awarded', 
              $pseudo_id, $points, $votes, $source);
    
    return true;
}

> **Gemini 3 Pro javaslat:** Nagy forgalom esetén a szinkron pontjóváírás lassíthatja a postback választ (timeout veszély). Érdemes megfontolni az **Action Scheduler** használatát a tényleges jóváíráshoz (`as_schedule_single_action`), így a postback azonnal 200 OK-val térhet vissza, a pontadás pedig a háttérben fut le tranzakciós biztonsággal.

```

**Kompatibilitás biztosítása:**
- ✅ Ugyanazt a `Sharity_Points_Manager` API-t használja
- ✅ Ugyanabba a `impactshop_ads_user_votes` táblába ír
- ✅ Ugyanazt a `impactshop_ads_watch_add_votes()` logikát követi
- ✅ User státusz (`GET /ads-watch/status`) automatikusan látja az új szavazatokat

#### Rate Limiting:

```php
// Új konstansok
define('IMPACTSHOP_OFFERWALL_RATE_LIMIT_PER_HOUR', 50);    // User
define('IMPACTSHOP_OFFERWALL_RATE_LIMIT_IP_PER_HOUR', 200); // IP

// ÚJRAHASZNÁLJUK a meglévő rate limit logikát!
$rate_user = impactshop_ads_watch_rate_limit_check(
    'offerwall_user:' . $pseudo_id,
    IMPACTSHOP_OFFERWALL_RATE_LIMIT_PER_HOUR,
    3600  // 1 hour
);

$rate_ip = impactshop_ads_watch_rate_limit_check(
    'offerwall_ip:' . $_SERVER['REMOTE_ADDR'],
    IMPACTSHOP_OFFERWALL_RATE_LIMIT_IP_PER_HOUR,
    3600
);
```

**Miért szigorúbb limitet?**
- Offerwall-nál nagyobb fraud risk (automated tools)
- Provider TOS compliance (abuse prevention)
- 50 offer/hour = ~800/day realisztikus maximum

#### Admin Settings Page:

```php
add_action('admin_menu', 'impactshop_offerwall_admin_menu');

function impactshop_offerwall_admin_menu(): void {
    add_submenu_page(
        'options-general.php',  // Settings menü alatt
        'Offerwall Settings',
        'Offerwall',
        'manage_options',
        'impactshop-offerwall',
        'impactshop_offerwall_admin_page'
    );
}
```

**Admin UI feature list:**
- Provider enable/disable toggle
- API Key + Secret Key input fields
- IFrame URL configuration
- Points/Votes multiplier sliders
- Postback URL display (copy-paste provider dashboardba)
- Stats table: completions, total points/votes, payout per provider

> **Sonnet javaslat - Modern Design System:**
> Vezess be egy **design token rendszert** CSS változókkal, ami konzisztens színeket, szóközöket és tipográfiát biztosít:
> ```css
> :root {
>   /* Brand colors - fiatalos, vibráló színpaletta */
>   --primary: #FF6B6B;        /* Élénk korall - CTA-k, kiemelések */
>   --primary-hover: #FF5252;
>   --secondary: #4ECDC4;      /* Türkiz - másodlagos akciók */
>   --accent: #FFE66D;         /* Napsárga - reward indikátorok */
>   --success: #51CF66;        /* Zöld - completion feedback */
>   
>   /* Glassmorphism effect */
>   --glass-bg: rgba(255, 255, 255, 0.1);
>   --glass-border: rgba(255, 255, 255, 0.18);
>   --backdrop-blur: blur(10px);
>   
>   /* Spacing scale - 8px grid system */
>   --space-xs: 4px;
>   --space-sm: 8px;
>   --space-md: 16px;
>   --space-lg: 24px;
>   --space-xl: 32px;
>   
>   /* Typography - modern sans-serif stack */
>   --font-heading: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
>   --font-body: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
>   
>   /* Shadows - soft, layered depth */
>   --shadow-sm: 0 2px 4px rgba(0,0,0,0.06);
>   --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
>   --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
>   
>   /* Border radius - rounded corners */
>   --radius-sm: 8px;
>   --radius-md: 12px;
>   --radius-lg: 16px;
>   --radius-full: 9999px;
>   
>   /* Transitions - smooth, natural */
>   --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
>   --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
>   --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
> }
> 
> /* Dark mode support */
> @media (prefers-color-scheme: dark) {
>   :root {
>     --primary: #FF8787;
>     --glass-bg: rgba(0, 0, 0, 0.4);
>     --glass-border: rgba(255, 255, 255, 0.1);
>   }
> }
> ```

---

### 2. Frontend: Offerwall Widget

**Elhelyezés:** `impactshop-offerwall.js` (új fájl, ~300 sor)  
**Betöltés:** `impactshop-ads-watch.php` enqueuebe bővítés

#### UI Integration Opciók:

**Opció A: Tab System (Ajánlott)**
```html
<!-- Meglévő ads-watch widget bővítése -->
<div id="impactshop-ads-watch">
    <div class="ads-watch-tabs">
        <button class="tab active" data-tab="video">🎬 Videó</button>
        <button class="tab" data-tab="offerwall">🎁 Feladatok</button>
    </div>
    
    <div class="tab-content active" id="tab-video">
        <!-- Meglévő videó UI -->
    </div>
    
    <div class="tab-content" id="tab-offerwall" style="display:none;">
        <div class="offerwall-header">
            <h3>Végezz feladatokat és szerezz pontokat!</h3>
            <p>Kérdőívek, applikációk, videók - több lehetőség!</p>
        </div>

> **Sonnet javaslat - Gamification & Progress:**
> ```html
> <!-- Daily streak & progress ring -->
> <div class="offerwall-header">
>     <div class="streak-indicator">
>         <svg class="progress-ring" viewBox="0 0 120 120">
>             <circle class="progress-ring__circle" 
>                     stroke="var(--accent)" 
>                     stroke-width="8" 
>                     fill="transparent" 
>                     r="52" cx="60" cy="60"
>                     stroke-dasharray="326.73" 
>                     stroke-dashoffset="163.37" />  <!-- 50% completed -->
>         </svg>
>         <div class="streak-content">
>             <span class="streak-emoji">🔥</span>
>             <span class="streak-count">3</span>
>             <span class="streak-label">nap</span>
>         </div>
>     </div>
>     
>     <div class="header-content">
>         <h3 class="gradient-text">Végezz feladatokat!</h3>
>         <p class="subtitle">Napi 5 feladat = extra bónusz! 🎁</p>
>         
>         <!-- Daily progress bar -->
>         <div class="daily-progress">
>             <div class="progress-bar">
>                 <div class="progress-fill" style="width: 40%"></div>
>                 <span class="progress-label">2/5 mai feladat</span>
>             </div>
>         </div>
>     </div>
> </div>
> 
> <style>
> .gradient-text {
>     background: linear-gradient(135deg, var(--primary), var(--secondary));
>     -webkit-background-clip: text;
>     -webkit-text-fill-color: transparent;
>     font-weight: 800;
>     font-size: 1.75rem;
>     letter-spacing: -0.02em;
> }
> 
> .progress-ring {
>     width: 80px;
>     height: 80px;
>     transform: rotate(-90deg);
> }
> 
> .progress-ring__circle {
>     transition: stroke-dashoffset 0.5s cubic-bezier(0.4, 0, 0.2, 1);
>     stroke-linecap: round;
>     filter: drop-shadow(0 0 8px var(--accent));
> }
> 
> .streak-content {
>     position: absolute;
>     inset: 0;
>     display: flex;
>     flex-direction: column;
>     align-items: center;
>     justify-content: center;
> }
> 
> @keyframes pulse-glow {
>     0%, 100% { filter: drop-shadow(0 0 8px var(--accent)); }
>     50% { filter: drop-shadow(0 0 16px var(--accent)); }
> }
> 
> .streak-emoji {
>     font-size: 2rem;
>     animation: pulse-glow 2s infinite;
> }
> </style>
> ```
        
        <div class="offerwall-providers">
            <!-- Dinamikusan generált provider gombok -->
        </div>
        
        <div class="offerwall-iframe-container" style="display:none;">
            <button class="btn-close-offerwall">✕ Bezárás</button>
            <iframe id="offerwall-frame" 
                    style="width:100%; height:600px; border:none;">
            </iframe>
        </div>
        
        <div class="offerwall-history">
            <h4>Legutóbbi teljesítések:</h4>
            <div id="offerwall-history-list">
                <!-- AJAX betöltés -->
            </div>
        </div>
    </div>
</div>
```

> **Codex 5.2 javaslat:** UX‑ben javasolt egy **“Mi ez?”** tooltip és rövid **adatkezelési mini‑banner** (“Az offerwall külső partner felülete, adatokat kezel.”). Ez csökkenti a támogatási terhelést és növeli a completion trust‑ot.
> 
> **Codex 5.2 javaslat:** Adj a provider gombok fölé **„Gyors szűrők”** (pl. „📋 Kérdőív”, „📱 App”, „🎬 Videó”), és használd **mikro‑copy**‑t („2 perc, 10+ pont”) – fiatalos, snack‑szerű választást ad, ami növeli az első kattintást.

**Opció B: Sidebar Widget**
```html
<!-- Új widget a videó mellett -->
<div class="offerwall-sidebar-widget">
    <div class="widget-header">
        🎁 További jutalmak
    </div>
    <div class="widget-offers">
        <div class="offer-teaser" data-provider="adgate">
            <span class="offer-icon">📋</span>
            <span class="offer-text">Kérdőívek</span>
            <span class="offer-reward">+10-50 pont</span>
        </div>
        <!-- ... további teasers -->
    </div>
    <button class="btn-open-offerwall">Feladatok megnyitása</button>
</div>
```

#### JavaScript Logic:

```javascript
(function($) {
    'use strict';
    
    const offerwallState = {
        providers: [],
        currentProvider: null,
        history: []
    };
    
    function initOfferwall() {
        loadOfferwallConfig();
        loadOfferwallHistory();
        bindOfferwallEvents();
    }
    
    function loadOfferwallConfig() {
        $.get('/wp-json/impact/v1/offerwall/config')
            .done(function(data) {
                offerwallState.providers = data.providers || [];
                renderProviderButtons();
            })
            .fail(function() {
                console.error('Failed to load offerwall config');
            });
    }
    
    function renderProviderButtons() {
        const $container = $('.offerwall-providers');
        $container.empty();
        
        offerwallState.providers.forEach(function(provider) {
            const $btn = $(`
                <button class="provider-btn" data-provider-id="${provider.id}">
                    <span class="provider-name">${provider.name}</span>
                    <span class="provider-cta">Feladatok →</span>
                </button>
            `);
            $container.append($btn);
        });
    }

> **Sonnet javaslat - Modern Card UI:**
> ```javascript
> function renderProviderButtons() {
>     const $container = $('.offerwall-providers');
>     $container.empty();
>     
>     // Grid layout with glassmorphism cards
>     $container.addClass('provider-grid');
>     
>     offerwallState.providers.forEach(function(provider, index) {
>         const $card = $(`
>             <button class="provider-card glass-effect" 
>                     data-provider-id="${provider.id}"
>                     style="animation-delay: ${index * 100}ms">
>                 <!-- Icon with gradient background -->
>                 <div class="provider-icon">
>                     <div class="icon-bg gradient-${index % 3}"></div>
>                     ${getProviderIcon(provider.id)}
>                 </div>
>                 
>                 <!-- Content -->
>                 <div class="provider-content">
>                     <h4 class="provider-name">${provider.name}</h4>
>                     <p class="provider-desc">${provider.description || 'Gyors jutalmak'}</p>
>                     
>                     <!-- Reward preview -->
>                     <div class="reward-preview">
>                         <span class="reward-badge">
>                             <span class="reward-icon">⭐</span>
>                             <span class="reward-value">10-50</span>
>                         </span>
>                         <span class="reward-label">pont</span>
>                     </div>
>                 </div>
>                 
>                 <!-- CTA with arrow animation -->
>                 <div class="provider-cta">
>                     <span>Feladatok</span>
>                     <svg class="arrow-icon" viewBox="0 0 20 20">
>                         <path d="M10 3l7 7-7 7" stroke="currentColor" 
>                               stroke-width="2" fill="none" stroke-linecap="round" />
>                     </svg>
>                 </div>
>                 
>                 <!-- Shimmer effect on hover -->
>                 <div class="shimmer"></div>
>             </button>
>         `);
>         $container.append($card);
>     });
> }
> 
> // Icon mapping
> function getProviderIcon(providerId) {
>     const icons = {
>         'adgate': '📋',
>         'tapjoy': '📱',
>         'adjoe': '🎮',
>         'fyber': '📺'
>     };
>     return `<span class="provider-emoji">${icons[providerId] || '🎁'}</span>`;
> }
> ```
> 
> **CSS (Modern glassmorphism + animations):**
> ```css
> .provider-grid {
>     display: grid;
>     grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
>     gap: var(--space-lg);
>     padding: var(--space-md);
> }
> 
> .provider-card {
>     position: relative;
>     display: flex;
>     flex-direction: column;
>     padding: var(--space-lg);
>     border: 1px solid var(--glass-border);
>     border-radius: var(--radius-lg);
>     background: var(--glass-bg);
>     backdrop-filter: var(--backdrop-blur);
>     cursor: pointer;
>     overflow: hidden;
>     transition: transform var(--transition-base),
>                 box-shadow var(--transition-base);
>     
>     /* Slide-in animation */
>     opacity: 0;
>     transform: translateY(20px);
>     animation: slideInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
> }
> 
> @keyframes slideInUp {
>     to {
>         opacity: 1;
>         transform: translateY(0);
>     }
> }
> 
> .provider-card:hover {
>     transform: translateY(-4px);
>     box-shadow: var(--shadow-lg), 0 0 0 1px var(--primary);
> }
> 
> .provider-card:active {
>     transform: translateY(-2px);
> }
> 
> /* Shimmer effect on hover */
> .shimmer {
>     position: absolute;
>     top: 0;
>     left: -100%;
>     width: 100%;
>     height: 100%;
>     background: linear-gradient(
>         90deg,
>         transparent,
>         rgba(255, 255, 255, 0.2),
>         transparent
>     );
>     transition: left 0.5s;
> }
> 
> .provider-card:hover .shimmer {
>     left: 100%;
> }
> 
> /* Arrow animation */
> .arrow-icon {
>     width: 16px;
>     height: 16px;
>     transition: transform var(--transition-base);
> }
> 
> .provider-card:hover .arrow-icon {
>     transform: translateX(4px);
> }
> 
> /* Gradient backgrounds */
> .gradient-0 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
> .gradient-1 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
> .gradient-2 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
> 
> /* Dark mode adjustments */
> @media (prefers-color-scheme: dark) {
>     .provider-card {
>         background: rgba(0, 0, 0, 0.4);
>         border-color: rgba(255, 255, 255, 0.1);
>     }
> }
> ```
    
    function openOfferwall(providerId) {
        const provider = offerwallState.providers.find(p => p.id === providerId);
        if (!provider || !provider.iframe_url) return;
        
        // Append pseudo_id to iframe URL
        const pseudoId = getPseudoId();
        const url = provider.iframe_url + 
                    (provider.iframe_url.includes('?') ? '&' : '?') +
                    'user_id=' + encodeURIComponent(pseudoId);
        
        $('#offerwall-frame').attr('src', url);
        
        // Security hardening
        $('#offerwall-frame').attr('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups');
        
        $('.offerwall-iframe-container').fadeIn(300);
        
        // GA4 tracking
        trackEvent('offerwall_opened', { provider: providerId });
    }

> **Gemini 3 Pro javaslat:** Az iframe `sandbox` attribútum használata erősen ajánlott a biztonság növelése érdekében (pl. megakadályozza, hogy a hirdetés átirányítsa a teljes szülőablakot (`top.location`), ami agresszív hirdetők esetén előfordulhat).

> **GPT 5.2 javaslat:** Iframe hardeninghez érdemes még (provider kompatibilitás mellett) a **`referrerpolicy="no-referrer"`** használata és az **„allow” permissions minimalizálása** (csak ami kell). Ez privacy és biztonság szempontból is tisztább, és ritkán fáj, ha jól van dokumentálva provider-enként.

    function loadOfferwallHistory() {
        const pseudoId = getPseudoId();
        if (!pseudoId) return;
        
        $.get('/wp-json/impact/v1/offerwall/history', { 
            pseudo_id: pseudoId,
            limit: 10 
        }).done(function(data) {
            offerwallState.history = data.completions || [];
            renderHistory();
        });
    }

> **Sonnet javaslat - Skeleton Loading & Progressive Disclosure:**
> ```javascript
> function loadOfferwallHistory() {
>     const pseudoId = getPseudoId();
>     if (!pseudoId) return;
>     
>     // Show skeleton loaders
>     renderHistorySkeleton();
>     
>     $.get('/wp-json/impact/v1/offerwall/history', { 
>         pseudo_id: pseudoId,
>         limit: 10 
>     }).done(function(data) {
>         offerwallState.history = data.completions || [];
>         
>         // Fade out skeleton, fade in content
>         $('.history-skeleton').fadeOut(200, function() {
>             $(this).remove();
>             renderHistory();
>         });
>     }).fail(function() {
>         // Error state with retry
>         renderHistoryError();
>     });
> }
> 
> function renderHistorySkeleton() {
>     const $list = $('#offerwall-history-list');
>     $list.empty();
>     
>     // Render 3 skeleton items
>     for (let i = 0; i < 3; i++) {
>         const $skeleton = $(`
>             <div class="history-item history-skeleton" 
>                  style="animation-delay: ${i * 100}ms">
>                 <div class="skeleton-avatar"></div>
>                 <div class="skeleton-content">
>                     <div class="skeleton-line" style="width: 70%"></div>
>                     <div class="skeleton-line" style="width: 50%"></div>
>                 </div>
>                 <div class="skeleton-badge"></div>
>             </div>
>         `);
>         $list.append($skeleton);
>     }
> }
> 
> function renderHistoryError() {
>     const $list = $('#offerwall-history-list');
>     $list.html(`
>         <div class="history-error">
>             <div class="error-icon">⚠️</div>
>             <p class="error-message">Nem sikerült betölteni az előzményeket</p>
>             <button class="btn-retry" onclick="loadOfferwallHistory()">
>                 <svg class="retry-icon" viewBox="0 0 24 24">
>                     <path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
>                 </svg>
>                 Újra próbálom
>             </button>
>         </div>
>     `);
> }
> ```
> 
> **Skeleton CSS:**
> ```css
> .history-skeleton {
>     opacity: 0;
>     animation: fadeInSkeleton 0.3s ease-out forwards;
> }
> 
> @keyframes fadeInSkeleton {
>     to { opacity: 1; }
> }
> 
> .skeleton-avatar,
> .skeleton-line,
> .skeleton-badge {
>     background: linear-gradient(
>         90deg,
>         rgba(255,255,255,0.05) 25%,
>         rgba(255,255,255,0.15) 50%,
>         rgba(255,255,255,0.05) 75%
>     );
>     background-size: 200% 100%;
>     animation: shimmer 1.5s infinite;
>     border-radius: var(--radius-sm);
> }
> 
> @keyframes shimmer {
>     0% { background-position: 200% 0; }
>     100% { background-position: -200% 0; }
> }
> 
> .skeleton-avatar {
>     width: 48px;
>     height: 48px;
>     border-radius: 50%;
> }
> 
> .skeleton-line {
>     height: 12px;
>     margin-bottom: 8px;
> }
> 
> .skeleton-badge {
>     width: 60px;
>     height: 24px;
> }
> 
> /* Error state */
> .history-error {
>     text-align: center;
>     padding: var(--space-xl);
> }
> 
> .error-icon {
>     font-size: 3rem;
>     margin-bottom: var(--space-md);
>     animation: shake 0.5s;
> }
> 
> @keyframes shake {
>     0%, 100% { transform: translateX(0); }
>     25% { transform: translateX(-10px); }
>     75% { transform: translateX(10px); }
> }
> 
> .btn-retry {
>     display: inline-flex;
>     align-items: center;
>     gap: var(--space-sm);
>     padding: var(--space-sm) var(--space-lg);
>     background: var(--primary);
>     color: white;
>     border: none;
>     border-radius: var(--radius-full);
>     cursor: pointer;
>     transition: all var(--transition-base);
> }
> 
> .btn-retry:hover {
>     background: var(--primary-hover);
>     transform: translateY(-2px);
>     box-shadow: var(--shadow-md);
> }
> 
> .retry-icon {
>     width: 16px;
>     height: 16px;
>     fill: currentColor;
> }
> 
> .btn-retry:active .retry-icon {
>     animation: spin 0.5s ease-out;
> }
> 
> @keyframes spin {
>     from { transform: rotate(0deg); }
>     to { transform: rotate(360deg); }
> }
> ```
    
    function renderHistory() {
        const $list = $('#offerwall-history-list');
        $list.empty();
        
        if (offerwallState.history.length === 0) {
            $list.append('<p class="no-history">Még nincs teljesített feladat.</p>');
            return;
        }
        
        offerwallState.history.forEach(function(item) {
            const $item = $(`
                <div class="history-item">
                    <span class="history-provider">${item.provider}</span>
                    <span class="history-name">${item.offer_name}</span>
                    <span class="history-reward">
                        +${item.points_awarded} pont, 
                        +${item.votes_awarded} szavazat
                    </span>
                    <span class="history-date">${formatDate(item.created_at)}</span>
                </div>
            `);
            $list.append($item);
        });
    }
    
    function bindOfferwallEvents() {
        $(document).on('click', '.provider-btn', function() {
            const providerId = $(this).data('provider-id');
            openOfferwall(providerId);
        });
        
        $('.btn-close-offerwall').on('click', function() {
            $('.offerwall-iframe-container').fadeOut(300);
            $('#offerwall-frame').attr('src', 'about:blank');
            
            // Refresh status (points/votes might have changed)
            loadUserStatus();  // Meglévő függvény!
            loadOfferwallHistory();
        });

        // Opus javaslat: Server-Sent Events a valós idejű pont frissítéshez
        // Így nem kell az iframe bezárására várni a pont megjelenéséhez
        /*
        function initSSE() {
            const pseudoId = getPseudoId();
            if (!pseudoId || !window.EventSource) return;
            
            const source = new EventSource('/wp-json/impact/v1/offerwall/events?pseudo_id=' + pseudoId);
            source.onmessage = function(event) {
                const data = JSON.parse(event.data);
                if (data.type === 'completion') {
                    showToast('🎉 +' + data.points + ' pont jóváírva!');
                    loadUserStatus();
                    loadOfferwallHistory();
                }
            };
        }
        */

> **Sonnet javaslat - Modern Toast System:**
> ```javascript
> // Advanced toast notification with confetti effect
> function showRewardToast(points, votes) {
>     // Create toast container
>     const $toast = $(`
>         <div class="reward-toast" role="alert">
>             <div class="toast-content">
>                 <!-- Animated icon -->
>                 <div class="toast-icon success">
>                     <svg viewBox="0 0 52 52" class="checkmark">
>                         <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
>                         <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
>                     </svg>
>                 </div>
>                 
>                 <!-- Message with counter animation -->
>                 <div class="toast-message">
>                     <h4 class="toast-title">Gratulálunk! 🎉</h4>
>                     <div class="toast-rewards">
>                         <span class="reward-item">
>                             <span class="reward-icon">⭐</span>
>                             <span class="reward-count" data-target="${points}">0</span> pont
>                         </span>
>                         <span class="reward-divider">+</span>
>                         <span class="reward-item">
>                             <span class="reward-icon">🗳️</span>
>                             <span class="reward-count" data-target="${votes}">0</span> szavazat
>                         </span>
>                     </div>
>                 </div>
>                 
>                 <!-- Progress bar -->
>                 <div class="toast-progress"></div>
>             </div>
>         </div>
>     `);
>     
>     $('body').append($toast);
>     
>     // Trigger animations
>     setTimeout(() => {
>         $toast.addClass('show');
>         
>         // Counter animation
>         $('.reward-count', $toast).each(function() {
>             const $counter = $(this);
>             const target = parseInt($counter.data('target'));
>             animateCounter($counter, 0, target, 800);
>         });
>         
>         // Confetti effect
>         triggerConfetti($toast[0]);
>         
>         // Auto dismiss after 4s
>         setTimeout(() => {
>             $toast.removeClass('show');
>             setTimeout(() => $toast.remove(), 300);
>         }, 4000);
>     }, 100);
> }
> 
> // Smooth counter animation
> function animateCounter($element, from, to, duration) {
>     const start = Date.now();
>     const range = to - from;
>     
>     function update() {
>         const now = Date.now();
>         const progress = Math.min((now - start) / duration, 1);
>         const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
>         const value = Math.round(from + range * eased);
>         
>         $element.text(value);
>         
>         if (progress < 1) {
>             requestAnimationFrame(update);
>         }
>     }
>     
>     requestAnimationFrame(update);
> }
> 
> // Canvas confetti effect
> function triggerConfetti(element) {
>     const rect = element.getBoundingClientRect();
>     const particles = [];
>     const colors = ['#FF6B6B', '#4ECDC4', '#FFE66D', '#51CF66'];
>     
>     for (let i = 0; i < 30; i++) {
>         particles.push({
>             x: rect.left + rect.width / 2,
>             y: rect.top + rect.height / 2,
>             vx: (Math.random() - 0.5) * 8,
>             vy: (Math.random() - 0.5) * 8 - 4,
>             color: colors[Math.floor(Math.random() * colors.length)],
>             size: Math.random() * 4 + 2,
>             life: 1
>         });
>     }
>     
>     const canvas = document.createElement('canvas');
>     canvas.className = 'confetti-canvas';
>     canvas.width = window.innerWidth;
>     canvas.height = window.innerHeight;
>     document.body.appendChild(canvas);
>     
>     const ctx = canvas.getContext('2d');
>     
>     function animate() {
>         ctx.clearRect(0, 0, canvas.width, canvas.height);
>         
>         let alive = false;
>         particles.forEach(p => {
>             if (p.life > 0) {
>                 alive = true;
>                 p.x += p.vx;
>                 p.y += p.vy;
>                 p.vy += 0.2; // gravity
>                 p.life -= 0.02;
>                 
>                 ctx.save();
>                 ctx.globalAlpha = p.life;
>                 ctx.fillStyle = p.color;
>                 ctx.beginPath();
>                 ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
>                 ctx.fill();
>                 ctx.restore();
>             }
>         });
>         
>         if (alive) {
>             requestAnimationFrame(animate);
>         } else {
>             canvas.remove();
>         }
>     }
>     
>     animate();
> }
> ```
> 
> **Toast CSS:**
> ```css
> .reward-toast {
>     position: fixed;
>     top: 24px;
>     right: 24px;
>     z-index: 10000;
>     transform: translateX(400px);
>     transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
> }
> 
> .reward-toast.show {
>     transform: translateX(0);
> }
> 
> .toast-content {
>     display: flex;
>     align-items: center;
>     gap: var(--space-md);
>     padding: var(--space-lg);
>     background: var(--glass-bg);
>     backdrop-filter: var(--backdrop-blur);
>     border: 1px solid var(--glass-border);
>     border-radius: var(--radius-lg);
>     box-shadow: var(--shadow-lg);
>     min-width: 320px;
>     position: relative;
>     overflow: hidden;
> }
> 
> /* Animated checkmark */
> .checkmark {
>     width: 52px;
>     height: 52px;
> }
> 
> .checkmark-circle {
>     stroke: var(--success);
>     stroke-width: 2;
>     stroke-dasharray: 166;
>     stroke-dashoffset: 166;
>     animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
> }
> 
> .checkmark-check {
>     stroke: var(--success);
>     stroke-width: 3;
>     stroke-linecap: round;
>     stroke-dasharray: 48;
>     stroke-dashoffset: 48;
>     animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.6s forwards;
> }
> 
> @keyframes stroke {
>     100% { stroke-dashoffset: 0; }
> }
> 
> /* Progress bar */
> .toast-progress {
>     position: absolute;
>     bottom: 0;
>     left: 0;
>     right: 0;
>     height: 4px;
>     background: var(--success);
>     transform-origin: left;
>     animation: progress 4s linear;
> }
> 
> @keyframes progress {
>     from { transform: scaleX(1); }
>     to { transform: scaleX(0); }
> }
> 
> .confetti-canvas {
>     position: fixed;
>     top: 0;
>     left: 0;
>     pointer-events: none;
>     z-index: 9999;
> }
> ```
        
        // Tab switching (ha tab UI)
        $('.ads-watch-tabs .tab').on('click', function() {
            const tab = $(this).data('tab');
            $('.ads-watch-tabs .tab').removeClass('active');
            $(this).addClass('active');
            $('.tab-content').hide();
            $('#tab-' + tab).fadeIn(300);
            
            // Update URL hash without scroll
            history.replaceState(null, null, '#' + tab);
        });

        // Handle refresh with existing hash
        if (window.location.hash === '#offerwall') {
            $('.ads-watch-tabs .tab[data-tab="offerwall"]').click();
        }
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        if ($('#impactshop-ads-watch').length > 0) {
            initOfferwall();
        }
    });

> **Gemini 3 Pro javaslat:** A fenti kiegészítés (`window.location.hash` kezelés) megoldja, hogy oldalfrissítéskor a felhasználó ne essen vissza a Videó tabra, hanem az Offerwall-on maradjon, ami javítja a UX-et.

})(jQuery);
```

**Fontos integráció pontok:**
- ✅ `loadUserStatus()` meglévő függvényt hívja → friss pont/szavazat látszik
- ✅ `getPseudoId()` már létezik → újrahasználás
- ✅ `trackEvent()` már létezik → GA4 tracking
- ✅ IFrame URL-hez `user_id={pseudo_id}` paraméter → provider tudja ki a user

> **Codex 5.2 javaslat:** Tegyél **örömteli empty state**‑et a history részhez („Még nincs teljesítés – válassz egy gyors feladatot 👇”), és **reward badge**‑eket a friss teljesítésekhez („+50 pont • 1 perc”). Ez modern, fiatalos feedback loopot ad.

---

## 🔄 Postback Flow Diagram

```
┌─────────────────┐
│  User clicks    │
│  offer in       │
│  iframe         │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Provider       │
│  tracks         │
│  completion     │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────────────┐
│  Provider sends POSTBACK to your server         │
│  GET /wp-json/impact/v1/offerwall/callback/...  │
│  ?user_id=abc&transaction_id=T123&amount=0.50   │
│  &sig=hash...                                   │
└────────┬────────────────────────────────────────┘
         │
         ▼
┌─────────────────┐
│  WordPress      │
│  REST API       │
│  handler        │
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌─────────────────┐
│  Validate       │ NO  │  Return 403     │
│  signature?     ├────►│  Forbidden      │
└────────┬────────┘     └─────────────────┘
         │ YES
         ▼
┌─────────────────┐     ┌─────────────────┐
│  Check          │ YES │  Return 200     │
│  duplicate?     ├────►│  "duplicate"    │
└────────┬────────┘     └─────────────────┘
         │ NO
         ▼
┌─────────────────┐
│  Calculate      │
│  rewards        │
│  (points/votes) │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Insert to      │
│  offerwall_     │
│  completions    │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  Award rewards                      │
│  - Sharity_Points_Manager           │
│  - impactshop_ads_user_votes update │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────┐
│  Return 200 OK  │
│  {success:true} │
└─────────────────┘
```

---

## 🛡️ Security & Fraud Prevention

### 1. Signature Validation (L1)
```php
// MINDEN postback-et validálunk
if (!impactshop_offerwall_validate_signature($provider, $params, $sig)) {
    // Log fraud attempt
    impactshop_offerwall_log_fraud([
        'type' => 'invalid_signature',
        'provider' => $provider,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'params' => $params
    ]);
    return WP_REST_Response(['error' => 'invalid_signature'], 403);
}
```

### 2. Transaction ID Deduplication (L2)
```sql
UNIQUE KEY dedupe (provider, transaction_id)
-- INSERT fog fail-elni ha ugyanaz a trx ID újra jön
```

> **GPT 5.2 javaslat:** A provider retry-k miatt a „duplicate” esetben jó, ha **mindig 200 OK**-t adsz (ahogy a flow-ban is van), és opcionálisan egy stabil `request_id`-t visszaadsz. Így a provider nem próbálkozik végtelenül, és neked is lesz referenciád support/QA során.

### 3. Rate Limiting (L3)
```php
// Per user
$rate_user = impactshop_offerwall_rate_limit_check(
    'offerwall_user:' . $pseudo_id,
    50,    // 50 completion / hour
    3600
);

// Per IP
$rate_ip = impactshop_offerwall_rate_limit_check(
    'offerwall_ip:' . $ip,
    200,   // 200 completion / hour from same IP
    3600
);
```

### 4. IP Tracking & Geolocation (L4)
```php
// Store user IP in completions table
'user_ip' => $_SERVER['REMOTE_ADDR']

// Optional: GeoIP check
if (function_exists('geoip_country_code_by_name')) {
    $country = geoip_country_code_by_name($ip);
    // Alert ha suspicious country pattern
}
```

### 5. Postback Audit Trail (L5)
```php
// Store FULL postback data
'postback_data' => json_encode($_GET)

// Későbbi forensics elemzéshez:
// - IP patterns
// - Time patterns
// - User agent analysis
```

### 6. Reversal Handling (L6)
```php
// Ha provider később visszavonja a completion-t
function impactshop_offerwall_handle_reversal(
    string $provider,
    string $transaction_id
): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    
    $completion = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE provider = %s AND transaction_id = %s",
        $provider, $transaction_id
    ));
    
    if (!$completion) return false;
    
    // Mark as reversed
    $wpdb->update($table, 
        ['status' => 'reversed'],
        ['id' => $completion->id]
    );
    
    // Deduct points/votes (ha még nem költötte el)
    impactshop_offerwall_deduct_rewards(
        $completion->pseudo_id,
        $completion->points_awarded,
        $completion->votes_awarded
    );
    
    return true;
}

> **Gemini 3 Pro javaslat:** Érdemes egy automatikus **alerting küszöböt** beállítani. Ha a `fraud_attempts` száma (pl. érvénytelen signature miatt) meghaladja a 10/perc értéket, a rendszer küldjön azonnali email értesítést az adminnak, mert ez aktív támadást vagy provider konfigurációs hibát jelezhet.
```

---

## 📊 Provider Integration Examples

### AdGate Media

**Dashboard setup:**
```
Postback URL: https://app.sharity.hu/wp-json/impact/v1/offerwall/callback/adgate
Signature Method: MD5
Secret Key: {ADMIN_GENERATED}
User ID Parameter: user_id
```

**Postback URL format:**
```
?user_id={subid}
&transaction_id={transaction_id}
&offer_id={offer_id}
&offer_name={offer_name}
&amount={amount}
&currency=USD
&sig={md5(user_id={subid}&transaction_id={transaction_id}...{SECRET})}
```

**Implementation checklist:**
- [ ] Register at AdGate partner dashboard
- [ ] Get API Key & Secret
- [ ] Configure postback URL
- [ ] Test with test postback tool
- [ ] Add to WP admin settings
- [ ] Generate iframe URL with API key
- [ ] Test live offer completion

### Adjoe (Mobile SDK alternative)

**Notes:**
- Adjoe jellemzően mobile SDK (Android/iOS)
- Ha van web SDK, akkor hasonló iframe flow
- Postback ugyanúgy működik

### Tapjoy, Fyber, ironSource

**Common pattern:**
- Mindegyik ugyanazt a server-to-server postback modellt használja
- Eltérés: parameter nevek, signature method
- Megoldás: Provider-specific config JSON

---

## 🎨 UX Flow - User Perspective

### 1. User érkezik az Ads Watch oldalra
```
┌──────────────────────────────────┐
│  🎬 Videó  |  🎁 Feladatok       │  ← Tab selector
├──────────────────────────────────┤
│  Pontjaid: 1,234                 │
│  Szavazataid: 45                 │
├──────────────────────────────────┤
│  [▶ Reklám megtekintése]         │  ← Meglévő videó
│                                   │
│  [🎁 További jutalmak]           │  ← ÚJ offerwall gomb
└──────────────────────────────────┘
```

### 2. User kattint "További jutalmak" gombra
```
┌──────────────────────────────────┐
│  Végezz feladatokat és szerezz   │
│  extra pontokat!                  │
├──────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐     │
│  │ AdGate   │  │ Tapjoy   │     │  ← Provider választás
│  │ Kérdőív  │  │ App      │     │
│  │ +10-50 pt│  │ +50-200  │     │
│  └──────────┘  └──────────┘     │
└──────────────────────────────────┘
```

### 3. User választ egy providert → IFrame megnyílik
```
┌────────────────────────────────────────────┐
│  [✕ Bezárás]                               │
├────────────────────────────────────────────┤
│  ┌──────────────────────────────────────┐ │
│  │  ADGATE OFFERWALL (iframe)           │ │
│  │  ┌────────────────────────────────┐  │ │
│  │  │ Survey: Win iPhone! +50 pts    │  │ │
│  │  │ App Install: Game XYZ +100 pts │  │ │
│  │  │ Video: Watch 3 videos +20 pts  │  │ │
│  │  └────────────────────────────────┘  │ │
│  └──────────────────────────────────────┘ │
└────────────────────────────────────────────┘
```

### 4. User teljesít egy offert (pl. survey)
```
User kitölti a kérdőívet a provider oldalán
    ↓
Provider validates completion
    ↓
Provider küldi a postback-et nekünk
    ↓
Backend jóváírja a pontokat/szavazatokat
    ↓
User bezárja az iframe-et
    ↓
Frontend refresh → ÚJ pont/szavazat számok látszanak!
```

### 5. User látja a history-t
```
┌────────────────────────────────────────────┐
│  Legutóbbi teljesítések:                   │
├────────────────────────────────────────────┤
│  📋 AdGate - Survey XYZ                    │
│     +50 pont, +5 szavazat                  │
│     2026-01-26 14:32                       │
├────────────────────────────────────────────┤
│  📱 Tapjoy - App Install                   │
│     +100 pont, +10 szavazat                │
│     2026-01-26 12:15                       │
└────────────────────────────────────────────┘
```

### 6. User használja a megszerzett szavazatokat
```
User clicks "Szavazok most" gombra
    ↓
UGYANAZ A FLOW mint a videónál!
    ↓
POST /ads-watch/allocate
    ↓
Szavazat levonás + NGO tally update
```

**✅ Teljesen átlátszó integráció!** A user számára mindegy, hogy videóból vagy offerwall-ból szerezte a szavazatokat.

---

## 🚀 Implementation Roadmap

### Phase 1: Backend Foundation (1-2 nap)
- [ ] Létrehozni `impactshop-offerwall.php` fájlt
- [ ] Database schema migration
- [ ] Provider config system (wp_options)
- [ ] REST API endpoints implementation
- [ ] Signature validation logic
- [ ] Rate limiting integration
- [ ] Reward calculation logic
- [ ] Sharity integration (points/votes)

### Phase 2: Admin Interface (0.5-1 nap)
- [ ] Settings page UI
- [ ] Provider enable/disable toggles
- [ ] API key input fields
- [ ] Postback URL display
- [ ] Stats table (completions/revenue)
- [ ] Test postback button

### Phase 3: Frontend Widget (1-2 nap)
- [ ] `impactshop-offerwall.js` létrehozása
- [ ] Tab system vagy sidebar integration
- [ ] Provider button rendering
- [ ] IFrame modal logic
- [ ] History display
- [ ] CSS styling (match existing design)

### Phase 4: Provider Registration (1-2 nap per provider)
- [ ] AdGate account setup
- [ ] Postback configuration
- [ ] Test mode verification
- [ ] Live mode activation
- [ ] Repeat for additional providers

### Phase 5: Testing & QA (1-2 nap)
- [ ] Unit tests (postback validation)
- [ ] Integration tests (end-to-end flow)
- [ ] Fraud scenario testing
- [ ] Rate limit verification
- [ ] Cross-browser testing
- [ ] Mobile responsiveness

> **Codex 5.2 javaslat:** UX‑re javasolt **5‑fős usability mini‑teszt** (15 perc/fő), külön fókusz a „miért nem kattintott” okokra. Mérd a **First Click Time**‑ot és a **Provider választási arányt** – ezek a fiatalos, gyors döntési flow kulcsmutatói.

> **Sonnet javaslat - Mobile-First & Accessibility:**
> ```css
> /* Mobile-first responsive breakpoints */
> 
> /* Base (mobile) styles - 320px+ */
> .offerwall-providers {
>     display: flex;
>     flex-direction: column;
>     gap: var(--space-md);
>     padding: var(--space-md);
> }
> 
> /* Touch-optimized buttons */
> .provider-card {
>     min-height: 88px; /* iOS touch target minimum */
>     -webkit-tap-highlight-color: transparent;
>     user-select: none;
> }
> 
> /* Tablet - 768px+ */
> @media (min-width: 768px) {
>     .offerwall-providers {
>         display: grid;
>         grid-template-columns: repeat(2, 1fr);
>         gap: var(--space-lg);
>         padding: var(--space-lg);
>     }
> }
> 
> /* Desktop - 1024px+ */
> @media (min-width: 1024px) {
>     .offerwall-providers {
>         grid-template-columns: repeat(3, 1fr);
>     }
> }
> 
> /* Large desktop - 1440px+ */
> @media (min-width: 1440px) {
>     .offerwall-providers {
>         grid-template-columns: repeat(4, 1fr);
>     }
> }
> 
> /* Touch device optimizations */
> @media (hover: none) and (pointer: coarse) {
>     .provider-card:hover {
>         transform: none; /* Disable hover lift on touch */
>     }
>     
>     .provider-card:active {
>         transform: scale(0.98);
>         transition: transform 0.1s;
>     }
> }
> 
> /* Reduced motion for accessibility */
> @media (prefers-reduced-motion: reduce) {
>     *,
>     *::before,
>     *::after {
>         animation-duration: 0.01ms !important;
>         animation-iteration-count: 1 !important;
>         transition-duration: 0.01ms !important;
>     }
> }
> 
> /* High contrast mode support */
> @media (prefers-contrast: high) {
>     .provider-card {
>         border: 2px solid currentColor;
>     }
>     
>     .glass-bg {
>         opacity: 1;
>         background: var(--bg-solid);
>     }
> }
> 
> /* Safe area insets for notched devices */
> .offerwall-header,
> .offerwall-providers {
>     padding-left: max(var(--space-md), env(safe-area-inset-left));
>     padding-right: max(var(--space-md), env(safe-area-inset-right));
> }
> 
> /* Bottom sheet for mobile iframe */
> @media (max-width: 767px) {
>     .offerwall-iframe-container {
>         position: fixed;
>         inset: 0;
>         z-index: 1000;
>         background: rgba(0, 0, 0, 0.8);
>         backdrop-filter: blur(4px);
>     }
>     
>     #offerwall-frame {
>         position: absolute;
>         bottom: 0;
>         left: 0;
>         right: 0;
>         height: 90vh;
>         border-radius: var(--radius-lg) var(--radius-lg) 0 0;
>         animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
>     }
>     
>     @keyframes slideUp {
>         from { transform: translateY(100%); }
>         to { transform: translateY(0); }
>     }
>     
>     /* Swipe-to-close handle */
>     #offerwall-frame::before {
>         content: '';
>         position: absolute;
>         top: 8px;
>         left: 50%;
>         transform: translateX(-50%);
>         width: 40px;
>         height: 4px;
>         background: rgba(255, 255, 255, 0.3);
>         border-radius: 2px;
>     }
> }
> 
> /* Accessibility enhancements */
> 
> /* Focus visible for keyboard navigation */
> .provider-card:focus-visible {
>     outline: 3px solid var(--primary);
>     outline-offset: 2px;
> }
> 
> /* Screen reader only class */
> .sr-only {
>     position: absolute;
>     width: 1px;
>     height: 1px;
>     padding: 0;
>     margin: -1px;
>     overflow: hidden;
>     clip: rect(0, 0, 0, 0);
>     white-space: nowrap;
>     border-width: 0;
> }
> 
> /* Skip to content link */
> .skip-link {
>     position: absolute;
>     top: -40px;
>     left: 0;
>     background: var(--primary);
>     color: white;
>     padding: 8px;
>     text-decoration: none;
>     z-index: 100;
> }
> 
> .skip-link:focus {
>     top: 0;
> }
> ```
> 
> **JavaScript touch handlers:**
> ```javascript
> // Swipe-to-close for mobile iframe
> let touchStartY = 0;
> let touchEndY = 0;
> 
> $('#offerwall-frame').on('touchstart', function(e) {
>     touchStartY = e.touches[0].clientY;
> });
> 
> $('#offerwall-frame').on('touchend', function(e) {
>     touchEndY = e.changedTouches[0].clientY;
>     const swipeDistance = touchEndY - touchStartY;
>     
>     // If swiped down > 100px, close
>     if (swipeDistance > 100) {
>         $('.offerwall-iframe-container').fadeOut(300);
>         $('#offerwall-frame').attr('src', 'about:blank');
>     }
> });
> 
> // Haptic feedback for touch devices (iOS)
> if ('vibrate' in navigator) {
>     $('.provider-card').on('touchstart', function() {
>         navigator.vibrate(10); // Short tap feedback
>     });
> }
> 
> // Prevent double-tap zoom on buttons
> $('.provider-card, .btn-close-offerwall').on('touchend', function(e) {
>     e.preventDefault();
>     $(this).trigger('click');
> });
> ```

> **Codex 5.2 javaslat:** Egészítsd ki explicit **postback replay** és **signature mismatch** tesztekkel (ezek a leggyakoribb valós incidensek).

### Phase 6: Launch & Monitor (ongoing)
- [ ] Soft launch (limited users)
- [ ] Monitor error logs
- [ ] Track conversion rates
- [ ] Adjust multipliers based on data
- [ ] Scale up gradually

> **Opus javaslat:** Adj hozzá egy **Phase 0: Feature Flag Setup** fázist. Implementálj egy egyszerű feature flag rendszert (pl. `wp_option`-ben tárolt `impactshop_offerwall_enabled_percent = 10`), ami lehetővé teszi a fokozatos rollout-ot (10% → 25% → 50% → 100%) és azonnali rollback-et kód deploy nélkül. Emellett készíts **rollback runbook**-ot, ami dokumentálja a lépéseket, ha kritikus hiba jelentkezik launch után.

**Total estimate: 6-10 munkanap** (1 developer)

---

## 🔧 Configuration Example

### Admin Settings Panel Structure:

```php
// wp-admin/options-general.php?page=impactshop-offerwall

┌─────────────────────────────────────────────┐
│  Offerwall Integráció Beállítások          │
├─────────────────────────────────────────────┤
│                                              │
│  ┌──── AdGate Media ───────────────────┐   │
│  │ [x] Engedélyezve                     │   │
│  │                                       │   │
│  │ API Key:                              │   │
│  │ [________________input_____________]  │   │
│  │                                       │   │
│  │ Secret Key:                           │   │
│  │ [________________input_____________]  │   │
│  │                                       │   │
│  │ IFrame URL:                           │   │
│  │ [________________input_____________]  │   │
│  │                                       │   │
│  │ Pont multiplikátor: [1.0_____]  (1x)│   │
│  │ Szavazat multiplikátor: [1.0__] (1x)│   │
│  │                                       │   │
│  │ Postback URL (másold be provider-hez):│  │
│  │ https://app.sharity.hu/wp-json/...   │   │
│  │ [📋 Másolás]                          │   │
│  └───────────────────────────────────────┘   │
│                                              │
│  ┌──── Tapjoy ──────────────────────────┐   │
│  │ [ ] Engedélyezve                      │   │
│  │ ... (same fields) ...                 │   │
│  └───────────────────────────────────────┘   │
│                                              │
│  [💾 Beállítások Mentése]                   │
│                                              │
├─────────────────────────────────────────────┤
│  Statisztikák                               │
├─────────────────────────────────────────────┤
│  Provider  │ Teljesítések │ Pontok │ Szav. │
│  AdGate    │    1,234     │ 45,678 │ 4,567│
│  Tapjoy    │      567     │ 23,456 │ 2,345│
└─────────────────────────────────────────────┘
```

---

## 📈 Analytics & Monitoring

### Key Metrics to Track:

```php
// Custom analytics table (optional)
CREATE TABLE wp_impactshop_offerwall_stats (
    date DATE PRIMARY KEY,
    provider VARCHAR(64),
    completions INT DEFAULT 0,
    total_points INT DEFAULT 0,
    total_votes INT DEFAULT 0,
    total_payout_usd DECIMAL(10,2) DEFAULT 0.00,
    unique_users INT DEFAULT 0,
    KEY date_provider (date, provider)
);

// Daily aggregation cron job
add_action('impactshop_offerwall_daily_stats', function() {
    // Aggregate yesterday's data
    // Insert into stats table
    // Send report email to admin
});
```

### GA4 Events to Implement:

```javascript
// Frontend tracking
trackEvent('offerwall_opened', {
    provider: 'adgate'
});

trackEvent('offerwall_closed', {
    provider: 'adgate',
    duration_seconds: 120
});

// Backend tracking (via PHP)
do_action('impactshop_offerwall_completed', $pseudo_id, $provider, $offer_id, $rewards);
// → GA4 Measurement Protocol API
```

> **GPT 5.2 javaslat:** Érdemes egy egyszerű **funnel event** sorozat: `offerwall_tab_view` → `offerwall_provider_click` → `offerwall_iframe_loaded` → `offerwall_reward_received` (transaction_id-val dedupe-olva). Így tisztán látszik, hogy UX (kattintás), provider (completion), vagy postback (reward) a szűk keresztmetszet.

---

## 🐛 Troubleshooting Guide

### Problem: Postback nem érkezik meg

**Debug steps:**
1. Ellenőrizd a provider dashboard-on a postback URL-t
2. Nézd meg a WordPress error logot: `/wp-content/debug.log`
3. Teszteld a postback URL-t manuálisan (curl)
4. Ellenőrizd a signature validation logot

```bash
# Manual postback test
curl "https://app.sharity.hu/wp-json/impact/v1/offerwall/callback/adgate?\
user_id=test123&\
transaction_id=TEST999&\
offer_id=1234&\
amount=0.50&\
sig=CALCULATE_THIS"
```

### Problem: Signature validation fail

**Check:**
- Secret key helyes-e az admin settings-ben?
- Signature method (MD5 vs SHA256) egyezik?
- Parameter ordering helyes?

```php
// Debug mode
define('IMPACTSHOP_OFFERWALL_DEBUG', true);

// Ez fog loggolni a WP debug.log-ba
if (IMPACTSHOP_OFFERWALL_DEBUG) {
    error_log('Expected sig: ' . $expected);
    error_log('Received sig: ' . $received);
    error_log('Payload: ' . $payload);
}
```

> **GPT 5.2 javaslat:** Adj minden callback válaszhoz egy `request_id`-t (pl. `wp_generate_uuid4()`), és logold ezt minden hibánál. Support/partner egyeztetésnél elég lesz annyit mondani: „küldd a request_id-t”, és azonnal visszakereshető.

### Problem: Duplicate completions

**Ellenőrzés:**
```sql
SELECT transaction_id, COUNT(*) as cnt
FROM wp_impactshop_offerwall_completions
WHERE provider = 'adgate'
GROUP BY transaction_id
HAVING cnt > 1;
```

Ha találsz duplicate-eket → provider postback retry mechanizmus működik, de a dedupe NEM. Check UNIQUE constraint.

---

## 🎓 Provider-Specific Notes

### AdGate Media
- **Best for:** Surveys, videos, app installs
- **Payout range:** $0.10 - $5.00
- **Postback:** Instant (< 5 sec)
- **Signature:** MD5 concatenation

### Adjoe
- **Best for:** Mobile gaming (playtime offers)
- **Payout range:** $0.50 - $10.00
- **Postback:** Delayed (1-24 hours)
- **Signature:** SHA256 HMAC

### Tapjoy
- **Best for:** High-value app installs
- **Payout range:** $1.00 - $50.00
- **Postback:** Instant
- **Signature:** SHA256 hex

### Fyber (Digital Turbine)
- **Best for:** Video ads, offerwall
- **Payout range:** $0.05 - $2.00
- **Postback:** Instant
- **Signature:** MD5 or SHA1 (configurable)

> **Opus javaslat:** Érdemes felvenni a listára az **OfferToro** és **CPX Research** providereket is, melyek kifejezetten jók magyar/EU piacra. Az OfferToro alacsony minimális kifizetési küszöbbel ($5) rendelkezik, míg a CPX Research kifejezetten survey-fokuszú, ami magasabb completion rate-et eredményez. Mindkettő standard S2S postback-et használ.

---

## ✅ Acceptance Criteria

### Backend Requirements:
- [x] Új adatbázis tábla migration sikeres
- [x] 4 új REST API endpoint működik
- [x] Signature validation minimum 3 provider-hez
- [x] Rate limiting működik (user + IP)
- [x] Sharity_Points_Manager integráció
- [x] impactshop_ads_user_votes frissítés
- [x] Transaction dedupe protection
- [x] Admin settings UI

### Frontend Requirements:
- [x] Tab vagy sidebar UI implementálva
- [x] Provider button rendering dinamikus
- [x] IFrame modal működik
- [x] History display működik
- [x] CSS match existing design
- [x] Mobile responsive

### Integration Requirements:
- [x] GET /ads-watch/status látja az új szavazatokat
- [x] POST /ads-watch/allocate működik offerwall szavazatokkal
- [x] Tally system frissül offerwall votes-ból is
- [x] Achievement system számítja az offerwall completions-t (optional)

### Security Requirements:
- [x] Signature validation minden postback-hez
- [x] Rate limiting active
- [x] IP tracking enabled
- [x] Postback audit trail (full data storage)
- [x] HTTPS enforced
- [x] CSRF protection (WP nonce)

### Testing Requirements:
- [x] Unit test: signature validation
- [x] Unit test: reward calculation
- [x] Integration test: full postback flow
- [x] Manual test: iframe → completion → points visible
- [x] Fraud test: duplicate transaction_id rejected
- [x] Fraud test: invalid signature rejected
- [x] Load test: 100 concurrent postbacks

---

## 🔮 Future Enhancements

### Phase 2 Features (post-launch):
1. **Offerwall-specific achievements:**
   ```php
   "offerwall_novice" => "Teljesítettél 10 offert"
   "offerwall_expert" => "Teljesítettél 100 offert"
   "survey_master" => "Teljesítettél 50 kérdőívet"
   ```

2. **Provider performance dashboard:**
   - Conversion rate tracking
   - Average payout per provider
   - User preference analytics
   - Auto-optimize provider ordering

3. **Smart offer recommendation:**
   ```php
   // Machine learning based on:
   // - User history
   // - Completion rates
   // - Payout optimization
   function impactshop_offerwall_recommend_offers($pseudo_id): array;
   ```

4. **Offer preview cards (no iframe):**
   - Server-side offer API fetch
   - Native WordPress UI
   - Better mobile UX

5. **Bonus multiplier events:**
   ```php
   // Weekend bonus: 2x points from offerwall
   // NGO campaign: 3x votes for specific NGO
   // Provider partnership: AdGate 50% bonus week
   ```

6. **Referral system:**
   ```php
   // User invites friend → both get bonus
   // Friend completes offer → referrer gets 10% commission
   ```

7. **Webhook notifications:**
   ```php
   // Real-time notification when user completes offer
   // Push notification: "Gratulálunk! +50 pont jóváírva"
   ```

8. **A/B Testing Framework (Opus javaslat):**
   ```php
   // Különböző UI variánsok tesztelése:
   // - Tab vs Sidebar elrendezés
   // - CTA szöveg variációk ("Feladatok" vs "Extra jutalmak" vs "Kérdőívek")
   // - Provider sorrend (legmagasabb payout first vs legtöbb completion first)
   function impactshop_offerwall_get_variant($pseudo_id): string {
       // Deterministic hash-based assignment
       return (crc32($pseudo_id) % 100) < 50 ? 'control' : 'treatment';
   }
   ```

> **Opus javaslat:** Az A/B testing keretrendszer kritikus a hosszú távú optimalizáláshoz. Track-eld GA4-ben a variáns + conversion eseményeket, és heti riportban elemezd az eredményeket. A sikeres variáns legyen az új default.

---

## 📚 Documentation for Codex

### Quick Start Guide (for developers):

```bash
# 1. Copy impactshop-offerwall.php to mu-plugins
cp impactshop-offerwall.php /wp-content/mu-plugins/

# 2. Database migration runs automatically (init hook)

# 3. Configure providers in WP Admin
# wp-admin/options-general.php?page=impactshop-offerwall

# 4. Copy postback URL to provider dashboard

# 5. Test with provider's test postback tool

# 6. Go live!
```

### API Documentation:

**Authentication:** WordPress REST API nonce (X-WP-Nonce header) for frontend calls. No auth for postback (signature validation).

**Base URL:** `https://app.sharity.hu/wp-json/impact/v1/offerwall`

**Endpoints:**

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/config` | Public | Get enabled providers |
| POST | `/callback/{provider}` | Signature | Provider postback |
| GET | `/history` | Public | User completion history |
| GET | `/offers` | Public | (Optional) Offer list |

**Error Codes:**

| Code | Meaning | Action |
|------|---------|--------|
| 400 | Bad Request | Check parameters |
| 401 | Unauthorized | Missing pseudo_id |
| 403 | Forbidden | Invalid signature |
| 409 | Conflict | Duplicate transaction |
| 429 | Too Many Requests | Rate limited |
| 500 | Server Error | Check logs |

---

## 🎯 Success Metrics (KPIs)

### Month 1 Goals:
- [ ] 100+ unique users try offerwall
- [ ] 500+ offer completions
- [ ] <1% fraud rate
- [ ] <0.1% signature validation failures
- [ ] 95%+ postback success rate

### Month 3 Goals:
- [ ] 1,000+ unique users
- [ ] 10,000+ offer completions
- [ ] 20%+ offerwall revenue vs video ads
- [ ] 3+ active providers
- [ ] User NPS score 8+

> **Opus javaslat:** Javasolt kiegészítő KPI-ok:
> - **Offer Completion Rate (OCR):** iframe megnyitás → sikeres completion arány. Ha <5%, a provider minősége vagy UX problémás.
> - **Time to Reward (TTR):** postback érkezéstől a pont megjelenéséig eltelt idő. Target: <100ms.
> - **Churn Reduction:** Offerwall-t használó userek visszatérési aránya vs. nem használók. Ez méri a feature engagement hatását.
> - **Support Ticket Rate:** Offerwall-hoz kapcsolódó hibajegyek aránya. Célérték: <0.5% a completions számához képest.

### ROI Calculation:
```
Revenue per completion = Payout * 0.7 (70% rev share típus)
User value = Points/Votes awarded
Break-even = When user donates via point/vote mechanism

Példa:
- AdGate $0.50 payout = $0.35 revenue
- User gets 50 points + 5 votes
- User szavaz 5 votes-ot NGO-ra
- NGO gets proportion from 500k Ft pool
- Donation amount > $0.35 → profitable!
```

---

## 🚨 Critical Notes for Implementation

### DO:
- ✅ Use **prepared statements** minden DB query-nél
- ✅ **Validate ALL inputs** (sanitize_text_field, absint, stb.)
- ✅ **Log everything** (fraud attempts, errors, completions)
- ✅ **Test signature validation** minden provider-hez ELŐTTE
- ✅ **Start with ONE provider** (AdGate ajánlott), add többit később
- ✅ **Monitor error logs** első 48 órában óránként

### DON'T:
- ❌ **NE módosítsd** a meglévő `impactshop-ads-watch.php` fájlt (csak extend)
- ❌ **NE használj global variables** a state-hez
- ❌ **NE hagyj debug logging-ot** production-ben
- ❌ **NE felejtsd el** a UNIQUE constraint-et a transaction_id-n
- ❌ **NE indítsd el** több providert egyszerre (gradual rollout!)

---

## 📝 Checklist for Codex Agent

```
BACKEND:
[ ] Create impactshop-offerwall.php file
[ ] Define constants (rate limits, multipliers)
[ ] Create database table (migration function)
[ ] Implement provider config system (wp_options based)
[ ] Implement signature validation function
[ ] Implement reward calculation function
[ ] Implement Sharity integration (points/votes)
[ ] Register REST API routes
[ ] Implement /config endpoint
[ ] Implement /callback/{provider} endpoint
[ ] Implement /history endpoint
[ ] Implement rate limiting (reuse existing function)
[ ] Implement fraud logging
[ ] Create admin settings page
[ ] Create stats display table

FRONTEND:
[ ] Create impactshop-offerwall.js file
[ ] Implement config fetch
[ ] Implement provider button rendering
[ ] Implement iframe modal logic
[ ] Implement history fetch & render
[ ] Implement tab switching (if tab UI)
[ ] Add CSS styles (match existing design)
[ ] Enqueue script in PHP
[ ] Add localization (i18n)

TESTING:
[ ] Unit test: signature validation (AdGate, Tapjoy)
[ ] Unit test: reward calculation
[ ] Integration test: full postback flow
[ ] Manual test: iframe → offer → postback → points visible
[ ] Fraud test: duplicate transaction_id
[ ] Fraud test: invalid signature
[ ] Fraud test: expired rate limit
[ ] Cross-browser test (Chrome, Safari, Firefox)
[ ] Mobile responsive test (iOS, Android)

DOCUMENTATION:
[ ] Update notes.md with changes
[ ] Create provider setup guide
[ ] Create troubleshooting doc
[ ] Add inline code comments
[ ] Document postback URL format per provider

DEPLOYMENT:
[ ] Code review (security focus)
[ ] Staging deployment
[ ] Smoke test on staging
[ ] Production deployment (off-peak hours)
[ ] Monitor logs (first 24h)
[ ] User feedback collection
```

---

## 🎉 Conclusion

Ez az implementációs terv egy **production-ready**, **skálázható**, és **biztonságos** offerwall integrációt ír le, amely:

1. ✅ **NEM módosítja** a meglévő videó ad watch funkciókat
2. ✅ **Újrahasználja** a meglévő Sharity pont/szavazat rendszert
3. ✅ **Bővíti** a platform bevételi lehetőségeit
4. ✅ **Javítja** a user engagement-et (több activity type)
5. ✅ **Betartja** a provider policy-kat (incentivization OK offerwall-nál)
6. ✅ **Skálázható** (multi-provider architecture)
7. ✅ **Biztonságos** (signature validation, rate limiting, fraud detection)

**Várható fejlesztési idő:** 6-10 munkanap (1 senior developer)  
**Várható ROI:** 20-40% revenue increase (3 hónapon belül)  
**Risk level:** Alacsony (isolated component, non-breaking change)

---

**Következő lépés:** Codex agent kezdje el a backend implementációt az `impactshop-offerwall.php` fájl létrehozásával!

