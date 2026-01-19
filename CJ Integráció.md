---
title: CJ integráció – részletes terv
owner: ImpactShop
status: draft
updated: 2025-11-15
---

## 1. Cél és scope

- **Cél:** a CJ (Commission Junction) network integrálása az ImpactShop ökoszisztémába úgy, hogy a shop/adveriser lista, a konverziós riportok és a státuszszinkron teljesen automatizált legyen.
- **Scope:** csak a joined programok és a Sharity/ImpactShop publisher SID alá tartozó jutalékok; ideiglenesen kizárjuk a lead alapú kampányokat és azokat a programokat, amelyek speciális cookie/fingerprint követést kérnek.
- **Nem scope:** merchant approval workflow, kreatívok kezelése, payout reconciliation (ezeket később külön guardrail kezeli).

## 2. High-level architektúra

1. **Adatforrások**
   - CJ Advertiser Lookup API → shop meta (név, tracking URL, kategória, támogatott országok).
   - CJ Commission Detail API → konverziók listája (pending/locked/trip-ready).
   - CJ Item Detail API (opcionális) → termékszintű információ, ha a jövőben szükség lesz.
2. **ImpactShop komponensek**
   - WP-CLI modul (`wp impactshop cj:*`) a szinkron feladatokhoz.
   - MU plugin (impactshop-cj-sync.php) → cron, adatbázis írás, guard.
   - Social ledger (impact-social-mvp) → CJ csatorna integráció a tickerhez.
3. **Adatáramlás**
   - Cron óránként kérdezi a CJ API-t, utolsó `date` paraméterrel.
   - A konverziók a `wp_impactshop_ledger` táblába kerülnek (ugyanazzal a sémával, amit Dognet használ).
   - A shop lista a `impactshop_shops` CSV/tábla automatikus mezőjét tölti, manual inputra nincs szükség.

## 3. Autentikáció és hozzáférés

| Elem                    | Részlet                                                                    |
| ----------------------- | -------------------------------------------------------------------------- |
| Publisher ID (PID)      | `7318997`                                                                   |
| Developer Key           | `CJ_DEVELOPER_KEY=NaNVErg7XUFUhFeGZOD5mHJdBg` (env + GitHub Secret)         |
| HTTP header             | `Authorization: NaNVErg7XUFUhFeGZOD5mHJdBg`, `Accept: application/json`     |
| Rate limit              | ~200 kérés / 5 perc; 429 esetén `Retry-After` tiszteletben tartása          |
| Sandbox                 | Nincs külön sandbox; staging azonos credentialeket használ, de limitált cron |

### 3.1 Credential rotation & guard
- `CJ_DEVELOPER_KEY` rotáció 90 naponta; guard figyeli és Slack figyelmeztetést küld 2 héttel korábban.
- Ha lehetséges, staginghez külön API key (külön guard a rotációra).
- Rate limit túllépés: 15 perc cooldown + WARN log; >3 egymást követő limit-hit → guard FAIL.

## 4. Shop lista automatikus szinkron

### 4.1 API hívás
```
GET https://advertiser-lookup.api.cj.com/v3/advertisers
    ?advertiser-ids=joined
    &records-per-page=100
    &page-number={n}
    &mobile-supported=true
```

### 4.2 Mezőtérkép
| CJ mező                   | ImpactShop mező        | Megjegyzés                                        |
| ------------------------- | ---------------------- | ------------------------------------------------- |
| `advertiser-id`           | `shop_slug`            | slug=lowercase + `-cj` suffix, pl. `visionexpress-cj` |
| `program-name`            | `name`                 | a felhasználó felé megjelenő név                 |
| `program-url`             | `landing_url`          | brand URL, csak megjelenítéshez                   |
| Link Search `clickUrl`    | `tracking_template`    | valódi CJ tracking link template (deeplink param) |
| `relationship-status`     | `status`               | joined/pending; csak joined kerül a listába      |
| `categories/category`     | `category`             | opcionális mező, filterhez                       |
| `seven-day-epc`           | `metrics.epc`          | jövőbeli rankinghez elérhető                     |

### 4.3 Implementáció
- Új WP-CLI parancs: `wp impactshop cj:sync-shops`.
  - Paginate-eli az összes joined advertiser-t.
  - JSON → belső tömb → `impactshop_shops` transient + CSV (`data/cj-shops.csv`).
  - Existing shop slug egyezés esetén frissíti a `dognet_base` értéket.
- Cron: `.codex/cron/impact-cj-shops-sync.sh` (naponta 01:00). Hiba esetén guard (Slack/e-mail) figyelmeztetés.
- Admin fallback: ha a CJ API nem elérhető, a legutóbbi sikeres CSV marad érvényben.
- Link Search API integráció a deeplink sablonokhoz (`clickUrl` + `sid` placeholder), `deep_link_supported` flag tárolása.

