# Profil mobil és telepítési felület — 2026-11-09

Felhasználói utasítás: a profil mobil elrendezése javítandó, mobilon és desktopon az új zöld telepítési gomb/súgó jelenjen meg, a régi akadálymentesítési gyorsgomb tűnjön el. Belépés, kódmentés, nyers visszatérési kontextus és profilfunkciók nem változhatnak. Alap b05834f87d1c22fa5f4c23ad8750fd5eb6bdf3af; saját ág fix/profile-mobile-install-ui-20261109.

## Koherencia és érintett funkciók

Protected files touched: wp-content/mu-plugins/impactshop-profile-ui.php, docs/impactshop-guard-hashes.json, docs/impactshop-guard-hashes.sha256. Smoke scope: pwa_shell, mobile_shell_runtime, route:profil, flow:pwa-install-entry. A meglévő profil-only adapter felületi változása. Az identity PHP/JS/overlay, actionbar és PWA forrásbyte-jai változatlanok. A régi PWA marad a natív telepítés egyetlen végrehajtója; a külön azonosítójú új indító/súgó nem navigál és nem küld űrlapot. Más host/route, manifest, SW, pont, szavazat, cron, DB, jogosultság és runtime-konfiguráció nem érintett.

Kockázat: mobil takarás, dupla telepítési kérés, fókuszvesztés; közvetett kockázat a natív form/return megváltoztatása. Védelem: eredeti node-ok/listenerek megőrzése, szűk CSS és saját UI-node-ok, meglévő offline restore/save/return tesztek, natív prompt darabszám és geometriateszt. Nincs új helper, release-rendszer vagy dependency. Kis összefüggő korrekció a főszálon; a kész diffre egy független review.

## Megőrzés, kiadás és rollback

A három korábbi saját dokumentum-WIP veszteségmentes stash-e: 21634b8bc55843938552a933709de4f776d5854c. Nem része automatikusan az új commitnak. A legacy teszt actionbar a már ismert 7d64aca5 Git-blobból, hash f05af5bf0c6112e78bc701637217e8ad3ffabba3ac4843f955856146e15fabfd; nem új éles állapotállítás.

Egy WP-PR, meglévő exact-file guardolt kiadás kizárólag a profil-UI fájlra. Előhash friss readbackből, backup/CAS/atomi csere/utóhash/0444. Rollback: bin/impactshop-guard-rollback.sh --production --apply --release-id=<tényleges ID> --expected-deployed-sha=<tényleges utóhash>. Csak saját fájl-előkép; nincs DB-restore. Main-művelethez külön rövid ablak; build/telepítés alatt nincs main-foglalás.

## Ellenőrzés és kézi UI checklist

- 320/390/768/1280 px, rövid kijelző és elforgatás: olvasható mezők/gombok, nincs belső szövegtúlcsordulás, alsó művelet görgetéssel hozzáférhető.
- Egy zöld letöltési indító; régi kék/reopen/mobil/a11y vezérlők nem láthatók és nem tab-stopok. Súgó/bezárás/Escape/fókusz; telepítési prompt egyszer, standalone/consent korrekt.
- Négy nyelv/deviza, eredeti formok, query/hash/history és JYSK restore/mentés-visszatérés változatlan. Minden tesztkérés elfogva; élő új profil, kód vagy pont nem keletkezhet.
- Éles kiadás után célfájl és megőrzött core-hashek, mobil/desktop UI, meglévő profilos JYSK-visszatérés. Forrás/merge/telepítés/éles elfogadás külön jelentendő.

Forráskészség és éles elfogadás külön; kiadás még nem történt.

## Célzott geometriai diagnózis

Az első új UI-kör igazolta, hogy320 px-en a régi megosztott magasság126px, de a dock tényleges magassága146px és az alsó tér8px. A gomb12px-et takart: saját --profile-ui-dock-clearance, getBoundingClientRect és a dockra kötött ResizeObserver javítja, a megosztott controller változatlan. A rövid,768×390 nézet végső űrlaptesztjében a dokumentum legvégére görgetés már a gomb fölé vitt (y=-17.56), nem a dock fedte el; a célzott teszt a tényleges gombot középre görgeti, és annak egész téglalapját méri. Nincs ismételt vak termékkód-javítás. A független review által jelzett asztali UA-s iPad súgója touch-képesség alapján iOS-ra vált; külön fixture igazolja.

## Tényleges helyi ellenőrzés és review

Node22.23.2, meglévő app Playwright-függőség, Chrome, egy futtató, nincs automatikus retry/install:26/26 profil-UI eset PASS18.847s. Legacy és managed bar, négy nyelv/deviza,320/390/768/1280px, rövid/elforgatott képernyő, egyszeri prompt/dupla kattintás, natív modal fókusz/Escape, consent/standalone, iPhone és asztali UA-s iPad súgó, valós PHP/JS restore/mentési visszatérés. Minden hálózat elfogva, unreachable proxy, service worker blokkolva; kizárólag szintetikus profil. A Safari eset UA-/capability-fixture Chrome-ban, nem valódi Safari/iOS elfogadás.

Független reviewer: profile_mobile_install_review, kért6.1-sol/high. PHP lint és diff-check futott, böngészőtesztet nem ismételt. Egy finding (asztali UA-s iPad) javítva és read-only deltában visszaellenőrizve; más actionable finding nincs. A reviewer a PWA-delegálást, privacy/return változatlanságot, consent/standalone/fókusz és dock-mérést ellenőrizte. Új source SHA-256:2d96364760a7fca9d32a437349dbf5099c25ddac211d056df86b2e108460e69f.

PHP plugin lint és a meglévő profile-code-save teszt PASS. Bástya: teljes aktuális inventory0 drift,6 izolált hash/checksum eset és deploy-wpcontent-map-bastion fixture PASS. Staged natív audit8 snapshot titokscan, diff-check, protected-touch és commit-lane PASS; a meglévő BASTION_OVERRIDE=1 az explicit felhasználói protected módosításhoz, valódi change record/rollback/smoke mezőkkel, változatlan guarddal. A csoport tagek más route-jain a pontos PHP-render gate no-op bizonyíték, nem teljes idegen funkcióteszt. DOCSYNC célzott registry guard allowed,0 reposChecked: nincs regisztrált lefedettség, ezért nem lefedettségi PASS; a saját kötelező dokumentumok frissültek, központi registry változatlan.
