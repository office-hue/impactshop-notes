# AI Memory & Learning Architecture (Sharity) - 2026-03-04 (Részletes)

Status: design only (`no implementation`)
Owner: Sharity AI workstream
Scope: `impact_hub` + `impactshop-notes` + AI Agent Core workflow alignment

## 1. Executive summary

A cél egy tartós, auditálható, biztonságos memória-réteg, amely leválasztja a modell válaszképességét
(`reasoning`) a szervezeti tudásról (`memory`). Ennek eredménye:

- gyorsabb kontextus-felépítés,
- kevesebb ismétlődő regresszió,
- ellenőrizhető tanulás az incidentekből,
- kontrollált, feature-flagelt AI bevezetés.

## 2. Alapelvek

1. `Model != Memory`:
- a modell nem tartós tudástár.
- a tartós tudást SQL + audit biztosítja.

2. `Fail-closed` működés:
- auth/proxy/scope hiba esetén nincs write/read downgrade.

3. `Human-in-the-loop`:
- Phase 0-2: kritikus workflowban nincs automatikus beavatkozás.

4. `Minimum necessary data`:
- PII minimalizálás, redaction ingest előtt.

5. `Version everything`:
- döntés, incident, policy mind verziózott objektum.

## 3. Nem-célok (jelen tervben)

- autonóm kódmódosítás/merge/deploy.
- teljesen automatikus policy frissítés.
- jogi/adójogi automatikus döntéshozatal emberi jóváhagyás nélkül.

## 4. Memória rétegek

### 4.1 Session memória (rövid táv)

Cél: egy aktív munka/blokk folytonossága.

- Tartalom: cél, döntési állapot, nyitott kérdések, risk note.
- Auto-summary: 10-20 üzenetenként.
- TTL: 7-30 nap.
- Használat: következő prompt/context előtöltés.

### 4.2 Projekt memória (közép táv)

Cél: csapat- és ticket-szintű tudás megőrzése.

- Entitások: `fact`, `decision`, `constraint`, `known_issue`, `runbook`, `task_link`.
- Verziózott store: SQL + embedding.
- TTL: 18 hónap (kanonikus jóváhagyásig default).

### 4.3 Szervezeti memória (hosszú táv)

Cél: ismétlődő hibák és bevált minták intézményesítése.

- Entitások: `incident`, `postmortem`, `policy`, `guard_pattern`.
- TTL: 36+ hónap, compliance szerint.
- Kimenet: pre-flight guard, checklist, standard runbook.

## 5. Részletes komponens-architektúra

### 5.1 Ingestion layer

Források:
- GitHub issue/PR/commit meta
- deploy log és incident log
- runbook/md dokumentáció
- manuális döntésrögzítés

Feladat:
- normalizálás közös sémára,
- duplikáció azonosítás,
- source trust címkézés.

### 5.2 Policy & Sanitization layer

- PII redaction (email, telefon, adószám minták tokenizálása).
- prompt injection marker szűrés.
- scope + environment címkézés (`dev/staging/prod`).

### 5.3 Memory store

- PostgreSQL 16 táblák (ACID, audit).
- pgvector embedding index.
- link-gráf kapcsolatok (cause/mitigation/supersede).

### 5.4 Retrieval orchestration

Lépések:
1. Query intent + filter builder.
2. Hard filter (scope, project, environment, status).
3. Hybrid recall:
- lexical (title/tags/ILIKE/BM25 jellegű),
- semantic (cosine vector),
- graph expansion (kapcsolt node-ok).
4. Re-rank kockázat/súly/recency alapon.
5. Top-k válasz context payload.

### 5.5 Learning loop engine

- incident/postmortem rögzítésből új memory item.
- checklist delta-javaslat.
- regression-test-javaslat.
- weekly digest input aggregálás.

### 5.6 Governance & audit

- write audit trail.
- RBAC (reader, curator, reviewer, admin).
- approver workflow kritikus itemekre.

## 6. Zárt tanulási loop (Incident -> Prevention)

1. Incident létrejön.
2. Kötelező postmortem sablon kitöltés.
3. Strukturált mentés + linkelés (issue/pr/test/checklist).
4. Retrieval rendszer következő hasonló feladatnál priorizálja.
5. Heti review során:
- fals pozitív,
- hiányzó guard,
- update szükséglet.

## 7. AI funkciók és konkrét működés

### 7.1 Memory Retriever

- Trigger: új task, incident, deploy prep.
- Input: query + scope + project + env.
- Output: top-k releváns objektum rövid indoklással.

### 7.2 Decision Logger

