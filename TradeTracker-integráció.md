# TradeTracker Integráció – ImpactShop/Sharity

> Állapot: **Tervezés** (2025-10-20) • Felelős: Engineering Lead  
> Ez a dokumentum a TradeTracker (TT) hálózat ImpactShop rendszerbe való összekötésének elfogadott tervezetét rögzíti. A specifikáció jelenleg dokumentációs és guardrail szinten él; a termékben nem történt változtatás.

## 1. Projekt áttekintés
- **Cél**: A meglévő Dognet és CJ (Commission Junction) integráció mellé harmadik affiliate forrásként bekötni a TradeTrackert.
- **Előfeltételek**: Sandbox hozzáférés, TT_CUSTOMER_ID és TT_PASSPHRASE titkos kezelésben.
- **Hatókör**: Adatbetöltés (fetch), normalizálás, aggregálás, cache, REST végpont tükrözés, `/go` router paraméterezés, monitorozás.
- **Nem cél**: UI módosítás, ImpactShop termékkód deploy – a dokumentum csak az előkészítő keretrendszert definiálja.

## 2. Tervezett könyvtárstruktúra
```
impact-bridge-tradetracker/
├── src
│   ├── api
│   │   ├── TradeTrackerClient.php
│   │   ├── TradeTrackerAuth.php
│   │   └── RateLimiter.php
│   ├── normalizers
│   │   ├── TradeTrackerNormalizer.php
│   │   └── TransactionMapper.php
│   ├── aggregators
│   │   ├── MultiSourceAggregator.php
│   │   └── DonationCalculator.php
│   ├── cache
│   │   ├── TransientCache.php
│   │   └── CacheKeyGenerator.php
│   ├── validators
│   │   ├── ResponseValidator.php
│   │   └── SchemaValidator.php
│   └── utils
│       ├── DateTimeHelper.php
│       ├── CurrencyConverter.php
│       └── ErrorLogger.php
├── config
│   ├── tradetracker-config.php
│   ├── endpoint-mappings.php
│   └── field-mappings.php
├── tests
│   ├── unit
│   │   ├── TradeTrackerClientTest.php
│   │   ├── NormalizerTest.php
│   │   └── AggregatorTest.php
│   ├── integration
│   │   ├── ApiConnectionTest.php
│   │   ├── DataFlowTest.php
│   │   └── CacheIntegrationTest.php
│   └── smoke
│       ├── EndpointSmokeTest.php
│       └── shortcode_tt_smoke.sh
├── scripts
│   ├── sync
│   │   ├── tt_sync_preflight.sh
│   │   ├── tt_fetch_transactions.sh
│   │   └── tt_data_validation.sh
│   ├── diagnostics
│   │   ├── tt_connection_check.sh
│   │   ├── tt_auth_verify.sh
│   │   └── tt_data_integrity.sh
│   └── deployment
│       ├── tt_deploy_staging.sh
│       ├── tt_deploy_prod.sh
│       └── tt_rollback.sh
├── docs
│   ├── API_REFERENCE.md
│   ├── INTEGRATION_GUIDE.md
│   ├── TROUBLESHOOTING.md
│   └── FIELD_MAPPINGS.md
├── migrations
│   ├── 001_add_tt_config.php
│   ├── 002_extend_cache_keys.php
│   └── 003_add_tt_metadata.php
├── .codex
│   ├── reports
│   │   └── tradetracker
│   │       ├── logs
│   │       ├── artifacts
│   │       └── backups
│   └── status
│       └── tt_status_latest.txt
├── composer.json
├── phpunit.xml
└── README.md
```

## 3. Konfigurációs réteg (`config/tradetracker-config.php`)
```php
<?php
/**
 * TradeTracker konfiguráció (terv) – nem aktív termékkód.
 * Path: wp-content/mu-plugins/impact-bridge-tradetracker/config/tradetracker-config.php
 */

define('TT_ENABLED', true);
define('TT_CUSTOMER_ID', getenv('TT_CUSTOMER_ID') ?: '');
define('TT_PASSPHRASE', getenv('TT_PASSPHRASE') ?: '');
define('TT_DEMO_MODE', false);

define('TT_API_VERSION', 'v4');
define('TT_API_LOCALE', 'en_GB');
define('TT_API_TIMEOUT', 20);
define('TT_PAGE_SIZE', 100);

define('TT_DATE_FIELD', 'registrationDate');
define('TT_TIMEZONE', 'Europe/Budapest');

define('TT_RATE_LIMIT_PER_MIN', 60);
define('TT_RATE_LIMIT_BURST', 10);
define('TT_BACKOFF_MAX_RETRIES', 5);
define('TT_BACKOFF_BASE_DELAY', 1);

define('TT_CACHE_TRANSACTIONS', 180);
define('TT_CACHE_CAMPAIGNS', 3600);

define('TT_STATUS_MAP', [
    'pending' => 'PENDING',
    'accepted' => 'OPEN',
    'approved' => 'OPEN',
    'rejected' => 'CANCELED',
    'disapproved' => 'CANCELED'
]);
```

