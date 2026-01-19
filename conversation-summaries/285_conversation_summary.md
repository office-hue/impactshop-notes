# 285. Beszélgetés összefoglaló: NAV digest letöltés (2025 INBOUND/OUTBOUND)

## Áttekintés
Elindítottam a NAV queryInvoiceDigest letöltéseket minden 35 napos batch-re 2025-re, INBOUND és OUTBOUND irányban. Az XML válaszok elmentésre kerültek.

## Megoldás
- Mentett digest XML-ek: `data/nav-online-invoice/` az ai-agent repo-ban.
- INBOUND (page=1) találatok: 2025-02-05–2025-03-11: 6, 2025-07-30–2025-09-02: 12, 2025-12-17–2025-12-31: 1, többi ablak 0.
- OUTBOUND (page=1): minden ablak 0.

## Következő lépések
1. Ha szükséges, lapozás (page>1) vagy részletes invoiceData letöltés az érintett ablakokra.
