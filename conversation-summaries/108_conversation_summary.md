# 108. Beszélgetés összefoglaló: Fragment prewarm cron ütemezése

## Áttekintés
A fragment cache folyamatos melegen tartásához a prewarm szkriptet óránként futó cronnal kellett ütemezni.

## Megfigyelések
- Új installer: `scripts/install-fragment-prewarm-cron.sh`. Felveszi (vagy frissíti) a lokális crontabban a `5 * * * * cd ~/Documents/GitHub/impactshop-notes && ./scripts/impact-fragment-prewarm.sh both >> tmp/impact-fragment-prewarm.log 2>&1 # impact-fragment-prewarm` sort.
- A script futtatása (`./scripts/install-fragment-prewarm-cron.sh`) megtörtént, a visszajelzés alapján a crontab mostantól minden óra 5. percében elindítja a production+staging prewarmot, a log pedig a repo `tmp/impact-fragment-prewarm.log` fájljába íródik.

## Következő lépések
1. Időnként nézd át a `tmp/impact-fragment-prewarm.log` tartalmát, hogy nincs-e SSH vagy WP-CLI hiba.
2. Ha valaha módosul a prewarm script (új shortcode, extra környezet), futtasd újra az installer szkriptet, hogy a crontab sor frissüljön.
