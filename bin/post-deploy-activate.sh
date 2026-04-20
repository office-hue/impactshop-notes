#!/usr/bin/env bash
set -euo pipefail

# Állítsd be:
STAGING_WP_PATH="/home/sharityh/app-staging"
PROD_WP_PATH="/home/sharityh/app"

PLUGINS=(
  "impact-bridge-local"
  "impact-mini-shortcodes"
)

activate_set () {
  local WP_PATH="$1"
  for slug in "${PLUGINS[@]}"; do
    wp plugin activate "$slug" --path="$WP_PATH" || true
  done
  wp plugin list --path="$WP_PATH"
}

case "${1:-}" in
  staging)  activate_set "$STAGING_WP_PATH" ;;
  prod|production) activate_set "$PROD_WP_PATH" ;;
  *) echo "Usage: $0 {staging|production}" ; exit 2 ;;
esac
