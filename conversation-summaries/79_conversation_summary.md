# 79. Beszélgetés összefoglaló: intent-alapú offer szűrés

## Áttekintés
Az ajánló réteg immár felismeri a videós/átláthatósági/„nem vásárolok” jellegű intenteket, és ezekre nem ad shop kuponokat – így az OpenAI réteg tisztább narratívát kaphat. Lefuttattam a teljes extra QA promptbatch-et, majd a kritikus-barát checklist szerint értékeltem az eredményt.

## Módosítások
- `apps/ai-agent-core/src/impi/recommend.ts`: új `detectHighLevelIntent()` + összefoglaló generátor → videós támogatás, transzparencia, „nem akarok vásárolni”, leaderboard és feedback témákban üres ajánlatlista + dedikált szöveg kerül vissza.
- Új build → rsync → szerver oldali `npm install --omit=dev` → `ai-agent-service` restart (PID 688785).

## QA eredmény
- Videós/átláthatósági promptok már nem kaptak Mobilfox/Lampak fallbacket, viszont a válasz még mindig általános (nincs konkrét kampány/REST link), így 2/5 pontot kaptak.
- Shop/kategória/döntési promptok továbbra is generikus fallbackre futnak, 1/5 ponttal.

## Következő lépések
1. Kényszerítsd az OpenAI réteget a 3 opciós welcome menüre és az 5 lépéses sablonra (különösen döntési/kategória kérdéseknél).
2. Alakíts ki kategória→NGO mappinget, hogy a „gyerek vs. állat” vagy „Bátor Tábor” jellegű kérdések egyszerre adjanak szervezetlistát és CTA-t.
3. Futtasd újra a QA batch-et, amíg minden prompt legalább 4/5 pontot kap.
