# 83. Beszélgetés összefoglaló: P0 állapot + P1/P2 backlog

## Áttekintés
Összegyűjtöttem az aktuális P0 eredményeket és rögzítettem, milyen P1/P2 feladatok maradtak hátra, hogy a következő tréningkörök célzottan erre fókuszálhassanak.

## P0 eredmények
- Greeting és transzparencia intentek sablonos választ kapnak (shop nélkül).
- Döntési promptoknál kötelező az 5 lépéses sablon.
- Intent-alapú offer-szűrés működik videó/transzparencia/nem-shop esetén.
- QA batch (T-P0…T-P2 + S10–S15) lefutott; jelenleg 1–4/5 pont között mozog, így további fejlesztés szükséges.

## P1 backlog
1. Kategória → NGO mapping + CTA, fallback hierarchia (kupon→kampány→videó→Fillout).
2. REST/technikai promptokhoz konkrét endpoint + minta/doksi.
3. Session state alapok (előző ajánlat visszaidézése, preferencia tárolása).
4. Shop nélküli flow-k (videó/transzparencia/feedback) narratívájának finomhangolása.

## P2 backlog
1. Multi-turn memória + fault katalógus átíró promptok.
2. Empátia/low-effort sablonok, confidence disclaimer.
3. Multi-turn storyk, perszóna-alapú tréning, hard safety válaszok.
4. Kritikus-barát 2.0 automata self-check levezetése minden QA batch után.
