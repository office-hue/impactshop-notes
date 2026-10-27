# Jóváhagyott CI-forrás hashének célzott követése

Protected files touched: `docs/impactshop-guard-hashes.json`,
`docs/impactshop-guard-hashes.sha256`; smoke scope: deploy_guard;
kötelező tagek: `deploy:checksum-verify`, `deploy:guard-preflight`.
Arnold 2026-10-28-i explicit engedélye: vizsgáld, tervezd és hajtsd végre
a korábban egyeztetett célzott hash-javítást. Alap: #228 merge9c15fff3.

## Vizsgálat és megoldási döntés

A régi CI-digest `ab43c08a...` pontosan a #197 merge44547312 CI-blobja.
Azóta ezen az egy fájlon #215/c401a970, #224/7417e18b és #225/cab09c88
változtatott; mindhárom GitHub-readback szerint MERGED. A teljes blobdiff
áttekintve: a történeti DEV-admission lépések kivezetése, read-only permission,
persist-credentials=false, tényleges natív source-/deploy-/rollback-fixture-k,
és a #225 Notes support regression-suite. A workflow nem tartalmaz deployt.
Jelenlegi CI-blob SHA256:
`c32ce57eaf58ce4ef78f06440125c204b1186434cb1b2105b6ee78505a3a7d34`.

Terv: csak ezt az egy hash-bejegyzést és a manifest külső checksumját cseréljük.
Az utóbbi már a javítás előtt sem egyezett a #228-ban lévő manifesttel:
stored6f574cd7..., actual9ecadf02.... A timestamp, protected inventory,
repo/owner, CI, guardprogram és minden más hash változatlan.
Nincs általános hash-refresh, auto-approve, gate-kikapcsolás vagy új helper.

## Koherencia, kockázat és érintettség

Érintett kizárólag a helyi kiadási guard CI-integritás-összevetése és a checksum
ellenőrzése. Nincs weboldal/route/REST/cron/identity/ledger/pont/szavazat,
env vagy provider változás. Fő kockázat nem jóváhagyott tartalom legitimálása:
ezt a teljes változástörténet, pontos diff és egyetlen engedélyezett kulcs
ellenőrzése kezeli. Az új baseline után minden további CI-eltérés ismét blokkol.

## Ellenőrzés és parity

A külön `tests/impactshop-guard-hash-baseline.test.py` célzott teszt a
változatlan valódi guard Python-hashellenőrző ágát futtatja izolált fixture-ben:
jóváhagyott aktuális CI elfogadás; régi hash reprodukció; új CI-tamper,
más protected fájl tamper és hiányzó CI elutasítás; külső checksum egyezés.
A teljes valódi protected inventoryn is nulla drift szükséges. Az eredeti
védett rollback truth teszt byte-változatlan, így nem keletkezik új hash-drift.
A CI a meglévő suite-okat változatlanul futtatja; az új hat regresszió most
helyi bizonyíték, hosted futását nem állítjuk. A CI/guard végrehajtandó
forrás byte-változatlan; hosted checkek eredményét külön olvassuk vissza.
Helyben ténylegesen PASS: a hat hash-regresszió, a meglévő rollback truth
suite, detached exact-main preflight suite, teljes külső checksum-ellenőrzés;
külön strukturális összevetés igazolta, hogy pontosan egy hash-kulcs változott.
Független review észrevétele: az eredeti védett teszt bővítése új driftet
okozott volna. Ezért az a fájl byte-azonosan visszaállt, a regresszió külön
fixture-be került; a javított teljes inventory ellenőrzés újrafutott.
Main-merge előtt rövid csendablak kell, utána SHA/tree-readback és elengedés.

## Backup és rollback

Az eredeti manifest/checksum pontos Git-base9c15fff3 alatt megmarad.
Rollback: e javítás saját commitjának célzott revertje új PR-ben, idegen
változások megőrzésével. Ekkor az ismert régi drift visszatér, nem tekinthető
sikeres kiadási baseline-nak. Élő adatot nem kell és nem szabad visszaállítani.

## Kézi UI checklist és kiadási határ

UI-checklist: nem alkalmazandó, egyetlen UI/runtime bájt sem változik.
Operátori ellenőrzés: a guard ne jelezze a jóváhagyott CI-blobot eltérőnek;
nem jóváhagyott új változás továbbra bukjon. Ez nem teljes deploy-PASS:
a következő valódi JYSK-kiadás minden egyéb backup/CAS/auth/readback feltétele
és a már megadott háromfájlos scope változatlan. Most nincs deploy/aktiválás.
