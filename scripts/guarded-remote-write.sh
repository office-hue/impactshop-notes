#!/usr/bin/env bash
set -euo pipefail

# Guarded remote write — impact-community.php deploy guard
# Ellenőrzi: szimbólumok, anti-shrink, backup, rollback

FILE_TO_WRITE=""
REMOTE_HOST="${REMOTE_HOST:-s59.tarhely.com}"
REMOTE_USER="${REMOTE_USER:-sharityh}"
REMOTE_APP="${REMOTE_APP:-/home/sharityh/app}"
ALLOW_SHRINK="${ALLOW_SHRINK:-0}"
CANONICAL_IMPACT_COMMUNITY_SOURCE="${CANONICAL_IMPACT_COMMUNITY_SOURCE:-/Users/bujdosoarnold/Developer/GitHub/wp-content/mu-plugins/impact-community.php}"
ALLOW_NONCANONICAL_COMMUNITY_SOURCE="${ALLOW_NONCANONICAL_COMMUNITY_SOURCE:-0}"
VALIDATE_ONLY="${VALIDATE_ONLY:-0}"
REMOTE_LINES_OVERRIDE="${REMOTE_LINES_OVERRIDE:-}"

usage() {
    cat <<'EOF'
Usage: guarded-remote-write.sh <file_path> [--allow-shrink] [--validate-only]
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --allow-shrink)
            ALLOW_SHRINK=1
            shift
            ;;
        --validate-only)
            VALIDATE_ONLY=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            if [[ -z "$FILE_TO_WRITE" ]]; then
                FILE_TO_WRITE="$1"
                shift
            else
                echo "ERROR: Unknown argument: $1" >&2
                usage >&2
                exit 1
            fi
            ;;
    esac
done

if [ -z "$FILE_TO_WRITE" ]; then
    usage >&2
    exit 1
fi

if [ ! -f "$FILE_TO_WRITE" ]; then
    echo "ERROR: File not found: $FILE_TO_WRITE"
    exit 1
fi

echo "[guard] Validating: $FILE_TO_WRITE"

realpath_portable() {
    local path="$1"
    if [[ ! -e "$path" ]]; then
        return 1
    fi
    local dir base
    dir="$(cd "$(dirname "$path")" && pwd -P)"
    base="$(basename "$path")"
    printf '%s/%s' "$dir" "$base"
}

sha256_file() {
    local path="$1"
    shasum -a 256 "$path" | awk '{print $1}'
}

# ========== SYMBOL CHECKS — impact-community.php ==========
if [[ "$FILE_TO_WRITE" == *"impact-community.php" ]]; then
    echo "[guard] Impact-Community required symbols check..."

    if [[ ! -f "$CANONICAL_IMPACT_COMMUNITY_SOURCE" ]]; then
        echo "FAIL: Canonical impact-community source not found: $CANONICAL_IMPACT_COMMUNITY_SOURCE"
        exit 1
    fi

    LOCAL_HASH="$(sha256_file "$FILE_TO_WRITE")"
    CANONICAL_HASH="$(sha256_file "$CANONICAL_IMPACT_COMMUNITY_SOURCE")"
    LOCAL_REALPATH="$(realpath_portable "$FILE_TO_WRITE")"
    CANONICAL_REALPATH="$(realpath_portable "$CANONICAL_IMPACT_COMMUNITY_SOURCE")"

    if [[ "$LOCAL_HASH" != "$CANONICAL_HASH" ]]; then
        echo "FAIL: Non-canonical impact-community deploy source detected."
        echo "  local path:      $LOCAL_REALPATH"
        echo "  canonical path:  $CANONICAL_REALPATH"
        echo "  local sha256:    $LOCAL_HASH"
        echo "  canonical sha256:$CANONICAL_HASH"
        if [[ "$ALLOW_NONCANONICAL_COMMUNITY_SOURCE" != "1" ]]; then
            echo "Set ALLOW_NONCANONICAL_COMMUNITY_SOURCE=1 only for explicit incident-approved deploys."
            exit 1
        fi
        echo "[guard] WARNING: bypassing canonical source guard (ALLOW_NONCANONICAL_COMMUNITY_SOURCE=1)."
    else
        echo "[guard] Canonical impact-community source verified by sha256 ✓"
        if [[ "$LOCAL_REALPATH" != "$CANONICAL_REALPATH" ]]; then
            echo "[guard] NOTE: alternate worktree path accepted because file hash matches canonical source."
        fi
    fi
    
    REQUIRED_SYMBOLS=(
        "function ic_maybe_migrate_db"
        "function ic_seed_ngo_circles"
        "CREATE TABLE.*ic_circles"
        "ic_rest_circles_list"
        "ic_rest_circle_detail"
        "attached_ngo_ids"
    )
    
    for symbol in "${REQUIRED_SYMBOLS[@]}"; do
        if ! grep -q "$symbol" "$FILE_TO_WRITE"; then
            echo "FAIL: Required symbol missing: $symbol"
            echo "Impact-Community database initialization will fail!"
            exit 1
        fi
    done
    
    echo "[guard] All required symbols present ✓"