### 4.4 Ütköző slug kezelés
- Ha már létezik azonos `shop_slug` más hálózatból (pl. Dognet), ugyanazon shop entitás alatt több `network` rekordot tartunk nyilván (`network = dognet|cj`).
- Ha mégis külön slug kell, CJ suffix `-cj` (pl. `visionexpress-cj`), de az admin UI-ban jelöljük a duplikált brandet és adunk merge lehetőséget.
- Shop aggregator tárolja: `source`, `tracking_template`, `deep_link_supported`, `network_priority` (Dognet elsődleges, ha mindkettő aktív).

## 5. Konverziós szinkron

### 5.1 API hívás
```
GET https://commission-detail.api.cj.com/v3/commissions
    ?date-type=event
    &start-date={ISO8601}
    &end-date={ISO8601}
    &advertiser-ids=joined
    &low-value=0
    &action-status=all
```

### 5.2 Mapping a ledgerhez
| CJ mező               | Ledger mező               | Megjegyzés                                 |
| --------------------- | ------------------------- | ------------------------------------------ |
| `action-id`           | `external_id`             | egyedi azonosító                           |
| `advertiser-id`       | `shop_slug`               | a fenti sync során képzett slug            |
| `sid` / `aid`         | `ngo_slug` / `ambassador` | `sid` mezőben küldjük az NGO slugot        |
| `commission-amount`   | `amount_huf`              | CJ USD → HUF konverzió aktuális MNB árfolyammal |
| `action-status`       | `status`                  | pending / locked / corrected               |
| `event-date`          | `happened_at`             | ISO string                                 |

### 5.3 Implementáció
- Új WP-CLI: `wp impactshop cj:sync-ledger --window=PT2H`.
- Cron 10 percenként, `--window=PT2H`, `--since-cache=/tmp/cj-ledger.window`.
- Idempotencia: `external_id` + `status` kombináció; ha duplikátum, a rekord frissül.
- Hibakezelés:
  1. `4xx`: credential/config hiba → guard FAIL.
  2. `5xx`: exponential backoff (max 3 próbálkozás).
  3. JSON parse: log + guard WARN, a következő körben újra próbáljuk.
- Dátum lekérdezés: alap beállítás `date-type=modified` (ha támogatott), így a korrektúrák is feljönnek; websitre szűkítés `website-ids=` paraméterrel.
- Státusz-mapping: pending → `P`, locked → `A`, corrected → negatív adjustment tétel (megőrizzük az eredeti tranzakció azonosítóját).
- `sid` mezőbe kerül `ngoSlug~ambCode` formában (max 128 char); a ledger bontja ketté `ngo_slug` és `ambassador` mezőkre. PII nem kerül a `sid`-be.

### 5.4 Retry logika és dead-letter queue
- Exponenciális backoff: 1s → 2s → 4s (max 3 próbálkozás).
- Sikertelen batch → `wp_impactshop_failed_syncs` táblába kerül (payload + hiba).
- Manual retry CLI: `wp impactshop cj:retry-failed --limit=100`.
- Alert: ha 24 órán belül >50 sikertelen rekord marad, Slack/PagerDuty riasztás.

### 5.5 Árfolyamkonverzió
- MNB (vagy ECB) API hívás minden nap 06:00-kor; cache `wp_options` (`impactshop_fx_rate_usd_huf`).
- Fallback: ha aznapi hívás sikertelen, előző nap árfolyamát használjuk és guard WARN-t küldünk.
- Ledger mezők: `amount_original`, `currency_original`, `exchange_rate` (HUF ellenérték auditálhatóságához).

### 5.6 Cancelled/Corrected tranzakciók
- CJ `action-status=corrected` → negatív összeggel új rekord (ledger `status=corrected`, `parent_action_id` hivatkozással).
- Social ticker címke: “Visszavont” (piros badge); >5% corrected arány esetén fraud check riasztás.
- Partial refund hiányában warning log + manual reconciliation lista.

## 6. WordPress / ImpactShop implementációs lépések

1. **Konfiguráció**
   - `.staging_env` / `.production_env`: `CJ_DEVELOPER_KEY`, `CJ_PUBLISHER_ID`.
   - WP option: `impactshop_cj_last_sync` (timestamp).
2. **MU plugin**
   - `impactshop-cj.php` létrehozása (authentication helper, WP-CLI regiszter, cron binding).
   - `impactshop_register_shop_source('cj', callback)` – meglévő shop aggregator bővítése.
3. **Shop lista feed**
   - `impactshop_get_shops()` módosítása: Dognet CSV + CJ CSV merge (slug dedupe).
4. **Ledger adaptor**
   - `impact-social-ledger-sync.php` kibővítése: `source=cj` branch, `channel` mező = `CJ`.
   - Social ticker: `channel` label → “CJ webshop”.
5. **Admin UI**
   - WP admin oldal: “CJ status” panel (utolsó sync, joined merchant count, utolsó hiba).
6. **Currency kezelés**
   - Ledger táblában tároljuk a CJ által küldött pénznemet/összeget; HUF konverzió csak megjelenítéskor történik.
