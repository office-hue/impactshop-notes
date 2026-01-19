# 237. Beszélgetés összefoglaló: Git SIGBUS javítás

## Áttekintés
A `git status -sb` minden futáskor SIGBUS/bus errorral kilépett, így nem tudtuk ellenőrizni a workspace állapotát vagy guardot indítani. A cél az volt, hogy diagnosztizáljuk és kijavítsuk a korrupt Git-objektumokat illetve az iCloud által „dataless”-re offloadolt fájlokat.

## Megoldás
- LLDB-vel futtatva a `git status` kimutatta, hogy a `pack-d103cac0….pack` fájl rövidebb a vártnál, ezért a teljes `.git/objects/pack` készletet lementettem és friss clone-ból visszamásoltam.
- Az iCloud által dataless-re jelölt állományokat (`find . -flags +dataless`) a tiszta clone tartalmából `install -p`-vel rehidratáltam, különösen a `.codex/bridge/current-task.json` + `usage.json` fájlokat, amelyek miatt „short read while indexing” hibát kaptunk.
- Ellenőrzés: `/opt/homebrew/bin/git status -sb` most már végigfut (több ezer módosítást listáz), a SIGBUS megszűnt. A lépéseket és az iCloud-optimalizációs kockázatot rögzítettem a `notes.md`-ben.

## Következő lépések
1. Kapcsold ki vagy kerüld el az iCloud „Optimize Mac Storage” opciót a `~/Documents/GitHub` könyvtáron, hogy a repo fájljai ne váljanak újra dataless állapotúvá.
2. Ha ismét SIGBUS jelentkezne, először `find . -flags +dataless` futtatással ellenőrizd, mely követett fájlokat offloadolta a rendszer, és másold vissza őket a friss clone-ból.
