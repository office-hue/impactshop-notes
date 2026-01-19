# 231. Beszélgetés összefoglaló: Core Console user manual

## Áttekintés
Felhasználói kézikönyvet kellett készíteni az AI Agent Core Console-hoz (http://core-ai.sharity.hu/admin/core-console), hogy az ops/csapat egységes runbookból érje el és használja a felületet.

## Megoldás
- Összegyűjtöttem a Core Console-hoz tartozó meglévő jegyzeteket (proxy setup, státuszkártyák, guard gombok, CLI helper), majd elkészítettem a `docs/ai-agent-core-console.md` dokumentumot.
- A kézikönyv lefedi az elérést (hosts + API kulcs), a dashboard fő részeit, az új feladat űrlap mezőit, a `bin/impactctl-core-task.sh` használatát, valamint a dokumentum guard triggerelését és a watchdog log táblázatot.
- Hibakeresési szekciót is hozzáadtam (403, STALE kártya, guard hiba stb.), így a felhasználó gyorsan tud reagálni a leggyakoribb problémákra. A `notes.md`-ben rögzítettem a változást.

## Következő lépések
1. Helyezz el frissített screenshotot a Core Console UI-ról (pl. `image/core-console/core-console-YYYYMMDD.png`) és linkeld a kézikönyvhöz.
2. Ha új modul vagy státuszkártya kerül a UI-ba (pl. Langfuse panel), frissítsd a kézikönyv megfelelő fejezetét és a hibakeresési táblázatot.
