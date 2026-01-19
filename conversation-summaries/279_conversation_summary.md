# 279. Beszélgetés összefoglaló: NAV tokenExchange – signKey aláírás

## Áttekintés
Átállítottam a tokenExchange requestSignature számítást a signing key-re (exchange key nélkül), majd újra futtattam a prod tesztet.

## Megoldás
- `buildTokenExchangeRequest` most a `signKey`-vel számolja a signature-t.
- TokenExchange újrapróba: továbbra is `INVALID_REQUEST_SIGNATURE (400)`.

## Következő lépések
1. NAV UI-ban erősítsd meg, hogy a sign key pontosan ehhez a technikai userhez tartozik (nem régi, nem visszavont).
2. Ha a NAV UI konkrét szoftveradatokat vár, add meg azokat szó szerint az env-ben.
