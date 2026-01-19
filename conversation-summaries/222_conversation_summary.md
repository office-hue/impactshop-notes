# 222. Beszélgetés összefoglaló: Langfuse kliens bekötés

## Áttekintés
A kérés szerint kezdjem meg a Langfuse telemetria bekötését az AI Agent API-ba, hogy az új Core Agent UI élesítéséhez legyen mérhető eseményfolyam.

## Megoldás
- Létrehoztam a `apps/api-gateway/src/services/langfuse-client.ts` modult (`trackLangfuseEvent`, `isLangfuseEnabled`), amely a `LANGFUSE_SERVER_URL` és `LANGFUSE_SERVER_API_KEY` alapján POST-olja az eseményeket a Langfuse track végpontjára.
- A `/core/tasks` endpoint most „core_task_created” eseményt küld (workspace, jobType, attachments flag), az Impi REST chat válasz (`/api/v1/chat/impi`) pedig „impi_chat_response” eventet rögzít (intent, openai model, ajánlatok száma, feldolgozási idő).
- A build (npm run lint) zöld maradt, a `notes.md` rögzíti, hogy a Langfuse secretet most már aktív klienskód is használja.

## Következő lépések
1. Készíts Langfuse dashboardot a fenti eseményekre (kérés/siker arány, latency, költség), állíts be alertet 5xx vagy guard FAIL esetére.
2. Dokumentáld az enablement anyagban, hogyan lehet a Langfuse-reportokat ellenőrizni release előtt.
