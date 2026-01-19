# 224. Beszélgetés összefoglaló: Langfuse dashboard terv + log watchdog

## Áttekintés
Feladat: dokumentálni a Langfuse dashboard/alert beállításait és gondoskodni arról, hogy a harvester/OpenAI logok folyamatosan frissüljenek a Core Console státuszkártyákhoz.

## Megoldás
- Új `notes.md` bejegyzés írja le a javasolt Langfuse panelek/alert szabályokat (event nevék: `core_task_created`, `impi_chat_response`, meta `processing_ms`, napi count + Slack riasztás, ha nincs új esemény vagy nő az error arány).
- Létrehoztam a `.codex/scripts/ai-agent-log-watchdog.sh` szkriptet, amely óránként ellenőrzi a `coupon-harvester-smoke.log` és `../ai-agent/tmp/logs/impi-chat.log` fájlok frissességét; az eredményt a `~/.codex/logs/ai-agent-log-watchdog.log` fájlba írja.
- A cron táblában (`.codex/cron/guards.crontab`) új sor gondoskodik a watchdog futtatásáról, így a Playwright/Gmail/Reliability státuszkártyákhoz hasonlóan a harvester/OpenAI panelek is naprakészek maradnak.

## Következő lépések
1. A Langfuse webes felületén hozd létre a javasolt grafikonokat/alert szabályokat a dokumentált paraméterekkel.
2. Figyeld a `~/.codex/logs/ai-agent-log-watchdog.log` kimenetét; ha tartós `STALE` vagy `MISSING` állapot jelenik meg, vizsgáld a vonatkozó cron scripteket.
