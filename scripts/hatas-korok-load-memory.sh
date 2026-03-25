#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

resolve_ai_agent_repo() {
  local search="$REPO_ROOT"
  local parent=""
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    if [[ "$(basename "$search")" == "ai-agent" && -f "$search/scripts/dev-memory.ts" ]]; then
      echo "$search"
      return 0
    fi
    if [[ -d "$search/ai-agent" && -f "$search/ai-agent/scripts/dev-memory.ts" ]]; then
      echo "$search/ai-agent"
      return 0
    fi
    for wt in "$search"/.worktrees/ai-agent*; do
      if [[ -d "$wt" && -f "$wt/scripts/dev-memory.ts" ]]; then
        echo "$wt"
        return 0
      fi
    done
    parent="$(cd "$search/.." && pwd)"
    [[ "$parent" == "$search" ]] && break
    search="$parent"
  done
  return 1
}

usage() {
  cat <<'EOF'
Usage:
  ./scripts/hatas-korok-load-memory.sh [--task "<task>"] [--full-sync] [--limit N] [--file-limit N]

Default:
  - generál egy kurált Hatás Körök memo fájlt
  - futtat egy memory:pre-task lekérést
  - generál egy dedikált context packot a repohoz

Opciók:
  --task        Egyedi task szöveg a memory kereséshez
  --full-sync   A végén futtat egy teljes memory:full-sync-et is
  --limit       Memory találati limit (default: 10)
  --file-limit  File chunk limit (default: 10)
  --help        Súgó
EOF
}

TASK="hatas-korok route, impact-community, impactshop-ngo-guides, deploy smoke, post-deploy checklist, production hotfix"
RUN_FULL_SYNC=0
LIMIT=10
FILE_LIMIT=10

while [[ $# -gt 0 ]]; do
  case "$1" in
    --task)
      TASK="${2:-}"
      shift 2
      ;;
    --full-sync)
      RUN_FULL_SYNC=1
      shift
      ;;
    --limit)
      LIMIT="${2:-10}"
      shift 2
      ;;
    --file-limit)
      FILE_LIMIT="${2:-10}"
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "ERROR: ismeretlen opció: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if ! [[ "$LIMIT" =~ ^[0-9]+$ ]] || ! [[ "$FILE_LIMIT" =~ ^[0-9]+$ ]]; then
  echo "ERROR: a --limit és --file-limit egész szám legyen." >&2
  exit 1
fi

AI_AGENT_REPO="$(resolve_ai_agent_repo 2>/dev/null || true)"
if [[ -z "$AI_AGENT_REPO" ]]; then
  echo "ERROR: ai-agent repo nem található a workspace-ben." >&2
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "ERROR: npm nem található." >&2
  exit 1
fi

BRANCH="$(git -C "$REPO_ROOT" branch --show-current 2>/dev/null || echo detached)"
CONTEXT_DIR="$REPO_ROOT/.codex/context"
MEMO_PATH="$CONTEXT_DIR/hatas-korok-work-memo.md"
CONTEXT_PACK_PATH="$CONTEXT_DIR/hatas-korok-work-context.md"

mkdir -p "$CONTEXT_DIR"

memo_files=(
  "wp-content/mu-plugins/impact-community.php"
  "wp-content/mu-plugins/impact-community-app.php"
  "wp-content/mu-plugins/impactshop-ngo-guides.php"
  "wp-content/mu-plugins/impactshop-ngo-guides/hatas-korok.html"
  "scripts/hatas-korok-post-deploy-smoke.sh"
  "scripts/hatas-korok-load-memory.sh"
  "bin/deploy-wpcontent-map.sh"
  "bin/post-deploy-checklist.sh"
  "docs/hatas-korok-post-deploy-checklist.md"
  "docs/impactshop-deploy.md"
  "notes.md"
  "system-status-snapshot.md"
)

{
  echo "# Hatás Körök work memo"
  echo
  echo "Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
  echo "Repo: $REPO_ROOT"
  echo "Branch: $BRANCH"
  echo "Task: $TASK"
  echo
  echo "## Scope"
  echo
  echo "- Route: \`/hatas-korok\`"
  echo "- App shell + community REST API"
  echo "- Deploy utáni read-only smoke és post-deploy checklist"
  echo "- Production hotfix continuity: notes + snapshot"
  echo
  echo "## Kulcs fájlok"
  echo
  for rel in "${memo_files[@]}"; do
    if [[ -f "$REPO_ROOT/$rel" ]]; then
      echo "- \`$rel\`"
    fi
  done
  echo
  echo "## Gyors parancsok"
  echo
  echo "- Smoke: \`./scripts/hatas-korok-post-deploy-smoke.sh https://app.sharity.hu\`"
  echo "- Checklist: \`bash ./bin/post-deploy-checklist.sh\`"
  echo "- Deploy mapping: \`bash ./bin/deploy-wpcontent-map.sh --production\`"
  echo
  echo "## Aktuális continuity bejegyzések"
  echo
  sed -n '1,12p' "$REPO_ROOT/system-status-snapshot.md"
  echo
  sed -n '1,12p' "$REPO_ROOT/notes.md"
} > "$MEMO_PATH"

echo "[hatas-korok-load-memory] memo -> $MEMO_PATH"

npm --prefix "$AI_AGENT_REPO" run -s memory:pre-task -- \
  --task "$TASK" \
  --out "tmp/state/dev-memory/last-brief.json" \
  --limit "$LIMIT" \
  --file-limit "$FILE_LIMIT"

npm --prefix "$AI_AGENT_REPO" run -s memory:context-pack -- \
  --repo "$REPO_ROOT" \
  --branch "$BRANCH" \
  --task "$TASK" \
  --out "$CONTEXT_PACK_PATH" \
  --limit "$LIMIT" \
  --file-limit "$FILE_LIMIT"

if [[ "$RUN_FULL_SYNC" == "1" ]]; then
  npm --prefix "$AI_AGENT_REPO" run -s memory:full-sync -- --task "$TASK"
fi

echo "[hatas-korok-load-memory] context-pack -> $CONTEXT_PACK_PATH"
echo "[hatas-korok-load-memory] ok"
