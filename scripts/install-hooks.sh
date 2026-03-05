#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "${REPO_ROOT}" ]]; then
  echo "[install-hooks] hiba: nem git repóban futsz." >&2
  exit 1
fi

HOOK_DIR="${REPO_ROOT}/.git/hooks"
mkdir -p "${HOOK_DIR}"

cat > "${HOOK_DIR}/pre-push" <<'HOOK'
#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
SAFE_AUDIT_SCRIPT="${REPO_ROOT}/scripts/safe-repo-audit.sh"
if [[ ! -x "${SAFE_AUDIT_SCRIPT}" ]]; then
  SAFE_AUDIT_SCRIPT="${REPO_ROOT}/../scripts/safe-repo-audit.sh"
fi

if [[ -x "${SAFE_AUDIT_SCRIPT}" ]]; then
  "${SAFE_AUDIT_SCRIPT}" --repo "${REPO_ROOT}" --strict --mode push
else
  echo "[repo-guard] missing safe audit script (local/fallback)" >&2
  echo "[repo-guard] checked: ${REPO_ROOT}/scripts/safe-repo-audit.sh and ${REPO_ROOT}/../scripts/safe-repo-audit.sh" >&2
  exit 1
fi

expected_origin="${IMPACTSHOP_NOTES_EXPECTED_ORIGIN:-https://github.com/office-hue/impactshop-notes.git}"
actual_origin="$(git remote get-url origin 2>/dev/null || true)"
if [[ "${actual_origin}" != "${expected_origin}" ]]; then
  echo "[repo-guard] Blocked push: origin mismatch" >&2
  echo "[repo-guard] expected: ${expected_origin}" >&2
  echo "[repo-guard] actual:   ${actual_origin}" >&2
  exit 1
fi
HOOK

chmod +x "${HOOK_DIR}/pre-push"
echo "[install-hooks] OK: ${HOOK_DIR}/pre-push telepítve."
