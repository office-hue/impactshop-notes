# 275. Beszélgetés összefoglaló: NAV tokenExchange XSD‑igazítás + retest

## Áttekintés
Átírtam a tokenExchange request generálót v3 XSD‑nek megfelelő root/attribútum/prefix struktúrára, majd újrafuttattam a prod tesztet.

## Megoldás
- Root elem: `TokenExchangeRequest` OSA API namespace alatt, `requestVersion="3.0"` és `headerVersion="1.0"` attribútumokkal.
- `header/user/software` elemek `http://schemas.nav.gov.hu/NTCA/1.0/common` namespace alatt.
- Újrapróba: a séma‑validációs hiba megszűnt, de továbbra is `INVALID_REQUEST (400)` válasz érkezik.

## Következő lépések
1. A prod technikai felhasználó login/taxNumber páros ellenőrzése.
2. A NAV UI‑ban a kulcspár és a szoftver adatok ellenőrzése (azonosító + dev tax number).
