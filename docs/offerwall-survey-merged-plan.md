# Offerwall Saját Kérdőívek + Szegmentáció – Részletes Implementációs Terv (összefésült)

## 0) Cél és scope
Ideiglenes, saját kérdőívek a Feladatok (offerwall) alatt **biztonságosan** és **szegmentáció‑kompatibilisen**, a Survey guide szabályokkal összhangban.

### Célok
- Csalásálló jutalom jóváírás (HMAC + IP allowlist + idempotencia).
- Kérdésválaszokból **szegmentációs** profil frissítése (question_mapping + segment_taxonomy).
- Csak **safe/low** szegmensek kerülhetnek partner célzásba (k‑anonymity 500+).
- Minimális beavatkozás az existing offerwall flow‑ba.

### Nem cél
- Nem változtatjuk a videó reward logikát.
- Nem végzünk személyes adatgyűjtést (PII tiltott).

## 1) Források (kötelező olvasmány)
- `docs/offerwall-survey-plan.md`
- `docs/offerwall-integration-plan.md`
- `/Users/bujdosoarnold/Developer/GitHub/Survey/codex_implementation_guide.md`
- `/Users/bujdosoarnold/Developer/GitHub/Survey/question_mapping.csv`
- `/Users/bujdosoarnold/Developer/GitHub/Survey/segment_taxonomy.csv`

## 2) Rögzített üzleti döntések
- **Target ID:** `impactad` (szám nélkül).
- **Kérdések:** max 5 / kérdőív.
- **Típus:** single choice (A–D).
- **Jutalom:** 10 pont + 10 szavazat / kitöltés.
- **Adatmegőrzés:** PII nélkül, adatmegőrzési policy szerint (javasolt időkorlát + aggregált retention).

## 3) Architektúra áttekintés
```
User → Offerwall Feladatok UI → Saját kérdőív (iframe)
  → Survey backend rögzít (answers + category)
  → Server‑to‑server Postback (HMAC, allowlist)
  → WP Offerwall Callback
      → jutalom jóváírás
      → survey answers tárolás
      → segment scoring (question_mapping)
      → consent gate + targetable filter
```

## 4) Adatmodellek

### 4.1 Offerwall completion (meglévő terv)
- `wp_impactshop_offerwall_completions` (idempotencia: provider+transaction_id+survey_id).

### 4.2 Survey válaszok
```sql
CREATE TABLE wp_impactshop_survey_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pseudo_id VARCHAR(32) NOT NULL,
  survey_id VARCHAR(64) NOT NULL,
  target_id VARCHAR(32) NOT NULL DEFAULT 'impactad',
  answers_json LONGTEXT NOT NULL,
  question_count TINYINT UNSIGNED NOT NULL,
  survey_version VARCHAR(16) DEFAULT 'v1',
  mapping_version VARCHAR(16) DEFAULT 'v1',
  request_id VARCHAR(128) DEFAULT '',
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_user_survey (pseudo_id, survey_id),
  KEY pseudo_id (pseudo_id),
  KEY survey_id (survey_id),
  KEY created_at (created_at)
);
```

### 4.3 Segment scoreboard
```sql
CREATE TABLE wp_impactshop_segment_scores (
  pseudo_id VARCHAR(32) NOT NULL,
  segment_code VARCHAR(32) NOT NULL,
  sum_val FLOAT NOT NULL DEFAULT 0,
  weight_val FLOAT NOT NULL DEFAULT 0,
  conf_val FLOAT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (pseudo_id, segment_code)
);
```

### 4.4 Domináns preferenciák
```sql
CREATE TABLE wp_impactshop_segment_prefs (
  pseudo_id VARCHAR(32) NOT NULL,
  segment_code VARCHAR(32) NOT NULL,
  score INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (pseudo_id, segment_code)
);
```
**Időzóna:** minden `created_at`/`updated_at` UTC-ben tárolandó.

## 5) Offerwall provider – saját kérdőív

### 5.1 Provider konfiguráció (admin)
- `provider_id`: `internal_survey`
- `enabled`: true
- `iframe_url`: saját kérdőív URL
- `postback_secret`: erős, egyedi
- **Secret rotáció:** 90 nap, átmeneti dual‑secret támogatással
- `signature_param`: `signature`
- `allow_ips`: survey backend IP

