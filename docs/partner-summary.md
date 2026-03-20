# Partner prep – summary

## Cél
Non‑affiliate partner integráció gyors, auditálható és idempotens bevezetése.

## Fő komponensek
- **API**: `docs/partner-api-openapi.yaml`
- **Auth**: HMAC + Bearer (`docs/partner-auth-secrets.md`)
- **Config**: max kedvezmény, cap, min kosár (`docs/partner-config-storage.md`)
- **Ledger**: partner tx tábla + link a ledgerhez (`docs/partner-db-schema.md`)

## Tesztelés / QA
- Postman: `docs/partner-postman-collection.json`
- Fixtures: `fixtures/partner/*.json`
- Runner: `tools/partner-test-runner.cjs`
- QA: `tools/partner-qa.cjs`
- OpenAPI check: `tools/openapi-check.cjs`

## Operáció
- Reconcile: `docs/partner-reconciliation-job.md`
- Monitoring: `docs/partner-monitoring-kpi.md`
- Dispute: `docs/partner-dispute-policy.md`
- Runbook: `docs/partner-staging-runbook.md`

## UI / UX
- Partner dashboard: `docs/partner-dashboard-wireframes.md`
- Admin UI: `docs/partner-admin-ui-draft.md`

## Következő lépés
- Pilot partner kiválasztása
- Staging smoke (Postman/runner)
- Prod kulcsok kiadása
