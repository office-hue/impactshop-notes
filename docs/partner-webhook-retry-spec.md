# Webhook retry spec – backoff + idempotency

## Cél
Standard retry viselkedés a partner webhookoknál, deduplikációval.

---

## Idempotency kezelése
- **Idempotency-Key** kötelező minden requesthez.
- Duplikált kulcs esetén **ugyanaz a válasz** térjen vissza.
- TTL javaslat: **24 óra** (`idempotency_ttl_sec=86400`).

---

## Retry backoff táblázat (javaslat)

| Próba | Várakozás | Megjegyzés |
| --- | --- | --- |
| 1 | 0s | első próbálkozás |
| 2 | 1m | gyors retry |
| 3 | 5m | rövid backoff |
| 4 | 30m | közepes backoff |
| 5 | 2h | hosszabb backoff |
| 6 | 6h | utolsó próbák |

---

## Retry feltételek
- **Retry**: 5xx, timeout, hálózati hiba
- **No retry**: 4xx (validáció/HMAC hiba)

---

## Response ajánlás
- `200` vagy `202` ha accepted
- `409` ha idempotency dupe
- `401` ha auth fail
