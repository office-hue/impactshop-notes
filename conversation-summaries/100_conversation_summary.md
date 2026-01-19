# 100. Beszélgetés összefoglaló: Kupon harvester stabilizálás + OCR env (2026-01-17)

## Mit csináltunk
- CJ domain mapping becsatolva a harvester whitelist/Gmail allowed_domains listába.
- Kupon kinyerés szigorítva: kontextus‑szűrés, stopword bővítés, HTML‑attribútum alapú kódolvasás, 12 karakteres max.
- OCR env beállítva a központi `capi.env` fájlban, és dokumentálva az impactall gyors emlékeztetőben.
- Árukereső Playwright pagination stabilizálva (href/rel=next + normalizeUrl helper).

## Futások
- Teljes cron‑kör (3 pipeline) újraindítva OCR‑rel.
- Aktív háttérfutás PID: `794` (Árukereső scrape folyamatban).

## Artefaktumok / logok
- `.codex/logs/coupon-harvester-full.run.out`
- `.codex/logs/coupon-harvester-full.cron.log`

## Következő lépés
- Ellenőrizni, hogy a PID `794` kör végigért‑e (DONE sor + új CSV/validation).
