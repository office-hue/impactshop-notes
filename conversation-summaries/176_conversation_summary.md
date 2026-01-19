# 176. Beszélgetés összefoglaló: Reliability scoring modul

## Áttekintés
Feladat volt a reliability scoring modul tényleges bevezetése: külön modulba szervezni a számítást, önálló scriptet adni hozzá, és gondoskodni róla, hogy az AI agent fallbackjei a friss statisztikákat olvassák.

## Megoldás
- `tools/ingest/reliability.ts` új modulként tartalmazza a korábbi normalizer-beli logikát; az új `tools/ingest/collect-reliability.ts` CLI a `tmp/ingest` mappában lévő normalizált JSON-okat olvassa és legenerálja a `manual_coupons_stats.json` + `reliability-scores.json` fájlokat.
- Az `apps/ingest/normalizer.ts` most ezt a modult hívja, így a reliabilty riport minden normalizáláskor és manuálisan is előállítható.
- Az `apps/ai-agent-core/src/impi/impact-data.ts` és `apps/.../services/reliability.ts` útvonalai a `tmp/ingest` fájlokra mutatnak (legacy sandbox útvonal csak fallback), tehát az Impi ajánlások valóban a friss pontszámokkal számolnak.
- Tesztek+linter: `node --test --import tsx tests/*.test.ts`, `npm run lint` → PASS.

## Következő lépések
1. Állíts be egy cron/scriptet, ami minden normalizer futás után meghívja a `collect-reliability.ts`-t (ha nem fut egyébként).
2. A `/healthz` és guard logok már látják a reliability flaget; érdemes a dashboardon is megjeleníteni a legutóbbi átlag-pontszámot és a rizikós boltok számát.
