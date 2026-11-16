# CJ explicit fiók/NGO megerősítés – 2026-11-16

## Felhatalmazás és határ
Arnold jóváhagyta az Impactshop R3 terv R2 CJ explicit fiók/NGO implementációját. Saját `impactshop-notes-cj-owner-handoff-20261116`, induló5411f85c, integráció a már merge-elt binding0ba3078d alapra. Jóváhagyott legacy touch kizárólag `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`. Érintett teszt: affiliate, purchase SQL, pontos régi GET fixture. Gazdasági formula, ledger, pont, retention, új provider és cron nincs módosítva. Hash-inventory csak e review-zott runtime egy digestjét és külső checksumját követi; sem guard, sem idegen hash nem változik.

## Koherencia és tényleges működés
Upstream az autentikált web BFF és a meglévő owner-grant session; explicit ngoId/merchantInput/confirmationId/authoritySnapshotId. Downstream a változatlan intent/correláció és első gazdasági binding. A Dognet megerősítés meglévő session/NGO lockját és tartós UUID-idempotenciáját újrahasználó, zárt CJ ág készül. A szerver oldja fel a konfigurált publisher:website scope-ot. Szigorú régi CJ payload külön ág; explicit megerősített bemenet nem eshet vissza fix NGO-ra.

Az új `impactshop_sharity_affiliate_cj_purchase_review` opció csak operator által szolgáltatott `publisherId,websiteId,advertiserId,linkId,merchantUrl,reviewExpiresAt` stringeket fogad. A publisher/site az aktuális meglévő CJ-konfigurációhoz, a többi mező a meglévő exact kanárihoz kötött; hiányzó/hibás/lejárt review OFF. Új dátumot, opciót vagy élő envet nem állítottunk be. A web a `shopping-confirmed-v1` válaszjelölőt és exact megbízható-origin opaque URL-t kéri; régi WP-válasz zárva marad.

## Konkrét kockázat és review-javítás
Régi és új verzió együtt élhet. A független reviewer reprodukálta: az eredetileg közös ready_to_redirect állapotot a régi GET review-lejárat után is303-mal elvitte, az új GET helyesen409-cel tiltott. Javítás: `confirmed_cj_ready` külön állapot (VARCHAR24-en belül), exact confirmed-forrás/állapot pár, UPDATE CAS mindkettőre. A régi GET nem fogadja el; a változatlan időkorlátos cleanup az új állapotot is kezeli. A befagyasztott base5411 GET fixture csak függvényátnevezésben tér el az eredetitől. A reviewer a javítást, a negatív régi-GET esetet és a valódi SQL-t újraellenőrizte: nincs nyitott érdemi lelet.

## Ellenőrzés
- Web route/form/activation-policy64/64 és teljes web TypeScript noEmit PASS; a webforrás másik saját worktree-ben van.
- PHP legacy affiliate, megerősített Dognet és új CJ fixture PASS, PHP lint és diff-check PASS.
- Saját eldobható MariaDB10.11.19 + a tényleges target hash-ellenőrzött wpdb: végleges purchase SQL75 PASS, függetlenül újrafuttatva. Owner/NGO/review/target/duplicate/scope, két session azonos UUID-val, lejárt és egyszeri committed opaqueGET, elveszett COMMIT-ACK, régiGET tiltás és cleanup.
- Valódi program/account/link review, WordPress/staging/production kiadás, UI és természetes konverzió nincs ebben az eredményben. A meglévő binding40 SQL és R1 bizonyíték újrahasznált, nem új szolgáltatói PASS.

## Telepítés és rollback
Meglévő exact-file backup/CAS/atomikus csere/0444 visszazárás; WP binding séma/readiness előbb, CJ runtime új funkció OFF. Egy összehangolt webcandidate majd exact aliasváltás csak saját közös ablakban, ellenőrzött READY artifactból. Vegyes verzióban az új megerősített ág zárva marad. Éles CJ csak friss valós publisher/site/program/cél és explicit review-konfiguráció után; lejárt dátum átírása nem review. Ez a PR nem kapcsolja be.

Rollback csak saját változatlan fájl/alias utóállapotból a meglévő eljárással. Előbb új CJ OFF; kiadott confirmed_cj_ready intent a régi kódban elutasított, nem kerülőút. Az additív binding oszlopok és gazdasági adatok megmaradnak. Nincs root/pont visszatöltés vagy globális flush.

## Kézi UI és protected scope
Kiadás után: Dognet kiválasztott NGO és consent tovább működik; CJ lejárt/hiányzó review esetén nem indul; más account új belépést kér; kiválasztott NGO retry után is azonos; duplakattintás nem ad második intentet; CJ opaqueGET egyszeri és review-lejárat után tiltott. Tünet: rossz NGO/profil, más domain, ismételt redirect vagy duplapont. UI most nem futott.

Protected files touched: runtime PHP, `docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`. Smoke scope (érintettség, nem live PASS): `route:factlens-vb-prod`, `route:impact-challenge`, `route:profil`, `flow:message-popup`, `flow:points-jump`, `flow:legacy-pool-visibility`, `flow:profile-return-account`, `flow:profile-return-restore`, `route:impactshop`, `flow:saved-offers-open`, `flow:go-deal`, `browser:webkit`, `browser:chrome`, `deploy:guard-preflight`, `deploy:checksum-verify`.
