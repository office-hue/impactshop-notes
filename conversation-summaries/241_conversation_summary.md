# 241. Beszélgetés összefoglaló: Git dataless guard + backup hardening

## Áttekintés
A cél az volt, hogy megelőzzük a korábbi SIGBUS jellegű Git hibákat: automatikusan jelezzük az iCloud „dataless” fájlokat, és úgy módosítsuk a backup folyamatot, hogy az ne hozza létre új sérült packokat.

- Elkészült a `.codex/scripts/git-dataless-check.sh` guard, amely `git ls-files` alapján keresi a dataless státuszú állományokat, automatikusan `brctl download`-dal megpróbálja visszahozni őket, majd listával hibát jelez, ha maradnak. Az `impactall` futtatásába bekerült a „Git dataless scan” lépés, ami jelenleg FAIL-t jelez a `.venv/...` fájlokra.
- A `bin/impact-backup.sh` mostantól kötelezően lefuttatja ugyanezt az ellenőrzést, `git bundle`-t + `git status`/`git diff` snapshotot készít, és nem végez automatikus commit/tag/push műveleteket. A rollback rész git bundle klónozást javasol, így a backup nem ír bele közvetlenül a `.git` könyvtárba.
- A script szándékosan megáll, ha bármelyik tracked fájl dataless állapotban marad, így az iCloud „Optimize Mac Storage” okozta korrupt állapot már a backup vagy guard futás előtt kiderül.

## Következő lépések
1. Kapcsold ki az „Optimize Mac Storage” opciót vagy manuálisan töltsd vissza a `.venv/...` fájlokat, különben a dataless guard továbbra is blokkolni fogja a backupot.
2. Futtasd le az `impactall`-t; ha a „Git dataless scan” FAIL-t dob, frissítsd a fájlokat, majd próbáld újra.