7. **Sid parser**
   - `sid=ngoSlug~ambCode` formátum; WP backendben validáció (max 64+64 kar). Ambassador hiányában csak `ngoSlug`.

## 7. Tesztelési terv

| Lépés | Leírás | Környezet |
| ----- | ------ | --------- |
| Unit  | Mockolt CJ API (WP_HTTP test double) → shop és ledger parser validáció | Local PHPUnit |
| CLI smoke | `wp impactshop cj:sync-shops --limit=5` manual run | staging |
| End-to-end | staging cron lefuttatása, majd social tickerben ellenőrizni, hogy megjelent-e a `CJ` csatorna sor | staging |
| Fallback | CJ API off → guard jelzés + legutóbbi CSV használata | staging |
| Production | guard log + manual ledger ellenőrzés az első 24 órában | production |

## 8. Ütemezés & feladatok

1. **Week 46**
   - MU plugin + CLI scaffold
   - Shop sync implementáció
2. **Week 47**
   - Ledger integráció + cron
   - Social ticker csatorna label + QA
3. **Week 48**
   - Guardrail + admin monitor panel
   - Dokumentáció, runbook, véglegesítés

## 9. Nyitott kérdések

1. Van-e külön CJ sub-account a Staging URL-ekhez? (Ha nincs, mindkét környezet ugyanazt használja.)
2. Mi a végleges publisher ID (PID) – a tervben szereplő értéket cserélni kell.
3. Kell-e dedikált webhooks feldolgozás a locking eseményekre, vagy elég a polling?

## 10. Monitoring & observability

| Metrika | Threshold | Action |
| ------- | --------- | ------ |
| Sync latency | >5 perc | WARN log + backlog mérés |
| Failed API calls | >10% egy órán belül | Slack alert |
| Konverziós anomália | ±50% eltérés az előző héthez képest | Manual review / guard WARN |
| Ledger gap | >2 órája nincs új rekord | guard FAIL + cron retry |
| Rate limit hit | >3 egymást követő 429 | cooldown + alert |

## 11. Implementáció állapot (2025-11-15)

- **MU plugin & CLI** elkészült (`wp-content/mu-plugins/impactshop-cj.php`), WP-CLI parancsok:  
  `wp impactshop cj sync-shops`, `wp impactshop cj sync-ledger`, `wp impactshop cj retry-failed`.  
  Az `impactshop_get_shops()` aggregator szűrőzése is aktív (CJ shopok automatikusan bekerülnek, ha a sync fut).
- **Környezet**: `.staging_env` / `.production_env` + GitHub Secret tartalmazza a `CJ_DEVELOPER_KEY` és `CJ_PUBLISHER_ID` értéket.
- **Blokkoló**: a `https://advertiser-lookup.api.cj.com/v3/...` és `commission-detail...` végpontok jelenleg 404-et adnak (üres body).  
  → Feladat: CJ accountban ellenőrizni, hogy engedélyezve van-e a Web Services API / a helyes hostot használjuk-e.  
  Amíg ez nincs jóváhagyva, a `sync-shops` és `sync-ledger` parancsok nem tudnak adatot beolvasni, ezért cron sem aktív.
- **Doksiexport automatizálva**  
  - `scripts/cj-docs-urls.txt` – ide vehetők fel a nyilvános CJ developer URL-ek.  
  - `npm run export` Playwright-tel PDF-be menti az összes oldalt és `cj-docs/CJ_Developer_Docs_merged.pdf` fájlba fűzi.  
  - `.github/workflows/cj-docs-export.yml` heti egyszer (vagy manuálisan) lefut és artefaktként feltölti a PDF-eket.

## 12. Feed Migration Report összegzés

Forrás: `Feeds-Migration-Report.csv` (Google Drive → 2025/Affiliate/CJ). Legutóbbi futás (2025-11-15):

- **Összes feed**: 168 sor, mind Google Shopping formátum.
- **Advertiserek**: 35 külön brand; top feed-szám szerint:
  - Byrokko World – 34 feed
  - Skechers – 19 feed
  - Reedog Europe – 15 feed
  - Monkeymum.com – 14 feed
  - PinkPanda Europe – 11 feed
  - Nanushka – 9 feed
  - Warsawsneakerstore.com – 8 feed
  - HeliumKing Europe – 7 feed
  - GeekBuying – 6 feed
- **Last Import Date**: több kulcs advertiser 2025. nov. 14–15-én frissített (GeekBuying, Skechers, Reedog, HeliumKing, WSS).  
  Néhány feed régebbi (pl. Lumary 2024.09.06), ezt jelezni kell a CJ csapat felé, ha aktív kampányt várunk.
- **Felhasználás**: a shop-sync első körében a fenti brandeket érdemes priorizálni; a CSV metaadataiból áll elő a `tracking_template` és a deep link képesség (Link Search API szükséges a végleges paraméterhez).

Amint a CJ API elérés biztosított, végrehajtható a shop/ledger sync, és aktiválhatók a cronok + guardok.
