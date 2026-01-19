# 319 Conversation Summary

- CJ backfill implementalva: `commissionId=3720682809` (orderId 11850266, advertiser 5619548) -> `teszt-ngo` NGO override.
- Activity/leaderboard/ticker REST endpointok most `from`/`to` parametert fogadnak; activity `limit` is allithato.
- Prod+staging deploy, cache/transient uritesek lefutottak.
- Ellenorzes: `.../activity?from=2025-12-01&to=2025-12-31` megjeleniti a CJ tetelt (`teszt-ngo`).
- Friss, sid-es CJ tranzakcio nincs az elmult 7 napban, igy “fresh” activity validacio nem lehetseges.
