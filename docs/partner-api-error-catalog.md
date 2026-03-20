# Partner API – error catalog

## Cél
Egységes hibakódok a partner API válaszokhoz.

---

| Code | HTTP | Meaning | Client Action |
| --- | --- | --- | --- |
| `partner_auth_failed` | 401 | Érvénytelen API kulcs vagy HMAC | Ellenőrizd kulcsot + aláírást |
| `partner_signature_missing` | 401 | Hiányzó HMAC aláírás | Küldd a `X-Impact-Signature` headert |
| `partner_idempotency_missing` | 400 | Hiányzó Idempotency-Key | Küldd az idempotency kulcsot |
| `partner_payload_invalid` | 422 | Hibás payload/validáció | Javítsd a mezőket |
| `partner_duplicate` | 409 | Duplikált event/idempotency | Kezeld idempotens válaszként |
| `partner_rate_limited` | 429 | Túl sok kérés | Retry backoff szerint |
| `partner_server_error` | 500 | Belső hiba | Retry backoff szerint |

---

## Megjegyzés
- Hibáknál a response tartalmazza: `code`, `message`, `request_id`.
