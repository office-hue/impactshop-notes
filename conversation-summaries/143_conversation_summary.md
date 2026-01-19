# 143. Beszélgetés összefoglaló: Reliability scoring pipeline

## Áttekintés
Folytattam az AI Agent stratégia T-2.10 lépését: elkészült a reliability scoring pipeline, amely slugonkénti pontszámokat gyárt a normalizerben, a core szolgáltatás pedig ezeket használja a `/healthz` és az Impi ajánlások során.

## Megfigyelések
- `tools/ingest/normalizer.ts` most `reliability-scores.json` fájlt ír a meglévő stats mellett (átlag, risky count, slugonkénti score/label). A futás logban megjelenik az összegzés.
- Új `apps/ai-agent-core/src/services/reliability.ts` gondoskodik a score-ok betöltéséről; a `resolveReliabilitySeed` először innen olvas, csak hiány esetén tér vissza a régi statikus képlethez.
- `/healthz` frissült: a `reliability` feature flag most a score-állomány alapján állapodik meg (`getReliabilityFeatureStatus()`), így az `aiagentall` ténylegesen látja a `last_run` és átlag értékeket.
- `npm run ingest:normalize` újra lefutott (50 Gmail + 43 Árukereső + 2 manual rekord), és a TypeScript lint is zöld maradt.

## Következő lépések
1. Építs dashboard/jelentés (`.codex/logs/ai-agent/reliability.log`) vagy guard, ami a `reliability-scores.json` alapján riaszt, ha nő a `risky` kuponok száma.
2. Integráld a score mezőket az Impi válaszaiba (pl. highlight/cleanup javaslatok).
