# 99. Beszélgetés összefoglaló: Netflix shortcode rollback + logo restore

## Áttekintés
A gyorsított ImpactShop redesign szétesett (Netflix kártyák, Fillout/D1 logika), ezért a feladat a mostani állapot mentése, majd a legacy fallback visszaállítása volt.

## Megfigyelések
- A jelenlegi `impactshop-shortcode-pack.php` és `wp-content/mu-plugins/impactshop-netflix-shortcodes.php` fájlokat timestampes másolatként elmentettem az `impactshop/backups/` mappába.
- A `wp-content/uploads/impactshop/ngo-logos-backup-20251202-140116` mappából visszamásoltam az eredeti logókat (`ngo-logos-backup-before-revert-<ts>` biztonsági mentéssel), így a PNG források a legacy shortcodelogika szerint elérhetők.
- A MU plugin elejéről kivettem az `impactshop-shortcode-pack.php` guardot, így újra a fallback kód fut; `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-netflix-shortcodes.php` lefutott, cache flush-sal.
- `wp eval 'wp_set_current_user(1); echo apply_filters("the_content", get_post_field("post_content", 16348));'` és `curl --http1.1 https://app.sharity.hu/impactshop/` mindkettő 200-as státuszt adott a rollback után.

## Következő lépések
1. Amikor lesz idő a redesign stabilizálására, a `backups/impactshop-*-20251202-174414` fájlokból vissza lehet tölteni az új kódot egy külön branchben.
2. A Netflix UI újratervezése előtt készíts részletes smoke listát (Fillout/D1, go-deal, logók, Owl nav), hogy a guard ne engedjen ilyen regressziót.
