# 2026-05-05 - Impact Community source guard hardening

## Scope
- Canonical source/hash guard bevezetese a `scripts/guarded-remote-write.sh` scriptben `impact-community.php` deploy elott.
- `scripts/impact-intl-runtime-backup.sh` es `scripts/impact-intl-runtime-rollback.sh` deduplikalasa.
- Community file backup/rollback explicit opt-in + explicit acknowledgement moge teve.
- CI workflow: `.github/workflows/impact-community-source-guard.yml`.

## Kockazat
- Alacsony: guard script szigoritas es folyamatvedelem, runtime plugin kodot nem modosit.

## Rollback
- Commit rollback: `git revert 2869ee02` es `git revert 25376e8c`.
- Operativ rollback: korabbi deploy script lane tovabbra is hasznalhato, community restore csak explicit flaggel.

## Smoke
- `bash -n scripts/guarded-remote-write.sh scripts/impact-intl-runtime-backup.sh scripts/impact-intl-runtime-rollback.sh`
- `scripts/impact-intl-runtime-backup.sh --dry-run --backup-id test-intl-guard`
- `scripts/impact-intl-runtime-backup.sh --dry-run --include-community --backup-id test-intl-guard` (vart fail ack nelkul)

## Megjegyzes
- A hajnali regresszio mintazatahoz igazodva a veletlen nem-kanonikus forrasbol torteno deploy lane immar fail-closed.