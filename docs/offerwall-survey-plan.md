# Feladatok (kérdőívek) – biztonságos interim megoldás terv

## Cél
Amíg a külső offerwall providerek nincsenek bekötve, lehessen **saját kérdőíveket** betenni a Feladatok (offerwall) szekcióba úgy, hogy:
- ne lehessen csalni pont/szavazat jóváírást,
- a meglévő offerwall infrastruktúrához illeszkedjen,
- egyszerűen visszavonható legyen.

## Kiindulási állapot
- A Feladatok UI az `impactshop_offerwall` shortcode-on keresztül jelenik meg.
- A pont/szavazat jóváírás a **postback** REST végponton történik:
  - `GET|POST /wp-json/impact/v1/offerwall/callback/{provider}`
- A postback validáció **opcionális** (signature és allowlist nélkül nem biztonságos).

## Rögzített üzleti döntések
- **Target ID:** `impactad` (szám nélkül).
- **Kérdésszám:** maximum 5 kérdés / kérdőív.
- **Kérdéstípus:** csak single choice (egy válasz).
- **Jutalom:** 10 pont + 10 szavazat / kitöltés.
- **Adatmegőrzés:** korlátlan (PII nélkül, kérdések és válaszok nem személyes adatok).

## Kockázatok
- **Sima űrlap/link** (pl. Google Forms) nem biztonságos: bárki hamis postbacket küldhet.
- **Client‑side trigger** (JS) nem biztonságos: könnyen hamisítható.

## Biztonságos megoldás (ajánlott)
**Belső provider + szerver‑oldali postback**.

### 1) Új belső provider
Hozzunk létre egy új providert az Offerwall adminban (pl. `internal_survey`):
- `enabled`: true
- `iframe_url`: saját kérdőív URL (belső oldal vagy saját domain)
- `postback_secret`: erős, egyedi secret
- `signature_param`: pl. `signature`
- `allow_ips`: a kérdőív backend IP‑jei
- `user_param`: `user_id` vagy `ext_user_id`
- `points_multiplier`, `votes_multiplier`: kezdetben `1.0`

### 2) Szerver‑oldali postback (kötelező)
A kérdőív backendje **szerver‑szerver** POST‑ot küld az Offerwall callbackre, HMAC‑kal:

**Endpoint**
```
/wp-json/impact/v1/offerwall/callback/internal_survey
```

**Kötelező paraméterek**
- `transaction_id` – egyedi azonosító (idempotencia)
- `user_id` / `pseudo_id` – felhasználó azonosító
- `payout` – pont/szavazat számítás alapja
- `signature` – HMAC a `transaction_id`‑ből és a secretből

**Signature példa**
```
signature = HMAC_SHA256(transaction_id, POSTBACK_SECRET)
```

**Javasolt payload példa**
```json
{
  "transaction_id": "survey-20260202-00123",
  "user_id": "abc123pseudo",
  "payout": 1.5,
  "currency": "USD",
  "offer_id": "survey-01",
  "offer_name": "Impact kérdőív #1",
  "signature": "..."
}
```

### 3) Idempotencia
Az offerwall DB-ben már van egyedi index:
```
UNIQUE KEY uniq_provider_tx (provider, transaction_id)
```
Ez védi a duplikált jóváírást.

### 4) IP allowlist
`allow_ips` kötelező legyen. Csak a kérdőív backendről fogadunk postbacket.

## UI/UX – hogyan jelenjen meg
- A Feladatok kártyák az offerwall config alapján generálódnak.
- Ha `internal_survey` aktív, megjelenik a listában (név alapján).
- A felhasználó kattintás után a saját kérdőív iframe‑ben nyílik meg.

## Minimális biztonsági követelmények (Go/No‑Go)
Go csak akkor, ha:
1) **Signature validáció** aktív (secret beállítva)
2) **IP allowlist** beállítva
3) **Unique transaction_id** képződik minden kitöltéshez

## Tesztelési terv
1) **Staging postback**: valid signature → `status: ok`
2) **Invalid signature**: 403
3) **Duplikált transaction_id**: `status: duplicate`
4) **IP tiltás**: 403 + fraud log
5) **UI**: Feladatok kártya megjelenik + iframe nyílik

## Rollback terv
1) Offerwall adminban `internal_survey` provider **disabled**
2) (Opcionális) provider törlése az optionből
3) Postback secret törlése

## Megvalósítási lépések (rövid)
1) Offerwall admin: `internal_survey` létrehozása
2) Kérdőív backend: postback endpoint implementálása + HMAC
3) Allowlist beállítása
4) Staging tesztek
5) Production élesítés

## Backend specifikáció (rövid)
- **Kérdőív azonosító:** `survey_id` (slug).
- **Target ID:** `impactad`.
- **Kérdések:** max 5, **single choice**.
- **Válasz mentés:** `survey_id`, `target_id`, `pseudo_id`, `answers_json`, `created_at`.
- **Jutalom:** fix 10 pont + 10 szavazat.

## Biztonsági és koherencia vizsgálat (eredmény + javítás)
### Talált kockázatok
1) **Sima iframe/URL** esetén nincs hiteles kitöltés → csalás.
2) **Signature nélkül** a postback endpoint hamisítható.
3) **IP allowlist nélkül** a postback bárhonnan hívható.
4) **ID alapú célzás** hiányában későbbi statisztika nem köthető targethez.

### Javítások a tervben
- Kötelező **szerver‑oldali postback** HMAC‑kal.
- Kötelező **IP allowlist**.
- **Target ID rögzítve**: `impactad`.
- **Kérdéstípus szűkítve**: single choice.
- **Jutalom rögzítve**: 10 pont + 10 szavazat.

## Nyitott kérdések
- Hol fut a kérdőív backend (domain + IP)?
- Hol tároljuk a postback secretet?
