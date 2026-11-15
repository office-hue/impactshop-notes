# Két kiadási referenciahash célzott rendezése

Protected files touched: `docs/impactshop-guard-hashes.json`,
`docs/impactshop-guard-hashes.sha256`. Smoke scope: deploy_guard;
tags: `deploy:checksum-verify`, `deploy:guard-preflight`.
Arnold2026-11-15-i külön engedélye: a két baseline-eltérés rendezése,
védelemgyengítés és üzleti kódváltozás nélkül. Alap: merge-elt Notes
`75ab8acc6a6ac8d26c5b9b3b9dc5e36c805ae21a` (#280).

## Koherencia, döntés és kockázat — módosítás előtt

A natív guard pontosan két eltérést talált tiszta, remote mainnel egyező
checkoutban. Mindkét régi digest bájtpontosan a már merge-elt
`91b8cc6835859168dcdfd5cbc742fd2b985ae912` előtti blob hash-e.
Azóta a CI kilenc új jutalommegfigyelési PHP-lint/tesztparancsot kapott;
meglévő lépést, permissiont vagy védelmet nem törölt, deployt nem ad hozzá.
A config.sha256 ugyanott az aktuális guard-config.json helyes checksumját
követi. E fájlok most változatlanok maradnak.

Csak a két név szerinti hash és a manifest külső checksumja változik.
Timestamp, inventory, tulajdonosok, minden más digest, guard/CI-program,
policy, deploy-profil és JYSK PHP bájtazonos. Nincs általános hash-refresh,
auto-approve/override telepítés vagy új helper. Fő kockázat jogosulatlan kód
legitimálása: pontos történeti diff, kétkulcsos összevetés és negatív
valódi checker-teszt kezeli. UI/REST/auth/ledger/cron/NGO nem érintett.
JYSK payload:554d3dbbf1fb428e346822039bf7cfcddbb30df87a2e2a2a24f177a77366e608.

## Ellenőrzés és parity

Meglévő `python3 tests/impactshop-guard-hash-baseline.test.py`: teljes
valós inventory, helyes CI elfogadása, régi baseline, új CI/idegen protected
tamper és hiányzó CI elutasítása, külső checksum. Meglévő
`bash tests/impactshop-guard-preflight-detached.test.sh`: exact-main út.
Külön összevetés: kizárólag két digest változik; config-checksum/JYSK payload
egyezik; protected-touch/commit-lane, staged diff, whitespace és titokscan.
Hosted CI és merge külön visszaolvasandó, nem futtatott check nem PASS.

Tényleges helyi eredmény: teljes protected inventory nulla drift; hat izolált
hash/checksum-eset és a detached exact-main fixture sikeres. Külön natív
összevetés igazolta: csak a két megnevezett digest változott, inventory/
metadata/CI/config/guard/JYSK payload bájtazonos. Whitespace-ellenőrzés tiszta.

## Backup és rollback

A Git-base75ab8acc őrzi az eredeti manifest/checksum párt. Rollback: csak
saját commit célzott revertje új PR-ben, idegen változások megőrzésével.
Az ismert baseline-hiba visszatérne, ez nem működési javítás. Nincs DB-,
PHP- vagy szolgáltatás-visszaállítás ebben a forráscsomagban.

## Kézi UI checklist és kiadási határ

UI: nem alkalmazandó, minden webes bájt változatlan. Operátori ellenőrzés:
jóváhagyott két blob elfogadva, új tamper elutasítva. Tiszta merge-elt
checkoutból natív guarded dry-run szükséges. A JYSK közleménye/játéka
maradjon elérhető; élő elfogadás nem következik a helyi fixture-ből.
Ez nem JYSK-telepítés/aktiválás.21:00-kor csak Arnold „indítsd” jelzésére,
friss backup/drain/CAS/rollback feltételekkel indulhat a külön kiadás.
