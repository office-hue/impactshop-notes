# Protected Change Record

- Date: 2026-06-29
- Scope: impactshop-notes local runtime N4 continuity/guard enforcement
- Reason: A helyi runtime starter lane mar kiadja a marker, a decision artifact es a koordinacios snapshot truthot, de ezek eddig nem voltak kotelezoen ervenyesitve push elott. A continuity/guard szeletnek ezt a hook-szintu rest kell fail-closed modon bezarnia.
- Risk: Medium (protected local guard/control-plane touch)
- Rollback: A commit teljes visszavonasa (`git revert <commit>`), vagy a `scripts/install-hooks.sh` es `scripts/guarded-push.sh` megelőző repo-tracked allapotanak visszaallitasa, majd `bash scripts/install-hooks-all.sh` ujrafuttatasa a korabbi hook-lanc visszaallitasahoz.

## Protected Files Touched

- `scripts/install-hooks.sh`
- `scripts/guarded-push.sh`

## Additional Runtime Files

- `scripts/worktree-continuity-guard.sh`
- `scripts/worktree-readiness-check.sh`
- `scripts/git-health-check.sh`

## Validation Plan

- `bash -n scripts/worktree-continuity-guard.sh scripts/install-hooks.sh scripts/guarded-push.sh scripts/worktree-readiness-check.sh scripts/git-health-check.sh`
- `bash scripts/worktree-task-start.sh feat/impactshop-notes-doc-sync-runtime-n4-20260629 --resume --doc-sync-label impactshop-notes-doc-sync --doc-sync-repo-id impactshop-notes --doc-sync-path-prefix docs/`
- `bash scripts/worktree-continuity-guard.sh --json --mode push`
- `bash scripts/install-hooks-all.sh`
- `bash scripts/git-health-check.sh`
- `git diff --check`
- `bash scripts/safe-repo-audit.sh --strict --mode local`
- `bash scripts/safe-repo-audit.sh --strict --mode push`

## Smoke Scope

- `guard:worktree-continuity`
- `hook:pre-push`
- `wrapper:wpush`
- `snapshot:active-worktree`
- `snapshot:active-worktrees`

## Expected Outcome

- a local pre-push hook es a `git wpush` ugyanazt a continuity guardot futtatja;
- a guard `blocked` allapotban megallitja a push-t, ha a marker/artifact/snapshot paritas serult;
- a guard `degraded` allapotban reviewer-visible warningot ad, de nem allit tevesen kesznek egy hianyos continuity truthot.
