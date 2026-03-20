# HMAC helper (CLI)

## Cél
Egyszerű HMAC SHA256 aláírás generálás a partner webhook payloadhoz.

## Használat
```bash
node tools/hmac-sign.js "test_hmac" '{"partner_code":"partner_demo","event_id":"order_1001","event_type":"purchase","pseudo_id":"test_pseudo_01","amount_gross":19990,"currency":"HUF","ngo_code":"bator-tabor","timestamp":"2026-01-23T10:30:00Z"}'
```

## Kimenet
- Hex HMAC signature (sha256)