### 5.2 Postback payload (server‑to‑server)
```
POST /wp-json/impact/v1/offerwall/callback/internal_survey
{
  "transaction_id": "survey-20260204-001",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707043200,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-xxxx-b1",
  "question_count": 5,
  "categories": ["KN_ENERGY", "ATT_CARE", "BEH_WASTE", "KN_CLIM", "ATT_EFFI"],
  "answers": {"KN_ENERGY":"B", "ATT_CARE":"D", "BEH_WASTE":"C", "KN_CLIM":"A", "ATT_EFFI":"B"},
  "consent_pers": 1,
  "request_id": "uuid"
}
```
- **Idempotencia:** `transaction_id` egyedi.
- **Idempotencia kulcs:** `provider_id + transaction_id + survey_id`.
- **HMAC:** kötelező (canonical string: UTF‑8, trim, pipe‑separated).
- **Opcionális API key:** low‑scope header a postbacknél (gyors revoke/rotate).
- **IP allowlist:** kötelező.
- **Timestamp:** kötelező, ±5 perc tolerancia.
- **transaction_id formátum:** regex ellenőrzés (pl. `^survey-\\d{8}-[A-Za-z0-9]+$`).

### 5.3 Iframe hozzáférés védelem
- **Signed token/JWT** az iframe URL-ben (query paraméter).
- Token payload: `pseudo_id`, `exp` (15 perc), `survey_id`.
- Token validálás HMAC‑cal (survey_token_secret vagy api_key).

## 6) Szegmentációs feldolgozás

### 6.1 Input validáció
- `question_count` <= 5.
- `answers` PII‑mentes (tiltott: email, név, tel, cím, szabad szöveg).
- `categories` szerepeljen a `question_mapping.csv`‑ben.
- `answers` JSON integritás: kulcsok száma = `question_count`.
- `answers` normalizáció: kulcsok rendezése, valid A–D értékek.
- `timestamp` valid (±5 perc).
- `transaction_id` format valid.

### 6.2 Mapping
- `question_mapping.csv` alapján:
  - `segments_updated`
  - `update_type`
  - `targetable` / `sensitivity`
- Betöltéskor taxonomy cross‑check (fail‑fast, invalid segment_code esetén).

### 6.3 Update típusok
- **Direct assignment**: `PROFILE_AGE`, `PROFILE_GEO`.
- **Scaled −2…+2**: `ATT_*`.
- **Frequency 0–3**: `BEH_*`.
- **Correct +1**: `KN_*`.
- **Top‑3**: `MOT_*`, `DON_*`, `CAUSE_*`, `FMT_*`.
- **Stage direct**: `STG‑SUS`, `STG‑DON`.

### 6.4 Scoreboard számítás
- `sum_val`, `weight_val` frissítése.
- `conf = min(1, weight_val / W_TARGET)` (W_TARGET=12).
- Level mapping L0–L5 0–100 skálán.

### 6.5 Rate limiting
- Max 10 completion / user / óra (postback oldal).
- 429 Too Many Requests + `Retry-After`.

## 7) Consent + Targeting szabályok
- **Consent gate:** segment update csak `CONS-PERS=1` esetén.
- **Consent gate timing:** a callback elején ellenőrizni, scoring előtt.
- **Partner targeting:** csak `targetable=safe` + `sensitivity=low`.
- **k‑anonymity:** partner query csak 500+ user.
- **High/internal** (SES, EDU, CONS, PRIV) partner felé tiltott.
- **k‑anonymity konfigurálható** (default 500, admin beállítás).

## 8) Admin / monitoring
- Admin listázás: utolsó 20 survey completion.
- Fraud log: invalid signature / IP.
- Dashboard widget: completions/day, pending/reversed.
- IP allowlist módosítások audit log + értesítés.
- Fraud metrikák: invalid signature %, rate limit hit %, PII detection %.
- Synthetic E2E monitor: óránként 1 teszt flow.
- Consent változás audit trail (előtte/utána érték, timestamp, source).

## 9) Tesztelési terv
1) **Valid postback**: 200 + jutalom.
2) **Invalid signature**: 403.
3) **Duplikált transaction_id**: duplicate.
4) **IP tiltás**: 403.
5) **Segment update**: kérdés → megfelelő segment változik.
6) **Consent off**: nem frissül segment.
7) **Partner filter**: csak safe/low szegmensek átadása.

## 10) Rollback
1) Provider disabled.
2) Postback secret törlés.
3) Survey UI eltávolítás (iframe link).

## 11) Implementációs lépések
1) `internal_survey` provider beállítás.
2) Survey backend UI (max 5 kérdés, single choice).
3) Postback HMAC + allowlist.
4) DB migrációk (answers + segment scores).
5) Mapping loader (CSV betöltés + validáció).
6) Scoring engine.
7) Consent gate + targetable filter.
8) Staging teszt.
9) Prod rollout.

---

# Koherencia és biztonsági vizsgálat

