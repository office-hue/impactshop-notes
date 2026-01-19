# 250. Beszélgetés összefoglaló: UpdraftPlus kizárások

## Áttekintés
A futó WordPress frissítés előtt a feladat az volt, hogy az UpdraftPlus backup ne csomagolja bele a korábbi git/zip mentéseket, így gyorsabban fusson a mentés.

## Megoldás
- Távolról módosítottam az UpdraftPlus `updraft_include_others_exclude` opcióját (`ssh sharityh@cp40.ezit.hu "/usr/local/bin/wp --path=/home/sharityh/app option update ..."`), és felvettem a `.backups`, `._backup*`, `ai1wm-backups`, `file-manager-backups`, `upgrade-temp-backup`, `impactshop_backups`, `impactshop-backups` útvonalakat a kizárólistára.
- A `notes.md` új bejegyzésben rögzíti a változtatást és hogy a futó backupot WP adminból kell lezárni/újraindítani, majd jöhet a WordPress core/plugin update.

## Következő lépések
1. A jelenlegi UpdraftPlus mentést a WP adminból fejezd be vagy indítsd újra, hogy az új kizárások érvényesüljenek.
2. Ha kész a backup, mehet a WordPress / Elementor / plugin frissítés (`wp core update`, `wp plugin update --all`).
