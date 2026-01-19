# 289. Beszélgetés összefoglaló: NAV Online runbook + env beállítás

## Áttekintés
Frissítettem az Impactall runbookot NAV Online résszel, és beállítottam a `NAV_ONLINE_INVOICE_SOFTWARE_ID` értéket a központi secret env-ben.

## Megoldás
- `impact-hub-system-v1.3.md`: NAV Online quick runbook (Impactall autoload blokk).
- `docs/nav-online.md`: összefoglaló és gyors infók frissítve.
- Központi env: `NAV_ONLINE_INVOICE_SOFTWARE_ID` beállítva (`HU<törzsszám>AIA00001`).

## Megjegyzés
- Az env ellenőrzés csak kulcs-jelenlétet vizsgált, értéket nem logolt.
