# Prod Guard Checklist

Ez a jegyzet összefoglalja az ImpactShop „bástya” védelmének gyors ellenőrzését és a deploy
előtti/utáni kötelező kézi lépéseket. A dokumentum a 2025. november 6-án tapasztalt
incidens tanulságai alapján készült.

## 1. Access guard cronok

1. **CPanel token frissítése:**  
   ```
   mkdir -p ~/.secrets
   chmod 700 ~/.secrets
   cat > ~/.secrets/cpanel.token <<EOF
   CPANEL_HOST=cp40.ezit.hu
   CPANEL_USER=sharityh
   CPANEL_TOKEN=...
   EOF
   chmod 600 ~/.secrets/cpanel.token
   ```
2. **Cronok telepítése:** `bash ~/impact-tools/access-guard.sh ensure-cron`
3. **Egészségvizsgálat:** `bash ~/impact-tools/access-guard.sh doctor`  
   - elvárt sorok: `rest:200 (alt:404)` és `cpanel:TOKEN_OK`

## 2. REST health endpoint

*Fájl:* `wp-content/mu-plugins/impactshop-health-endpoint.php`  
*URL:* `https://app.sharity.hu/wp-json/impact/v1/health`

Használjuk a guard script REST tesztjéhez és a manuális sanity checkhez:

```
curl -s https://app.sharity.hu/wp-json/impact/v1/health | jq .
```

## 3. Hotfix script használata

`bin/prod-hotfix-20251106.sh`:

- interaktív (`CONFIRM`) megerősítés nélkül nem fut tovább,
- `rsync` már nem használ `--delete`-et, helyette `--backup --suffix=.bak.$timestamp`,
- a script végig logol, a backup a szerveren marad.

**Soha ne** futtasd módosítás nélkül: előtte `git status` tiszta munkafát ellenőrizz.

## 4. Preflight → Snapshot → Deploy → Postflight

### Preflight
1. `git status` (nincs lokális módosítás)
2. `bin/staging-qa-suite.sh` (STAGING_ENV betöltve, DRY_RUN=1 → 21/21 PASS)
3. Langfuse dashboard ellenőrzés + screenshot (ld. `docs/langfuse-enablement.md` – mindkét panelen legyen friss esemény <15 perc, alert feed zöld, a képernyőmentést mentsd `image/langfuse/langfuse-YYYYMMDD-HHMM.png` néven és jegyezd fel a `notes.md`-ben)

### Snapshot
1. TM snapshot: `./.codex/tm/bin/tm-snapshot`
2. MU backup prodon:  
   `ssh production 'cd ~/app/wp-content && tar -czf ~/impactshop_backups/mu-plugins_$(date +%Y%m%d-%H%M%S).tgz mu-plugins'`

### Deploy
1. `./scripts/deploy.sh staging` (vagy a megfelelő release folyamat)
2. Prod hotfix script csak tiszta munkafán, `CONFIRM` után futtatható

### Postflight
1. `IMPACT_ENV=production DRY_RUN=1 bin/staging-qa-suite.sh`
2. Guard `doctor` staging + prod (`WP=/home/sharityh/app` / `.../app-staging`)
3. Új TM snapshot (`tm-snapshot`) és log archiválás (`impactshop-guard.log`)

## 5. Gyors ellenőrző lista (deploy előtt)

- [ ] `git status` tiszta  
- [ ] `bin/staging-qa-suite.sh` DRY_RUN zöld  
- [ ] TM snapshot + MU tar  
- [ ] `access-guard doctor` (min. `rest:200`, `cpanel:TOKEN_OK`)  
- [ ] Health endpoint 200-at ad  
- [ ] Langfuse dashboard + alert ellenőrizve, screenshot elmentve (`docs/langfuse-enablement.md`)  
- [ ] `source .codex/.env.local && ~/bin/impactall` → „Git dataless scan” zöld (nincs iCloud által offloadolt tracked fájl)  
- [ ] PIN rollout esetén: staging PIN smoke `delivery.status=sent`  
- [ ] PIN rollout esetén: prod PIN smoke `delivery.status=sent`  
- [ ] Hotfix script csak megerősítés után fut  
- [ ] Deploy után `QA suite` + `doctor`

### 5.1 PIN SMS go/no-go (ha PIN rollout is része)

- [ ] Vonage kulcsok prodon beállítva (`/home/sharityh/.impact-secrets/env.d/sms.env`)  
- [ ] No-go ha PIN endpoint 404/500 vagy `delivery.status=error`

## 6. Git / iCloud dataless védelem + recovery

1. **Time Machine snapshot log:** `./.codex/tm/bin/tm-snapshot` futtatása `tmutil snapshot` hívást indít, majd a `.codex/logs/system-recovery-log.md` fájlba rögzíti az eredményt és a legutóbbi git bundle nevét.
2. **Guard check:** futtasd `source .codex/.env.local && .codex/scripts/git-dataless-check.sh` (impactall is meghívja „Git dataless scan” néven). Ha akár egy tracked fájl is `dataless`, a guard FAIL-t jelez és listázza a problémás fájlokat. Automatizáláshoz használd a `.codex/scripts/git-dataless-monitor.sh` szkriptet (cron/LaunchAgent), amely a `.codex/logs/dataless-monitor.log`-ba írja az eredményt.
   - Ha a `DATALESS_DISCORD_WEBHOOK` változó be van állítva (ld. `.codex/.env.local` + `~/.impact-secrets/env.d/discord.env`), a monitor FAIL esetén Discord értesítést küld.
3. **Automatikus letöltés:** a guard és a `bin/impact-backup.sh` megpróbálja `brctl download`-dal visszahozni az állományokat. Ha ez nem sikerül, kézzel nyisd meg a fájlokat Finderben vagy másold vissza a tiszta klónból.
4. **Backup bundlék:** `bin/impact-backup.sh --git-only` → Git bundle (`.backups/impactshop-git-YYYYMMDD-HHMMSS.bundle`) + `git status`/`git diff` snapshot készül. A script addig nem indul, amíg a dataless guard piros.
5. **Recovery forgatókönyv:** hiba esetén a legutóbbi bundle-ből lehet visszaállni: `git clone <bundle> impactshop-notes-restore`, majd opcionálisan `git apply working-tree-*.patch`. Ez biztosítja, hogy egy iCloud-optimalizáció vagy megszaladt backup soha ne törje össze a `.git` könyvtárat.
6. **Off-site szinkron:** állítsd be a `BACKUP_SYNC_TARGET` env-et (pl. `rsync://nas/backups/impactshop` vagy egy csatolt NAS könyvtár), majd futtasd `bin/backup-sync.sh`-t. Így a `.backups/impactshop-git-*.bundle` fájlok külső tárolóra is kimennek.

> 💡 **Bástya védelem:** a fenti guardok és backup bundlék kötelező részei a „bástya” státusz fenntartásának. Deploy vagy hotfix előtt mindig bizonyosodj meg arról, hogy a dataless guard zöld és készült friss git bundle – így egy esetleges SIGBUS vagy sérült packfile néhány parancs alatt visszaállítható.
