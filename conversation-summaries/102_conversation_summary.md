# 102. Beszélgetés összefoglaló: Social ticker activity fallback

## Áttekintés
Az ImpactShop oldalon a "Kik támogattak mostanában?" rész (impact_social_ticker) nem mutatott friss adatot, mert a ledger forrás elavult volt. A cél egy activity-alapú fallback.

## Megoldás
- `/home/sharityh/app/wp-content/mu-plugins/impact-social-mvp.php`
  - Ha a ledger üres/öreg vagy nincs értelmes NGO, fallback az `/impact/v1/activity` adataira.
  - `from`/`to` paraméterekkel a teljes időablakot is lefedi.
  - `teszt-ngo` szűrése a fallbackből.

## Ellenőrzés
- `/wp-json/impact/v1/social/ticker?limit=5&status=all` most már activity-alapú tételeket ad (pl. 2025-12-18 CJ/Dognet sorok is).

## Megjegyzés
- A social ticker továbbra is ledgeres, ha friss és értelmes adat van; csak ilyenkor kapcsol fallbackre.