- Trigger: elfogadott technikai döntés.
- Kötelező mezők: `what`, `why`, `alternatives`, `impact`, `owner`.

### 7.3 Failure Pattern Detector

- Trigger: napi/órás log batch.
- Output: klaszterezett hibatípus + trend + súly.

### 7.4 Weekly Learning Digest

- Trigger: heti ütemezés.
- Címzett: jóváhagyott e-mail lista.
- Tartalom: top incidentek, ismétlődő minták, javasolt guardok.

### 7.5 Pre-flight Risk Checker

- Trigger: deploy előtti ellenőrzés.
- Input: változott komponensek + ismert hibatérkép.
- Output: risk score + blokkoló / nem blokkoló figyelmeztetés.

## 8. Memory Schema v1.1 (SQL)

```sql
-- 0) Extensions
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- 1) Core items
CREATE TABLE IF NOT EXISTS memory_items (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  scope TEXT NOT NULL CHECK (scope IN ('session','project','org')),
  item_type TEXT NOT NULL CHECK (item_type IN (
    'fact','decision','constraint','known_issue','runbook','incident','postmortem','policy','task_link','guard_pattern'
  )),
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  project_key TEXT,
  environment TEXT CHECK (environment IN ('dev','staging','prod')),
  severity TEXT CHECK (severity IN ('low','medium','high','critical')),
  status TEXT CHECK (status IN ('draft','active','superseded','archived')) DEFAULT 'active',
  source_kind TEXT,
  source_ref TEXT,
  source_trust NUMERIC(5,4) DEFAULT 0.5000,
  quality_score NUMERIC(5,4) DEFAULT 0.5000,
  tags TEXT[] DEFAULT '{}',
  created_by TEXT,
  updated_by TEXT,
  approved_by TEXT,
  approved_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  valid_from TIMESTAMPTZ,
  valid_to TIMESTAMPTZ,
  pii_level TEXT CHECK (pii_level IN ('none','low','restricted')) DEFAULT 'none'
);

-- 2) Historical versions
CREATE TABLE IF NOT EXISTS memory_item_versions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  memory_id UUID NOT NULL REFERENCES memory_items(id) ON DELETE CASCADE,
  version_no INT NOT NULL,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  tags TEXT[] DEFAULT '{}',
  changed_by TEXT,
  change_reason TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(memory_id, version_no)
);

-- 3) Embeddings
CREATE TABLE IF NOT EXISTS memory_embeddings (
  memory_id UUID PRIMARY KEY REFERENCES memory_items(id) ON DELETE CASCADE,
  model TEXT NOT NULL,
  dim INT NOT NULL,
  embedding vector(1536),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 4) Graph links
CREATE TABLE IF NOT EXISTS memory_links (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  from_id UUID NOT NULL REFERENCES memory_items(id) ON DELETE CASCADE,
  to_id UUID NOT NULL REFERENCES memory_items(id) ON DELETE CASCADE,
  relation_type TEXT NOT NULL CHECK (relation_type IN (
    'causes','mitigates','supersedes','duplicates','related_to','implemented_by','verified_by','depends_on'
  )),
  weight NUMERIC(5,4) DEFAULT 0.5000,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (from_id, to_id, relation_type)
);

-- 5) Feedback loop
CREATE TABLE IF NOT EXISTS memory_feedback (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  memory_id UUID NOT NULL REFERENCES memory_items(id) ON DELETE CASCADE,
  useful BOOLEAN,
  score INT CHECK (score BETWEEN 1 AND 5),
  note TEXT,
  created_by TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 6) Retrieval logs
CREATE TABLE IF NOT EXISTS memory_retrieval_log (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  query_text TEXT,
  query_scope TEXT,
  project_key TEXT,
  top_k INT,
  selected_ids UUID[] DEFAULT '{}',
  accepted_ids UUID[] DEFAULT '{}',
  latency_ms INT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 7) Write audit
CREATE TABLE IF NOT EXISTS memory_write_audit (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  action TEXT NOT NULL CHECK (action IN ('insert','update','delete','approve','archive')),
  memory_id UUID,
  actor TEXT,
  reason TEXT,
  payload_hash TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 8) Incident postmortem details
CREATE TABLE IF NOT EXISTS incident_postmortem (
  memory_id UUID PRIMARY KEY REFERENCES memory_items(id) ON DELETE CASCADE,
  symptom TEXT NOT NULL,
  root_cause TEXT NOT NULL,
  detection_method TEXT NOT NULL,
  fix_summary TEXT NOT NULL,
  prevention_guard TEXT NOT NULL,
  regression_test_ref TEXT,
  checklist_ref TEXT,
  owner TEXT,
  resolved_at TIMESTAMPTZ
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_memory_items_scope_type ON memory_items(scope, item_type);
CREATE INDEX IF NOT EXISTS idx_memory_items_project ON memory_items(project_key, status);
CREATE INDEX IF NOT EXISTS idx_memory_items_tags ON memory_items USING GIN(tags);
CREATE INDEX IF NOT EXISTS idx_memory_items_updated_at ON memory_items(updated_at DESC);
CREATE INDEX IF NOT EXISTS idx_memory_links_from ON memory_links(from_id);
CREATE INDEX IF NOT EXISTS idx_memory_links_to ON memory_links(to_id);
CREATE INDEX IF NOT EXISTS idx_memory_versions_memory ON memory_item_versions(memory_id, version_no DESC);
CREATE INDEX IF NOT EXISTS idx_memory_retrieval_log_time ON memory_retrieval_log(created_at DESC);

-- Vector index (tuning by data volume)
CREATE INDEX IF NOT EXISTS idx_memory_embeddings_ivfflat
ON memory_embeddings USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);
```

