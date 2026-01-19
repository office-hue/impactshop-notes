# 219. Beszélgetés összefoglaló: Core Console guard státusz

## Áttekintés
Feladat: a Core Agent admin UI-t egészítsem ki a dokumentum-ingest guard állapotával, hogy élesítés előtt is látható legyen, mikor futott utoljára a mintadokumentum feldolgozás.

## Megoldás
- Új `DOCUMENT_INGEST_LOG_PATH` env (default: `../impactshop-notes/.codex/logs/document-ingest.log`) alapján az API gateway beolvassa a guard log legfrissebb sorát, és státuszkártyát renderel a `/admin/core-console` oldalon.
- A `renderCoreConsolePage()` most kártyát rajzol (OK/WARN/FAIL jelzéssel, időbélyeggel és üzenettel); ha még nincs log, figyelmeztető blokk jelenik meg.
- A route TypeScript típusa frissült, új `GuardStatus` típus és `loadDocumentIngestStatus()` helper készült; a build (`npm run lint`) zöld.

## Következő lépések
1. Kapcsold be a guard crontabban a `.codex/guards/document-ingest.sh` scriptet, hogy a státuszkártya folyamatosan frissüljön.
2. Tervezz hasonló státuszkártyát a Playwright/Gmail/Reliability modulokra a `/healthz` kimenet alapján.
