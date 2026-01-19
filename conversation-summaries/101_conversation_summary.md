# 101. Beszélgetés összefoglaló: NGO névfeloldás a social tickerben

## Áttekintés
A social tickerben több NGO név slug/kód formában jelent meg; a cél a névfeloldás és az ékezetes megjelenítés volt.

## Megfigyelések
- `impact-social-mvp.php`: a ledger soroknál új `resolve_ngo_display` logika került be; ha a display slug/kód jellegű, akkor `impactshop_resolve_ngo_name` segítségével felold.
- `impactshop-ngo-card.php`: az `impactshop_resolve_ngo_name()` most már az `ngo_codes.csv` map + override alapján dolgozik (pl. `mbe` → „Mozgássérültek Budapesti Egyesülete”).
- Ellenőrzés: `/wp-json/impact/v1/social/ticker?limit=5&status=all` → Bátor Tábor és MBE ékezetesen jelenik meg.

## Nyitott kérdés
- A Dognetből érkező numerikus NGO kódok (`0-...`) nincsnek az `ngo_codes.csv`-ben, ezért továbbra is kódként látszanak; szükség van a megfelelő kód→név mappingre.
