# 301. Beszélgetés összefoglaló: CJ Commission Detail mezőmapping

- Letöltöttem a CJ Developer Portal Commission Detail GraphQL spec JSON-t az asset store-ból, és azonosítottam a releváns query/mezőket.
- A `docs/cj-transactions.md` dokumentumba bekerült a tényleges mezőmapping: `commissionId`, `eventDate/postingDate`, `validationStatus`, `pubCommissionAmountPubCurrency`, `saleAmountPubCurrency`, `advertiserId/Name`, `sid`.
- A `validationStatus` enum értékei (PENDING/AUTOMATED/ACCEPTED/DECLINED) alapján rögzítettem a pending/approved/rejected mappinget.