## Talált hiányosságok
1) **CSV verziózás**: nincs rögzített mapping/taxonomy verzió.
2) **Payload mezőeltérés**: a planban `question_category`/`user_id`, a megvalósítás `categories`/`pseudo_id`.
3) **Token secret forrás**: nincs rögzítve, miből képezünk iframe tokent (survey_token_secret/api_key).
4) **Adatmegőrzés**: nincs rögzített policy (időkorlát/aggregált retention).

## Javítások beépítve a tervbe
- Payload mezők egységesítése `pseudo_id` + `categories` irányba.
- Token secret forrás rögzítése (survey_token_secret, fallback: api_key).
- Adatmegőrzési policy explicit (időkorlát + aggregált retention).
- `mapping_version` mező az answer recordban + `request_id` naplózás.

---

# Véglegesített implementációs terv (javított)

## Adatmodell frissítés (javítva)
- A `survey_version`, `mapping_version`, `request_id` mezők és az `uniq_user_survey` constraint már a 4.2 táblában szerepelnek.

## Consent tárolás
- CONS‑PERS és CONS‑ADS segmenteket is a scoreboard táblában tartjuk.
- Update csak CONS‑PERS=1 esetén (jutalom után, scoring előtt).

## CSV verziózás
- `mapping_version` és `taxonomy_version` rögzítése WP optionben.
- Betöltésnél log: checksum + verzió.
- Backfill/rescore csak maintenance window-ban, throttlinggal (batch size).

## Postback log
- `request_id` mentése completion recordban.


---

# Végrehajtási checklist (owner/ETA)

| Step | Feladat | Owner | ETA | Status |
|---|---|---|---|---|
| 1 | `internal_survey` provider beállítás (iframe_url, secret, allowlist) | Ops | T+1 | pending |
| 2 | Survey backend UI (max 5 Q, single choice) | Backend | T+3 | pending |
| 3 | Postback HMAC + allowlist implementáció | Backend | T+3 | pending |
| 4 | DB migrációk (answers + segment scores + prefs) | Backend | T+2 | pending |
| 5 | Mapping loader (CSV + checksum/versions) | Backend | T+3 | pending |
| 6 | Scoring engine (KN/ATT/BEH/MOT/CAUSE/STG) | Backend | T+4 | pending |
| 7 | Consent gate + targetable filter | Backend | T+4 | pending |
| 8 | Admin monitoring + fraud log | Backend | T+5 | pending |
| 9 | Staging tesztcsomag | QA | T+6 | pending |
| 10 | Prod rollout + rollback plan | Ops | T+7 | pending |

---

# Risk / Rollback táblázat

| Kockázat | Hatás | Mitigáció | Rollback |
|---|---|---|---|
| Postback csalás (signature nélkül) | Jogosulatlan reward | HMAC + allowlist + idempotencia | Provider disable, secret revoke |
| Duplikált transaction_id | Dupla jóváírás | UNIQUE (provider, transaction_id) | Completion rekordok törlése + re-run |
| Consent gate hiánya | Privacy compliance sérül | CONS‑PERS gate | Segment update kikapcsolás |
| CSV mapping drift | Rossz szegmentáció | mapping_version + checksum log | Mapping revert + újra számolás |
| Survey backend leáll | Reward nem érkezik | Retry/backoff, manual resend | Provider disable |
| Partner targeting túlszűrés | Üres szegmensek | k‑anonymity fallback | Targeting limit rollback |
| PII bekerülés | GDPR kockázat | validation + PII filter | Survey response purge |


---

# Tesztelési lépések – részletesen

## 1) Postback validáció
1. Küldj **valid** postbacket HMAC‑kal → 200 + `status: ok`.
2. Küldj **invalid signature** postbacket → 403 + audit log bejegyzés.
3. Küldj **duplikált transaction_id** postbacket → `status: duplicate`.
4. Küldj postbacket nem allowlistelt IP‑ről → 403.
5. Küldj **invalid timestamp** (régi/jövőbeli) → 400.
6. Küldj **invalid transaction_id format** → 400.
7. Küldj **rate limit** feletti postbacket → 429.

## 2) Reward jóváírás
1. Valid postback után pont/szavazat **+10/+10**.
2. Ellenőrizd a `impactshop_ads_user_votes` frissítést.
3. Ellenőrizd a `points` ledger bejegyzést (dedupe kulcs).

## 3) Survey válasz tárolás
1. `wp_impactshop_survey_answers` rekord létrejön.
2. `question_count` <= 5.
3. `survey_version` + `mapping_version` kitöltött.
4. `answers` JSON integrity ok (kulcsok száma egyezik).

