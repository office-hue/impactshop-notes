# 278. Beszélgetés összefoglaló: NAV tokenExchange – header/user order + requestId fix

## Áttekintés
Követtem a v3 XSD szerinti ordert: requestVersion/headerVersion a headerben, requestSignature a user blokkban, és 30 karakteres requestId generálás. Újrapróbáltam a prod tokenExchange hívást.

## Megoldás
- Header order: requestId, timestamp, requestVersion, headerVersion.
- User blokk: requestSignature a userben, taxNumber 8 jegy.
- RequestId: 30 karakteres, alfanumerikus `RID<timestamp><rand>` formátum.
- Kulcsok: sign key és exchange key is kipróbálva.
- Eredmény: továbbra is `INVALID_REQUEST_SIGNATURE (400)`.

## Következő lépések
1. NAV UI-ban erősítsd meg, hogy a sign key/exchange key pontosan ehhez a technikai userhez tartozik.
2. Ellenőrizd, hogy a NAV UI-ban rögzített szoftver adatok egyeznek a requestben küldöttekkel.
