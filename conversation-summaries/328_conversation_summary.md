# 328. Beszélgetés összefoglaló: OpenAPI PIN végpontok

Kérés: a PIN kiadás/ellenőrzés végpontok felvétele az OpenAPI specifikációba.

- `POST /identity/pin/issue` és `POST /identity/pin/verify` hozzáadva.
- Schema: `PinIssueRequest/Response`, `PinVerifyRequest/Response`.
- Pseudo-ID pattern 10–12 karakterre frissítve.
- Validáció: `npx swagger-cli validate docs/api/openapi.yaml` → OK.
