# 302. Beszélgetés összefoglaló: CJ smoke script

- Létrehoztam egy futtatható CJ Commission Detail smoke scriptet: `scripts/cj-commission-smoke.sh`.
- A script Python3-mal állítja össze a GraphQL payloadot, és a `CJ_PAT`, `SINCE_EVENT_DATE`, `BEFORE_EVENT_DATE`, `VALIDATION_STATUS`, `SINCE_COMMISSION_ID` env változókat használja.
- A `notes.md` naplóban rögzítettem a script létrejöttét.
