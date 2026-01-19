#!/usr/bin/env bash
set -euo pipefail
# WHAT: MU plugin szinkronizálás staging/prod-ra + FPM reload + REST route/health smoke.
# WHY: Új szerver/DNS után automatizált ellenőrzés és alap flush.
# HOW: Futtasd: bash scripts/sync-mu-and-health.sh

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MU_DIR="$ROOT/wp-content/mu-plugins"
MU_ITEMS=()

while IFS= read -r path; do
  rel="${path#"${MU_DIR}/"}"
  MU_ITEMS+=("$rel")
done < <(find "$MU_DIR" -maxdepth 1 -mindepth 1 \( -type f -o -type d \) \
  ! -name '*.off' ! -name '*.off.*' ! -name '.DS_Store' -print | sort)

if [[ ${#MU_ITEMS[@]} -eq 0 ]]; then
  echo "No MU plugins found to sync." >&2
  exit 1
fi

PROD_HOST="sharityh@s59.tarhely.com"
PROD_PATH="/home/sharityh/app"
STG_PATH="/home/sharityh/app-staging"
WP="/usr/local/bin/wp"
SSH_OPTS="-o IdentitiesOnly=yes"

copy_mu() {
  local target_host=$1
  local target_path=$2
  ssh $SSH_OPTS "$target_host" "mkdir -p '${target_path}/wp-content/mu-plugins'"
  for f in "${MU_ITEMS[@]}"; do
    local src="${MU_DIR}/${f}"
    local dst="${target_path}/wp-content/mu-plugins/${f}"
    if [[ -d "$src" ]]; then
      rsync -a --checksum --delete -e "ssh $SSH_OPTS" "$src/" "${target_host}:${dst}/"
    else
      rsync -a --checksum -e "ssh $SSH_OPTS" "$src" "${target_host}:${dst}"
    fi
  done
}

reload_fpm() {
  local host=$1
  ssh $SSH_OPTS "$host" "sudo systemctl reload php-fpm || sudo service php-fpm reload || true"
}

rewrite_flush() {
  local host=$1
  local path=$2
  ssh $SSH_OPTS "$host" "$WP --path=$path rewrite flush --hard"
}

rest_check() {
  local url=$1
  curl -I -sS "$url" | head -n1
}

echo "Sync MU plugins to PROD..."
copy_mu "$PROD_HOST" "$PROD_PATH"
echo "Sync MU plugins to STAGING..."
copy_mu "$PROD_HOST" "$STG_PATH"

echo "Reload PHP-FPM on PROD..."
reload_fpm "$PROD_HOST"
echo "Reload PHP-FPM on STAGING..."
reload_fpm "$PROD_HOST"

echo "Rewrite flush PROD..."
rewrite_flush "$PROD_HOST" "$PROD_PATH"
echo "Rewrite flush STAGING..."
rewrite_flush "$PROD_HOST" "$STG_PATH"

echo "REST check PROD..."
rest_check "https://app.sharity.hu/wp-json/"
rest_check "https://app.sharity.hu/wp-json/impact/v1/organic-insights" || true
rest_check "https://app.sharity.hu/ai-agent/ping" || true

echo "REST check STAGING..."
rest_check "https://app.sharity.hu/impactshop-staging/wp-json/" || true

echo "Done."
