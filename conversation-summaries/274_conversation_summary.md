# 274. Beszélgetés összefoglaló: NAV Online INVALID_REQUEST – séma fókusz

## Áttekintés
Összegyűjtöttem a NAV tokenExchange INVALID_REQUEST tipikus okait és a v3 séma kötelező mezőit, hogy a hiba okát célzottan lehessen kizárni.

## Megoldás
- Helyes endpointok: prod `https://api.onlineszamla.nav.gov.hu/invoiceService/v3/tokenExchange`, test `https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3/tokenExchange`.
- Kötelező mezők + attribútumok: requestVersion/headerVersion, passwordHash `cryptoType=\"SHA-512\"`, requestSignature `cryptoType=\"SHA3-512\"`, teljes software blokk, taxNumber törzsszámként.
- Namespace követelmény: a v3 common namespace használata a root/user/software blokkokban.

## Következő lépések
1. A tokenExchange XML teljes igazítása a v3 xsd‑hez (prefixek, attribútumok, kötelező mezők).
2. A `software` blokk kötelező mezőinek kitöltése a valós NAV regisztrációs adatokkal.
