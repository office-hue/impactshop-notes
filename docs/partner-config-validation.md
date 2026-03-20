# Partner API – config validation spec

## Cél
Egyértelmű validációs szabálylista a partner API bejövő adataihoz.

---

## Azonosítók
- `partner_code`: regex `^[a-z0-9\-]{3,64}$`
- `event_id`: regex `^[A-Za-z0-9_\-]{3,128}$`
- `pseudo_id`: regex `^[a-z0-9]{10,12}$`
- `ngo_code`: regex `^[a-z0-9\-]{2,64}$`

---

## Összeg és pénznem
- `amount_gross`: `>= 0`, `<= 10_000_000`
- `currency`: `HUF|EUR|USD` (lista bővíthető)

---

## Időbélyeg
- `timestamp`: ISO8601 (`YYYY-MM-DDTHH:MM:SSZ`)

---

## Kedvezmény konfiguráció
- `partner_max_discount`: `0.00–0.50`
- `discount_rate`: `0.00–0.50`
- `discount_cap_amount`: `>= 0`
- `discount_min_cart`: `>= 0`
- `discount_stackable`: `true|false`

---

## Idempotency
- `Idempotency-Key`: regex `^[A-Za-z0-9_\-]{6,128}$`
- TTL: 86400 másodperc

---

## Webhook header
- `Authorization`: `Bearer <token>`
- `X-Impact-Signature`: `sha256=<hex>` (64 hex)

---

## HMAC
- Payload: raw JSON body
- Hash: `sha256`
