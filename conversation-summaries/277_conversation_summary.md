# 277. Beszélgetés összefoglaló: NAV tokenExchange – software blokk + softwareId fallback

## Áttekintés
A tokenExchange XML-t tovább igazítottam a v3 XSD-hez: a `software` blokk prefix nélküli lett, és érvényes 18 karakteres `softwareId` fallbacket vezettem be, majd újrapróbáltam a prod tokenExchange hívást.

## Megoldás
- `software` blokk: prefix nélküli (default API namespace), `header/user` maradt `NTCA/1.0/common` prefixen.
- `softwareId` fallback: `HU<törzsszám>AIA00001` (18 karakter, A–Z/0–9/-).
- TokenExchange újrapróba: továbbra is `INVALID_REQUEST (400)` választ adott.

## Következő lépések
1. NAV UI-ban ellenőrizd a regisztrált szoftver adatok pontos értékeit, és töltsd be env-be.
2. Ha van, add meg a NAV által elvárt `softwareId` értéket pontosan.
