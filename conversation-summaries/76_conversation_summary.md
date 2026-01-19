# 76. Beszélgetés összefoglaló: GPT setup prompt + QA bővítés

## Áttekintés
A GPT által adott részletes setup + QA instrukciókat beemeltem az Impi tréning doksiba, és kibővítettem a teszt prompt készletet az 5 lépéses mérlegelés gyakorlására.

## Módosítások
- `Impi Tudásbázis/AI-asszisztens-trening.md`: új 4.1 fejezet (teljes Instructions prompt) + 4.2 „kritikus barát” self-check, ami lépésenként rögzíti, hogyan ellenőrizzük Impi válaszait.
- `Impi Tudásbázis/AI-training-prompts.md`: „Extra teszt promptok” blokk (8 új kérdés) az 5 lépéses logika és CTA-k ellenőrzéséhez.
- `notes.md`: naplóztam a változásokat, hogy a jövőben is az új setup + QA-folyamatra hivatkozzunk.

## Következő lépések
1. Töltsd be az új setup promptot a Custom GPT-be / szolgáltatásba.
2. Futtasd le a friss extra teszt promptokat batch módban, és értékeld őket a „kritikus barát” checklist segítségével.
3. Ha bármelyik lépés hiányzik (flow map igazítás, CTA), módosítsd a setupot vagy a flow súlyokat.