## 4) Segment scoring
1. `question_category` → `question_mapping.csv` alapján frissül.
2. `sum_val` és `weight_val` nő.
3. `conf_val` nő (min(1, w/W_TARGET)).
4. L0–L5 szint helyes.

## 5) Consent gate
1. `CONS-PERS=0` esetén **nem** frissül segment.
2. `CONS-PERS=1` esetén frissül.

## 6) Targeting filter
1. `safe+low` szegmensek jönnek át.
2. `internal/high` szegmensek kiszűrve.
3. k‑anonymity (500+) működik.

## 7) Monitoring / log
1. Invalid signature logolja request_id‑t.
2. Rate limit log bejegyzés (ha túllépés).
3. JWT token lejárat → iframe tiltás.
4. Golden payload regressziós csomag (valid/invalid payloadok, verziózva).


---

# Staging smoke checklist
- Provider `internal_survey` active, iframe URL reachable.
- Valid postback → reward +10/+10 + completion record.
- Invalid signature → 403 + log.
- Duplikált transaction_id → duplicate.
- Segment update történik CONS‑PERS=1 esetén.
- Consent off → nincs segment update.
- UI kártya látszik a Feladatok listában.

# Prod readiness checklist
- Secrets/allowlist éles értékekkel beállítva.
- CSV mapping/taxonomy verzió rögzítve és checksum mentve.
- k‑anonymity threshold (>=500) konfigurálva.
- Admin monitoring + fraud log ellenőrizve.
- Rollback lépések dokumentálva és kipróbálva stagingen.

---

## Beépített AI javaslatok (összegzés)
- HMAC‑SHA256 payload formátum rögzítve.
- Timestamp validáció + transaction_id regex.
- Postback rate limiting (10/user/óra) + 429.
- Survey completion cap: UNIQUE (pseudo_id, survey_id).
- JWT/signed token az iframe‑hez.
- PII regex detektálás + elutasítás.
- Allowlist audit log + értesítés.
- k‑anonymity küszöb konfigurálható.
- CSV mapping/taxonomy consistency check (fail‑fast).
- Fraud dashboard metrikák.
- CSP + Referrer‑Policy a survey backendnél.
- JSON integrity check.
- Circuit breaker: UI rejtés hibaarány esetén.
- Data retention: cold storage archiválás, **nem törlés**.

---

# Végső koherencia és biztonsági ellenőrzés (összefoglaló)
- **Koherencia OK:** survey scoring és offerwall reward flow összehangolt (consent gate előtt nincs scoring, reward független).
- **Biztonság OK:** HMAC + allowlist + timestamp + rate limit + idempotencia együtt véd a replay/abuse ellen.
- **Adatvédelem OK:** PII tiltott, consent gate kötelező, targetable filter és k‑anonymity beállítva.
- **Operációs OK:** monitoring, fraud metrikák, synthetic check és rollback lépések definiálva.

---

# 🤖 CODEX JAVASLATOK (2025-01-18)

## A. Privacy / GDPR / DSA kiegészítések

### A.1 Differential Privacy aggregátumoknál
> **Javaslat:** Partner API válaszokban érdemes Laplace‑zajt hozzáadni a szegmens méretekhez (pl. ε=1.0 differential privacy). Ez megakadályozza a pontos user‑szám visszafejtését ismételt lekérdezésekkel.
>
> **Helyszín:** Partner targeting query (7. fejezet).
>
> **Implementáció:**
> ```php
> // Laplace zaj hozzáadása szegmens mérethez
> function add_dp_noise($count, $epsilon = 1.0) {
>     $sensitivity = 1; // egy user hozzáadása/eltávolítása
>     $scale = $sensitivity / $epsilon;
>     return max(0, round($count + laplace_sample($scale)));
> }
> ```

### A.2 DSA Art. 26–27 transzparencia
> **Javaslat:** A DSA (Digital Services Act) Art. 26–27 előírja a reklám‑célzási paraméterek nyilvánosságra hozatalát. Legyen egy `/transparency` végpont, amely visszaadja a felhasználó célzásához használt szegmenseket (nem az értékeket, csak a kategóriákat).
>
> **Helyszín:** Új végpont a 8. Admin/monitoring fejezethez.
>
> **Példa response:**
> ```json
> {
>   "targeting_categories": ["AGE", "GEO", "KN"],
>   "last_updated": "2025-01-15T12:00:00Z"
> }
> ```

### A.3 Minor (18 év alatti) védelem
> **Javaslat:** Ha `AGE-A1` (13–17 év) szegmensbe kerül valaki, automatikusan `targetable=no` a partner targeting szempontjából. GDPR és DSA is különleges védelmet ír elő.
>
> **Helyszín:** 6.3 Update típusok / Direct assignment.
>
> **Szabály:**
> ```
> IF segment_code = 'AGE-A1' THEN targetable = 'no', sensitivity = 'high'
> ```

