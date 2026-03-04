# AI Memory - Kanonikus Döntések (2026-03-04)

Status: proposed+owner input consolidated
Linked docs:
- ai-memory-learning-architecture-2026-03-04.md
- ai-memory-phase0-checklist-2026-03-04.md
- ai-memory-canonical-approvals-checklist-2026-03-04.md

## 1) Embedding modell + költség (végleges)

Cél: magas minőség, kontrollált költséggel.

Végleges döntés:
- Modell: `text-embedding-3-large`
- Dimenzió: `3072`
- Indok: minőség-prioritás, jobb szemantikus lefedettség jogi és technikai tartalmaknál.

Költség kalkuláció (modellfüggetlen):
- Havi embedding token = `új/updated memory tokenek száma`
- Havi költség = `(havi embedding token / 1,000,000) * (modell ára / 1M token)`

Becsült példa (nem vendor-fix):
- 150,000 token/hó -> költség = `0.15 * (ár/1M)`
- 500,000 token/hó -> költség = `0.50 * (ár/1M)`
- 2,000,000 token/hó -> költség = `2.00 * (ár/1M)`

Megjegyzés:
- A fő költség jellemzően nem az embedding, hanem a generatív hívások és az operatív idő.
- Embedding oldalon a költség jól fogható ingest throttlinggal és csak változott rekordok újra-embedelésével.
- Opcionális költségfék: heti batch re-embed csak a megváltozott rekordokra.

## 2) Retention idők (végleges javaslat)

A kért kötelező megőrzés és audit igény mellett:

- `session`: 90 nap
- `project`: 60 hónap
- `org incident/postmortem/policy`: 120 hónap
- `retrieval_log raw`: 12 hónap
- `retrieval_log aggregated`: 120 hónap

Ráció:
- session rövid marad, de vizsgálható.
- projekt és szervezeti tudás hosszú távon visszakereshető.
- jogi/forenzikus nyomvonal megmarad.

## 3) PII és ügyvédi titok (végleges irány)

Tulajdonosi döntés alapján:
- Az adatok ügyvédi titok alá esnek és kötelezően megőrzendők.

Ennek megfelelő rendszerelv:
- PII nem tiltott általánosan, de `restricted` besorolás kötelező.
- Titkosítás és audit kötelező minden `restricted` elemre.
- Export/egress csak explicit jóváhagyással.

## 4) Jogosultság (végleges, jelenlegi állapot)

Jelenleg 2 teljes jogkörű felhasználó:
- dr.horvath.helena@bujdosoiroda.com
- bujdoso.arnold@bujdosoiroda.com

RBAC ideiglenes profil:
- mindkettő: `admin` + `reviewer` + `curator` + `reader`

Jövőbeni bővítésnél:
- szerepkörök szétválasztása kötelező (minimum reviewer/admin külön).

## 5) Phase gate küszöbök (végleges javaslat)

Phase 0 -> Phase 1
- Retriever acceptance >= 60%
- False positive <= 25%
- Retrieval latency p95 <= 1200 ms
- 0 kritikus security/privacy esemény

Phase 1 -> Phase 2
- Retriever acceptance >= 65%
- False positive <= 20%
- Repeated incident trend: legalább 10% javulás
- 2 egymást követő weekly review PASS

Phase 2 -> Phase 3
- Acceptance >= 70%
- False positive <= 15%
- MTTR legalább 15% javulás baseline-hoz képest
- Rollback dry-run PASS

## 6) Weekly digest címzettek (végleges)

Rövid összefoglaló:
- dr.horvath.helena@bujdosoiroda.com

Teljes technikai részletesség:
- bujdoso.arnold@bujdosoiroda.com

## 7) Nyitott pont

- Nincs nyitott pont: modell és dim véglegesítve (`text-embedding-3-large`, `3072`).
