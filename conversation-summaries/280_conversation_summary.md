# 280. Beszélgetés összefoglaló: NAV tokenExchange sikeres (masked timestamp)

## Áttekintés
Átállítottam a requestSignature timestamp‑maszkolására (yyyyMMddHHmmss), és a prod tokenExchange végre sikeres lett.

## Megoldás
- requestSignature input: `requestId + maskedTimestamp + signKey` (masked timestamp: `yyyyMMddHHmmss`).
- TokenExchange prod teszt: OK, token érkezett.

## Következő lépések
1. Ezt a maszkolást tartsd meg a queryInvoiceDigest hívásnál is.
2. Következő lépésként jöhet a digest lekérdezés valós adatokra.
