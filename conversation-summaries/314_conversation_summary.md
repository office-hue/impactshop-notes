# 314. beszélgetés összefoglaló

- CJ shopok megjelenítéséhez bővítettem az `impactshop_get_shops()` függvényt: merge-eli az `impactshop_shops` option CJ elemeit (duplikált slugok kihagyva).
- Érintett fájl: `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`.
- Deploy prod+stagingre, cache flush és `impactshop_fragment_*` transiensek törlése megtörtént.
- A prod debug logban 1x „Undefined constant network” fatal jelent meg a hibásan idézőjeles WP-CLI `eval` ellenőrzés miatt (nem frontend hiba).
