# 80. Beszélgetés összefoglaló: Impi training roadmap bővítés

## Áttekintés
Az új flow-routing javaslatokat formalizáltam az `AI-training-pack.md` és az `AI-training-prompts.md` dokumentumokban, hogy az Impi tréning és QA folyamata pontosan kövesse a P0/P1/P2 feladatokat.

## Módosítások
- `Impi Tudásbázis/AI-training-pack.md` → új „Haladó kiterjesztések” fejezet (intent-alapú szűrés, welcome sablon, negatív intent, kategória→NGO mapping, multi-turn memória, stb.), valamint a hozzájuk tartozó sikerkritériumok.
- `Impi Tudásbázis/AI-training-prompts.md` → kiegészítve T-P0…T-P2 promptokkal és S10–S15 haladó szcenáriókkal (REST API kérés, hibás elvárás, empátia trigger, multi-turn memória).
- `notes.md` → naplóbejegyzés a roadmap publikálásáról és a következő lépések kijelöléséről.

## Következő lépések
1. Implementálni a dokumentált roadmap elemeit (P0 → intent-alapú szűrés teljes körűen, welcome fix, negatív intent kezelő flow).
2. Az új T/S promptokat batch módban lefuttatni, amíg mindegyik legalább 4/5 pontot kap a kritikus-barát checklist szerint.
