# ChatGPT beszélgetés - OpenAPI PIN végpontok
**Dátum**: 2026-01-18
**Cél**: PIN végpontok felvétele az OpenAPI specifikációba.
**Status**: Megoldva

## Probléma leírása
Az `openapi.yaml` fájlba be kellett emelni a PIN kiadás és ellenőrzés
végpontokat, a hozzájuk tartozó sémákkal és hibakódokkal.

## ChatGPT megoldása
Hozzáadtam a `/identity/pin/issue` és `/identity/pin/verify` végpontokat, a
hozzájuk tartozó request/response sémákat, és frissítettem a pseudo‑ID pattern
korlátozását 10–12 karakterre.

## Tesztelés eredménye
- `npx swagger-cli validate docs/api/openapi.yaml` sikeres.

## Következő lépések
- Implementáció a WordPress REST controllerben.

## Kapcsolódó fájlok
- [x] `docs/api/openapi.yaml`
- [x] `notes.md` frissítve
- [x] `conversation-summaries/328_conversation_summary.md`

## GitHub Copilot notes
Nincs külön megjegyzés.
