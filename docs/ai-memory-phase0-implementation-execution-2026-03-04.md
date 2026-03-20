# AI Memory Phase 0 - Implementációs Terv + Végrehajtási Állapot (2026-03-04)

Status: implementation executed (code completed), rollout pending
Related issue: https://github.com/office-hue/impactshop-notes/issues/35

## 1. Cél

A jóváhagyott kanonikus dokumentumok alapján a Phase 0 read-only memória réteg
technikai kivitelezése és biztonságos aktiválási terve.

## 2. Kanonikus inputok

- Embedding döntés: `text-embedding-3-large`, `3072`
- Phase 0 scope: read-only retriever + manuális decision/incident logging
- RBAC: ideiglenesen 2 admin felhasználó
- Retention/privacy/gate szabályok: kanonikus döntési fájl szerint

## 3. Implementációs bontás

### T1 - API memória szolgáltatás

Megvalósítva:
- új szolgáltatás modul: `apps/api-gateway/src/services/ai-memory-phase0.ts`
- PostgreSQL-alapú memória store hozzáférés
- dedikált schema támogatás (`AI_MEMORY_SCHEMA`)
- opcionális auto schema migration (`AI_MEMORY_AUTO_MIGRATE=1`)

### T2 - Read-only retriever endpoint

Megvalósítva:
- `GET /api/v1/memory/retrieve`
- API kulcs ellenőrzés
- feature flag ellenőrzés (`AI_MEMORY_PHASE0_ENABLED=1`)
- scope/project/environment szűrés
- retrieval audit log mentés

### T3 - Manuális decision / incident logging

Megvalósítva:
- `POST /api/v1/memory/decision`
- `POST /api/v1/memory/incident`
- incidenthez strukturált postmortem mezők kötelezők
- write audit naplózás (`memory_write_audit`)

### T4 - Feedback + pilot metrics

Megvalósítva:
- `POST /api/v1/memory/feedback`
- `GET /api/v1/memory/pilot-metrics`
- acceptance rate számítás alapjai

### T5 - Konfiguráció

Megvalósítva:
- `.env.example` bővítve memória flag-ekkel:
  - `AI_MEMORY_PHASE0_ENABLED`
  - `AI_MEMORY_DATABASE_URL`
  - `AI_MEMORY_SCHEMA`
  - `AI_MEMORY_AUTO_MIGRATE`

## 4. Biztonsági elvek (implementált)

- endpointok API key védettek
- feature flag nélkül nincs elérés
- read-only retriever elkülönítve
- write csak manuális, célzott endpointokon
- write audit log minden bejegyzésnél

## 5. Validáció

Elvégzett:
- TypeScript lint (`npm run -s lint`) PASS az `impact_hub/ai-agent` alatt

Még hátra van (rollout előtti ellenőrzés):
- staging DB kapcsolati teszt
- smoke teszt endpointonként (401/404/201/200)
- rollback dry-run

## 6. Élesítési sorrend (guardos)

1. Staging env beállítások felvétele
2. `AI_MEMORY_PHASE0_ENABLED=1` stagingen
3. Smoke + pilot baseline
4. Security/privacy review
5. Prod env beállítások
6. Feature flag ON prodon

## 7. Rollback

- azonnali: `AI_MEMORY_PHASE0_ENABLED=0`
- szükség esetén route-level blokkolás
- audit export + incident rögzítés

## 8. Következő lépés

- staging deploy + endpoint smoke futtatás
- issue #35-ben státuszfrissítés
