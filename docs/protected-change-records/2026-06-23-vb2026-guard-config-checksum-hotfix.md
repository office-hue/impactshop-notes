# Protected Change Record

- Date: 2026-06-23
- Scope: ImpactShop guard config checksum hotfix after VB2026 inventory cleanup
- Reason: A `docs/impactshop-guard-config.json` mar az uj, phantom-bridge bejegyzes nelkuli allapotot hordozta, de a hozza tartozo `docs/impactshop-guard-config.sha256` a regi checksumot tartotta. Emiatt a canonical guard deploy mar a config-integritasi gate-nel megallt.
- Risk: Low (checksum-truth helyreigazitas, runtime kodmodositas nelkul)
- Rollback: A commit teljes visszavonasa (`git revert <commit>`), vagy a megelőző `docs/impactshop-guard-config.json` + `docs/impactshop-guard-config.sha256` par egyutt visszaallitasa a korabbi repo-tracked allapotra.

## Protected Files Touched

- `docs/impactshop-guard-config.sha256`

## Continuity Files Touched

- `notes.md`
- `system-status-snapshot.md`

## Validation Plan

- `sha256sum docs/impactshop-guard-config.json`
- `cat docs/impactshop-guard-config.sha256`
- `bash bin/impactshop-guard-preflight.sh`
- `git diff --check`

## Smoke Scope

- `deploy:guard-preflight`
- `deploy:checksum-verify`
