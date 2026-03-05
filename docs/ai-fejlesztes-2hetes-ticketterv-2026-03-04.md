# AI fejlesztés – 2 hetes ticketterv (kanonikus)

> Dátum: 2026-03-04  
> Alap: `docs/ai-fejlesztes-veglegesitett-masterterv-2026-03-04.md`

## Hét 1 (P0)

## T-000 – Kanonikus parity-audit (kötelező)
- Repo: `impactshop-notes` + `impact_hub`
- Cél: rögzíteni, mi hiányzik/mi tér el a top-level `ai-agent` és a kanonikus `impact_hub/ai-agent` között.
- AC:
  - [ ] Dokumentált fájllista: hiányzó/eltérő modulok.
  - [ ] Jóváhagyott migrációs lista (csak terv, nincs kódmigráció ebben a ticketben).

## T-001 – Docs/runtime baseline szinkron
- Repo: `impactshop-notes`
- Cél: host/policy/guard valóság egységesítése.
- AC:
  - [ ] `cp40` maradványok kezelve.
  - [ ] `s59` és guard policy egységes hivatkozással szerepel.

## T-002 – API auth fail-closed egységesítés
- Repo: `impact_hub`
- Cél: fail-open ágak lezárása.
- AC:
  - [ ] Nincs olyan endpoint, ahol API key hiányában implicit hozzáférés marad.
  - [ ] Egységes `hasValidApiKey` vagy middleware alapú védelem.
  - [ ] API key query/body kezelése policy szerint szűkítve.

## T-003 – Trusted role binding hardening
- Repo: `impact_hub`
- Cél: role spoofing kockázat csökkentése.
- AC:
  - [ ] Aláírás nélküli role nem emel jogot.
  - [ ] Negatív/pozitív tesztek kész.
  - [ ] Signature séma dokumentált.

## T-004 – Smoke tesztlánc helyreállítás
- Repo: `impact_hub`
- Cél: a package script ne mutasson hiányzó tesztekre.
- AC:
  - [ ] `test:smoke` determinisztikusan lefut.
  - [ ] CI-ben reprodukálható.

## T-006 – Pre-push / release gate validálás
- Repo: `impactshop-notes` + `impact_hub`
- Cél: strict audit gate konzisztens ellenőrzése.
- AC:
  - [ ] Hook viselkedés dokumentált.
  - [ ] Új clone esetére install/verify lépés dokumentált.

## Hét 2 (P1)

## T-101 – Change-profile mátrix
## T-102 – Path instrukciók (legal-agent külön fájl)
## T-103 – PR template szigorítás
## T-104 – Security baseline (secret scanning + dependabot first)
## T-105 – Release evidence automata
## T-106 – Read-only advisor digest specifikáció

## Definition of Done

1. P0 ticketek lezárva és bizonyítva.
2. P1 ticketek dokumentáltan előkészítik a gyorsabb, biztonságosabb fejlesztést.
3. Implementációs változtatás csak külön fejlesztési körben történik.

