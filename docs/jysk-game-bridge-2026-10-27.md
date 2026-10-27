# JYSK game WordPress bridge — elkülönített forrás

## Publikált forráscheckpoint — 2026-10-27

Az alábbi korábbi helyi állapot történeti. A forrás PR #227-ben publikált:
`26365a9b759524baf89f8cf1d3e0207bd538c124`, rögzített main-alap
`68a6c3241c8be74ff9998d57ca0a3dac24c83904`;
tree `69432e0ad4de6423e831086d7310bae255b63d05`.
Arnold külön jóváhagyásával az egyetlen saját commit erre az alapra került;
a végső tartalom byte-azonos a korábbi helyi, feloldott integrációval.
Mentési ref: `backup/jysk-pre-replay-20261027-829cc9c5`.

A közvetlen, exact main→HEAD audit ténylegesen sikeres: 17 fájl titokscan,
diff-check, protected-touch és commit-lane. A változatlan PHP-fára a korábbi
178 bridge / 124 canonical-manager assertion és 14 privátprofil-szcenárió
bizonyítékát használjuk újra. Valódi MySQL staging és éles acceptance nincs.

A történetfrissítéshez külön jóváhagyott native recovery tartozott:
egyetlen, pontos régi remote SHA-val védett force-with-lease; a non-fast-forwardot
nem támogató opcionális wrapper egyparancsos hooksPath felülbírálással nem
futott. Tartós hook/config/guard módosítás nincs, wrapper-PASS nincs; a valódi
termékguardok külön, változatlanul lefutottak. Remote OID readback sikeres.

A PR-es safe-audit sikeres. A push-es run40539571569 exit2 oka: a CI a
történetátírás előtti `ddd14318...` commitot nem találta (`rev-parse --verify`),
nem tartalom- vagy PHP-hiba. Nincs vak rerun vagy workflow-módosítás: a jelen
valódi DOCSYNC-státuszfrissítés normál fast-forward pushja már elérhető
elődcommitot ad. Új checkek eredményét külön kell visszaolvasni.

Az app PR #1768 merge-commitja `e9a6f92cfe3001f7e99cd8cd0a1bc6bb8f4e534f`.
A WP PR main-merge-je, WP/app-telepítés és pontaktiválás még nem történt meg.
A TLS-javítás a Card-szálban marad; ebben a szálban nincs infrastruktúraírás.
A jóváhagyott main-ablak a műveletek/readback után felszabadult.

Állapot: helyi, nem commitolt forrás; nincs push, merge, telepítés, aktiválás
vagy éles elfogadás. Saját worktree `impactshop-notes-jysk-game-bridge-20261027`,
ág `feat/jysk-game-bridge-20261027`, alap `73beb6f4598acdca22e7910d39a0088cd5f38b14`.
Az új modul `wp-content/mu-plugins/impactshop-jysk-game.php`; meglévő védett
identity-, vote-, Offerwall- és guide-fájl változtatása nincs. A később igazolt
live parity alapján külön jóváhagyott minimális pseudo writer/level tranzakciós
javítás is készült; lásd a kapcsolódó protected change recordot.

## Koherencia, kockázat, érintett funkciók

A jóváhagyott additív bridge kizárólag a `jysk-game/v1` REST névteret használja.
A böngészős handoff valódi `impactshop_identity_owner_authorized(pseudo)`
tulajdonosi ellenőrzést végez. A szolgáltatás az opaque subjectet a saját,
WordPressben maradó pseudo/grant leképezésen keresztül oldja fel. A grantet
minden munkamenet-, üzenet- és jutalomkérésnél újra ellenőrzi az eredeti owner
grant táblában. A saját revoke nem von vissza más termék owner grantjét.

Az új állapottáblák: `jysk_game_grants`, `codes`, `sessions`, `nonces`,
`rewards`, `message_map` a WordPress prefixszel. Létrehozás kizárólag explicit
operátori WP-CLI-hívással: `Impactshop_Jysk_Game::install()`; böngészős vagy
REST-kérés nem készít sémát. Új cron vagy watchdog nincs. A séma InnoDB.
A bearer kódot/munkamenetet és a kérésnonce-t csak SHA-256 hash tárolja.
A külön subject-kulcs és service-kulcs eltérése kötelező.

Fő kockázatok: hibás identity-bizalom, replay, árazás/schedule manipuláció,
párhuzamos jóváírás, kapcsolatvesztés, bizonytalan COMMIT, hamis nyugta,
meglévő üzenetek téves publikálása. A bridge fix 5/10 pontos policyt, nap/slot
ellenőrzést, gazdasági dedupe kulcsot, tartós előzetes claimet, kapcsolathoz
kötött eseménylockot és explicit üzenetleképezést használ. A shared manager
saját tranzakciója előtt a saját claim COMMIT-je befejeződik. Kétértelmű
korábbi managerhívás nem indul újra automatikusan.

