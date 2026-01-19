# 20. Beszélgetés összefoglaló: VS Code impactall futtatási útvonal

## Áttekintés
A VS Code kiegészítő eddig az aktuális workspace gyökeréből hívta a `~/bin/impactall` scriptet, ezért az impactshop-notes vagy más repo megnyitásakor hiányzó baseline figyelmeztetést kapott. Beállítottam egy dedikált feladatot, amely mindig az `~/Documents/GitHub/impactshop` gyökeréből indítja a guardot, és létrehoztam egy szimbolikus linket ugyanarra a könyvtárra, hogy a hivatkozott útvonal biztosan létezzen.

## Főbb változások
- A `.vscode/tasks.json` kapott egy `Impactall Guard (impactshop root)` feladatot, amely `bash -lc "cd ~/Documents/GitHub/impactshop && ~/bin/impactall"` parancsot futtat dedikált panelben.
- A repo gyökere alatt létrehoztam az `impactshop` szimbolikus linket (az egész monorepóra mutat), így az extension által kért `~/Documents/GitHub/impactshop` útvonal létezik.
- A `notes.md` naplóban rögzítettem a változtatást, hogy a jövőbeni ügyeletesek tudják, miért nem jelenik meg többé a baseline hiány figyelmeztetés.

## Következő lépések
- Nyisd meg a VS Code feladatlistát (`Terminal > Run Task…`) és futtasd az `Impactall Guard (impactshop root)` taskot; a panel most már a megfelelő gyökérből olvassa a baseline dokumentumot.