## 9. Retrieval ranking v2

Formula:

`score = 0.40*semantic + 0.15*lexical + 0.15*recency + 0.10*severity + 0.10*source_trust + 0.10*feedback`

Kiegészítések:
- `graph_boost`: +0.05 additív boost, ha közvetlenül kapcsolódik aktív incidenthez.
- `stale_penalty`: -0.10 ha `valid_to` lejárt vagy superseded.

Hard filter sorrend:
1. status=active
2. scope match
3. project/environment match
4. pii_level hozzáférés ellenőrzés

## 10. Retention és adatvédelmi policy

Retention baseline:
- session: 30 nap
- project: 18 hónap
- org incidents/postmortem: 36 hónap
- retrieval log raw: 90 nap, utána aggregált stat marad

Privacy kontrollok:
- ingest előtt regex + NER redaction.
- restricted tartalom külön role-lal olvasható.
- security/legal témákhoz source allowlist.

## 11. Threat model és guard mátrix

Fő kockázatok:
1. Prompt injection bejut a memóriába.
2. Header spoofing/role escalation.
3. PII bekerül plain textben.
4. Hallucinált tartalom magas trust score-ral kerül mentésre.
5. Stale/invalid döntés újrahasznosítása.

Guardok:
- source_trust + approval gate,
- signed identity kötelező,
- pii redaction pipeline,
- versioning + supersede jelölés,
- retrieval hard filter + stale penalty.

## 12. Operáció és observability

Kötelező metrikák:
- retrieval latency p50/p95
- top-k acceptance ratio
- repeated incident rate
- policy violation count
- false-positive memory hit ratio

Dashboard nézetek:
- Memory Quality dashboard
- Incident Learning dashboard
- Pre-flight Risk dashboard

## 13. Integráció a jelenlegi rendszerrel

Érintett jelenlegi funkciók (tervezési szinten):
- Leaderboard/transzparencia narratíva: retrieval context tuning.
- Videó-támogatás AI flow: intent + known_issue gyors visszatöltés.
- Legal rész: advisory tudás csak approval alatt, audit trail kötelező.

Nem érintett közvetlenül:
- production üzleti logika automatikus módosítása.

## 14. Rollout terv (gated)

Phase 0: read-only retriever + manuális logging
Phase 1: decision/incident kötelező struktúrált rögzítés
Phase 2: pre-flight risk checker advisory
Phase 3: weekly digest + pattern detector
Phase 4: kontrollált auto-suggestion (write nélkül)

Minden fázis gate:
- security check PASS
- privacy check PASS
- KPI minimum elérés
- rollback teszt PASS

## 15. KPI és SLO célérték javaslat

- Context rebuild idő: -40% 8 héten belül
- Repeated incident rate: -25% 12 héten belül
- Retriever acceptance: >= 65%
- MTTR: -20%
- PII leak esemény: 0 tolerancia

## 16. Jóváhagyást igénylő kanonikus pontok

1. Embedding model + dim
2. Retention végleges idők
3. PII redaction szabályrendszer
4. Digest címzettek és tartalmi szint
5. Phase gate küszöbök (KPI/SLO)
6. Legal advisory scope korlátai

## 17. Rövid zárás

A részletes terv célja egy olyan memória-rendszer, amely nem szétbarmolja a futó rendszert,
hanem kontrolláltan, auditáltan és biztonságosan ad kontextust és tanulási mechanizmust.
A fejlesztés ettől gyorsabb, pontosabb és stabilabb lesz, miközben a kockázat kezelhető marad.
