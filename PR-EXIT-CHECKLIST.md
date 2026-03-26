# PR Exit Checklist

1. A munka dedikált branch/worktree alatt készült, nem `main`-en.
2. A releváns build/teszt parancsok lefutottak és zöldek.
3. `safe-repo-audit.sh --strict --mode push` lokálisan zöld.
4. Modulmódosításnál frissült a `system-status-snapshot.md`.
5. Modulmódosításnál frissült legalább egy `docs/*.md`.
6. Notes kontextusnál frissült a `notes.md` vagy `conversation-summaries/*`.
7. Új modulfájl esetén frissült a `docs/bastion-guard-status.md` evidencia.
8. Deploy előtt/után kötelező guard és smoke ellenőrzések dokumentálva vannak.
9. Rollback útvonal (backup + visszaállítási lépés) rögzítve van a változáshoz.
10. PR leírás tartalmazza: scope, kockázat, ellenőrzés, deploy/rollback jegyzet.
11. Impact Challenge érintettségnél rögzítve van, hogy a megoldás additív új kód vagy explicit jóváhagyott legacy módosítás.
12. Impact Challenge legacy módosításnál az explicit engedély és az additív alternatíva hiányának indoka dokumentálva van.
13. Production deploy esetén rögzítve van, hogy a védett fájlok írásvédettsége a deploy ablak után visszaáll.
14. Protected-file touch esetén készült koherencia vizsgálat és kockázatelemzés.
15. Protected-file touch esetén készült érintett funkciólista és post-deploy / post-merge ellenőrzési kör.
16. Protected-file touch esetén a felhasználónak átadott manuális UI checklist elkészült.
