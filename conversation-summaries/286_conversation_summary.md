# 286. Beszélgetés összefoglaló: NAV digest pagination + invoiceData letöltés (2025)

## Áttekintés
Lefuttattam a NAV queryInvoiceDigest paginationt minden 35 napos batch-re (INBOUND/OUTBOUND), majd minden digest tételre elindítottam a QueryInvoiceData letöltést.

## Megoldás
- Digest XML mentés: `data/nav-online-invoice/` (44 fájl).
- InvoiceData XML mentés: `data/nav-online-invoice/` (72 fájl).
- Összefoglaló JSON: `data/nav-online-invoice/download-summary.json`.

## Következő lépések
1. Ha kell, invoiceData XML-ek feldolgozása/parse-olása (pl. CSV export).
