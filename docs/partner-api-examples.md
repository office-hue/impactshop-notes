# Partner API – Request/Response példák

## 1) Tranzakció webhook
### Request
```http
POST /wp-json/impact/v1/partner/transaction
Authorization: Bearer PARTNER_API_KEY
X-Impact-Signature: sha256=...
Idempotency-Key: 52d0b1a8-4e6a-4b43-9a9f-2d9c2b6b4d88
X-Impact-Timestamp: 1737628500000
Content-Type: application/json
```
```json
{
  "partner_code": "shopify-abc",
  "event_id": "order_123456",
  "event_type": "purchase",
  "pseudo_id": "ab12cd34ef56",
  "ngo_code": "bator-tabor",
  "amount_gross": 19990,
  "currency": "HUF",
  "timestamp": "2026-01-23T10:30:00Z",
  "payment_status": "paid",
  "discount_tier": "gold",
  "discount_rate": 0.16,
  "partner_max_discount": 0.20,
  "amount_net": 16792
}
```

### Response (accepted)
```json
{
  "status": "accepted",
  "ledger_id": "ldg_123456",
  "event_id": "order_123456",
  "partner_code": "shopify-abc",
  "message": "ok"
}
```

### Response (duplicate)
```json
{
  "status": "duplicate",
  "ledger_id": "ldg_123456",
  "event_id": "order_123456",
  "partner_code": "shopify-abc",
  "message": "already processed"
}
```

---

## 2) Kedvezmény kalkuláció
### Request
```http
POST /wp-json/impact/v1/partner/discount/quote
Authorization: Bearer PARTNER_API_KEY
X-Impact-Signature: sha256=...
X-Impact-Timestamp: 1737628500000
Content-Type: application/json
```
```json
{
  "partner_code": "shopify-abc",
  "pseudo_id": "ab12cd34ef56",
  "amount_gross": 19990,
  "currency": "HUF"
}
```

### Response
```json
{
  "tier": "gold",
  "partner_max_discount": 0.20,
  "discount_rate": 0.16,
  "discount_amount": 3198,
  "amount_net": 16792,
  "explain": "Gold szint → 80% a max kedvezményből"
}
```

---

## 3) Dispute nyitás
### Request
```http
POST /wp-json/impact/v1/partner/dispute
Authorization: Bearer PARTNER_API_KEY
X-Impact-Signature: sha256=...
X-Impact-Timestamp: 1737628500000
Content-Type: application/json
```
```json
{
  "ledger_id": "ldg_123456",
  "event_id": "order_123456",
  "partner_code": "shopify-abc",
  "reason": "refund_not_reflected",
  "details": "Customer refunded, please mark declined."
}
```

### Response
```json
{
  "status": "opened",
  "dispute_id": "dsp_889900",
  "ledger_id": "ldg_123456"
}
```

---

## 4) Error response (általános)
```json
{
  "code": "invalid_payload",
  "message": "Missing required field: event_id",
  "details": {
    "field": "event_id"
  }
}
```


## 5) Pontozási contractminta (szintetikus, lane OFF)

Az 1. példa legacy ledgerkérés, önmagában nem pontozható. A teljes body mintája:

```json
{
  "partner_code": "synthetic-shop",
  "event_id": "order_123456",
  "event_type": "purchase",
  "pseudo_id": "ab12cd34ef56",
  "ngo_code": "synthetic-ngo",
  "amount_gross": 19990,
  "currency": "HUF",
  "timestamp": "2026-10-29T16:01:00Z",
  "payment_status": "unpaid",
  "original_purchase_id": "order_123456",
  "original_purchase_at": "2026-10-29T16:00:00Z",
  "donation_amount_huf": "0",
  "donation_policy_version": "merchant-approved-v1",
  "revision": "1",
  "attribution_token": "<SERVER_ISSUED_SAT1>"
}
```

Minden érték szintetikus; a token helykitöltő, nem érvényes SAT1 és nem valós hozzárendelés. Véglegesítés: ugyanaz a vásárlás/idő/token, `payment_status=paid`, `revision=2`, a szerződés szerinti végleges összeg például `"100.50"`. Teljes refund: ugyanaz a vásárlás/idő, `payment_status=refunded`, `revision=3`, `refund_scope=full`, az összegmező továbbra is decimális string. Részleges korrekció `refund_scope=partial`: megőrzött kézi eset.

Aláírás: `sha256=` + a **pontosan elküldött nyers body** HMAC-SHA256 hex digestje a partner HMAC-kulcsával. A jelenlegi fogadó nem a method/path/timestamp konkatenációját ellenőrzi. A külön `X-Impact-Timestamp` aktuális Unix milliszekundum, ±5 perc toleranciával; Bearer API-kulcs is kell. Titok nem kerül forrásba vagy logba. Státuszváltásnál új Idempotency-Key; változatlan kérés ismétlésekor az eredeti kulcs és body.

A 200-as ACK nem bizonyít jóváírást: azt a tárolt pontesemény és a ponttranzakció együtt igazolja. A lane OFF mellett nincs automatikus jóváírás. Valódi kereskedő, szerződés és aktiválás későbbi munkacsomag.
