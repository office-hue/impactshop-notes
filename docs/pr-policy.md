# PR Policy (Enforced One-Path)

Ez a repo **kötelező, egyetlen útvonalú** commit/push/PR/deploy policyt követ.

## Kötelező útvonal (minden munka)

1. Új klón vagy új worktree után futtasd:
   - `bash scripts/install-hooks-all.sh`
2. Új fejlesztés csak `origin/main` alapról indulhat:
   - `bash scripts/start-feature-worktree.sh <feature-branch>`
   - automatikusan fut: `memory:pre-task` + branch context pack (`.codex/context/<branch>.md`)
   - Impact Challenge érintettségnél az alapértelmezett megoldás additív új kód.
   - Védett meglévő Impact Challenge fájlhoz vagy útvonalhoz csak külön engedéllyel szabad nyúlni, és csak akkor, ha nincs ugyanilyen jó additív megoldás.
3. Módosítás után kötelező helyi állapotellenőrzés:
   - `bash scripts/git-health-check.sh`
4. Push csak feature/worktree ágról mehet, `main/master` közvetlen push tiltott.
   - Impact Challenge változásnál a push előfeltétele, hogy a commit/PR egyértelműen jelölje: additív új kód vagy jóváhagyott legacy touch történt.
5. PR nyitás javasolt parancsa:
   - `npm run pr:create-with-memory -- --fill` (PR + auto memory capture)
6. PR csak kötelező checklist blokkal nyitható (`PR-EXIT-CHECKLIST.md`).
   - Impact Challenge PR-ben kötelező külön rögzíteni: mely védett peremet érinti, történt-e legacy touch, volt-e explicit engedély, mi a rollback út.
   - Ha protected file érintett, a PR kötelező melléklete: koherencia vizsgálat, kockázatelemzés, érintett funkciólista, post-merge ellenőrzési kör, kézi UI checklist.
7. Napi zárás ajánlott:
   - `npm run memory:digest` (digest markdown, opcionális email)
8. Merge csak akkor engedhető, ha a PR explicit módon megfelel a bástyavédelmi és írásvédettségi szabályoknak.
9. Deploy csak guardolt útvonalon mehet, és csak merge-elt főágból.
   - Production deploy után a védett Impact Challenge fájlokat vissza kell zárni read-only állapotba.

## Impact Challenge védett útvonal szabály

- A teljes Impact Challenge perem bástyavédett és írásvédett területnek számít.
- Ide tartozik minden releváns funkció, útvonal, bekötés, kapcsolódási pont, nyilvántartás, adatírási mód, workflow és pipeline.
- Az Impact Challenge kanonikus baseline-ja: `docs/impact-challenge-canonical-baseline.md`. Impact Challenge PR / merge / deploy esetén ehhez kell mérni az eltérést.
- Külön beton protected perimeternek számít a teljes guide rendszer: `impactshop-ngo-guides.php` és `wp-content/mu-plugins/impactshop-ngo-guides/**`.
- Ezen belül a JYSK riport külön név szerint max-védett surface: `/jysk-riport/`, `/jysk-riport/?print=1`, `/jysk-riport.data.json`.
- Guide route / guide HTML / guide asset / guide PDF / guide fordítás módosítása csak explicit felhasználói engedéllyel mehet; sem PR, sem merge, sem deploy, sem automatika nem írhatja felül ezeket hallgatólagosan.
- JYSK riport touch esetén a protected smoke scope-nak ki kell terjednie a route renderre, a print nézetre és a JSON payloadra is; ezt nem lehet egyszerű guide-copyként vagy statikus assetcseréként kezelni.
- A default fejlesztési stratégia: `new code first`.
- Legacy módosítás csak külön, kifejezett jóváhagyással és csak akkor engedhető, ha nincs azonos minőségű additív megoldás.
- Protected file módosítás előtt kötelező a `docs/protected-file-change-checklist.md` szerinti koherencia és kockázati felmérés.
- Protected file módosítás után kötelező a funkció-ellenőrzési kör és a felhasználónak szóló UI checklist átadása.

