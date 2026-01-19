# 288. Beszélgetés összefoglaló: NAV Online tudás frissítése + env ellenőrzés

## Áttekintés
Összegyűjtöttem és frissítettem a NAV Online integrációval kapcsolatos dokumentációt, beleértve az Impactall autoload blokkot és a központi env kulcsok jelenlétének ellenőrzését.

## Megoldás
- `docs/nav-online.md`: javított signature/logika, v3 séma részletek, digest limit, export útvonalak, Impactall autoload blokk.
- `notes.md`: quick reference + central env kulcsok (csak jelenlét, érték nélkül).

## Állapot
- Kötelező kulcsok rendben (`NAV_ONLINE_INVOICE_LOGIN`, `NAV_ONLINE_INVOICE_PASSWORD`, `NAV_ONLINE_INVOICE_SIGN_KEY`, `NAV_ONLINE_INVOICE_EXCHANGE_KEY`, `NAV_ONLINE_INVOICE_TAX_NUMBER`).
- Opcionális/fallback kulcsok hiányoznak (`NAV_ONLINE_INVOICE_USER`, `NAV_TAX_NUMBER`, `NAV_ONLINE_INVOICE_SOFTWARE_ID`, `NAV_ONLINE_INVOICE_BASE_URL`).
