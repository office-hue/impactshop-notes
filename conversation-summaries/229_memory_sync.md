# Memory sync + Watchdog frissítés – 2025-12-06

- `/healthz` most a `memory_sync` feature státuszát is a `graphiti-ingest.cron.log` alapján számolja, így stale esetén WARN jelenik meg.
- A `.codex/scripts/ai-agent-log-watchdog.sh` immár a harvester, openai és memory logokat figyeli; STALE/MISSING esetén a logba ír és (ha van `AI_AGENT_WATCHDOG_WEBHOOK`) Discord értesítést küld.
- A Core Console dokumentum szekciója structured kártyákat mutat, JSON linkkel és guard újrafuttató gombbal.
