# 335. Beszélgetés összefoglaló: PIN rate limit cleanup + SMS sent

Kérés: staging rate limit cleanup és új smoke teszt.

- `wp transient delete --all` (staging) → 13 törölt.
- `POST /impact/v1/identity/pin/issue` (sms) → `delivery.status=sent`.
