#!/usr/bin/env bash
set -euo pipefail
# Convenience only. Native Git works without this wrapper or any DEV engine.
ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"
branch="$(git branch --show-current)"
case "$branch" in ''|main|master) echo 'Use a dedicated feature branch.' >&2; exit 1;; esac
origin="$(git remote get-url --push origin)"
case "$origin" in
  https://github.com/office-hue/impactshop-notes.git|git@github.com:office-hue/impactshop-notes.git) ;;
  *) echo 'Unexpected Notes origin; verify target before push.' >&2; exit 1;;
esac
# Multiple pushurls make native Git fan out. Validate every expanded URL before
# any transport, not merely the first returned by remote get-url.
while IFS= read -r push_url; do
  [[ "$push_url" == "$origin" ]] || { echo 'Unexpected/multiple Notes origin push URLs; no transport contacted.' >&2; exit 1; }
done < <(git remote get-url --push --all origin)
skip_option_value=0
for argument in "$@"; do
  if [[ "$skip_option_value" == 1 ]]; then skip_option_value=0; continue; fi
  case "$argument" in
    -o|--push-option|--repo|--receive-pack|--exec|--recurse-submodules) skip_option_value=1;;
    --no-v|--no-ve|--no-ver|--no-veri|--no-verif|--no-verify)
      echo 'guarded-push cannot bypass its native audit; no push performed.' >&2; exit 1;;
  esac
done
if [[ -L scripts || -L scripts/workspace-hooks.py ]]; then
  echo 'Unsafe helper path; foreign source not executed.' >&2; exit 1
fi
if command -v python3 >/dev/null 2>&1 && [[ -f scripts/workspace-hooks.py ]]; then
  exec python3 -B scripts/workspace-hooks.py push "$@"
else
  # Keep native ref/target protection even when the optional Python checker is
  # unavailable. No persistent hook/config writes and no synthetic audit PASS.
  NOTES_PUSH_TEMP="$(mktemp -d)"
  trap 'rm -rf "$NOTES_PUSH_TEMP"' EXIT
  NOTES_PUSH_OWNER_HOOK="$(git rev-parse --git-path hooks/pre-push)"
  if [[ "$NOTES_PUSH_OWNER_HOOK" != /* ]]; then NOTES_PUSH_OWNER_HOOK="$ROOT/$NOTES_PUSH_OWNER_HOOK"; fi
  [[ ! -L "$NOTES_PUSH_OWNER_HOOK" ]] || { echo 'Unsafe custom hook symlink; preserved without execution.' >&2; exit 1; }
  export NOTES_PUSH_TEMP NOTES_PUSH_OWNER_HOOK
  cat > "$NOTES_PUSH_TEMP/pre-push" <<'HOOK'
#!/bin/sh
set -eu
case "${2:-}" in
  https://github.com/office-hue/impactshop-notes.git|git@github.com:office-hue/impactshop-notes.git) ;;
  *) echo '[notes-hook] unexpected Notes push transport' >&2; exit 1;;
esac
case "${1:-}" in origin|"$2") ;; *) echo '[notes-hook] unexpected Notes push remote' >&2; exit 1;; esac
[ "$(git remote get-url --push origin)" = "$2" ] || { echo '[notes-hook] origin mismatch' >&2; exit 1; }
cat > "$NOTES_PUSH_TEMP/refs"
while read -r local_ref local_sha remote_ref remote_sha; do
  case "$remote_ref" in refs/heads/main|refs/heads/master) echo '[notes-hook] protected main/master target' >&2; exit 1;; esac
  case "$local_sha:$remote_sha" in 0000000000000000000000000000000000000000:*|*:0000000000000000000000000000000000000000) continue;; esac
  git merge-base --is-ancestor "$remote_sha" "$local_sha" || { echo '[notes-hook] non-fast-forward requires reviewed recovery' >&2; exit 1; }
done < "$NOTES_PUSH_TEMP/refs"
if [ -x "$NOTES_PUSH_OWNER_HOOK" ]; then "$NOTES_PUSH_OWNER_HOOK" "$@" < "$NOTES_PUSH_TEMP/refs"; fi
echo '[notes-hook] Optional checker unavailable; native Git continues. No audit evidence produced.' >&2
HOOK
  chmod 700 "$NOTES_PUSH_TEMP/pre-push"
  git -C "$ROOT" -c "core.hooksPath=$NOTES_PUSH_TEMP" push "$@"
  exit "$?"
fi
