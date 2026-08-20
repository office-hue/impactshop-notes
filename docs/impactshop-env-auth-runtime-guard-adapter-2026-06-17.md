# ImpactShop Env/Auth/Runtime Guard Adapter

Datum: 2026-06-17
Statusz: local adapter minimum
Scope: a kanonikus env/auth/runtime guard helyi, `impactshop-notes` protected/runtime lane-jeire konkretizalt adaptere

## 2026-08-19 affiliate runtime extension

Az adapter helyi protected runtime scope-ja kiterjed a
`wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php` modulra és a
`wp-content/mu-plugins/impactshop-boot.php` exact Shopping Assistant
delegációjára.

Az `allowed` állapot további feltételei ennél a lane-nél:

1. az aktivációs option default értéke `0` és csak exact `1` engedélyez;
2. a Dognet felé csak opaque `sat1` kerülhet, raw pseudo/NGO/URL nem;
3. a 15 perces intent és 45 napos retention cleanup cron regisztrált;
4. a külön ai-agent central watchdog checkpoint merge-elt és zöld;
5. a protected digest, rollback, célzott életciklus- és mutációs teszt zöld;
6. production aktiválás csak guarded deploy után, emberi canaryval történik.

Ezek hiányában a release státusz `blocked`; a repo-checkpoint önmagában csak
default-off, deployolatlan állapotot igazol.

## 2026-08-20 exact-file deploy env extension

A paired deploy env lane most explicit `DEPLOY_ENVIRONMENT=staging|production`
identitást hordoz. A két profil együtt változik, és egyik sem fogadhat el
whitespace-, traversal- vagy abszolút mapping source/destination értéket.

Production állapot csak `DRY_RUN=1` mellett érheti el a mapping végrehajtási
ágat. `IMPACTSHOP_DEPLOY_FILE` esetén pontosan egy normál, nem symlinkelt,
fizikailag a repo alatt maradó fájl oldható fel pontosan egy remote célra;
minden `--delete*` opció eltűnik és checksum kötelező. Valós production írás
remote backup/CAS/executable rollback admission nélkül `blocked`.

Kanonikus evidencia:

- `docs/impactshop-exact-file-deploy-safety-sol-plan-2026-08-20.md`;
- `tests/deploy-wpcontent-map-exact-file.test.sh`;
- `docs/protected-change-records/2026-08-20-exact-file-deploy-safety.md`.

## Cel

Ez a dokumentum nem uj protected policyt vezet be.

A celja az, hogy az `impactshop-notes` sajat protected-file, deploy-env es profile-return runtime lane-jeire konkretizalja a kozos guard minimumot:

1. local inventory scope
2. local managed env/auth target
3. host-config restore minimum
4. runtime contract checklist
5. release-gate wrapper sorrend
6. local continuity anchor

## Local Inventory Scope

Az adapter a kovetkezo helyi truthra es lane-ekre epul:

1. `docs/impactshop-guard-config.json`
2. `docs/impactshop-protected-files.json`
3. `docs/ai-assistant-canonical-policy.md`
4. `scripts/git-health-check.sh`
5. `scripts/safe-repo-audit.sh`
6. `bin/impactshop-guard-deploy.sh`
7. `wp-content/mu-plugins/impactshop-identity-panel.php`
8. `wp-content/mu-plugins/impactshop-identity-panel.js`

Allowed repo scope:

- `impactshop-notes`

Blocked scope:

- mas repo env file-jara vagy secret targetjere mutatas
- protected MU-plugin modositas guard evidence nelkul
- olyan release, ahol a protected inventory es a valos touched lane nincs osszerendelve

## Managed Env/Auth Target

A helyi fail-closed szabaly:

1. a protected env-parok (`.deploy.production.env` + `.deploy.staging.env`) egyetlen lane-nek szamitanak
2. ezek nem keverhetok mas repo secret store-javal
3. a repo csak a lane letezeset es a guardolt hasznalati utat dokumentalja, magat a secretet nem

Kovetkezmeny:

- unresolved env target -> `blocked`
- repo-tracked secret bevezetes -> `blocked`
- staging/prod paritas nelkuli protected deploy -> `blocked`

## Host-Config Restore Minimum

A helyi restore truth minimuma:

1. `docs/impactshop-guard-config.json`
2. `docs/impactshop-protected-files.json`
3. a relevans `docs/protected-change-records/*.md`
4. a protected MU-plugin file-ok known-good git allapota

Minimum restore-evidence:

1. melyik protected lane serult
2. melyik config/protected inventory volt a known-good referencia
3. milyen deploy/restore path volt hasznalva
4. milyen smoke vagy UI evidence igazolja a helyreallast

## Runtime Contract Checklist

Protected vagy bridge-erinto lane csak akkor lehet `allowed`, ha:

1. a touched file szerepel a protected inventoryban
2. a koherencia- es kockazatvizsgalat megtortent
3. a `scripts/git-health-check.sh` es a `scripts/safe-repo-audit.sh --strict --mode push` nem bukik el
4. a protected change record vagy vele egyenerteku continuity bizonyitek rogzitett

Kulonosen erintett runtime lane-ek:

1. profile-return / identity panel
2. FactLens bridge source-side callback
3. guardolt deploy env parok

## Release-Gate Wrapper Sorrend

A helyi minimum sorrend:

1. feature/worktree fegyelem
2. protected inventory es touched lane osszerendelese
3. health + safe audit
4. guardolt deploy path vagy explicit no-deploy docs-only dontes
5. continuity visszairas

Precedencia:

- `blocked`: unresolved env target, protected inventory hiany, missing guard evidence
- `degraded`: van local truth, de a runtime bizonyitek reszleges
- `allowed`: inventory + guard evidence + continuity koherens

## Continuity Anchor

A helyi continuity minimum:

1. `docs/impactshop-governance-system-plan-2026-06-16.md`
2. `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md`
3. `system-status-snapshot.md`
4. `notes.md`

## Focused Validation

Positive:

```bash
test -f docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md
test -f docs/impactshop-guard-config.json
test -f docs/impactshop-protected-files.json
test -f scripts/safe-repo-audit.sh
test -f bin/impactshop-guard-deploy.sh
```

Negative:

```bash
rg -n "owner_repo.*ai-agent|owner_repo.*impact_hub" docs/impactshop-protected-files.json
```

A negative ellenorzes helyes eredmenye itt az ures talalat.

## Deploy snapshot / rollback truth

Az env/auth/runtime adapterben a guard snapshot alapértelmezésben lokális
forrásmásolat. A wrapper csak akkor adhat gyors rollback parancsot, ha a
megnevezett entrypoint végrehajtható. Enélkül a production write `blocked`, és
a kimenetnek ezt explicit módon kell közölnie; a snapshot önmagában nem távoli
backup és nem runtime rollback.
A védett hash-checksum fájlcímkéje kizárólag repository-relatív lehet; az
abszolút gép- vagy worktree-útvonal env/runtime driftnek és adatfolyásnak
minősül, ezért fail-closed teszt védi.
