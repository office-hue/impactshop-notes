# Langfuse dashboard/alert TODO – 2025-12-06

Az események (core_task_created, impi_chat_response) már futnak, de a dashboard és Discord alert tényleges beállítása még nincs kész. Következő lépés:
1. Langfuse web UI → hozz létre panelt (napi count, avg processing_ms).
2. Hozz létre Discord webhook riasztást (absence 15 perc, error rate >10%).
3. Release checklistben rögzítsd, hogy deploy előtt ellenőrizni kell a panel/alert státuszát.
