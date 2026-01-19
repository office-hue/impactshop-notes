# 281. Beszélgetés összefoglaló: NAV queryInvoiceDigest próba (2025-01-01–2025-12-31 INBOUND)

## Áttekintés
Sikeres tokenExchange után lefuttattam a queryInvoiceDigest kérést 2025 teljes évre, INBOUND irányban.

## Megoldás
- TokenExchange: OK (masked timestamp + signKey).
- queryInvoiceDigest: `INVALID_REQUEST` + `SCHEMA_VIOLATION` (a válasz szerint a `common:exchangeToken` elem nem várt a `common:user` blokkban).

## Következő lépések
1. Ellenőrizd a NAV v3 XSD alapján, hogy a `exchangeToken` melyik blokkban/sorrendben szerepelhet a queryInvoiceDigest kérésben.
2. A séma szerinti helyre mozgatás után újrapróba.
