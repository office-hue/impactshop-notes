# Sharity ImpactShop – v3 Hardening (2025-10-09)

## Summary
Staging teljes helyreállítás + defense-in-depth („betonvédelem”).

### SSH / Access
- `ssh-recovery.sh` visszaállítja az authorized_keys-t blessed backupból  
- `access-guard.sh` egységes log: `/home/sharityh/impact-backups/access.log`  
- Kulcs-szinkron: `AUTH_DIFF:SAME`

### WordPress / API
- Aktiválva: `impact-mini-shortcodes`, `impact-bridge-local`  
- Belső REST `/impact/v1/health` → `OK`  
- Rövidkódok: `ims_ticker`, `impact_ticker`, `impact_leaderboard`, `impact_activity`  
- Állapot: `ACTIVE_SANE:YES`

### Automation / Cron (cPanel)
- `*/30 * * * *  bash $HOME/impact-tools/access-guard.sh ensure >/dev/null 2>&1`
- `15 7 * * *    bash $HOME/impact-tools/access-guard.sh doctor | logger -t impactaccess`

### System
- `.bashrc`/`.zshrc` minimal safety; aliasok: `~/.config/impact/aliases.sh`  
- Backups: `/home/sharityh/impact-backups/`, Tools: `/home/sharityh/impact-tools/`, WP: `/home/sharityh/app-staging`

### Protection
- Access + Recovery scriptek immutábilisak (CodeOwner review required)
- Future módosítás: csak PR → @office-hue jóváhagyással
