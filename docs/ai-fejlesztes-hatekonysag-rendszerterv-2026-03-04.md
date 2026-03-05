# AI fejlesztés hatékonysági rendszerterv (kanonikus változat)

> Dátum: 2026-03-04  
> Kapcsolódó master: `docs/ai-fejlesztes-veglegesitett-masterterv-2026-03-04.md`

## Cél

Gyorsabb, pontosabb, megbízhatóbb AI fejlesztési működés, koherens forráskezeléssel és biztonsági kapukkal.

## Rendszerszintű döntések

1. Kanonikus AI forrás: `impact_hub/ai-agent`.
2. Kanonikus dokumentáció: `impactshop-notes/docs`.
3. Top-level duplikátumok: read-only referencia.

## Fő problémák

1. Kettős AI fa drift (top-level vs kanonikus repo).
2. API auth/rbac heterogén állapot.
3. Törött smoke tesztlánc.
4. Docs/runtime host drift.

## Megoldási modell

1. P0: parity + biztonság + teszt stabilizáció.
2. P1: throughput + review gyorsítás + security baseline.
3. P2: csak stabil P0/P1 után további legal pipeline kiterjesztések.

## Nem-cél

1. Ebben a dokumentumban nincs runtime deploy.
2. Nincs automatikus termelési változtatás.

