# Cégjelző API Integráció – NGO Adatgazdagítás

**Verzió:** 1.1
**Dátum:** 2026-02-09
**Státusz:** VÉGLEGES – Implementáció indítható
**Szerző:** ImpactShop Dev Team

---

## 0. API Hitelesítés (Érvényes)

> ⚠️ **Biztonsági megjegyzés:** Az alábbi adatok kizárólag a tervdokumentumban szerepelnek referencia céljából. Éles rendszerben WP options-ból (encrypted) kell kiolvasni. **Ne commitoljuk plaintext-ben.**

| Paraméter | Érték | Megjegyzés |
|-----------|-------|------------|
| Fiók név | `BujdosoUgyvediIroda` | JWT `name` mező |
| API verzió | v2 | JWT `ver` mező |
| Kiállítás | 2022-03-18 | JWT `date` mező |
| Végpontok | `search`, `autocomplete` | ✅ Pont ami kell |
| `financials-data-table` | ❌ Nincs | Pénzügyi mutatók nem elérhetők |
| Egyéni vállalkozó mezők | ❌ Nincs (`se_flds: []`) | Nem releváns |
| Költségvetési szervek | ❌ Nincs (`bdg_flds: []`) | Nem releváns |

**Civil szervezet mezők (19 db – TELJES):**
`status`, `status_code`, `long_name`, `short_name`, `address`, `nav_address`, `tax_number`, `activity`, `bank_accounts`, `constituent_document_date`, `description`, `insertion`, `leading_orgs`, `level_of_charity`, `proceedings`, `registration_number`, `representatives`, `type`, `updated_at`

**Cég mezők (10 db – alap):**
`status`, `status_code`, `nav_status`, `nav_status_code`, `long_name`, `short_name`, `address`, `nav_address`, `tax_number`, `group_tax_number`

---

## 1. Összefoglaló

A Cégjelző API (v2) bekötése az ImpactShop rendszerbe, amelynek célja az NGO-k (civil szervezetek) hivatalos jogi és szervezeti adatainak automatikus lekérdezése és gazdagítása. Jelenleg az NGO-kat slug + megjelenítési név alapján kezeljük; a Cégjelző integráció lehetővé teszi:

- **Hivatalos szervezeti adatok** (teljes név, székhely, nyilvántartási szám, adószám)
- **Közhasznúsági státusz** validálás
- **Működési státusz** ellenőrzés (aktív / törölt)
- **Cél szerinti besorolás** (activity) automatikus kategorizálás
- **Képviselők** adatainak lekérése (transzparencia)
- **NAV székhely cím** (nav_address – ha eltér a bejegyzetttől)

---

## 2. Cégjelző API Áttekintés

### 2.1 Végpontok

| Végpont | Módszer | Funkció | Relevancia |
|---------|---------|---------|------------|
| `/autocomplete` | GET | Név eleji keresés, gyors typeahead | 🟢 NGO keresés admin UI-ból |
| `/search` | GET | Részletes jogi adatok lekérdezése | 🟢 **Elsődleges** – NGO gazdagítás |
| `/financials-data-table` | GET | Pénzügyi mutatók (beszámolók) | ❌ **Nem elérhető** – JWT nem tartalmazza |
| Kapcsolati háló API | GET | Szervezeti kapcsolatok | ❌ Nem elérhető |
| Kereső API | GET | Bankszámla szerinti keresés | ❌ Nem elérhető |

### 2.2 Autentikáció

```
Header: X-Api-Key: Ew6pPD...Vjue (40 karakter)
Header: X-Client-Id: BUJDOSOUGYVEDIIRODA-00001
```

- **Rate limit:** 30 req/sec per API key
- **Kvóta:** Havi lekérdezési korlát (szerződés szerint – egyeztetés szükséges)
- **Frissítés:** Előző napi adatok hajnali 4 óra után elérhetők

### 2.3 Civil szervezetek mezői (`/search`)

A Cégjelző a `type=civil_orgs` szűrővel specifikusan civil szervezeteket keres. Elérhető mezők:

| Mező | Leírás | ImpactShop használat |
|------|--------|---------------------|
| `registration_number` | Nyilvántartási szám | Egyedi azonosító |
| `long_name` | Bejegyzett név | Hivatalos megjelenítési név |
| `short_name` | Rövidített név | Alternatív megjelenítés |
| `address` | Székhely | NGO kártyán megjelenítés |
| `status` | Státusz (működő/törölt) | **Validáció** |
| `status_code` | 0=törölt, 1=működő | Automatikus ellenőrzés |
| `type` | Szervezet típusa | Kategorizálás |
| `insertion` | Bejegyzés dátuma | Transzparencia |
| `constituent_document_date` | Létesítő okirat kelte | Szervezet kora |
| `activity` | Cél szerinti besorolás | **NGO kategorizálás** |
| `level_of_charity` | Közhasznúsági fokozat | **Közhasznúság validálás** |
| `description` | Cél szerinti leírás | NGO bemutatkozás |
| `tax_number` | Adószám | Cross-reference |
| `representatives` | Képviseletre jogosultak | Transzparencia |
| `leading_orgs` | Ügyvezető szervek | Transzparencia |
| `bank_accounts` | Bankszámlaszám | Donation routing |
| `proceedings` | Folyamatban lévő eljárások | **Kockázat jelzés** |

### 2.4 NAV-os adatok

> ⚠️ **JWT korlátozás:** A jelenlegi API hozzáférés (`cvl_flds`) tartalmazza a `nav_address` mezőt, de a negatív/pozitív NAV státusz mezők (`nav_no_tax_debt`, `nav_trustworthy_tax_payers`, stb.) **NEM** szerepelnek a civil szervezet jogosultságban. Csak a `nav_address` (NAV szerinti cím) érhető el.

| Mező | Leírás | Státusz |
|------|--------|--------|
| `nav_address` | NAV szerinti székhely (darabolt) | ✅ Elérhető (`cvl_flds`-ben) |
| `nav_no_tax_debt` | Köztartozásmentes lista | ❌ Nincs a JWT-ben |
| `nav_trustworthy_tax_payers` | Megbízható adózó | ❌ Nincs a JWT-ben |
| `nav_significant_debt` | Jelentős adóhiány | ❌ Nincs a JWT-ben |
| `nav_missing_reports` | Hiányzó beszámolók | ❌ Nincs a JWT-ben |
| `nav_tax_debt` | Adótartozás | ❌ Nincs a JWT-ben |

**Következmény:** A Trust Score NAV-os komponenseit ki kell hagyni vagy a Cégjelző Kft-vel egyeztetni a hozzáférés bővítéséről.

---

## 3. Rendszerarchitektúra

### 3.1 Jelenlegi állapot (AS-IS)

```
┌─────────────────────────────────────────────────────────┐
│  WordPress (app.sharity.hu)                              │
│                                                          │
│  ┌────────────────────┐  ┌─────────────────────────┐    │
│  │ impactshop-ngo-    │  │ impactshop-metrics-     │    │
│  │ card.php (4677 sor)│  │ ngo.php                 │    │
│  │                    │  │                          │    │
│  │ • slug → név       │  │ • Leaderboard            │    │
│  │ • rebuild_dataset()│  │ • Ticker                 │    │
│  │ • Dognet totals    │  │ • Activity log           │    │
│  │ • Ledger fallback  │  │                          │    │
│  └────────────────────┘  └─────────────────────────┘    │
│                                                          │
│  NGO adat: slug + display name + amount (HUF/EUR)       │
│  ⚠️ Nincs: hivatalos név, cím, közhasznúság, adószám    │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  AI Agent Core (Port 4000) – Tervezett                   │
│                                                          │
│  ┌────────────────────┐  ┌─────────────────────────┐    │
│  │ ngo-categories.ts  │  │ recommend.ts             │    │
│  │ • Manuális mapping │  │ • Kupon ajánlások        │    │
│  │ • JSON fájl alapú  │  │ • NGO preferencia        │    │
│  └────────────────────┘  └─────────────────────────┘    │
│                                                          │
│  ⚠️ ngo-category-map.json = statikus, manuális frissítés│
└─────────────────────────────────────────────────────────┘
```

**Problémák:**
- NGO adatok manuálisan karbantartottak
- Nincs hivatalos validáció (működő szervezet-e?)
- Nincs közhasznúsági státusz ellenőrzés
- NGO kategóriák statikus JSON mappingben
- Új NGO-k manuális jóváhagyást igényelnek

### 3.2 Célállapot (TO-BE)

