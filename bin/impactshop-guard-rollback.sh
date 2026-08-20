#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.deploy.production.env"
ENGINE="$ROOT_DIR/scripts/impactshop-exact-release-remote.py"
PRODUCTION_CONFIRMED=0
APPLY_MODE=0
RELEASE_ID=""
EXPECTED_DEPLOYED_SHA=""

usage() {
  echo "Használat: $0 --release-id=ID [--production --apply --expected-deployed-sha=SHA256]" >&2
}

for argument in "$@"; do
  case "$argument" in
    --production)
      [[ $PRODUCTION_CONFIRMED -eq 0 ]] || { echo "❌ Duplikált --production." >&2; exit 2; }
      PRODUCTION_CONFIRMED=1
      ;;
    --apply)
      [[ $APPLY_MODE -eq 0 ]] || { echo "❌ Duplikált --apply." >&2; exit 2; }
      APPLY_MODE=1
      ;;
    --release-id=*)
      [[ -z "$RELEASE_ID" ]] || { echo "❌ Duplikált --release-id." >&2; exit 2; }
      RELEASE_ID="${argument#--release-id=}"
      ;;
    --expected-deployed-sha=*)
      [[ -z "$EXPECTED_DEPLOYED_SHA" ]] || { echo "❌ Duplikált --expected-deployed-sha." >&2; exit 2; }
      EXPECTED_DEPLOYED_SHA="${argument#--expected-deployed-sha=}"
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "❌ Ismeretlen rollback opció: $argument" >&2
      usage
      exit 2
      ;;
  esac
done

if [[ ! "$RELEASE_ID" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{7,96}$ ]]; then
  echo "❌ Biztonságos --release-id kötelező." >&2
  exit 2
fi
if [[ ! -f "$ENV_FILE" || ! -f "$ENGINE" || -L "$ENGINE" ]]; then
  echo "❌ Hiányzó production env vagy rollback engine." >&2
  exit 1
fi

# shellcheck disable=SC1090
source "$ENV_FILE"
if [[ "${DEPLOY_ENVIRONMENT:-}" != "production" || "${REMOTE_WP_PATH:-}" != "/home/sharityh/app" ]]; then
  echo "❌ A rollback kizárólag a kanonikus production rooton engedélyezett." >&2
  exit 1
fi
if [[ ! "${REMOTE_WP_CONTENT:-}" =~ ^/[A-Za-z0-9._/-]+$ ]] || \
   [[ "$REMOTE_WP_CONTENT" == *".."* ]] || \
   [[ "$(dirname "$REMOTE_WP_CONTENT")" != "$REMOTE_WP_PATH" ]]; then
  echo "❌ Nem biztonságos production wp-content útvonal." >&2
  exit 1
fi

if [[ $APPLY_MODE -eq 0 ]]; then
  echo "🔎 Read-only rollback inspect: $RELEASE_ID"
  ssh -o BatchMode=yes "$SSH_HOST" \
    python3 - inspect --root "$REMOTE_WP_PATH" --release-id "$RELEASE_ID" \
    < "$ENGINE"
  exit 0
fi

if [[ $PRODUCTION_CONFIRMED -ne 1 ]]; then
  echo "❌ Mutáló rollbackhoz --production szükséges." >&2
  exit 2
fi
if [[ ! "$EXPECTED_DEPLOYED_SHA" =~ ^[a-f0-9]{64}$ ]]; then
  echo "❌ Mutáló rollbackhoz 64 karakteres --expected-deployed-sha kötelező." >&2
  exit 2
fi

echo "↩️ Exact production rollback: id=$RELEASE_ID expected_sha256=$EXPECTED_DEPLOYED_SHA"
ssh -o BatchMode=yes "$SSH_HOST" \
  python3 - rollback \
  --root "$REMOTE_WP_PATH" \
  --release-id "$RELEASE_ID" \
  --expected-deployed-sha "$EXPECTED_DEPLOYED_SHA" \
  < "$ENGINE"
