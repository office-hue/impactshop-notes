# 181. Beszélgetés összefoglaló: Graphiti memória források kiegészítése

## Áttekintés
Frissítettem az AI Agent Graphiti memóriáját kiszolgáló adatpipeline-t, hogy minden kupontípus (Gmail, Árukereső, reliability statisztika, Impi tudásbázis) valós NGO sluggal és shop-információval kerüljön be, így a /api/v1/context/memory és a Graphiti aggregáció is tényleges fallbacket tud adni a prompt buildernek.

## Megoldás
- `tools/shops_registry.json` bővült 15 új partnerrel (Billingo, Dockyard, Pink Panda, PCLand, Mateking, TalkPal, MOL Move, Opten, Mobilfox, MVM Dome, Logitech, Lámpák.hu, Griffconnect, Turboscribe, FIZZ), mindegyikhez `default_d1` került, a meglévő REGIO/Sparkl entry pedig megkapta a valós NGO slugot.
- `data/shop-impact.json` most minden bolthoz `ngo_slug`-ot is tartalmaz; a `tools/ingest/shops-registry.ts` betölti ezt a mappinget és kulcsszó alapján is fallbacket ad (`mobiltelefon`→`magyar-gyermekmento`, `laptop`→`bator-tabor`, stb.), így az Árukereső kategória slugok is kapnak `ngo_slug`-ot.
- A Gmail promotions runner subdomain-támogatást kapott (pl. `newsletter.billingo.com` → `billingo.com`), így a `shop_slug`/`ngo_slug` páros ott is kitöltődik.
- Az `apps/memory-ingest/src/index.ts` immár a `tmp/ingest/reliability-scoreboard.json` fájlt is felküldi Graphitinek `ShopReliability` nodeként, kapcsolva a Shop + NGO csomópontokhoz; a memóriakontekstus most valódi statisztikát ad az LLM-nek.

## Következő lépések
1. Futtasd le a Graphiti docker stack-et és a `npx tsx apps/memory-ingest/src/index.ts` scriptet, hogy az új slug mapping ténylegesen bekerüljön Neo4j-be.
2. Ellenőrizd a `/aggregations/ngo-promotions` választ; ha üres, nézd meg, hogy a cron ír-e logot a `.codex/logs/graphiti-ingest.cron.log`-ba.
