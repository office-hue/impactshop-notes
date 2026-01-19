# 273. Beszélgetés összefoglaló: NAV Online tokenExchange további próbák

## Áttekintés
Prod környezetben további tokenExchange variánsokat próbáltam a NAV Online API-n, a kérés pontosítására.

## Megoldás
- Variánsok: signing key‑es requestSignature, valamint `yyyyMMddHHmmss` formátumú timestamp.
- Eredmény: továbbra is `INVALID_REQUEST (400)`.

## Következő lépések
1. NAV UI-ban ellenőrizd a technikai felhasználó login/jelszó/kulcspár párosítást.
2. Erősítsd meg, hogy a kulcsok prod környezethez tartoznak.
