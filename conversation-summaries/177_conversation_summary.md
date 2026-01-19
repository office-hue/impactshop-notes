# 177. Beszélgetés összefoglaló: Reliability cron bekötése

## Áttekintés
A cél az volt, hogy a `collect-reliability.ts` script minden normalizer futás után automatikusan lefusson (legalább napi egyszer), így a `/healthz` reliability flag mindig friss adatot mutasson.

## Megoldás
- Új `.codex/cron/collect-reliability.sh` készült, ami guard-környezettel futtatja a `npx tsx tools/ingest/collect-reliability.ts` parancsot az `ai-agent` repóból, a kimenet a `~/.codex/logs/reliability-report.log` fájlba kerül.
- A `.codex/cron/guards.crontab` 05:04-es időpontra kapott új bejegyzést (`collect-reliability.sh`), így minden hajnalban automatikusan regenerálódnak a `tmp/ingest/manual_coupons_stats.json` és `reliability-scores.json` fájlok.
- Kézi próba futtatás (`.codex/cron/collect-reliability.sh`) sikeres volt; a guard log „reliability-report” néven rögzítette az eredményt.

## Következő lépések
1. Ha a normalizer napközben többször is fut, és azonnali frissítés kell, hívd meg a `collect-reliability.sh`-t a normalizer pipeline végén is.
2. Ellenőrizd a `~/.codex/logs/collect-reliability.cron.log` fájlt időnként, hogy nincs-e sikertelen futás.
