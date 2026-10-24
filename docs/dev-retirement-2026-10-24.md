# Notes DEV-kivezetés — helyi forrás

A jóváhagyott kivezetés base-e:
`bbf2345416fe4aa2f4b49c8f02e57fa4ceb1a77c`, branch:
`dev/retirement-20261024`. Ez a helyi módosítás nem hosttelepítés,
GitHub protection-módosítás, futó chatbetöltés vagy termékdeploy.

## Működés

Natív Git engine/capsule/selector/taskpin/repin/modellkapu nélkül használható.
A lifecycle/delivery/admission/context/continuity/readiness/coordination
kompatibilitási belépők írásmentes `retired/advisory` jelentést adnak:
`sourceChecks:not-evaluated`, `authorityGranted:false`.
Nem indítanak régi controllert és nem változtatnak régi taskállapotot.

A start opcionális natív Git worktree helper: HEAD az alap, explicit
`--base`/ `--path` választható, nincs automatikus fetch, marker, hook,
cache/dependency install vagy közös koordinációs writer. Resume ugyanabban
a worktree-ben folytat; repinre nincs szükség.

A `safe-repo-audit.sh` a `workspace-hooks.py audit` olvasóját hívja:
index/helyi vagy pontos push tartalom, köztes commitok és root titokscan,
értékkiírás nélkül. Protected-touch/product lane és releváns affiliate/Impi
ellenőrzés megmarad. A staged mód nem vizsgálja idegen unstaged tartalom
engedélyét, és nem stage-el/töröl WIP-et. Hiányzó runtime/termékcheck
`unavailable`, nem PASS. Több ref vagy nem azonos checkout esetén a
worktree-t olvasó product checkekhez pontos checkout szükséges.
Új branch explicit termék-base nélkül nem kap product-permission állítást.
A titokscan az aktuális push tényleges céljának egyetlen read-only refsnapshotja
alapján csak a kimenő történetet vizsgálja; nincs fetch vagy upstream-cache
bizalom. Hiányos/távol elérhetetlen snapshot `unavailable`, nem PASS.
Explicit offline teljes történet: `audit --mode push --all-history`.

A `guarded-push.sh` nem HEAD-only előellenőrzést használ: tranzakció-lokális
pre-push hook kapja a natív Git összes refjét és tényleges remote-argumentumát,
az idegen végrehajtható saját hookot változatlan stdin/argumentumokkal láncolva.
Main/master cél, nem fast-forward, `--no-verify` vagy idegen/átírt cél tiltott.
Helper nélkül minimális shell target/ref-védelem marad; nincs audit-PASS.
A termékguardok kiválasztása a valódi protected/additive manifestből történik,
nem egy részleges runtime-listából; csak teljesen kivezetett DEV-fájl kivétel.

CI-ben az exact commitrange `--content-only` auditot kap: `sourceContentStatus`
a tényleges tartalomellenőrzést jelöli, `productGuards` és `productPermission`
explicit `not-evaluated`. Ez nem protected-change permit. Az alapértelmezett
helyi/natív push-audit minden valódi termékguardot továbbra is futtat;
CI-be nincs automatikus `BASTION_OVERRIDE=1` beépítve. A két meglévő termék-CI
és az öt deploy/rollback mockteszt változatlanul megmaradt.

## Opcionális hooktelepítés

```sh
bash scripts/install-hooks.sh --repo <saját-repo>
bash scripts/install-hooks.sh --repo <saját-repo> --apply --expect <beforeDigest>
bash scripts/install-hooks.sh --repo <saját-repo> --rollback <saját-backup-út>
```

Az első parancs csak előkészítés. Apply explicit, ellenőrzött preimage után,
backup/CAS/readback védelemmel. Saját exact régi Notes-renderer felismerhető;
idegen/custom hook megmarad. Egyedi cseréhez konkrét
`--replace <hook>=<sha256>` kell. Külső/shared hooksPath nem írható innen.
A teljes hookpreimage kizárólag a saját canonical Git-dir
`workspace-hook-backups/` privát 0700 könyvtárába, 0600 manifestbe kerül,
soha nem a tracked hooksPath alá vagy stdoutba. Rollback a repo/Git-dir/
hooksPath identitást köti, az eredeti és saját poststate kevert részleges
visszaállítását folytathatja; idegen harmadik állapotot megőriz és elutasít.
A telepítőn kívül a forráskivezetés nem változtat hosthookot vagy git configot.

Az opcionális checker/Python hiánya advisory, natív Git folytatható:
nincs sikeres auditállítás. Tényleges titok, protected main/master push vagy
product violation továbbra is hiba. Kötelező Memory-ID/trailer nincs;
nincs post-merge deploy, háttér-memória vagy sibling script.