## 4. API kliens (terv)
- `fetchTransactions($fromDate, $toDate, $dateField)`
- `getCampaigns()`
- `getTransactionDetails($transactionId)`
- `authenticateRequest($url, $params)`
- Exponenciális backoff + jitter, 200/400/401/429/500 kezelése.
- Log: `.codex/reports/tradetracker/logs/`.

## 5. Normalizálás
- Bemenet: TradeTracker tranzakció tömb.
- Kimenet: Dognet/CJ kompatibilis mezőnevek (ISO8601, status mapping, SubID bontás `ngo_|amb_|impactshop` formátumra).
- Item lista üres (TT nem szállít termékszintű adatot).

## 6. Multi-source aggregátor kiterjesztése
```php
if (defined('TT_ENABLED') && TT_ENABLED) {
    $sources[] = $this->fetchTradeTracker($fromUtc, $toUtc);
}
```
- Merge + rendezés `event_date` szerint csökkenő sorrendben.

## 7. REST végpontok
- Új adatforrás beolvasása nélkülöz UI változtatást.
- Cache kulcsok: `impact_ticker_v3_{hash(dognet+cj+tt)}`.

## 8. `/go` router bővítés
- Ha `shop.network === 'tradetracker'`, akkor `r={ngo}|{amb}|impactshop` query paraméter.
- Sem slug, sem UI nem módosul.

## 9. Tesztelés (tervezett)
- **Unit**: kliens, normalizer, aggregator.
- **Integráció**: sandbox fetch, adatfolyam, cache.
- **Smoke**: `shortcode_tt_smoke.sh` – `[impact_ticker]`, `[impact_activity]`, `[impact_leaderboard]`.

## 10. Üzembe helyezési scriptjeik
- `tt_sync_preflight.sh` – konfiguráció + API elérés + PHP verzió ellenőrzése.
- `tt_deploy_staging.sh` – rsync + smoke test.
- `tt_deploy_prod.sh` – a meglévő `shortcode_sync_run_REAL.sh` kibővítése (targets list).
- `tt_rollback.sh` – kód visszaállítás + cache flush + TT_ENABLED kikapcsolás.

## 11. Diagnosztika és logolás
- `.codex/reports/tradetracker/logs/tt_{timestamp}.log`.
- Kódok: `TT_ERR_AUTH`, `TT_ERR_RATE_LIMIT`, `TT_ERR_TIMEOUT`, `TT_ERR_INVALID_DATE`, `TT_ERR_PARSE`.
- Riasztás: 5 percen belül >10 hiba → e-mail `dev@sharity.hu`.

## 12. Teljesítmény célok
- API hívás: < 2s / 100 tranzakció.
- Normalizálás: < 0.5s / 100 tranzakció.
- Aggregáció: < 1s / 7 napos ablak.
- REST válasz (warm cache): < 1.5s.

## 13. Elfogadási kritériumok
- TT API staging/prod elérhető, normalizált adatok aggregatorban.
- REST végpontok kombinált adatot adnak vissza.
- Shortcode-ok TT adatot jelenítenek meg.
- Smoke, unit, integration tesztek zöldek; PHP lint OK.
- Nincs teljesítmény romlás (>20%).
- Rollback script ellenőrizve.
- Dokumentáció (API_REFERENCE, INTEGRATION_GUIDE, FIELD_MAPPINGS, TROUBLESHOOTING) kész.
- 24 órás monitorozási log tiszta.

## 14. Dokumentációs deliverable-ek
- **API_REFERENCE.md** – végpontok, auth flow, példák.
- **INTEGRATION_GUIDE.md** – konfiguráció, tesztmenet, FAQ.
- **FIELD_MAPPINGS.md** – TT → belső mezők.
- **TROUBLESHOOTING.md** – tipikus hibák, megoldások.

## 15. Implementációs fázisok (6 hét)
1. **Setup** – struktúra, config, sandbox.
2. **Core fejlesztés** – kliens, normalizer, aggregator, unit teszt.
3. **Integráció** – REST, `/go`, cache.
4. **Tesztelés** – staging, smoke, benchmark, UAT.
5. **Production** – deploy, 24h monitor, dokumentáció, tudásátadás.

---

### Guardrail státusz
- Integráció **dokumentálva**.
- Kód még **nincs telepítve**; Impact Shop működését nem érinti.
- Minden lépés a `.codex/scripts/tradetracker-scope-check.sh` riportban követhető (impactall).

