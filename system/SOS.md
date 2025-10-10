# Sharity ImpactShop — SOS / Pánik-manual (egyfájlos)

Ez a fájl tartalmaz minden vészlépést: szerver-önjavítás, WP-gyógyítás, GitHub vész-merge, cron seed, diagnosztika.

## 0) Gyors kivonat
- **SSH védelem:** `~/.ssh/authorized_keys` ↔ `~/impact-tools/authorized_keys.blessed` (self-heal + log: `~/impact-backups/access.log`)
- **Cron (cPanel):**
  - `*/30 * * * * bash $HOME/impact-tools/access-guard.sh ensure >/dev/null 2>&1`
  - `15 7 * * *   bash $HOME/impact-tools/access-guard.sh doctor | logger -t impactaccess`
- **WP kritikus pluginek:** `impact-mini-shortcodes`, `impact-bridge-local`
- **Health:** `/wp-json/impact/v1/health` → OK/BAD
- **GitHub védelem (main):** PR kötelező + Code Owner review kötelező (1) + required check: `protect-critical-files`
- **CODEOWNERS (kritikus scriptek):** `/impact-tools/access-guard.sh`, `/ssh-recovery.sh`, `/impact-restore.sh`, `/impact-rollback.sh`

## 1) Szerver-helyreállítás (ssh után futtasd KÖZVETLENÜL a szerveren)
\`\`\`bash
set -euo pipefail
WP="/home/sharityh/app-staging"
export WP_CLI_PHP_ARGS="-d display_errors=0 -d error_reporting=0"

# access guard
bash $HOME/impact-tools/access-guard.sh ensure || true
bash $HOME/impact-tools/access-guard.sh doctor || true
tail -n 5 $HOME/impact-backups/access.log || true

# WP gyógyítás
wp --allow-root --path="$WP" plugin activate impact-mini-shortcodes impact-bridge-local || true
wp --allow-root --path="$WP" rewrite flush --hard || true

# állapot
SC_OK="$(wp --allow-root --path="$WP" --skip-plugins=complianz-terms-conditions eval 'echo (shortcode_exists("impact_ticker") && shortcode_exists("impact_leaderboard") && shortcode_exists("impact_activity")) ? "1" : "0";' 2>/dev/null || echo 0)"
REST_OK="$(wp --allow-root --path="$WP" --skip-plugins=complianz-terms-conditions eval 'define("WP_USE_THEMES", false); $req=new WP_REST_Request("GET","/impact/v1/health"); $res=rest_do_request($req); echo (!is_wp_error($res) && !$res->is_error()) ? "OK" : "BAD";' 2>/dev/null || echo BAD)"
AUTH_DIFF=$([ -s "$HOME/impact-tools/authorized_keys.blessed" ] && [ -s "$HOME/.ssh/authorized_keys" ] && cmp -s "$HOME/impact-tools/authorized_keys.blessed" "$HOME/.ssh/authorized_keys" && echo SAME || echo DIFF)
echo "VERDICT: SC_OK=$SC_OK REST_OK=$REST_OK AUTH_DIFF=$AUTH_DIFF"

# blessed kulcs vissza, ha eltért
if [ "$AUTH_DIFF" = "DIFF" ]; then
  cp -a "$HOME/impact-tools/authorized_keys.blessed" "$HOME/.ssh/authorized_keys"
  chmod 600 "$HOME/.ssh/authorized_keys"
  echo "$(date +%F\ %T) authorized_keys restored (manual)" >> "$HOME/impact-backups/access.log"
fi

# cache/permák + health ping
wp --allow-root --path="$WP" transient delete --all || true
wp --allow-root --path="$WP" cache flush || true
wp --allow-root --path="$WP" rewrite flush --hard || true
SITEURL="$(wp --allow-root --path="$WP" option get siteurl 2>/dev/null || echo "https://app.sharity.hu/impactshop-staging")"
curl -s -o /dev/null -w "HEALTH %{http_code}\n" "$SITEURL/wp-json/impact/v1/health" || true

echo "DONE: AUTH_DIFF=$AUTH_DIFF  SC_OK=$SC_OK  REST_OK=$REST_OK"
\`\`\`

## 2) GitHub vész-merge (ha nincs reviewer, de azonnal kell)
> Feltétel: `gh auth login` megvan. PR számot cseréld a sajátodra.
\`\`\`bash
PR=2
# CO review ideiglenesen OFF
gh api -X PATCH -H "Accept: application/vnd.github+json" \
  repos/office-hue/impactshop-notes/branches/main/protection/required_pull_request_reviews \
  -F require_code_owner_reviews=false -F required_approving_review_count=1 -F dismiss_stale_reviews=false
# Merge (squash) + branch törlés
gh pr merge $PR --squash --delete-branch
# CO review vissza ON
gh api -X PATCH -H "Accept: application/vnd.github+json" \
  repos/office-hue/impactshop-notes/branches/main/protection/required_pull_request_reviews \
  -F require_code_owner_reviews=true -F required_approving_review_count=1 -F dismiss_stale_reviews=false
\`\`\`

## 3) cPanel cron seed
\`\`\`
*/30 * * * * bash $HOME/impact-tools/access-guard.sh ensure >/dev/null 2>&1
15 7 * * *   bash $HOME/impact-tools/access-guard.sh doctor | logger -t impactaccess
\`\`\`

## 4) Diagnosztika röviden
\`\`\`bash
bash $HOME/impact-tools/access-guard.sh doctor
tail -n 50 $HOME/impact-backups/access.log
curl -s -o /dev/null -w "HEALTH %{http_code}\n" "$(wp --allow-root --path=/home/sharityh/app-staging option get siteurl)/wp-json/impact/v1/health"
gh pr view --json number,title,state,mergeStateStatus,mergeable,url
\`\`\`

## 5) Stabil riport/REST szabályok
- forrás: **data1**; státusz: Approved + Pending
- `ad_channel_id = 26081`
- donation = **0.5 ×** commission (EUR)
- időablak: ticker = aktuális hónap + ma; leaderboard = aktuális hónap; activity = utolsó 14 nap (max 10)

