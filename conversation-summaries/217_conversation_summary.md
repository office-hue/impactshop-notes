# 217. Beszélgetés összefoglaló: Core Agent API secret beállítása

## Áttekintés
A Core Console UI/CLI a `AI_AGENT_API_URL` + `AI_AGENT_API_KEY` párost várja, ezért gondoskodni kellett arról, hogy ezek a secretek helyben és a deploy env-kben is elérhetők legyenek.

## Megoldás
- Létrehoztam a `~/.impact-secrets/env.d/ai-agent.env` fájlt, benne a Core Agent endpointtal és API kulccsal (`https://ai-agent.sharity.hu`, `sk_aiagent_core_console_20251206`); az `init.sh` loop automatikusan betölti, így minden shell `source ~/.impact-secrets/init.sh` után megkapja.
- A `.codex/.env.local` felismeri ezt a secretet és source-olja (különben placeholdert hagy), így a guardok/CLI-k azonnal használni tudják.
- A `.staging_env` és `.production_env` fájlokban is beállítottam ugyanazt az `AI_AGENT_API_URL/KEY` párost, hogy a staging/prod guardok/worker scriptjei ugyanazzal az auth-tal fusson.

## Következő lépések
1. Guard vagy UI futtatása előtt futtasd a `source ~/.impact-secrets/init.sh` parancsot, majd ellenőrizd `env | grep AI_AGENT`-tel, hogy a kulcs betöltődött.
2. Ha a kulcs rotálódik, frissítsd a `ai-agent.env` + `.staging_env` + `.production_env` fájlokat egyszerre.
