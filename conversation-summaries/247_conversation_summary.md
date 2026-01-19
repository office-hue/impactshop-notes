# 247. Beszélgetés összefoglaló: rendszerfrissítés előkészítése

## Áttekintés
Feladat: a macOS / VS Code / Copilot / WordPress frissítésekhez szükséges előzetes backup + guard lépések lefuttatása, és egy olyan runbook készítése, ami részletezi a bástyavédelem + egykattintásos helyreállítás folyamatát.

## Megoldás
- Lefuttattam a `bin/impact-backup.sh --git-only` scriptet (új bundle + git status snapshot), majd `.codex/.env.local` betöltése után a `bin/backup-sync.sh`-t, így az aktuális állapot bekerült a `~/impactshop-offsite-bundles/` mappába is.
- Végrehajtottam a `.codex/tm/bin/tm-snapshot` parancsot és a dataless guardot (`.codex/scripts/git-dataless-check.sh`), így a Time Machine log és a workspace guard egyaránt zöld.
- Új dokumentum született: `docs/system-update-prep.md` – ez a checklist tartalmazza a pre-update guard futtatást, a backup+offsite lépéseket, a platform-specifikus (macOS, VS Code, Copilot, WordPress) update rutinokat és a bundle-alapú azonnali visszaállítás parancsait.

## Következő lépések
1. A tényleges frissítések előtt kövesd a `docs/system-update-prep.md` checklistet, majd update után futtasd újra az `impactall`-t és jegyezd fel a `notes.md`-ben.
