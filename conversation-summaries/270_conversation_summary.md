# 270. Beszélgetés összefoglaló: NAV Online requestSignature megoldás rögzítése

## Áttekintés
Rögzítettem a NAV Online requestSignature hiba okát és a helyes számítási szabályokat, hogy az `INVALID_REQUEST_SIGNATURE` ne jöjjön vissza.

## Megoldás
- A requestSignature a `requestId + timestamp + signingKey` szöveg SHA3‑512 hash-e, hex formában, UPPERCASE.
- A signing key a NAV portál által generált literális karaktersorozat, kötőjelekkel együtt; nem szabad átkódolni.
- A timestampnek NAV által elvárt UTC formátumban kell érkeznie (a hivatalos példák ISO 8601-et használnak).
- Gyakori hiba: exchange key vs signing key keverése, rossz timestamp formátum, vagy kulcs átkódolása.
- Javítás: új kulcspár generálás, pontos másolás, SHA3‑512 UPPERCASE, `signKeyHex=false`, szerveridő szinkron.

## Következő lépések
1. Ha újra előjön a 400-as hiba, ellenőrizd a kulcsok frissességét és a timestamp formátumot.
