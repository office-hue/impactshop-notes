#!/usr/bin/env bash
# rollback.sh — impactshop-ngo-guides.php visszaállítás v1.1.3-ra
# Keletkezés: 2026-04-10 (ngo-guides-fix előtt készült backup)
# MD5 referencia: f2bf5afbe5ea61d5124397788708e395

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_FILE="$SCRIPT_DIR/impactshop-ngo-guides.v1.1.3.PROD.php"
REMOTE_USER="sharityh"
REMOTE_HOST="s59.tarhely.com"
REMOTE_PATH="/home/sharityh/app/wp-content/mu-plugins/impactshop-ngo-guides.php"
DRY_RUN="${1:-}"

echo "=== NGO Guides Rollback v1.1.3 — $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
echo ""

# Ellenőrzés
if [[ ! -f "$BACKUP_FILE" ]]; then
    echo "❌ HIBA: Backup fájl nem található: $BACKUP_FILE"
    exit 1
fi

LOCAL_MD5=$(md5 -q "$BACKUP_FILE")
EXPECTED_MD5="f2bf5afbe5ea61d5124397788708e395"

if [[ "$LOCAL_MD5" != "$EXPECTED_MD5" ]]; then
    echo "❌ HIBA: Backup fájl MD5 nem egyezik!"
    echo "   Kapott:  $LOCAL_MD5"
    echo "   Várt:    $EXPECTED_MD5"
    exit 1
fi

echo "✅ Backup fájl integritás OK (MD5: $LOCAL_MD5)"
echo ""

if [[ "$DRY_RUN" == "--dry-run" ]]; then
    echo "DRY-RUN mód — nem küld semmit a szerverre."
    echo "Parancs lenne: scp $BACKUP_FILE $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH"
    exit 0
fi

echo "Visszaállítás: $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH"
scp "$BACKUP_FILE" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH"
echo "✅ SCP kész"

# Ellenőrzés a szerveren
REMOTE_MD5=$(ssh "$REMOTE_USER@$REMOTE_HOST" "md5sum $REMOTE_PATH | awk '{print \$1}'")
if [[ "$REMOTE_MD5" != "$EXPECTED_MD5" ]]; then
    echo "❌ HIBA: Szerveren lévő fájl MD5 nem egyezik: $REMOTE_MD5"
    exit 1
fi
echo "✅ Szerveren lévő fájl MD5 OK ($REMOTE_MD5)"

# HTTP ellenőrzés
echo ""
echo "HTTP ellenőrzés (5 mp várakozás cache miatt)..."
sleep 5

CEGEKNEK=$(curl -s -o /dev/null -w "%{http_code}" -L https://app.sharity.hu/cegeknek/)
BEFEKTETOKNEK=$(curl -s -o /dev/null -w "%{http_code}" -L https://app.sharity.hu/befektetoknek/)

echo "  /cegeknek/           → $CEGEKNEK (kell: 200)"
echo "  /befektetoknek/      → $BEFEKTETOKNEK (kell: 404 — v1.1.3-ban még hibás)"

if [[ "$CEGEKNEK" == "200" ]]; then
    echo ""
    echo "✅ Rollback sikeres — cegeknek OK, befektetoknek visszaállt 404-re (v1.1.3)"
else
    echo ""
    echo "⚠️  WARN: cegeknek nem 200 — manuális ellenőrzés szükséges"
fi