Érintett funkciók: JYSK-profilkapcsolás és visszavonás, quiz-/videójutalom,
juttatás státusza, külön JYSK-profilüzenet és első láthatósági ACK. A meglévő
`read_at` nem kézbesítési bizonyíték; csak a saját `delivered_at` az. Az ACK
nem írja át a legacy olvasottsági állapotot. Üzenetpublikáló API nincs:
operátor írhat explicit meglévő targeted üzenet/pseudo + saját subject mappinget.

## Wire protokoll

A browser GET `/wp-json/jysk-game/v1/handoff` paraméterei:
`client_id=jysk_2026`, `redirect_uri=https://jysk.sharity.hu/api/jysk/game/session/callback`,
`state` (43–128 base64url karakter), `code_challenge` (43 base64url),
`code_challenge_method=S256`. A fix callback 302 választ kap `code` (64 hex)
és eredeti `state` paraméterrel. A code 120 másodperces és egyszer használható.
Új handoff generációváltása érvényteleníti az előző munkameneteket/kódokat.

A többi endpoint POST és service-HMAC köteles:

| Útvonal | Bemenet | Válasz |
| --- | --- | --- |
| `/exchange` | `clientId,redirectUri,code,codeVerifier` | `subject,sessionToken,expiresAt,earningAllowed` |
| `/context` | `subject,sessionToken` | ugyanaz az abszolút lejáratú context |
| `/revoke` | `subject,sessionToken` | `revoked:true` |
| `/award` | teljes eredeti jutalompayload | `state,eventId,payloadHash,points` |
| `/reward-status` | ugyanaz a teljes eredeti jutalompayload | ugyanaz a státuszséma |
| `/messages` | `subject,sessionToken` | `messages:[{id,title,body,type}]` |
| `/messages/delivered` | `subject,sessionToken,messageId` | `delivered:true,messageId` |

A subject és sessionToken 64 kisbetűs hex karakter; session 900 másodperc,
nem csúszó lejárat. A grant visszavonása minden contextet lezár. A korábban
elfogadott outbox jutalom sessionToken nélkül, még aktív grant mellett
sessionlejárat után is feldolgozható.

Fejlécek: `x-jysk-timestamp` (Unix másodperc, legfeljebb ±60 s),
`x-jysk-nonce` (32 kisbetűs hex), `x-jysk-signature` (64 kisbetűs hex).
A kulcs `hex2bin(JYSK_GAME_SERVICE_KEY)`. Az aláírt bemenet pontosan:

```text
timestamp\nnonce\nPOST\n/wp-json/jysk-game/v1/<path>\nsha256(rawBody)
```

A válasz signature ugyanazzal a kulccsal:
`response\nrequestNonce\nHTTPstatus\nsha256(rawResponseBytes)`. Az aláírt és
kiküldött JSON bájtok azonosak; minden bridge-válasz `no-store, private`.
Maximális request/response 65536 bájt. Hibás HMAC elutasításához nincs
adatbázisírás; hitelesített replay/storage hibák is aláírtak. Titkot nem naplóz.

A jutalompayload mezősorrendje a hashhez:
`eventId,subject,kind,dayIndex,slot,ngoId,points,occurredAt,policyHash,producerSha,isTest`.
Pontosan ezek a mezők engedettek, típusuk szigorú. A request kulcssorrendje
lehet más; a hash a fenti sorrendben képzett, unescaped JSON SHA-256 hash-e.
A `kind` `quiz_answered` (5 pont, slot 0–4) vagy `video_completed` (10 pont,
slot 0). `dayIndex` 0–20, a Budapest szerinti naptári naphoz kötve.
Időablak: 2026-11-09 12:00 Budapesttől 2026-11-30 00:00 Budapestig,
félig nyílt; tehát november 29. a zárónap. `occurredAt` UTC ISO milliszekundum
(`...000Z`), jövőbeli esemény legfeljebb 60 s toleranciával.
Új jóváírásnál a tényleges szerveróra is legalább a kezdő időpont kell legyen;
a 60 s occurrence-tolerancia nem ad korai írási jogot. A lezárás után már
elfogadott outbox továbbra is üríthető. A 105 quiz-ID
alkalmazásoldali kötése `jysk-(dayIndex+1+slot*21)`.