### A.4 Randomized Response Technique (RRT) szenzitív kérdésekhez
> **Javaslat:** Szenzitív kérdéseknél (pl. jövedelem, egészség) a válasz randomizálható kliens oldalon, és statisztikai dekódolás szerver oldalon. Ez védi a felhasználót még adat‑breach esetén is.
>
> **Alkalmazhatóság:** Jelenleg nincs szenzitív kérdéskategória tervezve, de ha lesz `SES_*` vagy `HEALTH_*`, kötelező megfontolni.

## B. Szegmens taxonómia pontosítások

### B.1 Hiányzó szegmens kódok a tervből
> **Javaslat:** A user kutatásban szereplő részletes taxonómia nincs teljes egészében leírva a tervben. Érdemes referenciaként csatolni vagy inline listázni:
>
> - **AGE:** A1 (13–17), A2 (18–24), A3 (25–34), A4 (35–44), A5 (45–54), A6 (55–64), A7 (65+)
> - **GEO:** HU-PE (Pest), HU-BP (Budapest), HU-KM (Komárom-E), stb.
> - **KN (Knowledge):** L0–L5 confidence szintek
> - **ATT (Attitude):** S-3..S+3 skála (strong neg → strong pos)
> - **BEH (Behaviour):** F0–F4 frekvencia (never → weekly+)
> - **MOT/DON/CAUSE:** Top-3 ranking, score 3/2/1
> - **STG (Stage):** SUS (sustainer), DON (donor), VOL (volunteer), NEW (new)
>
> **Helyszín:** Új 6.5 alszekció: "Segment Code Reference".

### B.2 Segment dependency graph
> **Javaslat:** Bizonyos szegmensek logikailag függenek egymástól (pl. `STG-DON` feltételezi, hogy volt donation). Érdemes definiálni a dependency‑ket, hogy inkonzisztens állapotok ne alakuljanak ki.
>
> **Példa:**
> - `STG-DON` → requires at least 1 completed donation record
> - `BEH-SH-F3` (frequent sharer) → requires share event log

## C. Biztonsági kiegészítések

### C.1 Health check endpoint hiánya
> **Javaslat:** A staging‑qa‑suite és prod‑rollout checklist‑ben nincs health check végpont definiálva a survey backendhez. Legyen `/health` endpoint:
>
> ```json
> GET /health
> Response: { "status": "ok", "db": "connected", "provider": "internal_survey" }
> ```
>
> **Helyszín:** 11. Implementációs lépések – új step: "Health check endpoint implementáció".

### C.2 Postback retry logika a survey backendnél
> **Javaslat:** Ha a WP offerwall callback 5xx hibát ad, a survey backend próbálkozzon újra exponenciális backoff‑fal (max 3 retry, 10s/30s/90s). A jelenlegi terv nem definiálja a retry viselkedést.
>
> **Helyszín:** 5.2 Postback payload kiegészítése.

### C.3 HMAC canonical form dokumentálása
> **Javaslat:** A HMAC signature számítás canonical formáját explicit dokumentálni kell a provider integration guide‑ban. Jelenleg „pipe‑separated" van említve, de érdemes konkrét példát adni:
>
> ```
> message = "survey-20260204-001|pseudo123|1|1707043200"
> signature = HMAC-SHA256(message, secret)
> ```
>
> **Figyelem:** Ékezetes karakterek normalizálása (NFC), trailing whitespace strip.

### C.4 Secret storage
> **Javaslat:** A postback secret **nem** tárolható wp_options‑ban plaintextben. Használj `wp_salt()` alapú titkosítást vagy environment variable‑t.
>
> **Helyszín:** 5.1 Provider konfiguráció.

## D. Operációs kiegészítések

### D.1 Mapping verzió inkompatibilitás kezelése
> **Javaslat:** Ha a postback `mapping_version` nem egyezik a jelenlegi betöltött verzióval, a scoring skippelhető és loggolható „version_mismatch" flag‑gel. A válasz tárolódik, de a szegmens update nem fut le, amíg az admin nem futtat migration scriptet.
>
> **Helyszín:** 6.2 Mapping.

### D.2 Cold start / empty segment handling
> **Javaslat:** Első survey kitöltésnél a `conf_val` nagyon alacsony lesz (1/12). Érdemes definiálni, hogy mikor válik egy szegmens „érvényessé" targeting szempontjából (pl. `conf_val >= 0.5`).
>
> **Helyszín:** 6.4 Scoreboard számítás.

