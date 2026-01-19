# 282. Beszélgetés összefoglaló: NAV queryInvoiceDigest schema javítás + 35 napos limit

## Áttekintés
Frissítettem a queryInvoiceDigest XML felépítését, majd újra lefuttattam a NAV prod lekérdezést 2025 teljes évre, INBOUND irányban. A NAV már érvényes kérésként fogadta, de a túl hosszú időintervallum miatt elutasította.

## Megoldás
- `nav-online-invoice.ts`: `invoiceIssueDate` wrapper + `dateFrom/dateTo` használat, `pageSize`/`relationalQueryParams` eltávolítva.
- Manuális prod query futtatás eredménye: `BAD_QUERY_PARAM_RANGE_EXCEEDED` (max 35 napos intervallum).

## Következő lépések
1. 35 napos (vagy rövidebb) időablakokra bontott lekérdezés implementálása és futtatása.
2. 2025-ös INBOUND lekérdezés végigfuttatása batch-ben.