Az `eventId` a `jysk_2026:<subject>:<kind>:<dayIndex>:<slot>` UTF-8 string
SHA-256 hash-e. A globális dedupe `jysk_2026:<eventId>`; producer SHA vagy
policy hash nem kerül a gazdasági kulcsba. Policy/producer/NGO eltérés csak
explicit konfigurációval fogadható el, kliens nem szabhat árat. `isTest=true`
csak az operátor által előre besorolt, legfeljebb egyetlen exact
`JYSK_GAME_TEST_SUBJECTS` subject számára adhat a kampány kezdete után valódi
globális pontot. Üres/default allowlist tiltja. Nincs korai bypass. A hat
napi aktivitás legfeljebb 35 valódi pontot adhat a teljes kampányra,
`is_test=true` metadatával; következő napon sincs új keret. A technical
subject minden eseménye ugyanazon subject-derived GET_LOCK alá kerül;
kanonikus JYSK ledger pozitív pontösszeg + új pont legfeljebb 35 lehet.
SQL-olvasási hiba fail-closed; korábbi nyugtát a limit után is visszaolvas.
NGO-rangsorból és
sorsolásból az alkalmazás zárja ki. A besorolt subject nem küldhet isTest=false-t.

Státuszok: `confirmed`, `absent`, `busy`, `unknown`, `policy_blocked`,
`conflict`. `absent` csak igazolt globális nyugtahiány esetén adható.
Már létező claim teljes eredeti payloadeltérése `conflict`. Accepted claim
nyugta nélkül kezdetben `unknown`. Új POST előtt a státuszolvasó ugyanazon
writer kapcsolaton, GET_LOCK alatt és ellenőrzött tranzakciós séma mellett
igazolhatja az absence-t; a saját claim `absent` lesz. Csak ezután indulhat
ugyanazon immutable kulcs/body újraküldése, újabb tartós claim COMMIT után.
SQL-hiba vagy aktív lock esetén ez nem megengedett. A `confirmed`
globális nyugta pseudo/pont/type/source/metadata hash egyezést és előzőleg
ellenőrzött tranzakciós készültséget kíván.

## Konfiguráció és készültségi korlát

Alapból minden route zárt: `JYSK_GAME_ENABLED` csak boolean `true` nyitja.
Éles saját konfiguráció: `/home/sharityh/jysk-game/config.json`, kizárólag
ennek az új modulnak. Könyvtár `0700`, fájl `0600`, azonos owner, symlink
tiltott, méret legfeljebb 16 KiB. Hiányzó/hibás profil zárt alapállapotot ad,
nem WordPress fatalt. Exact engedélyezett uppercase JSON kulcsok: `ENABLED`,
`SERVICE_KEY`, `SUBJECT_KEY`, `REWARDS_ENABLED`, `TRANSACTION_READY`,
`POLICY_HASH`, `PRODUCER_SHAS`, `NGO_IDS`, `TEST_SUBJECTS`. Típusellenőrzés
kötelező, ismeretlen kulcs tiltott. Konstansok fixture-kompatibilitásként
megmaradnak. Nincs shared wp-config írás vagy másik profil/titok másolás.
Titokmentes operátori CLI diagnosztika: `Impactshop_Jysk_Game::configurationStatus()`.
Secret nélkül nem használható: `JYSK_GAME_SERVICE_KEY`, `JYSK_GAME_SUBJECT_KEY`
két eltérő, 64 kisbetűs hex kulcs, konfigurációból; nincs repo-secret.
Jutalomhoz külön `JYSK_GAME_REWARDS_ENABLED=true` és
`JYSK_GAME_TRANSACTION_READY=true` kell; mindkettő alapból false.
További explicit értékek: `JYSK_GAME_POLICY_HASH` (SHA-256),
`JYSK_GAME_PRODUCER_SHAS` (review-zott SHA-1 commitok listája, a függő
nyugtákhoz szükséges korábbi producerekkel), `JYSK_GAME_NGO_IDS` (pontos
jóváhagyott string allowlist). Üres allowlist nem nyit jutalmat.

Az eredeti live/base `Sharity_Points_Manager::award_points_for_pseudo()`
figyelmen kívül hagy SQL-false és COMMIT hibákat, a level-manager is ír.
A tényleges live/base parityt a Mini saját S59-kulcsával, a jelenlegi we040
hoston és a megőrzött szigorú hostkey-alias ellenőrzéssel igazoltuk. Ezután
minimális helyi pseudo writer/level SQL-/COMMIT-hibakezelési javítás készült:
`docs/protected-change-records/2026-10-27-jysk-canonical-points-transaction.md`.
Mindkét eredeti live hash ott szerepel. Távoli módosítás nem történt.
A readiness **nem igazolt**, értéke false marad: a helyi javítás nem staging proof.

