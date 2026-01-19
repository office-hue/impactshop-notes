# 71. Beszélgetés összefoglaló: AI training prompts dokumentálása

## Áttekintés
A feladat a Sonnet által javasolt, szintekre bontott gyakorló prompt készlet rögzítése volt az Impi Tudásbázisban, valamint annak biztosítása, hogy a tudásindex ismerje az új forrást.

## Fő lépések
- Elkészítettem az `ai-agent/Impi Tudásbázis/AI-training-prompts.md` fájlt: 7 szintnyi (alap → edge case) prompt, QA checklist, batch futtatási script.
- A `knowledge-aliases.json` `knowledge_files` listája bővült az új Markdownnal, és létrejött a `tudasbazis-impi-training-prompts` topic alias ("training prompts", "gyakorló prompt", stb.).
- A változásokat rögzítettem a `notes.md`-ben; következő build/deploy során az `npm run build` + rsync húzza be a prompt csomagot az AI szolgáltatásba.

## Következő lépések
1. Futtasd le az új dokumentumban szereplő batch scriptet, és hasonlítsd össze a válaszokat a QA checklisttel.
2. Szükség esetén finomhangold a setup promptot és a flow súlyokat (pl. transzparencia, több szándék kezelése).
