#!/usr/bin/env bash
set -euo pipefail

NOW=$(date -u +%Y%m%dT%H%M%SZ)
RUN_DIR=".codex/reports/execution/run_${NOW}"
LOG_DIR="${RUN_DIR}/logs"
SUMMARY="${RUN_DIR}/execution_run_summary.md"
mkdir -p "$LOG_DIR"

{
  echo "# Execution-RUN ${NOW}"
  echo "Flags:"
  echo "  RUN_GIT_ROOT=${RUN_GIT_ROOT:-0}"
  echo "  RUN_VALIDATION=${RUN_VALIDATION:-0}"
  echo "  RUN_CI_MIGRATION=${RUN_CI_MIGRATION:-0}"
  echo "  RUN_DASHBOARD_CRON=${RUN_DASHBOARD_CRON:-0}"
} > "$SUMMARY"

PACK2_LOG="${LOG_DIR}/validation_gate_pilot.log"
PACK3_LOG="${LOG_DIR}/ci_migration_run.log"

if [[ "${RUN_VALIDATION:-0}" = "1" ]]; then
  {
    echo "=== Pack #2 - Validation Gate (REAL RUN) ==="
    echo "Host: ${SSH_HOST:-<unset>}"
    echo "User: ${SSH_USER:-<unset>}"
    echo "WP_PATH: ${WP_PATH:-<unset>}"
    echo "Command:"
    echo "  bash scripts/validate-deploy-profile.sh --profile=staging --timeout-ssh=10 --timeout-wpcli=30"
    bash scripts/validate-deploy-profile.sh --profile=staging --timeout-ssh=10 --timeout-wpcli=30 || true
  } &> "$PACK2_LOG"
else
  {
    echo "=== Pack #2 - Validation Gate (DRY-RUN) ==="
    echo "Would run:"
    echo "  bash scripts/validate-deploy-profile.sh --profile=staging --timeout-ssh=10 --timeout-wpcli=30"
    echo "No remote calls made."
    echo "Exit: DRY-RUN"
  } > "$PACK2_LOG"
fi

if [[ "${RUN_VALIDATION:-0}" = "1" ]]; then
  if grep -qE "Exit:\\s*0|✅" "$PACK2_LOG"; then
    echo "- Pack #2: PASS" >> "$SUMMARY"
    mkdir -p .codex/reports/execution
    date -u +%Y-%m-%dT%H:%M:%SZ > .codex/reports/execution/validation_signedoff.flag
  elif grep -qiE "fail|❌|exit:\\s*1|exit:\\s*2" "$PACK2_LOG"; then
    echo "- Pack #2: HOLD (see $PACK2_LOG)" >> "$SUMMARY"
  else
    echo "- Pack #2: REVIEW (see $PACK2_LOG)" >> "$SUMMARY"
  fi
else
  echo "- Pack #2: DRY-RUN (see $PACK2_LOG)" >> "$SUMMARY"
fi

echo "- Pack #2 logs: $PACK2_LOG" >> "$SUMMARY"

if [[ "${RUN_CI_MIGRATION:-0}" = "1" ]]; then
  {
    echo "=== Pack #3 - CI Migration ==="
    if [[ -f .github/workflows/ci.yml ]]; then
      echo "[OK] Workflow present: .github/workflows/ci.yml"
    else
      echo "[MISS] Workflow missing: .github/workflows/ci.yml"
    fi

    CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo main)"
    echo "[INFO] Current branch: ${CURRENT_BRANCH}"

    if command -v gh >/dev/null 2>&1; then
      echo "[INFO] gh version: $(gh --version | head -1)"
      echo "[INFO] gh auth status:"
      gh auth status || true

      echo "[INFO] Triggering workflow dispatch via gh workflow run"
      gh workflow run ci.yml --ref="${CI_REF:-${CURRENT_BRANCH}}" || echo "[WARN] gh workflow run failed or workflow disabled"

      echo "[INFO] Listing latest workflow runs"
      gh run list --limit 5 --json databaseId,status,conclusion,workflowName,displayTitle,headBranch,createdAt 2>/dev/null || echo "[WARN] gh run list unavailable"
    else
      echo "[WARN] gh CLI not available; relying on push-triggered workflow."
    fi
  } &> "$PACK3_LOG"
else
  {
    echo "=== Pack #3 - CI Migration (DRY-RUN) ==="
    echo "Would ensure .github/workflows/ci.yml present and trigger workflow dispatch."
    echo "No workflow run triggered."
  } > "$PACK3_LOG"
fi

if [[ "${RUN_CI_MIGRATION:-0}" = "1" ]]; then
  if grep -qiE "success|pass" "$PACK3_LOG"; then
    echo "- Pack #3: PASS" >> "$SUMMARY"
    mkdir -p .codex/reports/execution
    date -u +%Y-%m-%dT%H:%M:%SZ > .codex/reports/execution/ci_migration_completed.flag
  elif grep -qiE "queued|triggered" "$PACK3_LOG"; then
    echo "- Pack #3: TRIGGERED (waiting) – see $PACK3_LOG" >> "$SUMMARY"
  else
    echo "- Pack #3: HOLD (see $PACK3_LOG)" >> "$SUMMARY"
  fi
else
  echo "- Pack #3: DRY-RUN (see $PACK3_LOG)" >> "$SUMMARY"
fi

echo "- Pack #3 logs: $PACK3_LOG" >> "$SUMMARY"

echo "$RUN_DIR"