Élesítés előtt kell: a pontos javított manager/level-manager kiadási parity; minden
érintett SQL-, level-write-, COMMIT-hiba és connection-loss fail-closed
kezelése a kanonikus writerben; saját és globális táblák InnoDB/schema
ellenőrzése; valódi DB-párhuzamossági és fault teszt; független security review;
titok/producer/policy/NGO-konfiguráció és operatori kiadási felhatalmazás.
Konfigurációs boolean nem helyettesíti ezt a bizonyítékot. Futáskor ezenfelül
exact alap wpdb osztály, DB-dropin hiánya, `@@read_only=0`, a négy jutalomhoz
érintett tábla InnoDB engine-je és a teljes gazdasági dedupe/pseudo/claim
unique indexei ellenőrzöttek: own claim `event_id`, user_points `pseudo_id`,
kanonikus point_transactions `(pseudo_id,dedupe_key)` összetett index,
teljes oszlopokkal. Routeres DB-dropin külön igazolt
writer adapter nélkül fail-closed. Ez nem valódi staging működéspróba.

Élő, kizárólag SELECT/SHOW readback 2026-10-27: `@@read_only=0`, három
globális tábla InnoDB, `uniq_dedupe_pseudo(pseudo_id,dedupe_key)` és
`uniq_pseudo(pseudo_id)` teljes unique index; `wp-content/db.php` absent.
Saját bridge-tábla még nincs telepítve. Egy kezdeti metadata SELECT szintaktikai
hibát adott, a javított pontos lekérdezés sikeres; adatírás és staging fault-
vagy párhuzamossági teszt nem történt.

A kritikus szakasz végig `reconnect_retries=0`, GET_LOCK alatt fut.
CONNECTION_ID és IS_USED_LOCK ellenőrzés van minden veszélyes határnál;
finally visszaállítja a korábbi retry értéket release-hiba esetén is.
WordPress wpdb magic getter/setter támogatását tényleges célkörnyezeten
ellenőrizni kell; nincs reflection vagy alternatív DB writer.

## Ellenőrzés, backup és rollback

Futtatva: `php -l wp-content/mu-plugins/impactshop-jysk-game.php`,
`php tests/jysk-game-bridge.test.php` (178 determinisztikus assertion),
`php tests/jysk-canonical-points-failures.test.php` (124 tényleges manager
fixture assertion), mindhárom érintett production PHP lint és diff-check.
`php tests/jysk-game-private-config.test.php`: 14 virtuális privát-profil
pozitív/negatív szcenárió; valós hostfájl létrehozása nélkül.
Nyilvános, szintetikus kereszt-nyelvi request/response fixture:
`tests/fixtures/jysk-game-protocol.json` (pontos JSON bytes és HMAC).
A fixture PHP órát névtérrel helyettesít, a production forrást használja;
nincs hálózat vagy valódi adatbázis. Bizonyítja a claim COMMIT előtti/utáni
hibák, manager-failure, nonce, payload, lock, reconnect, owner, PKCE,
session-generation/revoke és message-mapping határokat. Nem bizonyít MySQL
atomicitást, valódi WP-hookrendet, production parityt vagy böngészős működést.

A source rollback az új modul és teszt saját diffjének visszavonása.
Deploy csak merge-elt exact source-ból, guardolt pontos fájllal, előzetes
absent/file readback és távoli backup/CAS/rollback mellett. Aktiválás előtt
adatbázisbackup és restore-próba kell. Runtime rollback először mindkét
játék/reward flag lezárása; a saját claims/sessions/messages táblák maradnak
egyeztetéshez. Pontjóváírás automatikus visszatörlése vagy újrajátszása tiltott.
Eltávolítás nem töröl shared pontot, usert vagy meglévő üzenetet.

Kézi UI checklist az engedélyezett staging/éles kiadás után:

- Tulajdonosként kapcsolás a JYSK felületről és fix callback; idegen/raw-cookie
  profil elutasítás; rossz callback/state/PKCE elutasítás.
- Öt napi quiz és egy videó 5/10 ponttal; ismételt kattintás, refresh és párhuzamos
  ablak ne duplázzon; kliensárazás és másik nap/slot ne adjon pontot.
- Sessionlejárat/revoke után a context zárjon; már accepted outbox státusza
  legyen őszinte, bizonytalan esetben ne jelenjen meg sikeres pont.
- Csak explicit JYSK mappingű saját üzenet jelenjen meg, láthatósági ACK után
  tűnjön el; más profil és legacy olvasottság ne kapjon mellékhatást.
- Kontroll: meglévő profil/identity, Ads Watch, Offerwall, szavazás és
  `/jysk-riport/` route/print/JSON maradjon a kanonikus baseline szerint.
