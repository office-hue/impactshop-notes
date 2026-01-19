# 276. Beszélgetés összefoglaló: NAV tokenExchange – prod ellenőrzés + retest

## Áttekintés
Ellenőriztem a prod technikai user és taxNumber értékeket az env‑ből, majd újra lefuttattam a tokenExchange tesztet a v3 XSD‑nek megfelelő XML‑lel.

## Megoldás
- Env ellenőrzés: login jelen van, taxNumber 8 számjegy (törzsszám), softwareDevTaxNumber fallback a taxNumber‑re.
- TokenExchange próba: signing key‑es requestSignature, v3 namespace + attribútumok rendben.
- Eredmény: továbbra is `INVALID_REQUEST (400)` a prod endpointtól.

## Következő lépések
1. NAV UI‑ban ellenőrizd a login/taxNumber párosítást a technikai felhasználónál.
2. Ha a szoftver adatok NAV UI‑ban rögzítettek, töltsd be őket az env‑be.
