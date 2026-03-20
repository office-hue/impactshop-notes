# AI Memory Phase 0 - Indulási Checklist (Sharity, Részletes)

Status: design only (`no implementation`)
Linked plan: `ai-memory-learning-architecture-2026-03-04.md`

## 1. Cél

Read-only pilot indítása úgy, hogy:
- a futó rendszerek stabilitása ne sérüljön,
- mérhető üzemi haszon jelenjen meg,
- azonnali rollback bármikor végrehajtható legyen.

## 2. Scope és határok

In scope:
- Memory Retriever (read-only)
- manuális Decision/Incident bejegyzés
- retrieval audit log
- heti quality review

Out of scope:
- automatikus code/config módosítás
- auto-merge/deploy
- kötelező org-wide folyamatkényszer

## 3. RACI (pilot)

- Responsible: AI workstream engineer
- Accountable: platform owner
- Consulted: security + legal
- Informed: ops + product

## 4. Előfeltételek (Go/No-Go)

### 4.1 Governance

1. Kanonikus jóváhagyás:
- embedding modell és dim (végleges: `text-embedding-3-large`, `3072`)
- retention baseline
- PII policy
- pilot owner + backup owner

2. Dokumentált rollback runbook.

### 4.2 Technikai

1. Dedikált memory DB/schema.
2. Read/write role szétválasztás.
3. Audit log route működés igazolva.
4. Feature flag megléte retrieverhez.

### 4.3 Security

1. Fail-closed auth.
2. Signed identity binding.
3. Redaction szabály élesítési terv.
4. Access log retention beállítva.

## 5. Pilot végrehajtás (részletes lépések)

### 5.1 Baseline hét (T0)

- Kontextus-felépítési idő mérése baselineként.
- Regresszió és MTTR baseline rögzítés.
- Top 10 ismétlődő hibatípus listázás.

### 5.2 Pilot hét 1 (T1)

- Retriever read-only teszt 5-10 valós taskon.
- Minden tasknál kézi értékelés: hasznos / részben / nem hasznos.
- Fals pozitív minta gyűjtése.

### 5.3 Pilot hét 2 (T2)

- Súlyok finomhangolása (ranking v2).
- Ismétlődő hibákra priorizált linkelés (`causes`/`mitigates`).
- Heti digest mintariport készítése (küldés még opcionális).

## 6. Kötelező sablonok (Phase 0)

1. Decision record sablon:
- `what`
- `why`
- `alternatives`
- `impact`
- `owner`

2. Incident/postmortem sablon:
- tünet
- gyökérok
- detektálás
- fix
- megelőző guard
- regressziós teszt referencia

## 7. KPI-k és célküszöbök

Kötelezően követett KPI-k:
- Context rebuild idő változás (%).
- Retriever acceptance ratio (%).
- Ismétlődő incidentek trendje.
- False-positive ratio.
- Retrieval latency p95.

Javasolt küszöbök Phase 0 végére:
- acceptance >= 60%
- false positive <= 25%
- latency p95 <= 1200 ms

## 8. Exit criteria (Phase 1-re)

Phase 1 csak akkor indulhat, ha:
1. 2 egymást követő heti security/privacy review PASS.
2. KPI küszöbök legalább 80%-a teljesül.
3. Van jóváhagyott postmortem workflow ownershipsel.
4. Rollback dry-run sikeres.

## 9. Risk register (Phase 0)

1. Prompt injection memory itembe kerül.
- Mitigation: source trust + approval.

2. PII accidental ingest.
- Mitigation: redaction + restricted scope.

3. Rossz relevancia miatti félrevezető kontextus.
- Mitigation: operator review + feedback.

4. Túl sok zaj (irreleváns találat).
- Mitigation: filter tightening + stale penalty.

## 10. Rollback terv

Rollback trigger:
- security incident,
- privacy breach gyanú,
- retrieval quality összeomlás.

Rollback lépések:
1. Retriever feature flag OFF.
2. Memory API allowlist mód.
3. Friss write tiltás, audit export.
4. Incident felvétel + RCA.
5. Pilot státusz: paused.

## 11. Operatív ritmus

- Heti 1x (30-45 perc): memory quality review.
- Heti 1x (15 perc): security spot-check.
- Kéthetente: ranking tuning döntés.
- Havonta: retention/privacy audit.

## 12. Phase 0 deliverable csomag

1. Jóváhagyott memory schema v1.1.
2. Dokumentált retriever read-only pilot eredmények.
3. Postmortem sablon + 3 kitöltött valós példa.
4. KPI baseline + trend riport.
5. Go/No-Go javaslat Phase 1-re.

## 13. Amit még érdemes hozzáadni (opcionális)

1. "Top repeated failures" automatikus heatmap.
2. "High-value decision" címkézés (mely döntések térnek vissza gyakran).
3. Feature-szintű memória scope (Leaderboard / Video flow / Legal advisory külön).
4. "Staleness" jelző dashboard (mi avult el és felülvizsgálandó).
5. Jóváhagyási SLA (pl. critical incident 24h, policy 72h).
