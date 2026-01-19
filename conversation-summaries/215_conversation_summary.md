# 215. Beszélgetés összefoglaló: Graphiti auth Rails kompatibilissé tétele

## Áttekintés
Kéréseid alapján ki kellett vezetni a házon belüli `X-Graphiti-Api-Key` használatát, és átállítani a worker/API gateway kódot, hogy a Rails által biztosított basic auth/JWT réteget használja a Graphiti endpointok védelméhez.

## Megoldás
- Új közös util (`apps/shared/graphitiAuth.ts`) készül, amely Basic auth vagy Bearer/JWT fejlécet épít (env: `GRAPHITI_BASIC_AUTH_USER/PASSWORD` vagy `GRAPHITI_BEARER_TOKEN`), és csak szükség esetén esik vissza a korábbi API key-re.
- A Graphiti-t hívó modulok (memory context szolgáltatás, aggregációs kliens, document insight sync, memory-ingest) mind ezt a helper függvényt használják, így egységesen küldenek Rails-kompatibilis authentikációt.
- A cron wrapperből kikerült az API key export, a `.codex/.env.local` Basic auth placeholdereket tartalmaz, és a TypeScript build (`npm run lint`) is lefutott, miután a job override paraméterek típusait pontosítottam.
- A staging/prod deploy env fájlok (`.staging_env`, `.production_env`) ugyanazt a `GRAPHITI_BASIC_AUTH_USER/PASSWORD` párt kapják, így a távoli guard/worker scriptjeink is ezt használják, és a központi `~/.impact-secrets/env.d/graphiti.env` most export formában tölti be ezeket.

## Következő lépések
1. Állítsd be a tényleges basic auth/JWT értékeket az `.codex/.env.local`-ban (vagy a megfelelő deploy környezetben), majd futtasd le a Graphiti guard/ingest szkripteket.
2. Ha minden környezet Rails-auth-ot használ, töröld a már nem szükséges `GRAPHITI_API_KEY` változókat a CI/CD secret store-okból is.
