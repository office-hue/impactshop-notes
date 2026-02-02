# Partner webhook SLA + szerződés (non‑affiliate)

## Cél
Egységes, auditálható webhook‑szerződés és SLA a non‑affiliate partnerekhez, hogy minden tranzakció **azonosítható**, **vitathatató** és **biztonságos** legyen.

---

## 1) Webhook szerződés (contract)
### 1.1 Kötelező endpoint
`POST /impact/v1/partner/transaction`

### 1.2 Kötelező header mezők
- `Authorization: Bearer <partner_api_key>`
- `X-Impact-Signature: sha256=<hmac>`
- `Idempotency-Key: <uuid>`
- `X-Impact-Timestamp: <unix_ms>`

### 1.3 Kötelező payload mezők
```json
{
  "partner_code": "shopify-abc",
  "event_id": "order_123456",
  "event_type": "purchase|booking|retail",
  "pseudo_id": "ab12cd34ef56",
  "ngo_code": "bator-tabor",
  "amount_gross": 19990,
  "currency": "HUF",
  "timestamp": "2026-01-23T10:30:00Z",
  "payment_status": "paid|unpaid|refunded"
}
```

### 1.4 Opcionális mezők (ajánlott)
- `discount_tier`
- `discount_rate`
- `partner_max_discount`
- `amount_net`
- `meta` (JSON)

---

## 2) HMAC aláírás (biztonság)
### 2.1 Aláírás képlet
```
base_string = METHOD + "\n" + PATH + "\n" + BODY + "\n" + TIMESTAMP
hmac = HMAC_SHA256(secret, base_string)
header = "sha256=" + hex(hmac)
```

### 2.2 Signature validáció
- `TIMESTAMP` max ±5 perc eltérés
- hibás signature → `401 invalid_signature`

---

## 3) Idempotencia szabályok
- `Idempotency-Key` kötelező
- duplikált kulcs → `200 duplicate` (nem könyvel újra)
- dedupe kulcs: `partner_code + event_id`

---

## 4) SLA és retry policy
### 4.1 SLA
- **Fogadási SLA**: 99.5% havi elérhetőség
- **Válaszidő**: p95 < 500 ms

### 4.2 Retry policy (partner oldalon)
- 5 próbálkozás
- exponenciális backoff: 1s → 2s → 4s → 8s → 16s
- timeout: 5s

---

## 5) Válaszkódok
| HTTP | Code | Meaning | Action |
| --- | --- | --- | --- |
| 200 | `accepted` | könyvelve | stop |
| 200 | `duplicate` | már volt | stop |
| 400 | `invalid_payload` | hibás adat | fix payload |
| 401 | `invalid_signature` | hibás HMAC | fix secret |
| 403 | `unauthorized` | API kulcs hiba | fix key |
| 409 | `conflict` | státusz ütközés | re‑query |
| 422 | `invalid_state` | pl. refund -> purchase nélkül | review |
| 429 | `rate_limited` | túl sok request | retry backoff |
| 500 | `server_error` | ImpactShop hiba | retry |

---

## 6) Válasz payload
```json
{
  "status": "accepted|duplicate|rejected",
  "ledger_id": "ldg_123456",
  "event_id": "order_123456",
  "partner_code": "shopify-abc",
  "message": "ok"
}
```

---

## 7) Audit & bizonyíthatóság
- `ledger_id` minden válaszban
- `proof_hash` tárolás (audit log)
- Dispute flow: `POST /impact/v1/partner/dispute`

---

## 8) Verziózás
- header: `X-Impact-API-Version: v1`
- breaking változás előtt 90 napos notice

---

## 9) Partner onboarding checklist
- API kulcs generálás
- HMAC secret átadás
- Webhook teszt (`/impact/v1/partner/transaction`)
- Dispute teszt
- Dashboard hozzáférés
