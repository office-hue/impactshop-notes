# Profil UI integráció a jelenlegi profilmaghoz — 2026-11-09

Felhasználói felhatalmazás: a kész profil-UI közös integrációja és meglévő kiadási útja; ebben a munkalépésben forrás, teszt és dokumentáció, commit/push/merge/deploy nélkül.
Saját fogadó branch: feat/jysk-profile-ui-integration-20261109; rögzített alap/HEAD: 580876b11e7a9880084a15c8f09c8c4215e42e04. Donor kizárólag olvasva: impactshop-sharity-profile-ui-20261109, alap dc2c031d3822c7c7c7187b52e8c42c69d509d258. Csak három új fájl átvétele, teljes notes/status másolás nélkül. A kész UI nem lett újragenerálva; nincs új fizetős Hermes-kör.

## Koherencia, érintett funkciók és kockázat

Protected files touched: wp-content/mu-plugins/impactshop-profile-ui.php; docs/impactshop-guard-config.json és .sha256; docs/impactshop-guard-hashes.json és .sha256; docs/impactshop-protected-files.json; docs/bastion-guard-status.md. Smoke scope: pwa_shell, mobile_shell_runtime, route:profil, meglévő native owner save/restore/return.

Additív MU plugin, wp_footer prioritás30, pontos app.sharity.hu /profil és /profil/ frontend. In-flow nyelv/deviza/ország, harmadik Szívügyeim gyorsgomb, profilra szűkített PWA geometria. A natív formok, node-ok/listenerek és managed actionbar vezérlő maradnak. UI-váltásból nincs navigáció vagy POST; nyers return/prctx/opaque query/hash/history.state megmarad. A deviza továbbra csak tájékoztató.

A fogadó #261–263 utáni identity PHP/JS/intl overlay, actionbar és PWA byte-jai érintetlenek. Nincs új REST endpoint, profil/pont/reward/ledger/affiliate/NGO-választási writer, cron vagy adatbázisséma. Közvetlen kockázat: selector, fókusz, rejtett elem, fordítás vagy mobil takarás; közvetett: nyers visszatérés sérülése és ismételt kérés. Az új fájl exact-path owner/hash bejegyzést kap, a meglévő pwa_shell/mobile_shell_runtime csoportban; csak a szükséges config/checksum digest változik. Guardkód és CI workflow nem módosul.

## Célzott ellenőrzés

A három donor fájl új tesztje a fogadó valódi PHP shortcode-ját és JS-ét használja. A fixture-ben a már meglévő site_url stub duplikációját elhagytuk. Két actionbar: a rögzített 7d64aca5 legacy fixture (SHA f05af5bf0c6112e78bc701637217e8ad3ffabba3ac4843f955856146e15fabfd) és a saját managed verzió. Minden kérés helyben elfogva; unreachable proxy és blokkolt service worker; szintetikus profil, élő profil/pont nincs.

Az új tesztek a régi üres generate mock helyett valós v2/string markerrel futnak: külön confirm/cancel generálás, PHP generálási/reveal/mentési függvények, egyszeri reveal, natív autocomplete form/egyetlen POST és PHP303 nyers visszatérés. A PHP döntését friss helyi GET-ként játsszuk vissza, mert a Playwright nem minden HTTP redirect-hopot route-ol újra; ez nem a teljes éles HTTP lánc bizonyítéka. PasswordCredential/store jelen van a fixture-ben, az üres mentés és UI nem hívja; böngészős jelszókezelő prompt-elfogadást nem állítunk.

A pontos JYSK-link csak az engedett minimális JYSK-kontextusban látható; a prctx/opaque kontextus külön megőrzési teszt. Restore siker/elutasítás, egyszeri selection-intent, nincs további POST/navigáció, 4 nyelv/deviza, country fókusz, mobil320/390, PWA/consent/standalone és history. A kezdeti sandbox Chrome-indítás process-hozzáférés miatt meghiúsult; a támogatott szűk emelt tesztfuttatás elindult. Első valódi futás19/21: a két túl tág JYSK-link tesztelvárás javítva a meglévő szigorú guardhoz; termékkód nem változott. A végső eredmény a záró bejegyzésben.

