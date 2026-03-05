# System Update Prep Playbook

Ez a jegyzet összegyűjti, milyen lépésekkel kell felkészíteni a helyi "bástya" környezetet nagyobb frissítések (macOS, VS Code, GitHub Copilot, WordPress) előtt és után. A cél: minden frissítés előtt legyen naprakész guard snapshot, Git bundle, Time Machine mentés és off-site szinkron, hogy bármikor egy kattintással visszaállhassuk az állapotot.

## 1. Előkészítő guard rutin (minden update előtt)
1. **Lépj be a monorepó gyökerébe** (`cd ~/Documents/GitHub`).
2. **Frissítsd a Codex kontextust**: `./impactctl refresh` – így az `impactall` logjai a legutóbbi fájlállapotot tükrözik.
3. **Guard futtatás**: `source .codex/.env.local && ~/bin/impactall` – csak tiszta állapottal indulunk frissítésnek.
4. **iCloud / dataless ellenőrzés**: `source .codex/.env.local && .codex/scripts/git-dataless-check.sh` – ha piros, a backup script blokkolva marad.

## 2. Egykattintásos helyreállítás: backup lépések
1. **Git bundle + working tree snapshot**  
   ```bash
   cd ~/Documents/GitHub
   bin/impact-backup.sh --git-only
   ```
   Ez elkészíti a `.backups/impactshop-git-YYYYMMDD-HHMMSS.bundle` fájlt + git status/diff snapshotokat, és bejegyzést ír a `.codex/logs/system-recovery-log.md`-be.
2. **Off-site szinkron**  
   ```bash
   source .codex/.env.local
   bin/backup-sync.sh
   ```
   A `BACKUP_SYNC_TARGET=$HOME/impactshop-offsite-bundles/` könyvtárba tükrözi a friss bundle-t, így azonnal elérhető akkor is, ha a fő repo megsérül.
3. **Time Machine gyors-snapshot**  
   ```bash
   ./.codex/tm/bin/tm-snapshot
   ```
   Ez `tmutil snapshot`-ot indít és logolja, melyik Git bundle-hez tartozik a mentés.
4. **Visszaállítás képlet (ha gond van)**  
   ```bash
   mkdir -p /tmp/impactshop-restore && cd /tmp/impactshop-restore
   git clone ~/impactshop-offsite-bundles/impactshop-git-<timestamp>.bundle impactshop-notes-restore
   # Ha volt working tree patch: git apply ~/impactshop-offsite-bundles/working-tree-<timestamp>.patch
   ```
   Innen `rsync`-cel vagy `cp -R`-rel lehet visszamásolni a kívánt fájlokat a fő munkakönyvtárba.

![1765207275734](image/system-update-prep/1765207275734.png)![1765207436554](image/system-update-prep/1765207436554.png)![1765219803509](image/system-update-prep/1765219803509.png)## 3. Platformspecifikus update checklist
### macOS / Xcode CLI
- Ellenőrizd, hogy a fenti backup lépések lefutottak (bundle + TM log).  
- System Settings → General → Software Update: futtasd a macOS/firmware frissítést.  
- Update után terminál: `sudo xcode-select --install` (ha szükséges CLI tool reinstall), `xcodebuild -license accept`, majd `brew update && brew upgrade && brew doctor`.  
- Zárásként: `source .codex/.env.local && ~/bin/impactall` + `notes.md` bejegyzés.

### VS Code
- Nyisd meg a VS Code-ot, futtasd: `Shift+Cmd+P → Check for Updates`.  
- `Extensions` panelen frissíts mindent (pl. PHP Intelephense, WordPress Snippets).  
- `settings sync` aktív-e? ellenőrizd (`gear icon → Settings Sync`).  
- Ha a CLI kell, frissítsd: `brew install --cask visual-studio-code`.

### GitHub Copilot (Chat + Autocomplete)
- Az `Extensions` frissítése után futtasd a Copilot diagnosztikát: `Shift+Cmd+P → GitHub Copilot: Collect Diagnostics` és mentsd az új eredményt a `chatgpt-history/## GitHub Copilot Chat.md` fájlba.  
- Ha vállalati tűzfal változott, kövesd a fájl végén lévő troubleshoot linket.  
- Ha a Copilot chat bejövő frissítéseket rejt, `github.copilot.editor.enableAutoCompletions` beállítást ellenőrizd.

### WordPress (core + plugin stack)
- `ssh sharityh@s59.tarhely.com` → `cd ~/app` és futtasd: `wp core update` + `wp plugin update --all`.  
- MU pluginekhez csak a repo szinkronból deployolj (`scripts/hotfix-sync.sh`).  
- Frissítés után: `~/bin/impactall` + `bin/staging-qa-suite.sh` (staging és, ha releváns, production flaggel).  
- Cache flush: `ssh sharityh@s59.tarhely.com "/usr/local/bin/wp --path=/home/sharityh/app cache flush"`.

## 4. Post-update verifikáció
1. `git status -sb` – nincs váratlan módosítás.  
2. `source .codex/.env.local && ~/bin/impactall` – mind a 13 guard PASS.  
3. `bin/staging-qa-suite.sh` (`DRY_RUN=1` ha csak sanity kell).  
4. `notes.md` + `conversation-summaries/` frissítése (időbélyeg, mit update-eltünk, eredmények).  
5. Ha bármely guard WARN/FAIL, a megfelelő runbook szerint járj el, majd újra `impactall`.

## 5. Gyakori hibák / gyors elhárítás
- **Dataless fájl miatt nem indul a backup**: futtasd `.codex/scripts/git-dataless-check.sh`, majd Finderben manuálisan töltsd vissza a listázott fájlokat vagy kapcsold ki az iCloud Optimize Storage-t.  
- **tmutil hiányzik**: valószínűleg nem macOS shellben vagy; hagyd ki a `tm-snapshot` lépést, de jegyezd fel a `notes.md`-ben.  
- **Copilot nem jelentkezik be**: ellenőrizd a `github.copilot.proxy` beállítást, majd `GitHub: Sign out`/`Sign in`.`
- **WordPress update után REST 500**: `ssh`-n futtasd `wp plugin list --status=inactive` és ellenőrizd, nem maradt-e félbemaradt plugin; rollbackhez használd a legutóbbi git bundle-t + `.codex/scripts/wp-hotfix-rollback.sh` (ld. `docs/prod-guard-checklist.md`).

> **Jegyzet:** bármilyen platformfrissítés előtt ez a checklist kötelező. A `bin/impact-backup.sh` + `backup-sync.sh` + `tm-snapshot` trió biztosítja az "egykattintásos" visszaállítást; ha pánikhelyzet van, elég a legfrissebb bundle-t klónozni, majd a working tree patch-et alkalmazni.
