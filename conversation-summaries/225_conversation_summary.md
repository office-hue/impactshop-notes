# 225. Beszélgetés összefoglaló: Langfuse UI terv + watchdog ellenőrzés

## Áttekintés
A feladat az volt, hogy a Langfuse dashboard/alert következő lépéseit rögzítsem, illetve megnézzem, hogy a harvester/OpenAI logokra épülő státuszkártyákhoz van‑e friss adat.

## Megoldás
- Mivel a Langfuse UI-hoz nem férek hozzá, a `notes.md`-ben rögzítettem a konkrét beállítási tervet: panel definíció (`core_task_created`, `impi_chat_response`, processing_ms), alert szabály (15 perc eseményhiány vagy error arány >10%, Slack webhook), így kézzel könnyen beállítható.
- Lefuttattam a `./.codex/scripts/ai-agent-log-watchdog.sh` szkriptet, ami `ai-agent-log-watchdog.log`-ba írja a harvester (`coupon-harvester-smoke.log`) és OpenAI (`../ai-agent/tmp/logs/impi-chat.log`) frissességét; jelenleg mindkettő STALE (4109/6066 perc), azaz szükség lesz manuális smoke/guard futtatásra.

## Következő lépések
1. A Langfuse felületen a fenti terv szerint hozz létre dashboardokat és alertet (Slack #ai-agent webhookkal).
2. Futtasd a `./.codex/cron/coupon-harvester-smoke.sh`-t és az Impi chat guardot, majd ellenőrizd, hogy a watchdog log „OK” értéket jelez-e; ha továbbra is STALE, vizsgáld meg a vonatkozó cronokat.