## Hard enforce (technikailag beállítva)

- `pre-commit` hook: blokkolja a commitot `main/master` ágon.
- `commit-msg` hook: automatikusan hozzáadja a `Memory-ID: none` sort, ha hiányzik.
- `pre-push` hook: blokkolja a közvetlen `main/master` push-t.
- `pre-push` hook: kötelező policy fájlok meglétét ellenőrzi.
- `pre-push` hook: `safe-repo-audit.sh --strict --mode push` futtatása kötelező.
- `pre-push` hook: Impact Challenge változásnál a bástyavédelmi és dokumentációs evidencia megléte kötelező.
- `pre-push` hook: memory gate (`memory:gate`) kötelező.
- `pre-push` hook: PR auto-memory sync (`memory:sync-pr`) fail-open módban.
- `post-commit` hook: automatikusan memóriába rögzíti a commit kontextust (fail-open).
- `post-merge` + `post-checkout` hook: automatikus memóriafrissítés throttlinggal (fail-open).
- CI: PR Checklist Guard kötelező PR body ellenőrzéssel.
- Merge/deploy döntésnél a bástyavédelmi szabály megsértése hard stop.

## Általános hardening szabályok

- A lokális guard és a GitHub oldali CI guard közötti szabályparitás kötelező.
  - Új protected modell vagy guard-szigorítás csak akkor tekinthető késznek, ha lokálban és CI-ben ugyanazt a döntést adja ugyanarra a lane-re.
- A guardolt push wrapper teljes értékű belépési pont.
  - Nem támaszkodhat kizárólag arra, hogy a hookok telepítve vannak; saját maga is köteles futtatni a lane guardot, a protected-touch checket, a strict auditot és a memory gate-et, ha elérhetők.
- Worktree-specifikus logika csak a valódi repo-identitásból indulhat ki.
  - Repo- és deploy-döntés nem épülhet a worktree mappanévre.
- Protected env párokat együtt kell kezelni.
  - `.deploy.production.env` és `.deploy.staging.env` ugyanannak a protected runtime lane-nek a részei; külön staging, külön review vagy külön deploy scope nem megengedett.
- Review-fix után kötelező a teljes recheck.
  - A javítás után újra kell futniuk a guard/check köröknek, és a ténylegesen megoldott review threadeket rendezni kell merge előtt.
- Harmadik fél inventory/cache integrációnál a stale empty cache külön kockázati kategória.
  - Üres provider-válasz nem válhat tartós kanonikus állapottá forced refresh / retry / rövid TTL nélkül.

## Workflow kiegészítők (dev-memory)

- `memory:pre-task`: task-indítási brief mentése `tmp/state/dev-memory/last-brief.json`-ba.
- `memory:context-pack`: branch-specifikus `.codex/context/<branch>.md` generálás.
- `memory:incident`: gyors incident capture + rollback checklist.
- `memory:digest`: napi markdown digest (`tmp/state/dev-memory/daily/`).
- `memory:install-digest-cron`: napi digest cron telepítés.
- `memory:install-copilot-cron`: Copilot chat ingest napi cron telepítés.

## Kötelező policy fájlok

- `docs/pr-policy.md`
- `PR-EXIT-CHECKLIST.md`
- `.github/pull_request_template.md`
- `scripts/start-feature-worktree.sh`
- `scripts/git-health-check.sh`

## Vészhelyzeti bypass (csak jóváhagyással)

- commit bypass: `IMPACT_POLICY_ALLOW_MAIN_COMMIT=1`
- push bypass: `IMPACT_POLICY_ALLOW_MAIN_PUSH=1`

A bypass használata csak ideiglenesen engedett, és PR-ben kötelező dokumentálni.