### D.3 Batch rescore mechanizmus
> **Javaslat:** Ha a mapping CSV frissül, szükség lehet a korábbi válaszok újra-pontozására. A terv említi a maintenance window‑ot és throttling‑ot, de nincs definiálva a batch méret és a rollback stratégia, ha a rescore hibázik.
>
> **Javasolt paraméterek:**
> - Batch size: 1000 user / iteráció
> - Sleep: 500ms batch‑ek között
> - Checkpoint: minden batch után mentés, rollback point
>
> **Helyszín:** CSV verziózás szekció.

## E. Tesztelési kiegészítések

### E.1 Consent state toggle teszt
> **Javaslat:** Tesztelni kell a consent state váltást mid‑session: ha a user CONS‑PERS=1 → 0 vált survey közben, a már begyűjtött, de még nem feldolgozott válaszok ne kerüljenek szegmentálásra.
>
> **Helyszín:** 9. Tesztelési terv – új 8. teszt.

### E.2 Load testing célértékek
> **Javaslat:** Nincs definiálva, mennyi postback/sec várható és mi az elfogadható latency. Javasolt baseline:
> - Normál: 10 postback/sec, p95 < 500ms
> - Peak: 100 postback/sec, p95 < 2000ms
>
> **Helyszín:** 9. Tesztelési terv – új „Load test" teszt.

### E.3 Golden dataset
> **Javaslat:** Létrehozni egy „golden dataset"‑et (10–20 curated survey response), amelyet minden release előtt lefuttatunk, és ellenőrizzük, hogy a segment scores pontosan az elvártnak megfelelőek.
>
> **Helyszín:** Tesztelési lépések – részletesen, 7. Monitoring/log kiegészítés.

---

# Összefoglalás – Codex javaslatok prioritása

| Prio | Javaslat | Terület | Komplexitás |
|------|----------|---------|-------------|
| P0 | Minor (AGE-A1) targeting tiltás | Privacy/GDPR | Alacsony |
| P0 | Secret storage (ne plaintext) | Security | Közepes |
| P1 | Health check endpoint | Operations | Alacsony |
| P1 | HMAC canonical form docs | Security | Alacsony |
| P1 | DSA transparency endpoint | Privacy/DSA | Közepes |
| P2 | Differential privacy aggregátumokhoz | Privacy | Magas |
| P2 | Postback retry logika | Reliability | Közepes |
| P2 | Mapping verzió inkompatibilitás | Operations | Közepes |
| P3 | Segment dependency graph | Data model | Magas |
| P3 | Load testing baseline | Testing | Közepes |
| P3 | RRT szenzitív kérdésekhez | Privacy | Magas (jövőbeli) |

---

## Opus javaslatok (2026-02-05)

### F. Security kiegészítések

#### F1. JWT token revocation lista
A jelenlegi terv nem tartalmaz token blacklist/revocation mechanizmust. Ha egy JWT kompromittálódik, nincs mód az érvénytelenítésre a lejárat előtt.

**Javaslat:** Redis-alapú revocation lista a `jti` (JWT ID) claim-ekhez:
```php
// Token kibocsátáskor
$jti = bin2hex(random_bytes(16));
$payload['jti'] = $jti;

// Revocation check middleware-ben
if ($redis->sismember('revoked_tokens', $jti)) {
    throw new TokenRevokedException();
}
```

#### F2. Rate limit IP-alapú bypass védelem
A 10 request/user/hour limit megkerülhető proxy rotációval ha csak user-alapú. 

**Javaslat:** Kombinált limit:
- 10/user/hour (meglévő)
- 100/IP/hour (új)
- Device fingerprint tracking opcionálisan

#### F3. Answer tampering védelem
Survey válaszok küldésekor nincs integrity check - a kliens manipulálhat válaszokat.

**Javaslat:** Kérdés-válasz session binding:
```php
$session_hash = hash_hmac('sha256', 
    $question_id . $session_id . $expected_answer_format,
    $session_secret
);
// Válasznál verificálni
```

### G. Koherencia javítások

#### G1. Segment scoring és reward atomicitás
A tervben a segment scoring és reward jóváírás két külön lépés - partial failure esetén inkonzisztens állapot.

**Javaslat:** Database transaction wrapper:
```php
DB::transaction(function() use ($userId, $surveyId, $answers) {
    $this->updateSegmentScores($userId, $answers);
    $this->creditReward($userId, $surveyId);
    $this->logCompletion($userId, $surveyId);
});
```

#### G2. Segment calculation és reward credit szétválasztás
A survey completion egyszerre triggerel segment update-et és reward-ot. Ha a segment calculation logika változik, a reward is újraszámolódhat.

