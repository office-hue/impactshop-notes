# Profil: régi országválasztó elrejtése, aktuális preferenciák ellenőrzése

Felhasználói utasítás2026-11-09: „Működik, de országválasztó eddig nem volt sehol. az egy régi implementáció maradványa. A nyelv és deviza remélem a legfrisebb implementáció ami a többi oldalon is kint van”. Saját ág: fix/profile-ui-preferences-20261109; tiszta alap: 388bc97259aff383fa79057957dfa1adb7270b09 (#264). Ez a következő saját forráscommit; push/PR/merge/deploy és a javítás éles UI-elfogadása külön szülő munkameneti lépés. Nincs új Hermes-hívás vagy providerköltség; apró meglévő adapterkorrekció.

## Koherencia, kockázat, érintett fájlok

Protected files touched: wp-content/mu-plugins/impactshop-profile-ui.php, docs/impactshop-guard-hashes.json, docs/impactshop-guard-hashes.sha256, docs/bastion-guard-status.md. Smoke scope: pwa_shell, mobile_shell_runtime, route:profil. Új plugin SHA-256: 87bd745dc522035d2aac02d4aa78ec7f4e0964340607395bf09f77be5225cf63.

Minimális profilra szűkített CSS elrejti a régi ország trigger/sheet/backdrop elemeit; a közös controller eredeti node-jai és listenerei megmaradnak. A látható stripben kizárólag a két meglévő nyelv/deviza select és tájékoztató marad. Mind a négy nyelven kikerül az országra utaló tájékoztató mondat. Más host/route no-op. A korábbi magyar/angol/német/francia preferenciák és HUF/EUR/USD/GBP devizák változatlanok.

Érintett felhasználói funkció: a profil preference-strip megjelenítése és tab-sorrendje. Kockázat: a rejtett managed controller DOM-függősége vagy mozgatott currency select sérülése, illetve legacy visszatérési/query regresszió. Nincs identity PHP/JS/overlay, actionbar, PWA, endpoint, adatbázis, pont/reward, civil választás vagy JYSK app forrásmódosítás. Az új fájl már szerepel a protected inventoryban; újraregisztráció, guardkód, policy- vagy CI-változás nincs. Kizárólag a plugin saját digestje és a manifest külső checksumja frissül; minden idegen hash megmarad.

## Mit igazolt a preferencia-összevetés

2026-11-09 közvetlen publikus HTTP GET, böngészőprofil és JS-futtatás nélkül: app.sharity.hu/profil/ és /impactshop/ kiszolgált navja nem tartalmazza a managed attribútumot, és nincs sharity-wp-preferences script. A profil új adaptere jelen van; az Impact Shopon nincs profiladapter. **A legújabb közös WP controller jelenleg nem élő ezeken a WP-oldalakon.** A profil a régi actionbarhoz illesztett compatibility adaptert használja; nem állítjuk, hogy ugyanaz a controller fut minden oldalon.

A jelenlegi publikus https://sharity.hu/vasarlasi-seged oldal által hivatkozott app/sharity-public/vasarlasi-seged/page-5fb4d4b18e5f534d.js és9218-0498874bfc190219.js bundle tartalmazza a négy nyelvet/devizát és a közös sharity_ui_language, sharity_display_currency, sharity_ui_preferences_v1 kulcsokat. Az első bundle fieldwise ui_lang/ui_currency getAll validálása, dupla érték elutasítása és csak az ui_* mezők eltávolítása nyers maradékmegőrzéssel is közvetlenül olvasva. A profil adapter ezeket a szerződéseket használja; a saját aktuális WP controllerrel a precedence/persist/query/history/link viselkedés célzott fixture-ben egyezik. Ez szerződésazonosság és kiszolgált artifact-bizonyíték; nem minden oldal összes futó felületi fordításának éles elfogadása. Nem frissítjük mellékesen a közös actionbart.

## Tényleges ellenőrzés

Egyetlen frissített teljes offline Chrome-futás:22/22 PASS49.361s. A meglévő21 esetből a két country-focus eset country-absence/tab-sorrend esetre változott; egy közös parity-eset került hozzá. A hidden eredeti DOM megmarad; 4 nyelv/4 deviza, kezdeti query/storage/cookie prioritás, dupla/hibás paraméter, rawURL/history/link, tiltott opcionális storage, PWA/mobil/standalone, egyszeri selection-intent, restore siker/elutasítás és aktuális v2 generálás/reveal/natív POST/visszatérés ellenőrzött. Más host és /, /impact-challenge/, /impactshop/ no-op. Minden hálózat elfogva, unreachable proxy, service worker blokkolva, kizárólag szintetikus profil. A PHP lint és diff-check sikeres. Élő profile/point writer vagy böngészős credential-prompt teszt nem történt.

Futtatás: a korábbi integration recordban rögzített USER_CARD_TEST_DEPENDENCIES_ROOT és PROFILE_UI_LEGACY_ACTION_BAR értékekkel Node22 --test tests/impactshop-profile-ui.test.mjs. A legacy fixture SHA változatlan f05af5bf0c6112e78bc701637217e8ad3ffabba3ac4843f955856146e15fabfd. Forrásaudit/commit eredmény a záró bejegyzésben.

## Kiadás, rollback és kézi UI lista

Kiadás továbbra a meglévő egyfájlos guarded deploy út: friss élő előhash0ae18e682194b8d9577440b68ee28e97ee5975415e8a0ab87b4909ba6c6959cf ellenőrzés, backup/CAS/atomic csere/utóhash0444. Ebből a munkalépésből nincs távoli írás.

Rollback: bin/impactshop-guard-rollback.sh --production --apply --release-id=<ACTUAL_RELEASE_ID> --expected-deployed-sha=87bd745dc522035d2aac02d4aa78ec7f4e0964340607395bf09f77be5225cf63; csak az adapter mentett előképét állítsa vissza, profilmag vagy adatreset nélkül. Az előkép visszahozza a korábbi országválasztót, ezért termékregresszióként kezelendő; csak a javítás hibájának helyreállítására. Backup retention max2 nap, más mentés érintetlen.

- Profil desktop/mobil: nincs ország gomb, országpanel vagy annak tab-stopja; nyolc gyorsgomb és Szívügyeim helye megmarad.
- A négy nyelv/deviza választása megőrzi a begépelt mezőket és nyers return kontextust.
- JYSK profilmentés/restore a megfelelő célra tér vissza; PWA nem takarja a dockot.