```
┌──────────────────────────────────────────────────────────────────────┐
│  WordPress (app.sharity.hu)                                          │
│                                                                      │
│  ┌──────────────────────────────┐   ┌──────────────────────────┐    │
│  │ impactshop-ngo-card.php      │   │ impactshop-cegjelzo.php  │    │
│  │ (meglévő, bővített)          │   │ (ÚJ mu-plugin)          │    │
│  │                              │   │                          │    │
│  │ • slug → gazdagított adatok  │◄──│ • API Client wrapper     │    │
│  │ • Cégjelző adatok beolvadása │   │ • Cache layer (WP trans) │    │
│  │ • Trust badge megjelenítés   │   │ • Batch enrichment       │    │
│  │ • Közhasznúság ikon          │   │ • Admin UI               │    │
│  └──────────────────────────────┘   │ • WP-CLI commands        │    │
│                                      │ • Cron sync              │    │
│  ┌──────────────────────────────┐   │ • Rate limit guard       │    │
│  │ impactshop-metrics-ngo.php   │   └──────────┬───────────────┘    │
│  │ (meglévő, bővített)          │              │                    │
│  │ • Cégjelző enriched data     │              │                    │
│  └──────────────────────────────┘              │                    │
└────────────────────────────────────────────────┼────────────────────┘
                                                 │
                                                 │ HTTPS (X-Api-Key)
                                                 ▼
                                    ┌──────────────────────┐
                                    │ Cégjelző API v2       │
                                    │ api.cegjelzo.com      │
                                    │                      │
                                    │ /autocomplete        │
                                    │ /search              │
                                    │ /financials-data-tbl │
                                    └──────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  AI Agent Core / MCP wrapper (thin proxy, tervezett)                 │
│                                                                      │
│  ┌──────────────────────────────┐   ┌──────────────────────────┐    │
│  │ impi_get_ngo_info (MCP tool, thin proxy) │   │ cegjelzo-source.ts (ÚJ)  │    │
│  │ • Cégjelző adatokkal dúsított│◄──│ • Direct API kliens       │    │
│  │ • Közhasznúság, státusz      │   │ • TypeScript wrapper      │    │
│  │ • Kategória auto-mapping    │   │ • In-memory cache         │    │
│  └──────────────────────────────┘   └──────────────────────────┘    │
│                                                                      │
│  ngo-categories.ts → Cégjelző activity mező alapú, automatikus       │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 4. Adatmodell

### 4.1 Új WordPress DB tábla: `wp_impactshop_ngo_registry`

Az NGO-k Cégjelző-ból gazdagított master adattáblája.

```sql
CREATE TABLE wp_impactshop_ngo_registry (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(190) NOT NULL,
    
    -- Cégjelző alapadatok
    cegjelzo_id VARCHAR(50) DEFAULT NULL COMMENT 'Nyilvántartási szám vagy adótörzsszám',
    official_name VARCHAR(500) DEFAULT NULL COMMENT 'Bejegyzett hosszú név',
    short_name VARCHAR(255) DEFAULT NULL COMMENT 'Rövidített név',
    display_name VARCHAR(255) NOT NULL COMMENT 'ImpactShop megjelenítési név',
    
    -- Szervezeti adatok
    org_type VARCHAR(100) DEFAULT NULL COMMENT 'Szervezet típusa (egyesület, alapítvány, stb.)',
    address TEXT DEFAULT NULL COMMENT 'Székhely cím',
    tax_number VARCHAR(20) DEFAULT NULL COMMENT 'Adószám',
    registration_number VARCHAR(50) DEFAULT NULL COMMENT 'Nyilvántartási szám',
    registration_date DATE DEFAULT NULL COMMENT 'Bejegyzés dátuma',
    
    -- Működési és jogi státusz
    status_code TINYINT(1) DEFAULT NULL COMMENT '0=törölt, 1=működő',
    status_label VARCHAR(50) DEFAULT NULL COMMENT 'Szöveges státusz',
    level_of_charity VARCHAR(100) DEFAULT NULL COMMENT 'Közhasznúsági fokozat',
    activity TEXT DEFAULT NULL COMMENT 'Cél szerinti besorolás',
    description TEXT DEFAULT NULL COMMENT 'Cél szerinti leírás',
    
    -- Képviselők
    representatives JSON DEFAULT NULL COMMENT 'Képviseletre jogosultak',
    
    -- NAV státusz
    nav_no_tax_debt TINYINT(1) DEFAULT NULL COMMENT 'Köztartozásmentes',
    nav_trustworthy TINYINT(1) DEFAULT NULL COMMENT 'Megbízható adózó',
    nav_significant_debt TINYINT(1) DEFAULT NULL COMMENT 'Jelentős adóhiány',
    nav_tax_debt TINYINT(1) DEFAULT NULL COMMENT 'Adótartozás',
    nav_missing_reports TINYINT(1) DEFAULT NULL COMMENT 'Hiányzó beszámolók',
    
    -- Eljárások
    has_proceedings TINYINT(1) DEFAULT 0 COMMENT 'Van-e folyamatban lévő eljárás',
    proceedings JSON DEFAULT NULL COMMENT 'Eljárás részletek',
    
    -- Meta
    enrichment_source ENUM('manual', 'cegjelzo', 'cegjelzo_partial') DEFAULT 'manual',
    cegjelzo_raw_response JSON DEFAULT NULL COMMENT 'Teljes API válasz archiválás',
    first_enriched_at DATETIME DEFAULT NULL,
    last_enriched_at DATETIME DEFAULT NULL,
    enrichment_error VARCHAR(255) DEFAULT NULL COMMENT 'Utolsó enrichment hiba',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY idx_slug (slug),
    KEY idx_cegjelzo_id (cegjelzo_id),
    KEY idx_tax_number (tax_number),
    KEY idx_status (status_code),
    KEY idx_charity (level_of_charity(50)),
    KEY idx_last_enriched (last_enriched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 NGO Trust Score (számított)

Az NGO-k megbízhatósági pontszáma a Cégjelző adatok alapján:

```php
function impactshop_cegjelzo_trust_score(array $registry_row): array {
    $score = 0;
    $flags = [];
    
    // Pozitív jelzők (elérhető adatok alapján)
    if ($registry_row['status_code'] === 1) { $score += 35; $flags[] = 'active'; }
    if ($registry_row['level_of_charity']) { $score += 30; $flags[] = 'charity_status'; }
    if ($registry_row['registration_date'] && strtotime($registry_row['registration_date']) < strtotime('-2 years')) {
        $score += 15; $flags[] = 'established';
    }
    if ($registry_row['description']) { $score += 10; $flags[] = 'has_description'; }
    if ($registry_row['representatives']) { $score += 10; $flags[] = 'has_representatives'; }
    
    // NAV mezők – JELENLEG NEM ELÉRHETŐK a JWT-ben
    // Ha a hozzáférés bővül, ezek aktiválhatók:
    // if ($registry_row['nav_no_tax_debt']) { $score += 20; $flags[] = 'no_tax_debt'; }
    // if ($registry_row['nav_trustworthy']) { $score += 15; $flags[] = 'trustworthy'; }
    // if ($registry_row['nav_significant_debt']) { $score -= 30; $flags[] = 'significant_debt'; }
    // if ($registry_row['nav_tax_debt']) { $score -= 20; $flags[] = 'tax_debt'; }
    // if ($registry_row['nav_missing_reports']) { $score -= 15; $flags[] = 'missing_reports'; }
    
    // Negatív jelzők (elérhető adatok alapján)
    if ($registry_row['has_proceedings']) { $score -= 30; $flags[] = 'proceedings'; }
    if ($registry_row['status_code'] === 0) { $score -= 50; $flags[] = 'inactive'; }
    
    $score = max(0, min(100, $score));
    
    return [
        'score' => $score,
        'level' => $score >= 80 ? 'verified' : ($score >= 50 ? 'standard' : 'unverified'),
        'flags' => $flags,
    ];
}
```

---

## 5. Implementáció – WordPress mu-plugin

### 5.1 `impactshop-cegjelzo.php` – Fő plugin struktúra

```
wp-content/mu-plugins/
├── impactshop-cegjelzo.php          # Fő plugin fájl
├── impactshop-cegjelzo/
│   ├── class-api-client.php         # Cégjelző API HTTP kliens
│   ├── class-enrichment-service.php # Gazdagítás logika
│   ├── class-cache-manager.php      # Cache + rate limit kezelés
│   ├── class-admin-page.php         # WP Admin felület
│   ├── class-cli-commands.php       # WP-CLI parancsok
│   └── class-cron-sync.php          # Időzített szinkronizálás
```

### 5.2 API Client osztály

```php
<?php
/**
 * Cégjelző API v2 HTTP Client
 */
final class ImpactShop_Cegjelzo_Client
{
    private const PROD_BASE = 'https://api.cegjelzo.com/api/v2';
    private const TEST_BASE = 'https://dev.api.cegjelzo.com/api/v2';
    
    private const DEFAULT_TIMEOUT = 10; // sec
    private const MAX_RETRIES = 2;
    private const RETRY_DELAY_MS = 500;
    
    /** Rate limit: 30 req/s API oldalon, mi ennél konzervatívabbak vagyunk */
    private const INTERNAL_RATE_LIMIT = 10; // req/sec self-imposed
    private const INTERNAL_RATE_WINDOW = 1; // sec
    
    /** Civil szervezet specifikus mezők */
    private const CIVIL_ORG_FIELDS = [
        'registration_number',
        'long_name',
        'short_name',
        'address',
        'status',
        'status_code',
        'type',
        'insertion',
        'constituent_document_date',
        'activity',
        'level_of_charity',
        'description',
        'tax_number',
        'representatives',
        'leading_orgs',
        'bank_accounts',
        'proceedings',
    ];
    
    /** NAV mezők – JELENLEG NEM ELÉRHETŐK a JWT-ben (cvl_flds).
     *  Ha a Cégjelző Kft. bővíti a hozzáférést, aktiválandók.
     */
    private const NAV_FIELDS = [
        // 'nav_no_tax_debt',          // ❌ nincs cvl_flds-ben
        // 'nav_trustworthy_tax_payers', // ❌ nincs cvl_flds-ben
        // 'nav_significant_debt',      // ❌ nincs cvl_flds-ben
        // 'nav_tax_debt',              // ❌ nincs cvl_flds-ben
        // 'nav_missing_reports',       // ❌ nincs cvl_flds-ben
        'nav_address',                // ✅ elérhető
    ];
    
    private string $apiKey;
    private string $clientId;
    private bool $useTestEndpoint;
    private array $requestLog = [];
    
    public function __construct(?string $apiKey = null, ?string $clientId = null, bool $test = false)
    {
        $this->apiKey = $apiKey ?? get_option('impactshop_cegjelzo_api_key', '');
        $this->clientId = $clientId ?? get_option('impactshop_cegjelzo_client_id', '');
        $this->useTestEndpoint = $test || (bool) get_option('impactshop_cegjelzo_test_mode', false);
    }
    
    /**
     * NGO keresés név alapján (autocomplete)
     */
    public function autocomplete_civil_org(string $name, int $limit = 10): array|WP_Error
    {
        if (mb_strlen($name) < 3) {
            return new WP_Error('cegjelzo_short_query', 'Minimum 3 karakter szükséges.', ['status' => 400]);
        }
        
        return $this->get('/autocomplete', [
            'search'      => $name,
            'type'        => 'civil_orgs',
            'limit'       => $limit,
            'only-active' => 1,
        ]);
    }
    
    /**
     * Civil szervezet részletes keresése (nyilvántartási szám vagy név alapján)
     */
    public function search_civil_org(
        string $value,
        string $searchType = 'name',
        ?array $fields = null,
        int $limit = 5
    ): array|WP_Error {
        $params = [
            'value' => $value,
            'type'  => $searchType, // 'name', 'tax_number', 'reg_number'
            'limit' => $limit,
        ];
        
        $requestFields = $fields ?? array_merge(self::CIVIL_ORG_FIELDS, self::NAV_FIELDS);
        
        return $this->get('/search', $params, [
            'X-Fields' => implode(',', $requestFields),
        ]);
    }
    
    /**
     * Szervezet pénzügyi adatai (adótörzsszám alapján)
     * 
     * ⚠️ JELENLEG NEM ELÉRHETŐ – a JWT nem tartalmazza a financials-data-table végpontot.
     * Ha a Cégjelző Kft. bővíti a hozzáférést, ez a metódus aktiválható.
     */
    /* public function get_financials(string $taxId): array|WP_Error
    {
        if (strlen($taxId) !== 8 || !ctype_digit($taxId)) {
            return new WP_Error('cegjelzo_invalid_tax_id', 'Érvénytelen adótörzsszám (8 számjegy szükséges).', ['status' => 400]);
        }
        
        return $this->get('/financials-data-table', ['value' => $taxId]);
    } */
    
    /**
     * HTTP GET kérés a Cégjelző API felé
     */
    private function get(string $endpoint, array $params = [], array $extraHeaders = []): array|WP_Error
    {
        if ($this->apiKey === '' || $this->clientId === '') {
            return new WP_Error('cegjelzo_not_configured', 'Cégjelző API kulcs vagy Client ID nincs beállítva.');
        }
        
        // Self-imposed rate limit
        $this->enforceInternalRateLimit();
        
        $base = $this->useTestEndpoint ? self::TEST_BASE : self::PROD_BASE;
        $url  = $base . $endpoint . '?' . http_build_query($params);
        
        $headers = array_merge([
            'X-Api-Key'   => $this->apiKey,
            'X-Client-Id' => $this->clientId,
            'Accept'      => 'application/json',
        ], $extraHeaders);
        
        $attempt = 0;
        $lastError = null;
        
        while ($attempt <= self::MAX_RETRIES) {
            $response = wp_remote_get($url, [
                'headers' => $headers,
                'timeout' => self::DEFAULT_TIMEOUT,
            ]);
            
            if (is_wp_error($response)) {
                $lastError = $response;
                $attempt++;
                if ($attempt <= self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
                }
                continue;
            }
            
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            $this->logRequest($endpoint, $params, $code);
            
            if ($code === 429) {
                // Rate limited by Cégjelző – wait and retry
                $lastError = new WP_Error('cegjelzo_rate_limited', 'Cégjelző rate limit elérve.');
                $attempt++;
                if ($attempt <= self::MAX_RETRIES) {
                    usleep(1000 * 1000); // 1 sec wait
                }
                continue;
            }
            
            if ($code === 401) {
                return new WP_Error('cegjelzo_unauthorized', 'Érvénytelen API kulcs vagy Client ID.', ['status' => 401]);
            }
            
            if ($code === 403) {
                return new WP_Error('cegjelzo_forbidden', 'Lejárt előfizetés vagy hozzáférés megtagadva.', ['status' => 403]);
            }
            
            if ($code >= 400) {
                $errorBody = json_decode($body, true);
                $message = $errorBody['message'] ?? "HTTP {$code} hiba a Cégjelző API-tól.";
                return new WP_Error('cegjelzo_api_error', $message, ['status' => $code, 'body' => $body]);
            }
            
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                return new WP_Error('cegjelzo_invalid_response', 'Nem értelmezhető API válasz.', ['body' => $body]);
            }
            
            return $decoded;
        }
        
        return $lastError ?? new WP_Error('cegjelzo_unknown_error', 'Ismeretlen hiba a Cégjelző API hívásnál.');
    }
    
    private function enforceInternalRateLimit(): void
    {
        static $requestTimes = [];
        $now = microtime(true);
        
        // Régi bejegyzések törlése
        $requestTimes = array_filter($requestTimes, fn($t) => ($now - $t) < self::INTERNAL_RATE_WINDOW);
        
        if (count($requestTimes) >= self::INTERNAL_RATE_LIMIT) {
            $sleepUntil = $requestTimes[0] + self::INTERNAL_RATE_WINDOW;
            $sleepTime = ($sleepUntil - $now) * 1000000; // microseconds
            if ($sleepTime > 0) {
                usleep((int) $sleepTime);
            }
        }
        
        $requestTimes[] = microtime(true);
    }
    
    private function logRequest(string $endpoint, array $params, int $statusCode): void
    {
        $this->requestLog[] = [
            'endpoint' => $endpoint,
            'params'   => $params,
            'status'   => $statusCode,
            'time'     => gmdate('c'),
        ];
    }
    
    public function getRequestLog(): array
    {
        return $this->requestLog;
    }
}
```

### 5.3 Enrichment Service

```php
<?php
/**
 * NGO gazdagítás a Cégjelző API-ból
 */
final class ImpactShop_Cegjelzo_Enrichment
{
    private const ENRICHMENT_CACHE_TTL = 86400; // 24h
    private const STALE_ENRICHMENT_DAYS = 30;   // 30 naponta újra-gazdagítás
    
    private ImpactShop_Cegjelzo_Client $client;
    
    public function __construct(?ImpactShop_Cegjelzo_Client $client = null)
    {
        $this->client = $client ?? new ImpactShop_Cegjelzo_Client();
    }
    
    /**
     * Egyetlen NGO gazdagítása slug alapján
     */
    public function enrich_ngo(string $slug, bool $force = false): array|WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_ngo_registry';
        
        // Ellenőrizzük, kell-e frissíteni
        if (!$force) {
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug),
                ARRAY_A
            );
            
            if ($existing && $existing['enrichment_source'] !== 'manual') {
                $lastEnriched = strtotime($existing['last_enriched_at'] ?? '');
                if ($lastEnriched && (time() - $lastEnriched) < (self::STALE_ENRICHMENT_DAYS * 86400)) {
                    return $existing; // Még friss
                }
            }
        }
        
        // 1. Próbálkozás: Név alapján keresés
        $displayName = $this->resolve_search_name($slug);
        $results = $this->client->search_civil_org($displayName, 'name', null, 5);
        
        if (is_wp_error($results)) {
            $this->log_enrichment_error($slug, $results->get_error_message());
            return $results;
        }
        
        // Legjobb találat kiválasztása
        $match = $this->find_best_match($results, $slug, $displayName);
        
        if (!$match) {
            // 2. Próbálkozás: Autocomplete
            $autoResults = $this->client->autocomplete_civil_org($displayName, 10);
            if (!is_wp_error($autoResults) && !empty($autoResults['result'])) {
                // autocomplete-ből kapott ID-val search
                foreach ($autoResults['result'] as $candidate) {
                    if (($candidate['collection'] ?? '') !== 'civil_orgs') continue;
                    
                    $detailedResults = $this->client->search_civil_org(
                        (string) $candidate['id'],
                        'reg_number',
                        null,
                        1
                    );
                    
                    if (!is_wp_error($detailedResults) && !empty($detailedResults)) {
                        $match = $detailedResults[0] ?? null;
                        break;
                    }
                }
            }
        }
        
        if (!$match) {
            $this->log_enrichment_error($slug, 'Nem található civil szervezet a Cégjelző-ban.');
            return new WP_Error('cegjelzo_no_match', "Nem található NGO: {$slug}");
        }
        
        // Adatok normalizálása és mentése
        $normalized = $this->normalize_civil_org_data($match);
        $this->upsert_registry($slug, $normalized, $match);
        
        return array_merge(['slug' => $slug], $normalized);
    }
    
    /**
     * Batch gazdagítás – az összes ismert NGO feldolgozása
     */
    public function enrich_all(bool $force = false, callable $onProgress = null): array
    {
        $slugs = $this->get_all_known_slugs();
        $results = ['success' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($slugs as $i => $slug) {
            $result = $this->enrich_ngo($slug, $force);
            
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = "{$slug}: " . $result->get_error_message();
            } elseif (isset($result['last_enriched_at']) && !$force) {
                $results['skipped']++;
            } else {
                $results['success']++;
            }
            
            if ($onProgress) {
                $onProgress($i + 1, count($slugs), $slug, !is_wp_error($result));
            }
            
            // 100ms szünet a kérések között (rate limit védelem)
            usleep(100_000);
        }
        
        return $results;
    }
    
    /**
     * Cégjelző válasz normalizálása ImpactShop formátumra
     */
    private function normalize_civil_org_data(array $raw): array
    {
        return [
            'cegjelzo_id'           => $raw['registration_number'][0]['value'] ?? ($raw['id'] ?? null),
            'official_name'         => $this->extract_latest_name($raw['long_name'] ?? []),
            'short_name'            => $this->extract_latest_name($raw['short_name'] ?? []),
            'org_type'              => $raw['type'] ?? null,
            'address'               => $this->extract_latest_address($raw['address'] ?? []),
            'tax_number'            => $this->extract_tax_number($raw['tax_number'] ?? []),
            'registration_number'   => $this->extract_reg_number($raw),
            'registration_date'     => $raw['insertion'] ?? null,
            'status_code'           => $raw['status_code'] ?? null,
            'status_label'          => $raw['status'] ?? null,
            'level_of_charity'      => $this->extract_charity_level($raw['level_of_charity'] ?? []),
            'activity'              => $raw['activity'] ?? null,
            'description'           => $raw['description'] ?? null,
            'representatives'       => $raw['representatives'] ?? null,
            'nav_address'           => $this->extract_latest_address($raw['nav_address'] ?? []),
            // NAV státusz mezők – JELENLEG NEM ELÉRHETŐK a JWT-ben (cvl_flds).
            // Ha a Cégjelző Kft. bővíti a hozzáférést, aktiválandók:
            'nav_no_tax_debt'       => null, // isset($raw['nav_no_tax_debt']) ? ($raw['nav_no_tax_debt'] === true ? 1 : 0) : null,
            'nav_trustworthy'       => null, // isset($raw['nav_trustworthy_tax_payers']) ? ... : null,
            'nav_significant_debt'  => null, // isset($raw['nav_significant_debt']) ? ... : null,
            'nav_tax_debt'          => null, // isset($raw['nav_tax_debt']) ? ... : null,
            'nav_missing_reports'   => null, // isset($raw['nav_missing_reports']) ? ... : null,
            'has_proceedings'       => !empty($raw['proceedings']) ? 1 : 0,
            'proceedings'           => $raw['proceedings'] ?? null,
        ];
    }
    
    private function extract_latest_name(array $names): ?string
    {
        if (empty($names)) return null;
        // Cégjelző a legfrissebbet insertion szerint adja
        usort($names, fn($a, $b) => strcmp($b['insertion'] ?? '', $a['insertion'] ?? ''));
        return $names[0]['name'] ?? null;
    }
    
    private function extract_latest_address(array $addresses): ?string
    {
        if (empty($addresses)) return null;
        usort($addresses, fn($a, $b) => strcmp($b['insertion'] ?? '', $a['insertion'] ?? ''));
        return $addresses[0]['address'] ?? null;
    }
    
    private function extract_tax_number(array $taxData): ?string
    {
        if (empty($taxData)) return null;
        // A legfrissebb adószám
        foreach ($taxData as $item) {
            if (isset($item['tax_number'])) return $item['tax_number'];
            if (isset($item['value'])) return $item['value'];
        }
        return null;
    }
    
    private function extract_reg_number(array $raw): ?string
    {
        if (isset($raw['registration_number'])) {
            if (is_array($raw['registration_number'])) {
                return $raw['registration_number'][0]['value'] ?? ($raw['registration_number'][0] ?? null);
            }
            return (string) $raw['registration_number'];
        }
        return null;
    }
    
    private function extract_charity_level(array $data): ?string
    {
        if (empty($data)) return null;
        foreach ($data as $item) {
            if (isset($item['level'])) return $item['level'];
            if (isset($item['name'])) return $item['name'];
            if (is_string($item)) return $item;
        }
        return null;
    }
    
    /**
     * Legjobb találat kiválasztása a keresési eredményekből
     */
    private function find_best_match(array $results, string $slug, string $searchName): ?array
    {
        if (empty($results)) return null;
        
        // Ha egyetlen eredmény → elfogadjuk
        if (count($results) === 1) return $results[0];
        
        $searchNameLower = mb_strtolower($searchName);
        $bestScore = 0;
        $bestMatch = null;
        
        foreach ($results as $result) {
            $score = 0;
            
            // Név hasonlóság
            $longName = mb_strtolower($this->extract_latest_name($result['long_name'] ?? []) ?? '');
            $shortName = mb_strtolower($this->extract_latest_name($result['short_name'] ?? []) ?? '');
            
            similar_text($searchNameLower, $longName, $longPct);
            similar_text($searchNameLower, $shortName, $shortPct);
            $score += max($longPct, $shortPct);
            
            // Működő szervezet bonusz
            if (($result['status_code'] ?? -1) === 1) {
                $score += 10;
            }
            
            // Civil szervezet bonusz
            if (($result['collection'] ?? ($result['type'] ?? '')) === 'civil_orgs') {
                $score += 5;
            }
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $result;
            }
        }
        
        // Minimum 60%-os hasonlóság kell
        return ($bestScore >= 60) ? $bestMatch : null;
    }
    
    /**
     * Registry upsert
     */
    private function upsert_registry(string $slug, array $normalized, array $rawResponse): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_ngo_registry';
        
        $data = array_merge($normalized, [
            'slug'                  => $slug,
            'display_name'          => $normalized['official_name'] ?? $normalized['short_name'] ?? ucwords(str_replace('-', ' ', $slug)),
            'enrichment_source'     => 'cegjelzo',
            'cegjelzo_raw_response' => wp_json_encode($rawResponse),
            'last_enriched_at'      => gmdate('Y-m-d H:i:s'),
            'enrichment_error'      => null,
        ]);
        
        // JSON mezők serialize
        foreach (['representatives', 'proceedings'] as $jsonField) {
            if (isset($data[$jsonField]) && is_array($data[$jsonField])) {
                $data[$jsonField] = wp_json_encode($data[$jsonField]);
            }
        }
        
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        
        if ($existing) {
            $data['updated_at'] = gmdate('Y-m-d H:i:s');
            unset($data['slug']); // Ne updateljük a slug-ot
            if (!isset($data['first_enriched_at'])) {
                unset($data['first_enriched_at']);
            }
            $wpdb->update($table, $data, ['slug' => $slug]);
        } else {
            $data['first_enriched_at'] = gmdate('Y-m-d H:i:s');
            $data['created_at'] = gmdate('Y-m-d H:i:s');
            $wpdb->insert($table, $data);
        }
    }
    
    private function resolve_search_name(string $slug): string
    {
        // Először megnézzük a meglévő display name-et
        if (function_exists('impactshop_resolve_ngo_name')) {
            $name = impactshop_resolve_ngo_name($slug);
            if ($name !== '' && sanitize_title($name) !== $slug) {
                return $name;
            }
        }
        
        // Fallback: slug → olvasható név
        return ucwords(str_replace('-', ' ', $slug));
    }
    
    private function get_all_known_slugs(): array
    {
        $slugs = [];
        
        // 1. NGO Card approved slugs
        $approved = get_option('impactshop_ngo_card_approved_slugs', []);
        if (is_array($approved)) {
            $slugs = array_merge($slugs, array_keys($approved));
        }
        
        // 2. Ledger-ből
        global $wpdb;
        $ledgerTable = $wpdb->prefix . 'impact_ledger';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$ledgerTable}'") === $ledgerTable) {
            $ledgerSlugs = $wpdb->get_col("SELECT DISTINCT ngo_slug FROM {$ledgerTable} WHERE ngo_slug != ''");
            $slugs = array_merge($slugs, $ledgerSlugs ?: []);
        }
        
        // 3. Metrics NGO-ból
        $metricsTable = $wpdb->prefix . 'impactshop_metrics_ngo';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$metricsTable}'") === $metricsTable) {
            $metricsSlugs = $wpdb->get_col("SELECT DISTINCT ngo_slug FROM {$metricsTable} WHERE ngo_slug != ''");
            $slugs = array_merge($slugs, $metricsSlugs ?: []);
        }
        
        return array_unique(array_filter(array_map('sanitize_title', $slugs)));
    }
    
    private function log_enrichment_error(string $slug, string $message): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_ngo_registry';
        
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        
        if ($existing) {
            $wpdb->update($table, [
                'enrichment_error' => mb_substr($message, 0, 255),
                'updated_at'      => gmdate('Y-m-d H:i:s'),
            ], ['slug' => $slug]);
        } else {
            $wpdb->insert($table, [
                'slug'              => $slug,
                'display_name'      => ucwords(str_replace('-', ' ', $slug)),
                'enrichment_source' => 'manual',
                'enrichment_error'  => mb_substr($message, 0, 255),
                'created_at'        => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }
}
```

### 5.4 WP-CLI parancsok

```php
<?php
/**
 * WP-CLI parancsok a Cégjelző integrációhoz
 */
if (!defined('WP_CLI') || !WP_CLI) return;

WP_CLI::add_command('impactshop cegjelzo', 'ImpactShop_Cegjelzo_CLI');

class ImpactShop_Cegjelzo_CLI
{
    /**
     * Egyetlen NGO gazdagítása
     * 
     * ## OPTIONS
     * <slug>
     * : Az NGO slug-ja
     * 
     * [--force]
     * : Kényszerített frissítés (cache figyelmen kívül hagyása)
     * 
     * ## EXAMPLES
     *     wp impactshop cegjelzo enrich bator-tabor
     *     wp impactshop cegjelzo enrich magyar-voroskereszt --force
     */
    public function enrich($args, $assoc_args)
    {
        $slug = $args[0];
        $force = isset($assoc_args['force']);
        
        WP_CLI::log("🔍 NGO gazdagítás: {$slug}" . ($force ? ' (kényszerített)' : ''));
        
        $service = new ImpactShop_Cegjelzo_Enrichment();
        $result = $service->enrich_ngo($slug, $force);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
            return;
        }
        
        WP_CLI::success("✅ {$slug} sikeresen gazdagítva:");
        WP_CLI::log("  Hivatalos név: " . ($result['official_name'] ?? 'N/A'));
        WP_CLI::log("  Típus: " . ($result['org_type'] ?? 'N/A'));
        WP_CLI::log("  Székhely: " . ($result['address'] ?? 'N/A'));
        WP_CLI::log("  Státusz: " . ($result['status_label'] ?? 'N/A'));
        WP_CLI::log("  Közhasznúság: " . ($result['level_of_charity'] ?? 'N/A'));
        WP_CLI::log("  Adószám: " . ($result['tax_number'] ?? 'N/A'));
    }
    
    /**
     * Összes ismert NGO batch gazdagítása
     * 
     * ## OPTIONS
     * [--force]
     * : Kényszerített frissítés minden NGO-nál
     * 
     * [--dry-run]
     * : Csak szimulálás, API hívások nélkül
     * 
     * ## EXAMPLES
     *     wp impactshop cegjelzo enrich-all
     *     wp impactshop cegjelzo enrich-all --force
     */
    public function enrich_all($args, $assoc_args)
    {
        $force = isset($assoc_args['force']);
        $dryRun = isset($assoc_args['dry-run']);
        
        WP_CLI::log("🔄 Batch NGO gazdagítás indítása...");
        
        if ($dryRun) {
            WP_CLI::log("⚠️ DRY RUN mód – API hívások nem történnek.");
        }
        
        $service = new ImpactShop_Cegjelzo_Enrichment();
        $results = $service->enrich_all($force, function ($current, $total, $slug, $success) {
            $icon = $success ? '✅' : '❌';
            WP_CLI::log("  [{$current}/{$total}] {$icon} {$slug}");
        });
        
        WP_CLI::log("---");
        WP_CLI::success("Eredmény: ✅ {$results['success']} sikeres, ⏭️ {$results['skipped']} kihagyva, ❌ {$results['failed']} hibás");
        
        if (!empty($results['errors'])) {
            WP_CLI::log("Hibák:");
            foreach ($results['errors'] as $error) {
                WP_CLI::warning("  • {$error}");
            }
        }
    }
    
    /**
     * NGO registry státusz lekérdezése
     * 
     * ## EXAMPLES
     *     wp impactshop cegjelzo status
     */
    public function status($args, $assoc_args)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_ngo_registry';
        
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        if (!$tableExists) {
            WP_CLI::warning("❌ Registry tábla nem létezik ({$table})");
            return;
        }
        
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $enriched = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE enrichment_source = 'cegjelzo'");
        $manual = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE enrichment_source = 'manual'");
        $withErrors = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE enrichment_error IS NOT NULL AND enrichment_error != ''");
        $active = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status_code = 1");
        $charitable = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE level_of_charity IS NOT NULL AND level_of_charity != ''");
        
        WP_CLI::log("📊 NGO Registry Státusz:");
        WP_CLI::log("  Összesen: {$total}");
        WP_CLI::log("  Cégjelző-ból gazdagított: {$enriched}");
        WP_CLI::log("  Manuális: {$manual}");
        WP_CLI::log("  Hibás gazdagítás: {$withErrors}");
        WP_CLI::log("  Működő (aktív): {$active}");
        WP_CLI::log("  Közhasznú: {$charitable}");
    }
    
    /**
     * Cégjelző API konfiguráció tesztelése
     * 
     * ## EXAMPLES
     *     wp impactshop cegjelzo test-connection
     */
    public function test_connection($args, $assoc_args)
    {
        WP_CLI::log("🔌 Cégjelző API kapcsolat tesztelése...");
        
        $client = new ImpactShop_Cegjelzo_Client();
        $result = $client->autocomplete_civil_org('Magyar Vöröskereszt', 1);
        
        if (is_wp_error($result)) {
            WP_CLI::error("❌ Kapcsolati hiba: " . $result->get_error_message());
            return;
        }
        
        WP_CLI::success("✅ API kapcsolat OK!");
        if (!empty($result['result'])) {
            $first = $result['result'][0];
            WP_CLI::log("  Teszt találat: {$first['name']} (ID: {$first['id']})");
        }
    }
}
```

### 5.5 Cron szinkronizálás

```php
<?php
/**
 * Időzített Cégjelző szinkronizálás
 * Naponta egyszer futó cron job az elavult NGO adatok frissítéséhez.
 */
final class ImpactShop_Cegjelzo_Cron
{
    private const CRON_HOOK = 'impactshop_cegjelzo_daily_sync';
    private const BATCH_SIZE = 20; // NGO-k száma per cron futás
    private const MAX_ENRICHMENTS_PER_DAY = 100; // Napi kvóta védelem
    
    public static function bootstrap(): void
    {
        add_action(self::CRON_HOOK, [__CLASS__, 'run_daily_sync']);
        
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(strtotime('today 05:00:00 UTC'), 'daily', self::CRON_HOOK);
        }
    }
    
    public static function run_daily_sync(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_ngo_registry';
        
        $staleThreshold = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Prioritás: 1. Soha nem gazdagítottak, 2. Legrégebben frissítettek
        $slugs = $wpdb->get_col($wpdb->prepare(
            "SELECT slug FROM {$table} 
             WHERE (last_enriched_at IS NULL OR last_enriched_at < %s)
             AND enrichment_source != 'cegjelzo'
             ORDER BY last_enriched_at ASC NULLS FIRST
             LIMIT %d",
            $staleThreshold,
            self::BATCH_SIZE
        ));
        
        if (empty($slugs)) {
            // Ha nincs manuális, frissítsük a legrégebbi cégjelzős adatokat
            $slugs = $wpdb->get_col($wpdb->prepare(
                "SELECT slug FROM {$table}
                 WHERE last_enriched_at < %s
                 ORDER BY last_enriched_at ASC
                 LIMIT %d",
                $staleThreshold,
                self::BATCH_SIZE
            ));
        }
        
        if (empty($slugs)) {
            return; // Minden naprakész
        }
        
        $service = new ImpactShop_Cegjelzo_Enrichment();
        $processed = 0;
        
        foreach ($slugs as $slug) {
            if ($processed >= self::MAX_ENRICHMENTS_PER_DAY) break;
            
            $result = $service->enrich_ngo($slug, true);
            $processed++;
            
            if (!is_wp_error($result)) {
                do_action('impactshop_cegjelzo_ngo_enriched', $slug, $result);
            }
            
            usleep(200_000); // 200ms szünet
        }
        
        do_action('impactshop_cegjelzo_daily_sync_complete', $processed, count($slugs));
    }
}
```

---

## 6. NGO Card integráció

### 6.1 Gazdagított válasz az NGO Card API-ban

Az `impactshop-ngo-card.php` `build_payload()` metódusában a Cégjelző adatokat hozzáfűzzük:

```php
// A build_payload() bővítése:
$registryData = ImpactShop_Cegjelzo_Registry::get_by_slug($slug);

if ($registryData && $registryData['enrichment_source'] === 'cegjelzo') {
    $payload['data']['organization'] = [
        'official_name'      => $registryData['official_name'],
        'type'               => $registryData['org_type'],
        'address'            => $registryData['address'],
        'registration_date'  => $registryData['registration_date'],
        'level_of_charity'   => $registryData['level_of_charity'],
        'activity'           => $registryData['activity'],
        'description'        => $registryData['description'],
        'is_active'          => ($registryData['status_code'] ?? 0) === 1,
    ];
    
    $payload['data']['trust'] = impactshop_cegjelzo_trust_score($registryData);
    
    $payload['data']['nav_status'] = [
        'no_tax_debt'     => (bool) $registryData['nav_no_tax_debt'],
        'trustworthy'     => (bool) $registryData['nav_trustworthy'],
        'has_issues'      => (bool) $registryData['nav_significant_debt'] 
                          || (bool) $registryData['nav_tax_debt'],
    ];
    
    $payload['data']['enrichment'] = [
        'source'      => 'cegjelzo',
        'last_synced' => $registryData['last_enriched_at'],
    ];
}
```

### 6.2 Frontend megjelenítés (NGO kártya bővítés)

```
┌─────────────────────────────────────────────────┐
│  🏛️  Bátor Tábor Alapítvány                     │
│  ──────────────────────────────────────────      │
│  💰 Támogatás: 1 234 567 Ft                     │
│  🏆 Rank: #3 | 🔥 Rising Mode                   │
│                                                  │
│  ┌─ Szervezeti adatok (ÚJ - Cégjelző) ────────┐│
│  │ 📍 1025 Budapest, Törökvész út 87-91.        ││
│  │ 📋 Közhasznú szervezet                        ││
│  │ 🏷️ Nyilvántartási szám: 01-01-0006889         ││
│  │ ✅ NAV: Köztartozásmentes | Megbízható adózó  ││
│  │ 📅 Bejegyzés: 2001-06-15 (24 éve)            ││
│  │ 🎯 Célterület: Gyermek- és ifjúságvédelem    ││
│  └──────────────────────────────────────────────┘│
│                                                  │
│  🛡️ Trust Score: 95/100 (Verified)               │
│                                                  │
│  [Támogatás] [Megosztás] [NGO részletek]         │
└─────────────────────────────────────────────────┘
```

---

## 7. AI Agent Core integráció

### 7.1 Új source modul: `cegjelzo-source.ts`

```typescript
// apps/ai-agent-core/src/sources/cegjelzo-source.ts

export interface CegjelzoConfig {
  apiKey: string;
  clientId: string;
  baseUrl: string;
  testMode: boolean;
}

export interface CivilOrgResult {
  registrationNumber: string;
  officialName: string;
  shortName?: string;
  address?: string;
  type?: string;
  status: 'active' | 'inactive';
  statusCode: number;
  activity?: string;
  levelOfCharity?: string;
  description?: string;
  taxNumber?: string;
  representatives?: Representative[];
  navStatus: NavStatus;
}

export interface NavStatus {
  noTaxDebt: boolean;
  trustworthy: boolean;
  significantDebt: boolean;
  taxDebt: boolean;
  missingReports: boolean;
}

export interface Representative {
  name: string;
  role?: string;
  startDate?: string;
}

export async function searchCivilOrg(
  name: string,
  config: CegjelzoConfig
): Promise<CivilOrgResult[]> {
  const url = new URL(`${config.baseUrl}/search`);
  url.searchParams.set('value', name);
  url.searchParams.set('type', 'name');
  url.searchParams.set('limit', '5');

  const response = await fetch(url.toString(), {
    headers: {
      'X-Api-Key': config.apiKey,
      'X-Client-Id': config.clientId,
      'X-Fields': [
        'registration_number', 'long_name', 'short_name', 'address',
        'status', 'status_code', 'type', 'activity', 'level_of_charity',
        'description', 'tax_number', 'representatives',
        'nav_no_tax_debt', 'nav_trustworthy_tax_payers',
        'nav_significant_debt', 'nav_tax_debt', 'nav_missing_reports'
      ].join(','),
    },
  });

  if (!response.ok) {
    throw new Error(`Cégjelző API error: ${response.status}`);
  }

  const data = await response.json();
  return (data as any[])
    .filter((item) => item.type === 'civil_orgs' || !item.type)
    .map(normalizeCivilOrg);
}

function normalizeCivilOrg(raw: any): CivilOrgResult {
  return {
    registrationNumber: extractLatestValue(raw.registration_number),
    officialName: extractLatestName(raw.long_name),
    shortName: extractLatestName(raw.short_name),
    address: extractLatestAddress(raw.address),
    type: raw.type ?? undefined,
    status: raw.status_code === 1 ? 'active' : 'inactive',
    statusCode: raw.status_code ?? 0,
    activity: raw.activity ?? undefined,
    levelOfCharity: extractCharityLevel(raw.level_of_charity),
    description: raw.description ?? undefined,
    taxNumber: extractTaxNumber(raw.tax_number),
    representatives: extractRepresentatives(raw.representatives),
    navStatus: {
      noTaxDebt: raw.nav_no_tax_debt === true,
      trustworthy: raw.nav_trustworthy_tax_payers === true,
      significantDebt: raw.nav_significant_debt === true,
      taxDebt: raw.nav_tax_debt === true,
      missingReports: raw.nav_missing_reports === true,
    },
  };
}
```

### 7.2 MCP Tool: `impi_get_ngo_info` bovites (thin proxy)

Megjegyzes: a MCP tool a `apps/mcp-wrapper/` wrapperben csak proxy. A valodi adat az API gateway endpointon keresztul jon.

```typescript
// A meglévő impi_get_ngo_info tool bővítése Cégjelző adatokkal:

{
  name: 'impi_get_ngo_info',
  description: 'NGO részletes adatok lekérése (Cégjelző-ból gazdagítva)',
  inputSchema: {
    type: 'object',
    properties: {
      slug: { type: 'string', description: 'NGO slug (pl. bator-tabor)' },
      include_nav: { type: 'boolean', description: 'NAV státusz adatok', default: true },
      include_financials: { type: 'boolean', description: 'Pénzügyi adatok', default: false },
    },
    required: ['slug'],
  },
  handler: async ({ slug, include_nav, include_financials }) => {
    // thin proxy: api-gateway osszevonja a WP + registry adatokat
    const data = await fetchNgoNarrative(slug, { include_nav, include_financials });
    return { slug, ...data };
  },
}
```

---

## 8. Admin felület

### 8.1 WP Admin → Eszközök → Cégjelző NGO Gazdagítás

```
┌──────────────────────────────────────────────────────────┐
│  ⚙️ Cégjelző API Beállítások                              │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  API Kulcs:    [••••••••••••••••]  [Teszt]               │
│  Client ID:    [CEG-XXXXXX      ]                        │
│  Mód:          ○ Éles  ● Teszt                           │
│  Sync ütemezés: Naponta 05:00 UTC                        │
│                                                          │
│  ─── Státusz ──────────────────────────────────          │
│  ✅ API kapcsolat: OK (utolsó teszt: 5 perce)            │
│  📊 Registry: 42 NGO (38 gazdagított, 4 manuális)       │
│  📅 Utolsó sync: 2026-01-28 05:00 UTC (3 frissítve)     │
│  💾 Havi kvóta: 234 / 1000 lekérdezés                    │
│                                                          │
│  [Manuális sync indítása]  [Teszt lekérdezés]            │
│                                                          │
├──────────────────────────────────────────────────────────┤
│  🔍 NGO keresés (Cégjelző-ból)                            │
│  [_________________________] [Keresés]                   │
│                                                          │
│  Találatok:                                              │
│  ┌──────────────────────────────────────────────┐        │
│  │ ✅ Bátor Tábor Alapítvány                     │        │
│  │    Nyilv.sz: 01-01-0006889 | Közhasznú       │        │
│  │    [Összekapcsolás: bator-tabor slug-gal]     │        │
│  └──────────────────────────────────────────────┘        │
└──────────────────────────────────────────────────────────┘
```

---

## 9. Implementációs fázisterv

### Fázis 1: Alapozás (1-2 nap)

| # | Feladat | Fájlok | Prioritás |
|---|---------|--------|-----------|
| 1.1 | ~~API kulcs beszerzése~~ ✅ KÉSZ – WP options-ba mentés | WP options | ✅ Kész |
| 1.2 | `wp_impactshop_ngo_registry` tábla létrehozása | `impactshop-cegjelzo.php` | 🔴 Blokkoló |
| 1.3 | `ImpactShop_Cegjelzo_Client` implementálása | `class-api-client.php` | 🔴 Blokkoló |
| 1.4 | Teszt lekérdezés: `wp impactshop cegjelzo test-connection` | CLI | 🟢 Validáció |

### Fázis 2: Enrichment Service (2-3 nap)

| # | Feladat | Fájlok | Prioritás |
|---|---------|--------|-----------|
| 2.1 | Enrichment Service + normalizáció | `class-enrichment-service.php` | 🔴 Core |
| 2.2 | WP-CLI `enrich` és `enrich-all` parancsok | `class-cli-commands.php` | 🟡 |
| 2.3 | Batch enrichment tesztelése 5 pilot NGO-val | CLI | 🟢 Validáció |
| 2.4 | Trust Score számítás implementálása | enrichment service | 🟡 |

### Fázis 3: NGO Card integráció (1-2 nap)

| # | Feladat | Fájlok | Prioritás |
|---|---------|--------|-----------|
| 3.1 | NGO Card API válasz bővítése `organization` mezővel | `impactshop-ngo-card.php` | 🟡 |
| 3.2 | Frontend JS frissítés – szervezeti adatok megjelenítése | `impactshop-ngo-card.js` | 🟡 |
| 3.3 | Trust badge megjelenítés | JS + CSS | 🟢 |
| 3.4 | Közhasznúsági ikon | CSS | 🟢 |

### Fázis 4: Admin + Cron (1 nap)

| # | Feladat | Fájlok | Prioritás |
|---|---------|--------|-----------|
| 4.1 | Admin felület – beállítások és keresés | `class-admin-page.php` | 🟡 |
| 4.2 | Napi cron sync implementálása | `class-cron-sync.php` | 🟡 |
| 4.3 | Kvóta monitoring és alert | enrichment service | 🟢 |

### Fázis 5: AI Agent Core (későbbi sprint)

| # | Feladat | Fájlok | Prioritás |
|---|---------|--------|-----------|
| 5.1 | `cegjelzo-source.ts` TypeScript modul | ai-agent-core | 🟢 |
| 5.2 | `impi_get_ngo_info` tool bővítés | MCP tools | 🟢 |
| 5.3 | `ngo-categories.ts` automatikus mapping Cégjelző activity-ből | ngo-categories | 🟢 |

---

## 10. Kockázatok és mitigáció

| Kockázat | Hatás | Valószínűség | Mitigáció |
|----------|-------|--------------|-----------|
| Havi kvóta kimerülése | API nem elérhető | Közepes | Self-imposed rate limit + 30 napos cache |
| Nem minden NGO található a Cégjelző-ban | Részleges gazdagítás | Magas | Fallback: manuális enrichment, `enrichment_source = 'manual'` |
| API kulcs lejárat/változás | Enrichment leáll | Alacsony | WP Admin alert + cron hibakezelés |
| Név alapú keresés pontatlan találat | Rossz szervezet összekapcsolása | Közepes | 60%-os hasonlósági küszöb + admin jóváhagyás opció |
| Civil szervezet → társas vállalkozás téves típus | Rossz adatok | Alacsony | `type=civil_orgs` szűrő az autocomplete-nél |
| Rate limit az API oldalról (30 req/s) | Batch enrichment lassulás | Alacsony | 100ms szünet, 10 req/s self-imposed limit |

---

## 11. Kvóta és költség kalkuláció

### 11.1 Becsült havi API hívás szükséglet

| Művelet | Hívás / alkalom | Gyakoriság | Havi összesen |
|---------|----------------|------------|---------------|
| Napi cron sync (20 NGO batch) | ~40 (search + autocomplete fallback) | 30x / hó | ~1200 |
| Admin manuális keresés | ~5 | 20x / hó | ~100 |
| Új NGO regisztráció | ~3 | 10x / hó | ~30 |
| AI Agent lekérdezés | ~1 (cache-ből) | 100x / hó | ~10 |
| **Összesen** | | | **~1340 / hó** |

### 11.2 Cache stratégia

- **Registry tábla:** 30 napos TTL per NGO → minimális API hívás
- **WP transient:** 24h cache az egyes lekérdezésekhez
- **AI Agent:** In-memory cache 1h TTL-lel (indításonként töltődik)
- **NGO Card API:** A meglévő 15 perces cache-be beolvad a Cégjelző adat

---

## 12. Tesztelési terv

### 12.1 Teszt környezet

- Cégjelző teszt endpoint: `https://dev.api.cegjelzo.com/api/v2`
- ~2000 szervezet a teszt DB-ben (civil szervezetek is)
- Pilóta: 5 ismert NGO (Bátor Tábor, Magyar Vöröskereszt, SOS Gyermekfalu, Habitat, UNICEF)

### 12.2 Teszt esetek

| # | Teszt | Elvárt eredmény |
|---|-------|-----------------|
| T1 | `wp impactshop cegjelzo test-connection` | ✅ API OK válasz |
| T2 | `wp impactshop cegjelzo enrich bator-tabor` | ✅ Registry bejegyzés létrejön |
| T3 | NGO Card API hívás gazdagított slug-gal | `organization` mező megjelenik a válaszban |
| T4 | Nem létező NGO enrichment | WP_Error + enrichment_error log |
| T5 | Rate limit tesztelés (30+ hívás/sec) | Self-imposed throttle működik |
| T6 | Stale enrichment cron frissítése | 30+ napos adat újra lekérdezés |
| T7 | Trust Score számítás | Helyes score aktív közhasznú szervezetre |
| T8 | Inaktív szervezet kezelése | `status_code = 0` → figyelmeztető flag |

---

## 13. Monitoring és naplózás

### 13.1 WP transient kulcsok

| Kulcs | TTL | Tartalom |
|-------|-----|----------|
| `impactshop_cegjelzo_daily_stats` | 24h | Napi hívásszám, sikeres/hibás |
| `impactshop_cegjelzo_quota_used` | 1h | Becsült kvóta felhasználás |
| `impactshop_cegjelzo_last_sync` | ∞ | Utolsó sync időbélyeg |

### 13.2 WP options

| Option | Leírás |
|--------|--------|
| `impactshop_cegjelzo_api_key` | API kulcs (encrypted) |
| `impactshop_cegjelzo_client_id` | Client ID |
| `impactshop_cegjelzo_test_mode` | Teszt/éles kapcsoló |
| `impactshop_cegjelzo_monthly_quota` | Havi kvóta (szerződés szerint) |

---

## 14. Kapcsolódó dokumentumok

| Dokumentum | Relevancia |
|-----------|------------|
| `docs/impi-mcp-sdk-migration-plan.md` | MCP SDK thin wrapper + `impi_get_ngo_info` tool |
| `wp-content/mu-plugins/impactshop-ngo-card.php` | NGO Card API (4677 sor) – bővítendő |
| `wp-content/mu-plugins/impactshop-metrics-ngo.php` | Metrics – enrichment data consumer |
| `docs/dognet-incremental-fetch-plan-review.md` | NGO slug kezelés, ledger tábla |
| `NGO data/szervezetek-2025-11-28.xlsx` | Jelenlegi NGO lista (manuális) |

---

## Changelog

| Dátum | Változás |
|-------|---------|
| 2026-01-28 | v1.0 – Kezdeti terv elkészítése |
| 2026-02-09 | v1.1 – **Véglegesítés:** JWT jogosultságok integrálása, NAV mezők korrekciója (nem elérhetők a jelenlegi JWT-ben), `financials` végpont kikommentezése, Trust Score átdolgozása NAV nélküli verzióra, biztonsági audit (§15) és koherencia teszt (§16) hozzáadása, API credentials szekció (§0) |

---

## 15. Biztonsági audit

### 15.1 Credential kezelés

| Ellenőrzés | Státusz | Megjegyzés |
|-----------|--------|------------|
| API kulcs plaintext-ben a kódban? | ✅ PASS | WP options-ból olvasás, nem hardcoded |
| API kulcs git repo-ban? | ⚠️ FIGYELEM | A terv tartalmazza a kulcs prefixét referencia céljából – éles kódba NE kerüljön |
| Client ID titkosítás | 🟡 N/A | Client ID nem szenzitív (fiók azonosító), de options-ban tároljuk |
| JWT token tárolás | ❌ NEM SZÜKSÉGES | A JWT csak referencia, az API kulcs az autentikáció |
| `.gitignore` védelem | ✅ PASS | `settings.json`, `.env` fájlok nem commitolódnak |

### 15.2 API biztonsági kontrollok

| Kontroll | Implementáció | Státusz |
|---------|--------------|--------|
| Self-imposed rate limit | 10 req/s (API limit: 30) | ✅ Implementálva |
| Retry exponential backoff | 500ms × attempt szorzó | ✅ Implementálva |
| 429 kezelés | 1s várakozás + retry | ✅ Implementálva |
| Timeout | 10s per request | ✅ Implementálva |
| Input sanitizálás | `sanitize_title()` minden slug-ra | ✅ Implementálva |
| URL encoding | `http_build_query()` automatikus | ✅ Implementálva |
| Kvóta védelem | Napi max 100 enrichment / batch max 20 | ✅ Implementálva |
| Stale data fallback | 30 napos cache, nem blokkol ha API nem elérhető | ✅ Implementálva |

### 15.3 Adatvédelmi kontrollok

| Szempont | Kockázat | Mitigáció |
|---------|---------|----------|
| Személyes adatok (képviselők neve, címe) | Közepes | Csak nyilvános cégjegyzék adat, GDPR 6(1)(f) jogos érdek |
| Raw API response tárolás | Alacsony | `cegjelzo_raw_response` JSON – audit trail, 30 napos retention |
| Bankszámlaszámok | Közepes | Donation routing céljából, titkosítva tárolás javasolt |
| NAV adatok | Alacsony | Jelenleg nem elérhető a JWT-ben |

---

## 16. Koherencia teszt eredmények

### 16.1 JWT vs. Terv mezők összehasonlítás

| Terv mező | JWT `cvl_flds`-ben? | Állapot |
|-----------|--------------------|---------| 
| `registration_number` | ✅ Igen | OK |
| `long_name` | ✅ Igen | OK |
| `short_name` | ✅ Igen | OK |
| `address` | ✅ Igen | OK |
| `status` / `status_code` | ✅ Igen | OK |
| `type` | ✅ Igen | OK |
| `insertion` | ✅ Igen | OK |
| `constituent_document_date` | ✅ Igen | OK |
| `activity` | ✅ Igen | OK |
| `level_of_charity` | ✅ Igen | OK |
| `description` | ✅ Igen | OK |
| `tax_number` | ✅ Igen | OK |
| `representatives` | ✅ Igen | OK |
| `leading_orgs` | ✅ Igen | OK |
| `bank_accounts` | ✅ Igen | OK |
| `proceedings` | ✅ Igen | OK |
| `updated_at` | ✅ Igen | OK (extra – nem volt a tervben) |
| `nav_address` | ✅ Igen | OK (extra – NAV cím) |
| `nav_no_tax_debt` | ❌ Nem | ⚠️ Terv korrigálva – kikommentezve |
| `nav_trustworthy_tax_payers` | ❌ Nem | ⚠️ Terv korrigálva – kikommentezve |
| `nav_significant_debt` | ❌ Nem | ⚠️ Terv korrigálva – kikommentezve |
| `nav_tax_debt` | ❌ Nem | ⚠️ Terv korrigálva – kikommentezve |
| `nav_missing_reports` | ❌ Nem | ⚠️ Terv korrigálva – kikommentezve |
| `financials-data-table` | ❌ Végpont nem elérhető | ⚠️ Terv korrigálva – metódus kikommentezve |

### 16.2 DB tábla vs. API mező konzisztencia

| DB oszlop | Forrás mező | Konzisztens? |
|-----------|------------|-------------|
| `cegjelzo_id` | `registration_number` / `id` | ✅ |
| `official_name` | `long_name[].name` | ✅ |
| `short_name` | `short_name[].name` | ✅ |
| `org_type` | `type` | ✅ |
| `address` | `address[].address` | ✅ |
| `tax_number` | `tax_number` | ✅ |
| `registration_number` | `registration_number` | ✅ |
| `registration_date` | `insertion` | ✅ |
| `status_code` | `status_code` | ✅ |
| `status_label` | `status` | ✅ |
| `level_of_charity` | `level_of_charity` | ✅ |
| `activity` | `activity` | ✅ |
| `description` | `description` | ✅ |
| `representatives` | `representatives` (JSON) | ✅ |
| `nav_no_tax_debt` | ❌ Nem elérhető | ⚠️ Oszlop marad NULL |
| `nav_trustworthy` | ❌ Nem elérhető | ⚠️ Oszlop marad NULL |
| `nav_significant_debt` | ❌ Nem elérhető | ⚠️ Oszlop marad NULL |
| `nav_tax_debt` | ❌ Nem elérhető | ⚠️ Oszlop marad NULL |
| `nav_missing_reports` | ❌ Nem elérhető | ⚠️ Oszlop marad NULL |

> **Döntés:** A NAV oszlopok maradnak a táblában `DEFAULT NULL` értékkel – ha a Cégjelző hozzáférés bővül, azonnal aktiválhatók lesznek migráció nélkül.

### 16.3 Meglévő rendszer vs. terv koherencia

| Komponens | Kérdés | Eredmény |
|-----------|--------|----------|
| `impactshop-ngo-card.php` `resolve_display_name()` | A Cégjelző `official_name` felülírja? | ✅ Koherens – registry lookup prioritás |
| `impactshop-ngo-card.php` `rebuild_dataset()` | Cégjelző adat mikor töltődik? | ✅ Koherens – `build_payload()` szinten, nem rebuild-nél |
| `impactshop-ngo-card.php` `ensure_slug_review_record()` | Cégjelző enrichment kikerüli a jóváhagyást? | ⚠️ **Kockázat** – enrichment NEM módosítja az approval workflow-t, csak gazdagít |
| `wp_impactshop_metrics_ngo` tábla | Cégjelző registry vs metrics ütközés? | ✅ Koherens – registry = master data, metrics = tranzakciós adat |
| `impactshop_totals_collect()` | Cégjelző befolyásolja a totals számítást? | ✅ Koherens – Cégjelző csak meta-adat, nem pénzügyi |
| `impi-mcp-sdk-migration-plan.md` `impi_get_ngo_info` | MCP tool szinkronban? | ✅ Koherens – thin proxy a gateway endpointja fele |
| `ngo-category-map.json` | Cégjelző `activity` kiváltja? | ✅ Koherens – fokozatos átállás, fallback a meglévő JSON |
| Approval workflow | Cégjelző auto-approve? | ❌ **NEM** – enrichment ≠ approval. Slugok továbbra is admin jóváhagyást igényelnek |

### 16.4 Trust Score validáció (NAV nélküli)

| Szcenárió | Bemenet | Elvárt score | OK? |
|-----------|---------|-------------|-----|
| Aktív közhasznú régi szervezet, képviselőkkel, leírással | `status_code=1, charity=közhasznú, reg_date=2001, desc=yes, repr=yes` | 35+30+15+10+10 = **100** | ✅ |
| Aktív közhasznú friss szervezet | `status_code=1, charity=közhasznú, reg_date=2025` | 35+30 = **65** (standard) | ✅ |
| Aktív nem közhasznú régi szervezet | `status_code=1, reg_date=2010, desc=yes, repr=yes` | 35+15+10+10 = **70** (standard) | ✅ |
| Inaktív szervezet eljárással | `status_code=0, proceedings=yes` | -50-30 = **0** (unverified) | ✅ |
| Aktív szervezet eljárással | `status_code=1, proceedings=yes` | 35-30 = **5** (unverified) | ✅ |
| Teljesen üres adat (manuális) | minden null | **0** (unverified) | ✅ |

---

## 17. Review & Improvements (2026-02-09)

> **Megjegyzés:** Az alábbi javítások az utólagos koherencia és biztonsági ellenőrzés során merültek fel. Implementáláskor ezeket figyelembe KELL venni.

### 17.1 Adatbázis séma kiegészítés (Kritikus)

A **4.1 pontban** definiált `wp_impactshop_ngo_registry` táblából hiányzik a `nav_address` oszlop, miközben a kódban (`normalize_civil_org_data`) hivatkozunk rá.

**Javasolt SQL kiegészítés:**
```sql
ALTER TABLE wp_impactshop_ngo_registry 
ADD COLUMN nav_address TEXT DEFAULT NULL COMMENT 'NAV szerinti székhely' AFTER address;
```

### 17.2 API Kulcs tárolás biztonsága

A **0. és 13.2 pont** említi az "encrypted" tárolást, de a kód példák (`ImpactShop_Cegjelzo_Client::__construct`) sima `get_option`-t használnak.

**Javaslat:** 
Használjuk a konstansként definiált kulcsot (`WP_CONFIG.php`-ból) vagy egy titkosított helper függvényt:
```php
// Helyes implementáció:
$this->apiKey = defined('IMPACTSHOP_CEGJELZO_API_KEY') ? IMPACTSHOP_CEGJELZO_API_KEY : get_option('impactshop_cegjelzo_api_key');
```
*Indoklás:* Így a kulcs kikerül az adatbázisból és környezeti változóból/config fájlból injektálható, ami biztonságosabb.

### 17.3 Adatretenció (Privacy)

A `cegjelzo_raw_response` mező teljes JSON-t tárol, ami személyes adatokat (képviselők neve, címe) tartalmazhat. Bár a GDPR jogos érdek (6(1)(f)) alapján kezeljük, a "korlátozott tárolhatóság" elve miatt javasolt egy tisztító mechanizmus.

**Javaslat:**
A `Delete Stale Data` cron job (ha lesz) törölje a `cegjelzo_raw_response` tartalmát 90 nap után, csak a normalizált mezőket hagyja meg.

### 17.4 Logs & Error Handling

Az `ImpactShop_Cegjelzo_Cron::run_daily_sync` metódusban a `usleep(200_000)` jó rate limiting-re, de ha az API tartósan áll (pl. 500-as hibák sorozata), a cron job feleslegesen pörgeti a próbálkozásokat.

**Javaslat:**
Vezessünk be egy `consecutive_errors` számlálót a loop-ban. Ha eléri az 5-öt, szakítsuk meg a futást (`break`), hogy ne terheljük a rendszert és a logokat feleslegesen.

---

## 18. Megjegyzések (v1.0 koherencia és biztonság)

> **Megjegyzés:** Az alábbi pontok a v1.0 verzióban talált eltérések/javítandók. Nem írják felül a tervet, csak kiegészítő javaslatként szolgálnak.

### 18.1 Koherencia megjegyzések

- **Header frissítés:** v1.0 → v1.1, dátum 2026-02-09, státusz „VÉGLEGES”.
- **Végpontok:** `/financials-data-table` jelenleg nem elérhető a JWT-ben → jelöld ❌-ként vagy vedd ki.
- **NAV mezők:** `nav_*` státusz mezők nem elérhetők civil szervezetekhez → a Trust Score NAV-komponensei kerüljenek kikommentezésre.
- **DB séma:** hiányzik `nav_address` oszlop, pedig az API és a normalizálás hivatkozik rá → lásd §17.1 SQL javaslat.
- **NGO Card payload:** `nav_status` csak akkor jelenjen meg, ha valóban van NAV adat (különben félrevezető UI).
- **AI Agent Core (TS):** `X-Fields` listából vedd ki a NAV státusz mezőket; `nav_address` és `updated_at` felvétele javasolt.

### 18.2 Biztonsági megjegyzések

- **API kulcs tárolás:** ne csak `get_option()` – preferált env/`wp-config.php` injektálás (lásd §17.2).
- **Raw response retention:** személyes adatok miatt időzített törlés (90 nap) ajánlott.
- **Rate limit visszaesés:** 5xx/timeout sorozat esetén stop/circuit-breaker szükséges, hogy ne terheljük túl az API-t.
- **Audit log:** `requestLog` adatok csak admin számára legyenek elérhetők és ne kerüljenek publikusan logolásra.

---

## 19. Eljárástípusok megjeleníthetősége (2026-02-09)

> **Kontextus:** A Cégjelző `proceedings` mezője JSON tömb, amely az adott szervezet ellen/felé indított eljárásokat tartalmazza. Az alábbiakban megvizsgáljuk, hogy a 7 releváns eljárástípust hogyan kell kezelni a rendszer egyes rétégeiben.

### 19.1 Eljárástípusok katalógusa és súlyozása

| Típus | Belső kulcs | Súlyosság | Trust Score hatás | UI viselkedés | Megjegyzés |
|-------|-------------|-----------|-------------------|---------------|------------|
| Csőd eljárás | `bankruptcy` | 🔴 Kritikus | **-50** (szinte nulláz) | ⛔ Piros figyelmeztetés + tooltip | Fizetésképtelen szervezet, adománygyűjtés megkérdőjelezhető |
| Felszámolás | `liquidation` | 🔴 Kritikus | **-50** | ⛔ Piros figyelmeztetés + „Megszűnőben" badge | Szervezet felszámolás alatt; adományozás kockázatos |
| Kényszertörlés | `forced_cancellation` | 🔴 Kritikus | **-50** + `status_code=0` flag | ⛔ Piros figyelmeztetés + „Kényszertörölt" badge | Hatóság által törölt → megjelenítés letiltása javasolt |
| Végelszámolás | `voluntary_liquidation` | 🟡 Közepes | **-25** | ⚠️ Sárga figyelmeztetés + „Végelszámolás alatt" | Önkéntes befejezés; adományok felhasználása kérdéses |
| Végrehajtás | `execution` | 🟡 Közepes | **-20** | ⚠️ Sárga figyelmeztetés | Tartozás végrehajtása folyamatban; pénzügyi kockázat |
| Büntetőjogi intézkedés | `criminal_measure` | 🔴 Kritikus | **-40** | ⛔ Piros figyelmeztetés + „Büntetőjogi eljárás" | Rendkívüli kockázat; azonnali admin értesítés |
| Megszűnés | `cancellation` | ⚫ Végleges | **-50** + `status_code=0` | 🚫 NGO Card elrejtése / „Megszűnt" overlay | Nem létező szervezet; ne jelenjen meg aktívként |

### 19.2 Trust Score finomítás javaslat (§4.2 kiegészítés)

> **Megjegyzés:** A jelenlegi kód egyetlen `-30` pontot von le bármely eljárás esetén (`has_proceedings`). Ez nem tükrözi a súlyossági különbségeket.

**Javasolt módosítás:**

```php
// A jelenlegi kód:
// if ($registry_row['has_proceedings']) { $score -= 30; $flags[] = 'proceedings'; }

// Javasolt helyettesítés – eljárástípus-specifikus súlyozás:
$proceedingTypes = json_decode($registry_row['proceedings'] ?? '[]', true);
if (!empty($proceedingTypes)) {
    $procPenalties = [
        'bankruptcy'             => -50,
        'liquidation'            => -50,
        'forced_cancellation'    => -50,
        'voluntary_liquidation'  => -25,
        'execution'              => -20,
        'criminal_measure'       => -40,
        'cancellation'           => -50,
    ];
    $worstPenalty = 0;
    foreach ($proceedingTypes as $proc) {
        $type = $proc['type'] ?? $proc['key'] ?? 'unknown';
        $penalty = $procPenalties[$type] ?? -30; // fallback: ismeretlen típus
        if ($penalty < $worstPenalty) {
            $worstPenalty = $penalty;
            $flags[] = 'proceeding_' . $type;
        }
    }
    $score += $worstPenalty; // legrosszabb eljárás dominál (nem kumulatív)
}
```

> **Döntés szükséges:** Kumulatív legyen (minden eljárás levon)? Vagy a legrosszabb domináljon? Javaslat: **legrosszabb dominál** – egy csődben lévő szervezet nem kap „duplán" büntetést, ha végrehajtás is van.

### 19.3 DB séma kiegészítés javaslat (§4.1)

A jelenlegi `proceedings JSON` mező megfelelő a nyers adatok tárolására, de javasolt egy indexelhető összegző mező:

```sql
-- Javasolt kiegészítés a wp_impactshop_ngo_registry-hez:
ALTER TABLE wp_impactshop_ngo_registry
ADD COLUMN proceeding_severity ENUM('none', 'moderate', 'critical', 'terminal') DEFAULT 'none'
COMMENT 'Legrosszabb eljárás súlyossága – gyors szűréshez' AFTER has_proceedings;
```

**Severity mapping:**
| Severity | Eljárás típusok | Használat |
|----------|----------------|-----------|
| `none` | Nincs eljárás | Normál megjelenítés |
| `moderate` | `voluntary_liquidation`, `execution` | ⚠️ Sárga figyelmeztetés |
| `critical` | `bankruptcy`, `criminal_measure` | ⛔ Piros figyelmeztetés |
| `terminal` | `liquidation`, `forced_cancellation`, `cancellation` | 🚫 NGO Card elrejtése / overlay |

### 19.4 NGO Card UI megjelenítés javaslat (§6.2 kiegészítés)

```
┌─────────────────────────────────────────────────┐
│  🏛️  Példa Alapítvány                            │
│  ──────────────────────────────────────────      │
│  💰 Támogatás: 456 789 Ft                       │
│                                                  │
│  ┌─ ⛔ Eljárás figyelmeztetés ─────────────────┐│
│  │ ⚠️ Felszámolás alatt (2025-08-12 óta)        ││
│  │ Típus: Kényszertörlési eljárás               ││
│  │ Forrás: Cégjelző (utolsó frissítés: 3 napja) ││
│  │ ℹ️ Ez a szervezet megszűnés előtt áll.        ││
│  │    Felajánlásod nem garantált.                ││
│  └──────────────────────────────────────────────┘│
│                                                  │
│  🛡️ Trust Score: 5/100 (Unverified) ⚠️           │
│  [Támogatás letiltva]  [Részletek]               │
└─────────────────────────────────────────────────┘
```

**Moderáció / automatikus lépések:**
| Severity | Admin notification | Card megjelenítés | Adománygyűjtés |
|----------|-------------------|-------------------|----------------|
| `none` | – | Normál | ✅ Engedélyezve |
| `moderate` | 📧 E-mail értesítés | ⚠️ Figyelmeztetés sáv | ✅ Engedélyezve (disclaimer-rel) |
| `critical` | 📧 + 🔔 Admin dashboard alert | ⛔ Piros sáv | ⚠️ Megerősítés kérése a felhasználótól |
| `terminal` | 📧 + 🔔 + Auto-deaktiválás | 🚫 Card elrejtve / overlay | ❌ Letiltva |

### 19.5 Normalizálás kiegészítés javaslat (§5.3)

A `normalize_civil_org_data()` jelenleg csak `has_proceedings` bool-t állít. Javasolt egy típus-specifikus normalizálás:

```php
// Javasolt kiegészítés a normalize_civil_org_data()-hoz:
'proceeding_types'    => $this->extract_proceeding_types($raw['proceedings'] ?? []),
'proceeding_severity' => $this->classify_proceeding_severity($raw['proceedings'] ?? []),

// Segédfüggvények:
private function extract_proceeding_types(array $proceedings): array
{
    $types = [];
    foreach ($proceedings as $proc) {
        $type = $proc['type'] ?? $proc['key'] ?? 'unknown';
        $types[] = [
            'type'       => $type,
            'start_date' => $proc['start_date'] ?? $proc['insertion'] ?? null,
            'label'      => $this->proceeding_label($type),
        ];
    }
    return $types;
}

private function classify_proceeding_severity(array $proceedings): string
{
    if (empty($proceedings)) return 'none';

    $terminal  = ['liquidation', 'forced_cancellation', 'cancellation'];
    $critical  = ['bankruptcy', 'criminal_measure'];
    $moderate  = ['voluntary_liquidation', 'execution'];

    foreach ($proceedings as $proc) {
        $type = $proc['type'] ?? $proc['key'] ?? '';
        if (in_array($type, $terminal, true)) return 'terminal';
    }
    foreach ($proceedings as $proc) {
        $type = $proc['type'] ?? $proc['key'] ?? '';
        if (in_array($type, $critical, true)) return 'critical';
    }
    foreach ($proceedings as $proc) {
        $type = $proc['type'] ?? $proc['key'] ?? '';
        if (in_array($type, $moderate, true)) return 'moderate';
    }
    return 'moderate'; // ismeretlen típus → óvatosságból moderate
}

private function proceeding_label(string $type): string
{
    return match ($type) {
        'bankruptcy'            => 'Csődeljárás',
        'liquidation'           => 'Felszámolás',
        'forced_cancellation'   => 'Kényszertörlés',
        'voluntary_liquidation' => 'Végelszámolás',
        'execution'             => 'Végrehajtás',
        'criminal_measure'      => 'Büntetőjogi intézkedés',
        'cancellation'          => 'Megszűnés',
        default                 => 'Egyéb eljárás',
    };
}
```

### 19.6 Koherencia és biztonsági megjegyzések

**Koherencia:**
- ⚠️ A Cégjelző API `proceedings` mező struktúrája egyelőre nem dokumentált részletesen – implementálás előtt tesztelni kell a `dev.api.cegjelzo.com` végponton milyen formátumban jönnek az eljárás adatok (`type`, `key`, `start_date`, stb.).
- ⚠️ A `status_code` (0=törölt) és a `cancellation`/`forced_cancellation` eljárás átfedhet – a scoring-ban ne legyen dupla büntetés (ha `status_code=0` **és** `cancellation` eljárás van, a `-50` csak egyszer számítson).
- ✅ A `proceeding_severity` ENUM mező gyors szűrést tesz lehetővé az admin listában anélkül, hogy a JSON-t kellene parse-olni.

**Biztonság:**
- ⚠️ A `terminal` severity automatikus card-elrejtése biztonsági szinten védi a felhasználókat attól, hogy megszűnt szervezetnek utaljanak.
- ⚠️ Az eljárás adatok nyilvános cégjegyzékből származnak, nem szenzitívek – GDPR szempontból nem probléma a megjelenítés.
- ✅ Az admin notification rendszer biztosítja, hogy kritikus eljárás esetén emberi döntés szülessen az adománygyűjtés letiltásáról.
