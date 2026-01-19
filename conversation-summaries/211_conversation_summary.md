# 211. Beszélgetés összefoglaló: Core Console UI + CLI (2025-12-06 15:00)

## Áttekintés
A kérés a Core Agent UI feladataihoz kapcsolódott: láthatóvá tenni a worker által feldolgozott dokumentumokat, guardolni a dokumentum smoke tesztet, és biztosítani egy impactctl-alapú parancsot, amellyel jobType/params támogatással lehet feladatot indítani.

## Megoldás
- Új admin oldal: `/admin/core-console` (API kulcs védett) listázza a workspace-eket és feladatokat, valamint űrlapot kínál a `/core/tasks` végpont meghívására jobType/jobParams mezőkkel.
- `/healthz` mostantól a `document_ingest` logot is figyeli, így jelzi, ha a dokumentum pipeline 24 órán túl nem futott.
- CLI helper: `bin/impactctl-core-task.sh` – egyszerű curl wrapper, amely AI_AGENT_API_URL/AI_AGENT_API_KEY alapján feladatot küld, opcionálisan JSON params-szal.
- A LangGraph dokumentum loader korábban már két oldalról kap adatot (attachments + worker snapshot), így a UI-ból létrehozott feladatok kimenete automatikusan megjelenik a Graph pipeline-ban is.

## Következő lépések
1. Egészítsd ki a dokumentum UI-t LangGraph status kijelzővel (structuredDocuments/ingestWarnings), és kösd be a guard script outputját.
2. Ha szükséges, az `impactctl` fő parancsba is építsd be a `core-task` subcommandot, hogy ne külön scriptként kelljen hívni.
