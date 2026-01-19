# Excel / PDF elemzés tudásbázis

## 1. Cél
Az Impi/Core Agent képes Excel és PDF alapú üzleti dokumentumokat (költségvetés, beszámoló, üzleti terv) normalizálni, majd a LangGraph pipeline részeként felhasználni a kulcsmegállapításokat (Graphiti memória + ajánlás). Ez a jegyzet a műveleti csapatnak szól: hogyan futtatható lokálisan a feldolgozás, hogyan ellenőrizhető a guard, és milyen biztonsági korlátokat kell betartani.

## 2. Quickstart (CLI)
1. Lépj az `ai-agent/` gyökérbe.
2. Futtasd az Excel extractor CLI-t:  
   ```bash
   npm run document:ingest -- --file path/to/document.xlsx --outDir tmp/ingest/excel/sample
   ```
   - Output: minden munkalap JSON fájlban (`<sheet-id>-<slug>.json`) + `metadata.json` összefoglaló.
3. PDF esetén:  
   ```bash
   tsx tools/pdf/table-ocr.ts --file path/to/document.pdf
   ```
   - A kimenet JSON, `tables[].previewRows` mezővel és `textPreview` műszerfallal.
4. A CLI a `apps/document-ingest/src/index.ts` modult használja (ExcelJS + pdf-parse + opcionális Camelot), a további feldolgozás a LangGraph `documentLoaderNode` → `documentAnalysisNode` útvonalon történik.

## 3. Guard / ellenőrzés
- Új guard script: `.codex/guards/document-ingest.sh` – óránként lefuttatható a meglévő guard cronból.
  - Lépések: generál egy mintaköltségvetést ExcelJS-sel → `npm run document:ingest` → `metadata.json` alapján ellenőrzi a lapokat.
  - Log: `.codex/logs/document-ingest.log`, a guard scoreboardban `document_ingest` flagként jelenik meg.
- Hibakeresés: ha a guard FAIL/WARN státuszt ad, futtasd manuálisan a CLI-t (1. pont), nézd meg a `tmp/…` outputot, majd ellenőrizd a Camelot/Tabula függőségeket (ld. 4. pont).

## 4. PDF táblázat OCR (Camelot integráció)
- Opcionálisan engedélyezhető a `DOCUMENT_INGEST_CAMELOT=1` env-vel. Ehhez a szerveren legyen elérhető Python + `camelot-py` + Ghostscript.
- Ha az env nincs beállítva vagy a Camelot hívás hibát dob, a fallback szöveg-alapú táblafelismerés fut.
- Verbózus logoláshoz állítsd `DOCUMENT_INGEST_VERBOSE=1`-re (guard/CLI futásnál).

## 5. Biztonság / fertőzésvédelem
- **Csak megbízható forrású fájlokat tölts fel**: jelenleg a user-facing Impi widgetben nincs szabad feltöltés, kizárólag az admin oldal (Banner elemzés → Dokumentum OCR) fogad fájlokat.
- A feltöltött fájlok maximum 10 MB-osak lehetnek, és csak `application/pdf` vagy `application/vnd.ms-excel` / `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` MIME engedélyezett.
- Minden fájlt VÍRUSMENTES környezetben (pl. Google Drive, M365 E5) kell előszűrni; a pipeline nem végez antivírus vizsgálatot.
- Tilos ismeretlen felhasználótól származó fájlokat közvetlenül a szerverre feltenni. Használj köztes tárolót (Drive/SharePoint), ahol az MDM/AV szűr.

## 6. UI / Graphiti integráció
- Admin oldali „Dokumentum OCR” form (Banner elemzés) drag&drop + progress sávval futtatja a `POST /api/v1/vision/document-ocr` endpointot, a kimenetet JSON-ban adja vissza.
- A `documentAnalysisNode` az insightokat Graphiti memóriába is átküldi (`/ingest/document-insights`), így a Graphiti dashboardon is megjelenik.
- Impi válaszai automatikusan hivatkozzák az insightokat (📄 blokk), ha a sessionhez tartozó dokumentum elemzés történt.

## 7. FAQ
- **PDF / Excel upload fertőzés veszélyes?** – közvetlen user upload nincs; csak admin use-case létezik. Minden fájlt előszűrt tárhelyről töltünk fel, a MIME + méret limitek blokkolják az extrém inputot. Ügyelj arra, hogy a guard script csak lokális, generált fájlt használjon.
- **Nem jelenik meg a document_ingest guard a scoreboardon** – ellenőrizd, hogy `.codex/guards/document-ingest.sh` fut, és a `/healthz` optional features listája tartalmazza a `document_ingest` flaget.
- **Camelot hiányzik** – `pip install camelot-py[cv] ghostscript` + `brew / apt-get install ghostscript`. Ha nem konfigurálható, állítsd a `DOCUMENT_INGEST_CAMELOT` env-et 0-ra.

## 8. Ajánlott end-to-end workflow
1. **Forrás validálás** – a dokumentumot először Drive/SharePoint AV szűrőn futtasd, majd oszd meg az `ai-agent` csapattal. Ha lehetséges, konvertáld `.xlsx` formátumba (a CLI ezt kezeli a legjobban).
2. **Lokális ellenőrzés** – futtasd `npm run document:ingest -- --file <fájl> --outDir tmp/ingest/excel/<id>` parancsot, majd nézd meg a generált JSON-t. Ha PDF az input, próbáld ki a Camelot módot is (`DOCUMENT_INGEST_CAMELOT=1`).
3. **Guard / log** – ha a CLI sikeres, futtasd le a `document-ingest` guardot is (script automatikusan logol). Guard WARN esetén ellenőrizd a laza cellákat vagy pivot struktúrát.
4. **LangGraph seed** – az admin `POST /api/v1/vision/document-ocr` feltöltéssel add át a fájlt az Impi/Core agentnek. A LangGraph `documentLoaderNode` automatikusan kitölti a `structuredDocuments` mezőt, a `documentAnalysisNode` pedig Graphitibe is menti az insightokat, így az ajánlórendszer és a Graphiti dashboard ugyanazt a forrást látja.
5. **Review / publikálás** – ellenőrizd az Impi válaszban megjelenő 📄 blokkot: ha tartalmazza a kulcsadatokat és cellahivatkozást, a dokumentum feldolgozás sikeres volt. Ha hiányos, futtasd újra a CLI-t magasabb Camelot beállításokkal vagy manuális mintasorral.

Legjobb gyakorlat: Excel esetén mindig biztosítsd, hogy az első sor header, PDF esetén pedig a táblázat minimális 2 oszlopot tartalmazzon. Ha a dokumentumban csak szöveges összefoglaló található, érdemes előbb Vision/PDF text BLOB-ot készíteni, majd manuálisan kiválasztani a releváns számokat – így elkerülhető, hogy a pipeline üres táblát adjon.

Ez a Tudásbázis jegyzet folyamatosan frissül, ha új ingest pipeline kerül a rendszerbe. EOF
