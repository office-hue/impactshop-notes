# Offerwall Survey – Rovid modszer (hasznalhato brief)

## Cel
Edukacios, adaptiv kerdoiv 5 kerdeses blokkokban. A kerdesek valaszok alapjan agaznak, nehezseg es tema valtozik. A kerdesbank folyamatosan bovitheto.

## Forrasok
- Docx kerdeslista (alap, attitud/viselkedes/tudas)
- Batch5 CSV (minosegi, delayed pair, consistency probe)

## Kerdesbank szerkezet
Minden kerdes metaadata:
- `cognitive_type`: knowledge / attitude / behavior
- `segment`: SUST / ENV / SOC / DON
- `difficulty`: 1–4
- `question_category`: KN_GENERAL / ATT_GENERAL / BEH_GENERAL
- opcionális: `delayed_pair_id`, `consistency_probe`

## Flow (5 kerdes/blokk)
1) Intro (behavior/attitude, konnyu)
2) Knowledge
3) Knowledge
4) Apply (behavior)
5) Reflect (attitude/behavior)

## Elagazas
- Helyes valasz → nehezseg fel, rossz → le
- Dominans `segment` elonyt kap a kovetkezo kerdesekben
- `delayed_pair` kerdes parja 2 lepes mulva preferaltan jon

## Pontozas
- 5 kerdes utan a user “Pontok jovairasa” gombra kattint
- Ekkor submit, reward sikeres postback utan iródik jóvá

## Minoseg
- “Nem tudom / kihagyom” opcio tudas kerdeseknel
- Opcio sorrend randomizalas (ahol lehet)
- Valaszido/mintazat figyeles (flag, nem buntet)

