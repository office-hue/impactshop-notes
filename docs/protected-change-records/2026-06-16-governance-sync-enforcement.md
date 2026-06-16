# Protected Change Record - 2026-06-16 - governance sync enforcement

## Protected files touched
- scripts/safe-repo-audit.sh

## Why this change is needed
- A local governance/guard lane eddig csak altalanos docs continuityvel volt vedve.
- Emiatt a local governance hub frissitese utolagos adminisztraciova tudott csuszni.
- A cel az, hogy a helyi governance system plan explicit push-gate legyen, ne csak utolagos naplo.

## Change summary
- A `scripts/safe-repo-audit.sh` most mar elbukik, ha governance/guard/policy lane valtozas tortenik a local governance system plan syncje nelkul.
- A `scripts/git-health-check.sh` kulon ellenorzi a safe-audit bekoteset.
- Az `AGENTS.md`, a local governance system plan, a `system-status-snapshot.md` es a `notes.md` ugyanebben a szeletben visszadokumentalja az uj szabalyat.

## Rollback
- Revert this commit.
- Allitsd vissza a korabbi `scripts/safe-repo-audit.sh` allapotot, ha a helyi push-gate tul szelesen vagy hibasan blokkol.
- Ellenorizd utana a local pre-push hookot es a strict auditot.

## Smoke
- smoke tag: deploy:guard-preflight
- smoke tag: deploy:checksum-verify
- local checks: `bash -n scripts/safe-repo-audit.sh`
- local checks: `bash -n scripts/git-health-check.sh`
- local checks: `git diff --check`
