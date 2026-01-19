# 51. Beszélgetés összefoglaló: Impi lokális fallback válasz

## Áttekintés
A GPT-mini prompt frissítése után is előfordult, hogy Impi csak egy száraz mondatot adott vissza. Most írtam egy saját "konverzációs" fallback generátort, ami akkor fut, ha OpenAI nem elérhető vagy nincs API kulcs, így legalább alapszintű beszélgetés mindig működik.

## Fő lépések
- `apps/api-gateway/src/services/impi-openai.ts`: új `buildLocalSummary()` helper (köszönés, felhasználó szándék visszajelzés, NGO említés, max. 3 ajánlat bulletben, CTA-k, záró biztatás), és hívás OpenAI hiba esetén.
- `npm run build` → rsync `cp40` szerverre → `~/ai-agent-service.js` restart.
- `.codex/guards/ai-agent-guard.sh` lefutott (OK).

## Következő lépések
- Monitorozd, hogy a valós user flow mennyire elégedett ezzel a fallbackkel; ha OpenAI kulcsot bekötünk, a GPT továbbra is elsődleges.
