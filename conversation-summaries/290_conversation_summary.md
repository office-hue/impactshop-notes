# 290. Beszélgetés összefoglaló: NAV Online BASE_URL + audit checklist

## Áttekintés
Beállítottam a NAV Online prod BASE_URL-t a központi secret env-ben, és bővítettem a NAV Online dokumentációt egy gyors audit checklisttel.

## Megoldás
- Központi env: `NAV_ONLINE_INVOICE_BASE_URL=https://api.onlineszamla.nav.gov.hu/invoiceService/v3`.
- `docs/nav-online.md`: audit checklist blokk hozzáadva.

## Megjegyzés
- A beállítás prod URL-re készült; teszt URL külön változóval kezelhető, ha szükséges.
