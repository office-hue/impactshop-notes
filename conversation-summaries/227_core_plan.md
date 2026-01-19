# Részletes AI Agent Core rollout terv

1. **Langfuse dashboard + alert**
   - Dashboard panelek: `core_task_created` (count per day/workspace), `impi_chat_response` (avg `metadata.processing_ms`, error rate).
   - Alert: Discord webhook, hiány (15 perc), error arány >10%.
   - Release checklist: grafikon + alert állapot ellenőrzése deploy előtt.

2. **Dokumentum-ingest UX**
   - Core Console panel mutassa structured dokumentumokat, warnings badge, JSON link.
   - Guard timestamp, ingestWarnings UI, opcionális „Re-run guard” gomb.
   - Worker job `attachments.ingestPath` auto-populate, LangGraph log event.
   - Release check: ingest guard log + Graphiti sync screenshot.

3. **Memory sync + Graphiti orchestration**
   - Cron `graphiti-ingest.sh`, worker `memory_sync` job, output `tmp/state/memory`.
   - LangGraph jobType switch: memory sync → graphitiContextNode + log only.
   - `/healthz` memory_sync status + stale, Discord alert.

4. **Langfuse enablement + log watchdog**
   - Tudásbázis cikk, notes/README release checklist, Discord webhook secret.
   - `ai-agent-log-watchdog` figyeli harvester/openai logokat; STALE → manual smoke (coupon-harvester + impi guard).
