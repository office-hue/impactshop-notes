# 272. Beszélgetés összefoglaló: NAV Online tokenExchange éles teszt

## Áttekintés
Éles tokenExchange tesztet futtattam a NAV Online Számla endpointok felé a központi env alapján, több request-variánssal.

## Megoldás
- Env forrás: `/Users/bujdosoarnold/.impact-secrets/env.d/capi.env` (exporttal betöltve).
- Endpointok: prod `https://api.onlineszamla.nav.gov.hu/invoiceService/v3`, test `https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3`.
- Variációk: requestId korlátozott formátum (RID+timestamp), SHA3‑512 UPPERCASE, ISO 8601 timestamp.
- Eredmény: mindkét környezet `INVALID_REQUEST (400)` választ adott.

## Következő lépések
1. NAV UI-ban technikai felhasználó és kulcsok ellenőrzése/újragenerálása.
2. Erősítsd meg, hogy a login/jelszó/kulcsok prod vagy test környezethez tartoznak.
