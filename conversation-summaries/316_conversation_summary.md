# 316. beszélgetés összefoglaló

- A CJ shopok továbbra sem látszottak, mert a Netflix shortcode `categories` listája nem tartalmazta a `CJ` kategóriát.
- A CJ shopok kategóriáját `Vegyes`-re állítottam (MU plugin + WPCode big snippet), így beleesnek a fő oldal kategória-szűrésébe.
- Cache flush + `impactshop_fragment_*` transiensek törlése prodon megtörtént.
- Ellenőrzés: `impactshop_get_shops()` CJ minták (`cj-2709631`, `cj-3387283`, …) már `Vegyes` kategóriával jönnek.
