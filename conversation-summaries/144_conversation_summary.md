# 144. Beszélgetés összefoglaló: Reliability guard script

## Áttekintés
A reliability scoring pipelinera ráépítve létrehoztam egy Codex guard/report scriptet, amely figyeli a `reliability-scores.json` állományt, logolja az átlagot és a "risky" kuponok számát, és riaszt, ha romlik az állapot.

## Megfigyelések
- Új futtatható: `.codex/scripts/ai-agent-reliability-guard.sh` – `jq`-val kiolvassa az aktuális átlag/risky értéket, a `.codex/state/ai-agent-reliability.json`-ban tárolt előző méréshez hasonlít, majd ír a `.codex/logs/ai-agent-reliability.log` fájlba (⚠️, ha nő a kockázat).
- A script sikeresen lefutott: `⚠️ [2025-12-04T07:11:58+0100] avg=0.36 risky=44 (prev=0)` bejegyzés bizonyítja, hogy érzékeli az első „risky” értéket és állapotot ment.
- `notes.md` frissült a guard telepítésének részleteivel, így a runbook most már tartalmazza a monitoring lépést is.

## Következő lépések
1. Akaszd be a guardot a cron/ai-agent runbookba, hogy a `reliability` flagre külön alert is érkezzen.
2. Használd a logot a release döntéseknél: ha a `risky` szám nő, manuális review szükséges.
