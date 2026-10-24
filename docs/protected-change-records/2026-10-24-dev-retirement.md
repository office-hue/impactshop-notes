# DEV-kivezetés — protected source rekord

Protected files touched: `safe-repo-audit.sh`, `install-hooks.sh`,
`guarded-push.sh`, `start-feature-worktree.sh`, `worktree-task-start.sh`,
`worktree-task-start-guard.sh`, `worktree-readiness-check.sh`,
`worktree-coordination-sync.sh`, `worktree-continuity-guard.sh`,
`dev-delivery-v2-adapter.sh`, `check-protected-file-touch.sh`,
`check-commit-lane.sh` és a saját új opcionális natív helper.
Felhatalmazás: Arnold kifejezetten elfogadott DEV-enforcement retirement terve.
Ez forrásmódosításra szól, nem deployra vagy hosthook aktiválására.

Koherencia: engine/capsule/selector/model/admission kényszer kivezetve;
a tényleges termék/domain/titok/backup/CAS/rollback vizsgálat megmarad.
Érintett funkciók: natív Git, index/push titokscan, protected-touch,
product lane, hook prepare/apply/rollback, worktree start/resume.
Kockázat: hamis permission PASS, idegen WIP/hook felülírás, termékguard
eltűnése. Védelem: explicit retired/advisory, pontos tartalmi fixture,
custom-hook megőrzés, CAS, visszaolvasás és változatlan termékpayload.

Rollback: a `bbf2345416fe4aa2f4b49c8f02e57fa4ceb1a77c` saját forráspreimage
célzott visszaállítása, idegen WIP megtartásával. Git object a helyi backup.
Smoke scope: `deploy:guard-preflight`, `deploy:checksum-verify` itt kizárólag
helyi syntax/source checksum és izolált mock/fixture, nem staging vagy deploy.
34/34 retirement fixture + 1 új célzott CI content-only fixture, öt deploy/rollback mockteszt, affiliate tamper fixture
és Impi source audit sikeres. Ez nem élő deploy vagy staging-próba.
Termék UI checklist N/A; későbbi host/klienscanary külön feladat.
