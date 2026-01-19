# 283. Beszélgetés összefoglaló: NAV queryInvoiceDigest batch (2025 INBOUND)

## Áttekintés
35 napos batch-ekre bontottam a 2025-ös INBOUND queryInvoiceDigest lekérdezést, és lefuttattam a teljes évet prod környezetben.

## Megoldás
- `nav-online-invoice.ts`: batch helper hozzáadva (date range split + összesítés).
- Prod batch futás eredménye: összesen 25 találat.
- Találatok időablakokban: 2025-03-12–2025-04-15: 10, 2025-04-16–2025-05-20: 15, többi ablak 0.

## Következő lépések
1. Ha szükséges, indíts részletes letöltést ezekre az ablakokra (invoiceDigest lista feldolgozás).
2. OUTBOUND irány futtatása 2025-re ugyanígy.
