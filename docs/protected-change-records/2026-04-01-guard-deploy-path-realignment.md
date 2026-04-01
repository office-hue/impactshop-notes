## Összefoglaló
- a guard deploy preflight most már ugyanahhoz a repóhoz tartozó worktree-t is elfogadja a git common dir alapján
- a guard config és hash repo-meta többé nem a régi `ops/adswatch-clean` branchre mutat, hanem a kanonikus `main`-re
- a kanonikus védelmi doksik most már explicit kimondják, hogy hibás guard deploy infrastruktúra esetén csak dokumentált, nem-kanonikus incidens restore megengedett

## Érintett fájlok
- `bin/impactshop-guard-preflight.sh`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-guard-config.sha256`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `docs/ai-assistant-canonical-policy.md`
- `docs/impactshop-deploy.md`
- `docs/bastion-guard-status.md`

## Kockázat
- ha a repo-meta rossz branchre mutat, a kanonikus deploy wrapper használhatatlanná válik és hamis nem-kanonikus kerülőutakhoz vezet
- ha a preflight nem ismeri fel helyesen a worktree-t, a tiszta feature/worktree deploy-előkészítés is hamis negatív blokkolást kap

## Ellenőrzés
- `bash -n bin/impactshop-guard-preflight.sh`
- `python3 -m json.tool docs/impactshop-guard-config.json`
- `python3 -m json.tool docs/impactshop-guard-hashes.json`
- dirty, nem-`main` checkoutban a preflight továbbra is blokkol branch mismatch-csel
- worktree-topológián a git common dir azonosítás igazoltan ugyanarra a repo-admin gyökérre mutat

## Smoke
- `deploy:guard-preflight`
- `deploy:checksum-verify`

## Rollback
- a branch root vagy repo-meta realignment visszaállítható az előző commitra
- a checksum fájlok a commit rollbackkel együtt állnak vissza
