# 122. Beszélgetés összefoglaló: Gmail + whitelist élesítés státusz

## Áttekintés
Megvizsgáltam, hogy a kupon-harvester pipeline átállítható-e valós Gmail + Dognet/CJ whitelist alapú futásra (DRY_RUN=0), de a jelenlegi megoldás ezt nem támogatja.

## Megfigyelések
- A `scripts/coupon_harvester_pipeline.py` kizárólag a `fixtures/coupon-harvester/gmail` és `fixtures/coupon-harvester/html` állományokból dolgozik; nincs Gmail API hitelesítés (credentials/token beolvasás, historyId checkpoint, rate limit kezelés) implementálva.
- Bár létezik egy `tools/secrets/gmail/{credentials.json,token.json}` mappa (felsőbb szinten), a pipeline kódja egyáltalán nem hivatkozik rá, így a valós mailbox adatai nem integrálhatók további fejlesztés nélkül.
- Dognet/CJ whitelist feed sincs bekötve: a `.codex/cron/coupon-harvester-config.json` hardcode-olt 3 domaint tartalmaz, nincs generator script, amely a feedekből állítaná elő a slug/domain mappinget.

## Következő lépések
1. Bővítsd a pipeline-t echte Gmail API integrációval (credentials/tokens, message list+get, rate limit/backoff, history checkpoint), majd húzd be a `tools/secrets/gmail` fájlokat config paraméterként.
2. Készíts Dognet/CJ feed alapú whitelist generátort (`shops_registry`), és a configot frissítsd erre; csak ezután érdemes DRY_RUN=0 futást végezni.
