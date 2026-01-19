# 203. Beszélgetés összefoglaló: Core Console skeleton (2025-12-05 22:05)

## Áttekintés
A cél az volt, hogy a teljes vállalati asszisztensi feladatkört kiszolgáló Core agenthez közös UI/infra réteget kezdjünk építeni. Ehhez létrejött az első, REST-alapú „Core Console” endpoint készlet, ami workspace-eket és feladatokat tud kezelni.

## Megoldás
- Új `apps/api-gateway/src/services/core-workspaces.ts`: workspace + template definíciók (Impact Shop, Finance, Operations) alapból betöltődnek, opcionálisan konfig fájlból felülírhatók.
- Új `apps/api-gateway/src/services/core-tasks.ts`: fájl-alapú store (tmp/state/core-tasks.json), Drive-path javaslat generálás, task státuszkezelés.
- API végpontok: `GET /core/workspaces`, `GET /core/tasks`, `POST /core/tasks` – mind x-api-key védelemmel, user headerből (`x-user-email`) örökli a „createdBy” mezőt.
- A notes.md rögzíti, hogy ez a Core Console skeleton első lépése (következő iteráció: Drive API integráció + queue + UI front).

## Következő lépések
1. Drive service account műveletek (folder + file létrehozás) bekötése a `createCoreTask` folyamatba.
2. Queue / worker (BullMQ vagy saját) a feladatok futtatásához, UI front-end a kollégáknak.
3. Workspace konfiguráció externalizálása (config/core-workspaces.json) + role alapú jogosultság.