**Javaslat:** Explicit reward snapshot:
```sql
ALTER TABLE survey_completions ADD COLUMN reward_locked_at TIMESTAMP;
ALTER TABLE survey_completions ADD COLUMN reward_amount_locked INT;
```

#### G3. Consent verzió snapshot
A terv említi a consent gate-et, de nem specifikálja mi történik ha a consent policy változik egy survey közben.

**Javaslat:** Consent snapshot tárolás a survey session indításakor:
```php
$session->consent_version = ConsentPolicy::currentVersion();
$session->consent_snapshot = ConsentPolicy::currentText();
```

### H. Operations kiegészítések

#### H1. Health check endpoint specifikáció
A terv említi de nem részletezi. Konkrét implementáció:

```php
// GET /api/survey/health
return [
    'status' => 'ok',
    'postback_queue_depth' => Queue::size('postbacks'),
    'db_latency_ms' => $this->measureDbLatency(),
    'last_successful_postback' => PostbackLog::latest()->created_at,
    'segment_calculation_lag_seconds' => $this->getSegmentLag(),
];
```

#### H2. Audit log retention policy
A terv 7 éves GDPR retention-t említ, de nincs partitioning stratégia nagy volumen esetén.

**Javaslat:** Monthly partitioning:
```sql
CREATE TABLE survey_audit_log (
    ...
) PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at));
```

#### H3. Graceful degradation fallback-ek
Ha segment scoring service elérhetelenül válik, a survey ne akadjon el.

**Javaslat:** Async scoring queue + default segment fallback:
```php
try {
    $this->segmentService->updateScores($userId, $answers);
} catch (ServiceUnavailableException $e) {
    Queue::push(new DeferredSegmentUpdate($userId, $answers));
    Log::warning('Segment scoring deferred', ['user_id' => $userId]);
}
```

### Opus javaslatok prioritás táblázat

| Prio | Javaslat | Terület | Komplexitás |
|------|----------|---------|-------------|
| P0 | JWT revocation lista (F1) | Security | Közepes |
| P0 | Transaction atomicitás (G1) | Data integrity | Alacsony |
| P1 | Rate limit IP bypass védelem (F2) | Security | Közepes |
| P1 | Consent verzió snapshot (G3) | GDPR | Alacsony |
| P1 | Health check specifikáció (H1) | Operations | Alacsony |
| P2 | Answer tampering védelem (F3) | Security | Közepes |
| P2 | Reward snapshot (G2) | Data integrity | Alacsony |
| P2 | Graceful degradation (H3) | Reliability | Közepes |
| P3 | Audit log partitioning (H2) | Operations | Közepes |

---

## Gemini javaslatok (2026-02-05)

### I. Security & Compliance (Deep Dive)

#### I1. Szigorú CSP és Frame Védelem
A `JWT` token védi az endpointot, de az iframe környezet sérülékenységei ellen (pl. script injection, clickjacking) további védelem szükséges.

**Javaslat:**
- **CSP:** `Content-Security-Policy: default-src 'self'; script-src 'self'; frame-ancestors 'self' https://impactshop.hu;`
- **Clickjacking:** Mivel iframe-ben fut, a `X-Frame-Options` nem elég rugalmas, a CSP `frame-ancestors` direktíva kötelező a survey backend válaszaiban, hogy csak az offerwall domainje ágyazhassa be.

#### I2. Raw Answer Retention Policy
A terv "korlátlan" adatmegőrzést ír a `answers_json` mezőre. Bár PII mentes, a raw válaszok korlátlan tárolása növeli az adatvagyon kockázatát és a DB méretét.

**Javaslat:** 
- **Aggregation-only:** 12 hónap után a `answers_json` tartalmának törlése (NULL), csak a `segment_scores` és a `survey_completions` metaadat (time, survey_id) maradjon meg.
- Ez csökkenti a jogi kitettséget profilozás esetén.

### J. Reliability & Edge Cases

#### J1. Dead Letter Queue (DLQ)
A postback retry logika (C2, Opus) fontos, de mi történik, ha a 3. retry is sikertelen (pl. validációs hiba vagy tartós leállás)?

**Javaslat:**
- Sikertelen retry-k után a payload kerüljön egy `wp_impactshop_postback_dlq` táblába.
- Admin felületen "Replay" gomb a javított/későbbi újraküldéshez.

#### J2. Session Expiry UX
A JWT token 15 perces lejárata (5.3) problémás lehet lassú kitöltőknél. Ha a token lejár submit előtt, a user munkája elveszik.

**Javaslat:**
- A UI (iframe) 14 percnél küldjön egy "keepalive" kérést (ha van user aktivitás), vagy
- A JavaScript kliens mentse `localStorage`-ba a válaszokat, és hiba esetén ajánlja fel az újratöltést/beküldést friss tokennel.

