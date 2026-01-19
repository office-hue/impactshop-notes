#!/usr/bin/env bash
set -euo pipefail

# ImpactShop fragment prewarm helper
# Futtasd a repo gyökeréből: ./scripts/impact-fragment-prewarm.sh
# opcionális ENV param: production|staging|both (alap: both)

TARGET=${1:-both}
HOST="sharityh@s59.tarhely.com"

case "$TARGET" in
  production|prod)
    TARGETS=("production:/home/sharityh/app")
    ;;
  staging|stage)
    TARGETS=("staging:/home/sharityh/app-staging")
    ;;
  both|all)
    TARGETS=("production:/home/sharityh/app" "staging:/home/sharityh/app-staging")
    ;;
  *)
    echo "Ismeretlen környezet: $TARGET" >&2
    exit 1
    ;;
 esac

run_wp(){
  local ENV=$1
  local PATH_ROOT=$2
  local LABEL=$3
  local CMD=$4
  echo "[$ENV] $LABEL" >&2
  ssh "$HOST" "cd $PATH_ROOT && wp eval '$CMD' > /dev/null"
}

echo "Impact fragment prewarm start ($(date -u +%Y-%m-%dT%H:%M:%SZ))" >&2

for entry in "${TARGETS[@]}"; do
  ENV=${entry%%:*}
  ROOT=${entry#*:}
  run_wp "$ENV" "$ROOT" "impact_ticker" "wp_set_current_user(1); echo do_shortcode(\"[impact_ticker]\");"
  run_wp "$ENV" "$ROOT" "impact_leaderboard ngo" "wp_set_current_user(1); echo do_shortcode(\"[impact_leaderboard tab=\\\"ngo\\\"]\");"
  run_wp "$ENV" "$ROOT" "impact_leaderboard shop" "wp_set_current_user(1); echo do_shortcode(\"[impact_leaderboard tab=\\\"shop\\\"]\");"
  run_wp "$ENV" "$ROOT" "impact_activity" "wp_set_current_user(1); echo do_shortcode(\"[impact_activity]\");"
  run_wp "$ENV" "$ROOT" "impactshop_netflix" "wp_set_current_user(1); echo do_shortcode(\"[impactshop_netflix max_items=8]\");"
  run_wp "$ENV" "$ROOT" "impact_deals_netflix" "wp_set_current_user(1); echo do_shortcode(\"[impact_deals_netflix limit=12]\");"
  run_wp "$ENV" "$ROOT" "impact_coupons_netflix" "wp_set_current_user(1); echo do_shortcode(\"[impact_coupons_netflix max_items=6]\");"
  run_wp "$ENV" "$ROOT" "impactshop_deals_banners" "wp_set_current_user(1); echo do_shortcode(\"[impactshop_deals_banners limit=12]\");"
  echo "[$ENV] prewarm complete" >&2
  echo >&2
 done

echo "Impact fragment prewarm finished." >&2
