# Partner pilot tesztek – parancsok és ellenőrzés

## 1) Tranzakció webhook (accepted)
```bash
curl -X POST "https://app.sharity.hu/wp-json/impact/v1/partner/transaction" \
  -H "Authorization: Bearer PARTNER_API_KEY" \
  -H "X-Impact-Signature: sha256=..." \
  -H "X-Impact-Timestamp: 1737628500000" \
  -H "Idempotency-Key: 11111111-1111-1111-1111-111111111111" \
  -H "Content-Type: application/json" \
  -d '{"partner_code":"shopify-abc","event_id":"order_123456","event_type":"purchase","pseudo_id":"ab12cd34ef56","ngo_code":"bator-tabor","amount_gross":19990,"currency":"HUF","timestamp":"2026-01-23T10:30:00Z","payment_status":"paid"}'
```

**Elvárt válasz**: `status=accepted`, `ledger_id` értékkel.

---

## 2) Tranzakció webhook (duplicate)
```bash
curl -X POST "https://app.sharity.hu/wp-json/impact/v1/partner/transaction" \
  -H "Authorization: Bearer PARTNER_API_KEY" \
  -H "X-Impact-Signature: sha256=..." \
  -H "X-Impact-Timestamp: 1737628500000" \
  -H "Idempotency-Key: 11111111-1111-1111-1111-111111111111" \
  -H "Content-Type: application/json" \
  -d '{"partner_code":"shopify-abc","event_id":"order_123456","event_type":"purchase","pseudo_id":"ab12cd34ef56","ngo_code":"bator-tabor","amount_gross":19990,"currency":"HUF","timestamp":"2026-01-23T10:30:00Z","payment_status":"paid"}'
```

**Elvárt válasz**: `status=duplicate`.

---

## 3) Kedvezmény kalkuláció
```bash
curl -X POST "https://app.sharity.hu/wp-json/impact/v1/partner/discount/quote" \
  -H "Authorization: Bearer PARTNER_API_KEY" \
  -H "X-Impact-Signature: sha256=..." \
  -H "X-Impact-Timestamp: 1737628500000" \
  -H "Content-Type: application/json" \
  -d '{"partner_code":"shopify-abc","pseudo_id":"ab12cd34ef56","amount_gross":19990,"currency":"HUF"}'
```

**Elvárt válasz**: `tier`, `discount_rate`, `partner_max_discount` mezők.

---

## 4) Dispute nyitás
```bash
curl -X POST "https://app.sharity.hu/wp-json/impact/v1/partner/dispute" \
  -H "Authorization: Bearer PARTNER_API_KEY" \
  -H "X-Impact-Signature: sha256=..." \
  -H "X-Impact-Timestamp: 1737628500000" \
  -H "Content-Type: application/json" \
  -d '{"ledger_id":"ldg_123456","event_id":"order_123456","partner_code":"shopify-abc","reason":"refund_not_reflected","details":"Customer refunded."}'
```

**Elvárt válasz**: `status=opened`, `dispute_id`.

---

## 5) Bridge dry‑run (lokális)
```bash
node tools/partner-bridge-samples/runner.js --dry-run
```

**Elvárt kimenet**: payload + signature + idempotency key.
