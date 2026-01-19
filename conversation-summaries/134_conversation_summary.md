# 134. Beszélgetés összefoglaló: Manual kupon review + ingest sync

## Áttekintés
A friss CJ whitelist futás után manuálisan átnéztem a `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T172443.csv` fájlt, kiszűrtem a zajos kuponokat, frissítettem az ai-agent manual feedet, majd újra lefuttattam az ingest pipeline-t (`npm run ingest:sync`).

## Megfigyelések
- A draft 24 sorából csak a Decathlon `WINTER20` és a Notino `ILLAT15` kód volt érvényes; a CSV-t és az ai-agent `tmp/ingest/raw/manual_coupons.csv` fájlt is erre a két rekordra szűkítettem.
- `npm run ingest:sync` (ai-agent) → a `tmp/ingest/raw/*.csv/json` fájlok naprakészek, a normalizer összesen 2 manuális és 43 Árukereső rekordot írt.

## Következő lépések
1. Ha új manuális vagy CJ kupon kerül be, ismételd meg a review + ingest folyamatot, hogy az AI feed csak valid kódokat tartalmazzon.
2. A manuális CSV alapján futtasd a `npm run ingest:normalize` parancsot is, ha külön diffre vagy statra van szükség.
