# 208. Beszélgetés összefoglaló: AI Agent Core enablement kickoff (2025-12-06 11:05)

## Áttekintés
Kérésre elindítottam az AI Agent Core bővítését: tételes feladatlista készült a Playwright/Gmail/Reliability, dokumentum ingest és LangGraph/Langfuse témákra, a core worker pedig moduláris job-típus támogatást kapott.

## Megoldás
- Sprint backlog: új `.codex/sprint-tasks/S3.md` a három fő terület részfeladataival (cronok, guard flag-ek, dokumentum ingest lánc, memória/telemetria).
- Core worker refaktor: `apps/core-worker/src/index.ts` most `generic`, `document_ingest`, `memory_sync` handler registryt használ (`job-types.ts` segédfájl), így könnyebb bekötni a dokumentum és Graphiti/LangGraph munkákat.
- Guard frissítés: `.codex/guards/ai-agent-guard.sh` most `AI_AGENT_REQUIRED_FEATURES` alapján olvassa a kötelező feature listát, ezért az új modulok megjelenésekor nem kell a scriptet módosítani.

## Következő lépések
1. A T-3.1/T-3.3 ticketek szerint implementálni kell a cronokat, guard integrációt és LangGraph/Langfuse kapcsolódást.
2. A worker stub handlerekbe építsd be a dokumentum ingest és memória szinkron pipeline-t, amint az env / service account adatok rendelkezésre állnak.
