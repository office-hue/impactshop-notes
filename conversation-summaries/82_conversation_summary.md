# 82. Beszélgetés összefoglaló: P0 flow fix + QA rerun

## Áttekintés
Bevezettem az első körös (P0) flow-technikai javításokat: greeting/transzparencia intent sablon, döntési sablon kényszerítése, konverziós kulcsszavak bővítése, majd lefuttattam a T-P0…T-P2 + S10–S15 QA batch-et.

## Változtatások
- `index.ts`: greeting esetén új welcome menü jelenik meg; transzparencia intent 0 shop ajánlattal Impact/Fillout CTA-t ad.
- `conversation-map.ts`: bővített kulcsszavak (videó, átláthatóság, „nem vásárolok”, kategóriák).
- `impi-openai.ts`: döntési kulcsszavakra kötelező számozott 5 lépéses sablon.
- Build → rsync → `npm install --omit=dev` → service restart (PID 889028).

## QA eredmények (15 prompt)
1. `T-P0-1` (videó): 2/5 – shop nincs, de videós CTA helyett transzparencia sablon érkezett.
2. `T-P0-2` (welcome): 5/5 – sablon rendben.
3. `T-P0-3` (átláthatóság): 4/5 – visszakérdezés hiányzik.
4. `T-P1-4` (kategória): 2/5 – nincs NGO lista, csak videós fallback.
5. `T-P1-5` (döntési mechanizmus): 3/5 – számozott lépések vannak, CTA hiányos.
6. `T-P1-6` (hiányzó kupon): 2/5 – nincs alternatíva.
7. `T-P2-7` (multi-turn): 1/5 – nincs session memória.
8. `T-P2-8` (bizonytalan user): 1/5 – nincs empátia + 3 opció.
9. `T-P2-9` (rossz nap): 2/5 – empátia van, konkrét low-effort CTA nincs.
10. `S10` (REST API): 3/5 – endpoint ok, példa/dok link hiányos.
11. `S11` (teljes vásárlás = adomány): 1/5 – shop ajánlat magyarázat nélkül.
12. `S12` (kombinált szándék): 1/5 – nem kezeli duál flow-t.
13. `S13` (elutasítás): 1/5 – nincs visszajelzéskérés.
14. `S14` (leaderboard): 1/5 – nincs toplista + motiváció.
15. `S15` (metaprompt): 1/5 – hiányzik belső gondolkodás.

## Következő lépések
1. Implementálni a kategória→NGO mappinget és fallback hierarchiát (kupon/kampány/videó/Fillout).
2. Session memóriát és multi-turn kontextust hozzáadni („az előző ajánlat” típusú kérdésekhez).
3. Flow-specifikus narratívát kényszeríteni videó/transzparencia/feedback esetén + REST link/doksi válaszokat pótlólag.
4. A QA batch-et ismét lefuttatni és ≥4/5 pontot célozni minden promptnál.
