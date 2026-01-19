# 86. Beszélgetés összefoglaló: Impi backlog státusz + végrehajtási terv

## Áttekintés
Átvizsgáltam a legfrissebb Impi vonatkozású jegyzeteket (`notes.md` 2025-12-02 07:45-ös blokk) és a részletes backlog dokumentumot (`docs/ai-agent-backlog.md`), majd összegyűjtöttem, mely fejlesztések nem készültek még el. A cél egy végrehajtható terv kidolgozása volt a hiányzó funkciókra.

## Fennmaradó fő feladatok
1. **Story guard pipeline** – S9 flow eventek loggolása + automatikus guard riport nincs még implementálva; impactall/doc-missing-refs lefuttatása is hiányzik.
2. **Multi-turn memória bővítése** – a session store csak utolsó ajánlatot tartja, nincs preferencia + REST állapot + fault replay.
3. **P1 REST prompt + deeplink CTA** – suppressed intent sablonokban nincs konkrét endpoint/slug/CTA; transzparencia és shopping fallback továbbra is általános.
4. **Playwright + ingest roadmap** – a T-2.8/T-2.10 feladatok (Árukereső Playwright scraper, Gmail Promotions ingest, reliability scoring + health flag) teljesen hátravannak.

## Javasolt végrehajtási terv
- **Sprint S1 cleanup + story guard**: doc-missing-refs + impactall → `story_guard_reporter` modul → `.codex/logs/story-guard.log` + guard actions dokumentálása.
- **Session/memória fejlesztés**: `apps/api-gateway/src/index.ts` bővítése preferencia/REST/fault mezőkkel, "folytassuk" intent kezelése, majd S9 QA batch újrafuttatása.
- **REST prompt/CTA deepening**: `Impi Tudásbázis/NGO-category-map.md` + `docs/impactshop-ngo-card-usage.md` alapján slugolt CTA-k összegyűjtése; suppressed intent sablonok aktualizálása, transparency QA futtatása.
- **Playwright/Gmail/reliability**: sorban implementálni a T-2.8–T-2.10 lépéseket (runner, ingest, scoring), mindegyik után `npm run build` + cp40 deploy + `impactall`/`aiagentall` guard, `/healthz` `features` mező frissítéssel.

## Következő lépések
1. Sprint dokumentáció frissítése (S1/S2 task listák) és guard figyelmeztetések lezárása.
2. Story guard pipeline implementálása → QA log megosztása.
3. Multiturn memória + REST CTA fejlesztések implementálása, majd QA batch futtatása.
4. Playwright/Gmail/reliability modulok soronkénti megvalósítása + deploy + guard ellenőrzés.
