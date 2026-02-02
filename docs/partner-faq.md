# Partner FAQ (tech)

## 1) Kell-e SDK?
Nem. Egyetlen webhook endpoint elég (HMAC + idempotency).

## 2) Mit írjak alá HMAC‑cal?
A **raw JSON body**‑t (pontosan, módosítás nélkül).

## 3) Mi az Idempotency‑Key?
Egyedi kulcs minden eseményhez; duplikált küldésnél ugyanaz a válasz jön.

## 4) Milyen státuszokat kapok vissza?
`accepted`, `duplicate`, `rejected`.

## 5) Milyen mezők kötelezők?
`partner_code`, `event_id`, `event_type`, `pseudo_id`, `ngo_code`, `amount_gross`, `currency`, `timestamp`, `payment_status`.

## 6) Mennyi ideig tároljátok a payloadot?
180 nap, utána archiválás/anonimizálás (lásd retention policy).

## 7) Mi történik hiba esetén?
4xx → fixálandó (validáció/HMAC), 5xx → retry backoff.

## 8) Staging vs prod?
Stagingen csak `test_*` kulcsok, prod kulcs stagingen tiltott.