## Végső helyi eredmény

Végső helyi eredmény:21/21 UI-eset sikeres (37.173s), PHP plugin/fixture lint és profile-code-save szerződésteszt sikeres. Exact owner/hash/csoport/checksum és összes idegen digest/core byte-megőrzés igazolt; protected-touch és deploy-wpcontent-map-bastion fixture sikeres, diff-check tiszta. Commit/push/CI/deploy és éles elfogadás még nincs.

Az első javítás után19/21 maradt: a nyers kontextushoz tartozó link nem rejtett node, hanem szándékosan hiányzik; az ott maradt href-assert ezért timeoutolt. A második célzott javítás csak a tesztet helyezte a pontos JYSK-fixture-be. Először2/2 célzott eset, majd a végleges21/21 teljes UI-suite sikeres. Nincs termékkód-javítás e teszteltérés miatt.

Paritás: a protected modell és local checker ugyanazt az egy új exact pathot/csoportot használja; meglévő required tagek és guard/CI kód byte-változatlan. A GitHub CI-ben jelenleg szereplő deploy-wpcontent-map-bastion fixture helyben sikeres; ez nem friss hosted CI-PASS, az a publikálási lépéshez tartozik.

Reprodukció (meglévő függőségek, install/build nélkül):

```sh
USER_CARD_TEST_DEPENDENCIES_ROOT=/Users/bujdosoarnold/Developer/GitHub/.worktrees/ai-agent-feat-jysk-ngo-recruitment-continuation-20261025/apps/jysk-recruitment PROFILE_UI_LEGACY_ACTION_BAR=/private/tmp/profile-ui-legacy-action-bar.php /Users/bujdosoarnold/.local/share/fnm/node-versions/v22.23.2/installation/bin/node --test tests/impactshop-profile-ui.test.mjs
php tests/impactshop-profile-code-save.test.php
php -l wp-content/mu-plugins/impactshop-profile-ui.php
php -l tests/fixtures/impactshop-profile-ui-render.php
bash tests/deploy-wpcontent-map-bastion.test.sh
git diff --check
```

## Kiadás és rollback

Forráskészség nem merge/deploy/aktiválás/éles elfogadás. A szülő munkamenet viszi tovább a publikálást, független review-t és a meglévő guarded exact-file release-t. Nem frissítjük mellékesen az élő actionbart vagy profilmagot. Telepítés előtt friss célfájlhiány/hash, backupmanifest és CAS; atomi új plugin telepítés, utóhash/0444 és friss marker readback (body.impactshop-profile-ui, #impactshop-profile-strip).

Rollback: bin/impactshop-guard-rollback.sh --production --apply --release-id=<ACTUAL_RELEASE_ID> --expected-deployed-sha=<ACTUAL_DEPLOYED_SHA>. Eredeti hiánynál csak az új plugin hash-egyezéses eltávolítása; meglétnél saját mentett preimage. Profilmag resetje vagy adat-visszaállítás nincs. Ellenőrizendő a marker eltűnése és a megőrzött core hash. Backup retention max2 nap, UI-review után, más mentéshez nem nyúlunk.

## Kézi UI checklist

- app.sharity.hu/profil desktop/mobil: eredeti színek,8 gyorsgomb, Szívügyeim harmadik.
- Nyelv/deviza ismételt váltásakor a megkezdett profilmezők és return megmaradnak; ország close/Tab/Escape fókusz helyes.
- PWA install/akadálymentesítés/összecsukás/consent/standalone működik és nem takarja a dockot.
- JYSK profil → mentés vagy restore → helyes játék-visszatérés; hiba esetén azonnali saját plugin rollback. Élő jelszókezelő/owner és pontelfogadás külön, itt nem történt.