### K. Koherencia vizsgálat

#### K1. Mapping Version Race Condition
Opus G3 (Consent snapshot) és D1 (Mapping version mismatch) kiváló, de hiányzik a *tényleges* mapping lockolás. Ha a survey kitöltés közben változik a mapping CSV a szerveren, a user még a régi kérdéseket látja, de az új mapping szerint pontozódik.

**Javaslat:**
- A `survey_id` tartalmazza a verziót is implicit módon (pl. `survey-001-v2`), VAGY
- A mapping engine a `created_at` timestamp alapján válassza ki a *történetileg helyes* mapping verziót visszamenőlegesen is.

### Gemini Prioritás Táblázat

| Prio | Javaslat | Terület | Komplexitás |
|------|----------|---------|-------------|
| P1 | CSP & Frame Ancestors (I1) | Security | Alacsony |
| P1 | Dead Letter Queue (J1) | Reliability | Közepes |
| P2 | Raw Answer Retention (I2) | Compliance | Alacsony |
| P2 | Session Expiry UX (J2) | UX | Közepes |
| P3 | Mapping Version History (K1) | Consistency | Magas |

---

## Codex javaslatok (2026-02-05)

### L. Biztonsági kiegészítések

#### L1. Postback replay detektálás rövid TTL-lel
Az idempotencia jó, de signature replay támadásnál ugyanaz a payload többször is érkezhet a TTL ablakon belül. Érdemes a signature hash-t rövid ideig megőrizni.

**Javaslat:**
- `signature_hash = sha256(signature + timestamp)`
- TTL store (Redis vagy DB) 10 percig
- Ha `signature_hash` már létezik → `409 replay_detected`

#### L2. Survey schema whitelist validáció
Az `answers_json` integritás ellenőrzése megvan, de nincs explicit survey‑verzióhoz kötött schema/ID whitelist. Ismeretlen kérdés/ID esetén jelenleg átcsúszhat a validáció.

**Javaslat:**
- `survey_version` → kérdéslista whitelist (survey definition snapshot)
- Ismeretlen kérdés vagy válasz érték → 400 + fraud log

#### L3. Log redaction szabály
Hibaeseteknél (invalid signature, payload parse error) könnyen bekerülhet a raw `answers_json` az alkalmazás logba.

**Javaslat:**
- Logokban csak `survey_id`, `transaction_id`, `question_count` szerepeljen
- `answers_json` *soha* ne kerüljön logba (redaction policy)

### M. Koherencia / Adatintegritás

#### M1. Survey definition snapshot hash
Ha a survey UI tartalma változik (A/B vagy gyors javítás), a későbbi audit során nem rekonstruálható, hogy pontosan melyik kérdésszett futott.

**Javaslat:**
- `survey_definition_hash` (pl. SHA256) mentése a completion recordba
- Hash alapján visszakereshető és auditálható a survey‑verzió

#### M2. Reward ledger dedupe kulcs bővítés
Jelenleg a dedupe a completion táblán történik, de a reward ledger-ben is érdemes egyedi kulcsot tartani a dupla jóváírás ellen.

**Javaslat:**
- UNIQUE (`provider_id`, `transaction_id`, `survey_id`, `reward_type`)
- Retry esetén ledger `duplicate` és no-op

### N. Operációs kiegészítések

#### N1. Rescore dry-run mód
CSV mapping frissítésnél a batch rescore kockázatos; jó lenne egy dry‑run mód, ami csak diff‑statot készít.

**Javaslat:**
- Dry‑run: csak `diff summary` (pl. mennyi user score változna, top 10 eltérés)
- Valódi rescore csak admin jóváhagyással

#### N2. Anomália riasztás survey completion spike-ra
Ha 10x emelkedik a completion rate, az fraud vagy bot aktivitás lehet.

**Javaslat:**
- Riasztás: baseline + 3σ (moving window)
- Automatikus throttling a survey providerre (ideiglenes 429)

### Codex Prioritás Táblázat

| Prio | Javaslat | Terület | Komplexitás |
|------|----------|---------|-------------|
| P1 | Replay detektálás TTL-lel (L1) | Security | Alacsony |
| P1 | Schema whitelist validáció (L2) | Security | Közepes |
| P2 | Log redaction policy (L3) | Security | Alacsony |
| P2 | Survey definition hash (M1) | Auditability | Alacsony |
| P2 | Reward ledger dedupe (M2) | Data integrity | Alacsony |
| P3 | Rescore dry-run (N1) | Operations | Közepes |
| P3 | Completion spike alert (N2) | Reliability | Közepes |
