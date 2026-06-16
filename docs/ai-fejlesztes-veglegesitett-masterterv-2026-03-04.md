# AI fejlesztés – véglegesített masterterv (kanonikus)

> Dátum: 2026-03-04  
> Jóváhagyott kanonikus döntések:  
> 1A `impact_hub/ai-agent`, 2A `impactshop-notes/docs`, 3A `impactshop-notes/scripts`, 4A repo workflow only, 5A top-level read-only, 6A issue-csomag véglegesítés

## 1) Rövid végső állapot

1. Kanonikus AI kódforrás: `impact_hub/ai-agent`.
2. Kanonikus tervdoksi hely: `impactshop-notes/docs`.
3. Kanonikus issue script: `impactshop-notes/scripts/create-ai-issues.sh`.
4. CI/workflow csak repókon belül számít kanonikusnak.
5. Top-level duplikátumok read-only referenciának tekintendők.
6. A repo-helyi governance entrypoint: `docs/impactshop-governance-system-plan-2026-06-16.md`.

## 1.1) Helyi governance hub

Az `impactshop-notes` repo helyi governance, review es continuity belepesi pontja:

- `docs/impactshop-governance-system-plan-2026-06-16.md`
- `docs/impactshop-governance-hub-coherence-audit-2026-06-16.md`

Ez a hub nem valtja ki a kanonikus policy-dokumentumokat, hanem rovid belso rendszerterv/reference pontkent osszefogja a repo sajat anchorjait.

## 2) Válasz a kiemelt kérdésre (Phase 1–28 legal)

Igen, **érintett**.

Indok:
1. A top-level `ai-agent/` és a kanonikus `impact_hub/ai-agent` nincs szinkronban.
2. Emiatt a „Phase 1–28 legal implementáció kész” állítás csak akkor tekinthető véglegesnek, ha a kanonikus fában is visszaellenőrizhető.
3. Kötelező lépés: parity-audit (T-000), csak ezután szabad Phase állapotot lezártnak tekinteni.

## 3) Koherencia és biztonsági audit összegzés

## 3.1 P0 kritikus tételek

1. Fail-open API key logika a kanonikus API gateway-ben.
2. API key query/body elfogadás, csak headeres modell hiánya.
3. Role binding fallback (header/body/default role), trusted aláírás nélküli ágak.
4. Admin `?key=` query secret URL-ben.
5. Admin/banner + promotions/memory endpoint auth egységtelenség.
6. Törött smoke tesztlánc (hiányzó tesztfájlokra mutat).

## 3.2 P1 fejlesztési hatékonyság tételek

1. Change-profile mátrix.
2. Path-szintű instrukciók (`legal-agent` külön fájllal).
3. PR sablon (risk/evidence/rollback/scope).
4. Security baseline (secret scanning + dependabot; CodeQL külön ütem).
5. Release evidence gyűjtés.
6. Read-only advisor digest specifikáció.

## 4) Végleges backlog (2 hét)

## Hét 1 (P0)

1. T-000 Kanonikus parity-audit (`impact_hub/ai-agent` vs top-level `ai-agent`, csak dokumentálás).
2. T-001 Docs/runtime host/policy szinkron.
3. T-002 API auth hardening (fail-closed egységesítés).
4. T-003 Trusted role binding hardening + tesztek.
5. T-004 Smoke tesztlánc javítás.
6. T-006 Pre-push/release gate validálás + hook install standard.

## Hét 2 (P1)

1. T-101 Change-profile mátrix.
2. T-102 Path instrukciók (+ legal-agent.instructions.md).
3. T-103 PR template szigorítás.
4. T-104 Security baseline (secret scanning/dependabot first).
5. T-105 Release evidence automata.
6. T-106 Read-only advisor digest spec.

## 5) Döntési guardrail

1. Implementáció előtt mindig a kanonikus forrás számít.
2. Top-level nem használható „kész” állítás igazolására.
3. A script csak jóváhagyott scope-pal futhat issue-nyitásra.