## Megőrzött termékhatárok

WordPress, Hatás Körök/Impact Challenge, guide/JYSK, affiliate/identity/
reward/settlement, guarded deploy/restore és fizikai read-only követelmények
megmaradnak. Protected termékváltozásnak valódi felhatalmazás, rekord,
backup/CAS/rollback és releváns smoke kell. A DEV-kivezetés önmagában
egyikhez sem ad jogosultságot. A Codex bridge végrehajtó és a termék-CI
source guardok érintetlenek; ezt a munkát nem szabad a bridge-del futtatni.

## Ellenőrzés és rollback

Helyi `python3 -B tests/workspace-retirement.test.py`: 34/34 pozitív és
negatív fixture sikeres (19.914 s).
Ezután az új CI content-only regresszió és három érintett alapértelmezett
guard/push-regresszió együtt 4/4 sikeres (3.504 s); a többi teszt változatlan.
A kezdeti három assertion a hook stdout/stderr és
macOS /var realpath különbségét javította; a tiltások már előtte működtek.
Ez nem teljes repo-CI vagy élő smoke. Az öt meglévő deploy/rollback
fixture, affiliate tamper fixture és Impi source audit ténylegesen sikeres.
Az exact-file fixture hiányzó fixture-staging-guard figyelmeztetése megmaradt;
nem volt valódi staging/SSH hívás, a tesztek a saját mockjaikat használták.
A push-fixture-k helyi eldobható bare repókat használnak, nem GitHubot.
Két új kezdeti fixturehiba: up-to-date main nem küld refupdate-et, illetve
a bare fixture push-option hirdetése hiányzott; a fixture-előkészítés javult.
A strict local audit protected rekord nélkül ténylegesen tilt; az elfogadott
forráskivezetés rekordjával és explicit `BASTION_OVERRIDE=1`, rollback-note,
`deploy:guard-preflight,deploy:checksum-verify` mock-smoke scope-pal sikeres.
Ez nem host- vagy product-deploy felhatalmazás.

Független, szűk forrás-utóellenőrzés két P1-et talált és a javításokat lezárta:
HEAD-only pushaudit helyett tényleges ref/stdin/remote ellenőrzés, illetve
a hiányos terméklista helyett valódi manifest-partíció. A push-javítás része:
a transportvédelem nem az ideiglenes fájlnévtől függ, és minden expanded
origin pushurl ellenőrzött még a transport előtt. Külön negatív fixture az
approved+foreign második pushurl (push-invocation nélkül) és az átnevezett
hook idegen tényleges célja. A privát backup/output és repo-redirect/symlink
regresszió is része a 34 esetnek. A lezáró olvasó ebben a körben nem talált
új P1/P2-t; ez nem teljes repo-biztonsági audit.

Source rollback: a fenti base saját fájlpreimage-jének célzott visszaállítása,
idegen WIP megőrzésével. A Git object a változatlan forrás-backup.
Hosttelepítés nem történt; későbbi install rollbackje csak a saját privát
tranzakció-backup és saját before/after állapot alapján.

Kézi termék-UI: N/A, termék/UI payload nem változott. Klienscanary később:
tényleges aktuális útmutató-olvasás vagy checkpointból új chat ugyanabban
a worktree-ben. Telepített fájlból ne állíts futó chatbetöltést.

## Öt tengelyű saját ellenőrzés

1. Helyesség: natív commit/start/resume és secret/protected negatív esetek
   valódi eldobható Git fixture-ben működnek; a retired jelentés nem permission.
2. Koherencia: helyi belépők és CI azonos natív ellenőrzésre mutatnak;
   nincs engine/selector/modell/receipt visszakapuzás. Az aktuális DOCSYNC
   felülírja a régi DEV-kapuleírásokat, nem a termékforrásokat.
3. Biztonság: titokérték nem kerül outputba; köztes commitok is vizsgáltak;
   main/master target, protected product, custom hook és CAS negatív lefedett.
   A termékhez nem kapcsolódó DEV-forrás nem kér régi termék-lane engedélyt.
4. Végrehajthatóság: standard library és natív Git; nincs automatikus fetch,
   dependency install, staging-próba vagy sibling writer. Hiányzó opcionális
   checker mellett nincs ellenőrzési sikerállítás. Pontos product-base nélküli
   új branch termékellenőrzése továbbra is explicit hiány.
5. Evidencia és határ: 34 teljes + 1 új célzott fixture és hét megőrzött termékellenőrzés tényleges;
   célzott független source-utóellenőrzés történt, teljes repo-audit/CI,
   host/kliens/production elfogadás nincs állítva.
   A telepített régi hookok és kliensek külön readbackes rolloutot igényelnek.