fi

# ========== ANTI-SHRINK CHECK ==========
echo "[guard] Anti-shrink validation..."
LOCAL_LINES=$(wc -l < "$FILE_TO_WRITE")
BASENAME=$(basename "$FILE_TO_WRITE")
if [[ -n "$REMOTE_LINES_OVERRIDE" ]]; then
    REMOTE_LINES="$REMOTE_LINES_OVERRIDE"
else
    REMOTE_LINES=$(ssh "${REMOTE_USER}@${REMOTE_HOST}" "wc -l < ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME}" 2>/dev/null || echo "0")
fi

if [ "$REMOTE_LINES" -gt 0 ]; then
    MIN_LINES=$((REMOTE_LINES * 90 / 100))
    if [ "$LOCAL_LINES" -lt "$MIN_LINES" ]; then
        if [[ "$ALLOW_SHRINK" != "1" ]]; then
            echo "FAIL: Local file shrink detected!"
            echo "  Local lines:  $LOCAL_LINES"
            echo "  Remote lines: $REMOTE_LINES (90% threshold: $MIN_LINES)"
            echo "  Use --allow-shrink with explicit review."
            exit 1
        fi
        echo "[guard] WARNING: anti-shrink bypass enabled (--allow-shrink)."
        echo "[guard]          local=$LOCAL_LINES remote=$REMOTE_LINES threshold=$MIN_LINES"
    else
        echo "[guard] Anti-shrink OK: local=$LOCAL_LINES, remote=$REMOTE_LINES ✓"
    fi
fi

# ========== SYNTAX CHECK ==========
echo "[guard] Local syntax validation..."
if [[ "$FILE_TO_WRITE" == *.php ]]; then
    php -l "$FILE_TO_WRITE" > /dev/null || { echo "FAIL: PHP syntax error"; exit 1; }
    echo "[guard] PHP syntax OK ✓"
fi

if [[ "$VALIDATE_ONLY" == "1" ]]; then
    echo "[guard] Validation-only mode: remote backup/deploy skipped."
    exit 0
fi

# ========== BACKUP ==========
echo "[guard] Creating backup..."
BACKUP_TS=$(date +%Y%m%d-%H%M%S)
ssh "${REMOTE_USER}@${REMOTE_HOST}" "cp ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME} ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME}.bak-${BACKUP_TS} && chmod 644 ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME}"
echo "[guard] Backup created: *.bak-$BACKUP_TS ✓"

# ========== DEPLOY ==========
echo "[guard] Deploying..."
scp "$FILE_TO_WRITE" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_APP}/wp-content/mu-plugins/${BASENAME}"

echo "[guard] Remote syntax validation..."
ssh "${REMOTE_USER}@${REMOTE_HOST}" "php -l ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME} && chmod 444 ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME}"

echo "[guard] Deploy completed successfully ✓"
ROLLBACK_CMD="ssh ${REMOTE_USER}@${REMOTE_HOST} 'chmod 644 ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME}.bak-${BACKUP_TS} && cp ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME}.bak-${BACKUP_TS} ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME} && chmod 444 ${REMOTE_APP}/wp-content/mu-plugins/${BASENAME}'"
echo "[guard] To rollback: $ROLLBACK_CMD"
