# Core Console UI + Watchdog fejlesztések – 2025-12-06

## Dokumentum-ingest UI
- A `/admin/core-console` oldal most structured dokumentum kártyákat jelenít meg (munkalap/tábla count, figyelmeztetések, JSON link). A státuszkártya mellett megjelent a guard újrafuttató gomb, ami a `.codex/guards/document-ingest.sh` scriptet hívja.
- Új API-k: `/core/documents/:file` (JSON letöltés) és `/core/guard/document-ingest` (guard trigger). A JS a query param `key` alapján adja át az `x-api-key` headert.

## Watchdog
- A `.codex/scripts/ai-agent-log-watchdog.sh` most már harvester, openai és memory_sync logokat is figyel, opcionális Discord webhook értesítéssel (`AI_AGENT_WATCHDOG_WEBHOOK`).
- A `notes.md` frissült a webhook használatáról és a manuális guard futtatás lépéseiről.
