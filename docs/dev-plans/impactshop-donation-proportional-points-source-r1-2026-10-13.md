# Impact Shop egyszerűsített pontozás — implementáció és kiadás

Dátum: 2026-10-29. Alap: d953e525; donor: 369e227d, csak célzott újrahasználat.
Állapot: #238/80d6eb7f merge-elt, staging/production telepítve és migrálva, CJ aktív UTC15:37:39-től; rendes posting/locking poll sikeres, új pontesemény0. Két valós normál-UI-hibára szűk handoff/tracking follow-up forráskész és függetlenül review-zott, még nem merge-elt/telepített; élő elfogadás nyitott.

## Elfogadott működés

Az átállás UTC-időpontjától kezdődő vásárlás végleges, igazolt adománya 1 egész HUF = 1 purchase pont. Egyszeri jóváírás és egyszeri teljes refund automatikus; részleges, ismételt, ellentmondó, történeti és ismeretlen eredeti idejű eset kézi. A régi egyenleg és történet megmarad. Nincs gazdasági backfill.

Provider/account/purchase azonosítja az immutable rootot, nem credential-generáció. A hiteles adapter pontos adományt, eredeti UTC-időt és meglévő SAT1-kötést ad. Kattintás, kosárérték, becsült jutalék, pending státusz vagy nyers pseudo nem pontozási authority. Elfogadott affiliate-képzés: tényleges végleges jutalék 50%-a, a véglegessé válás magyar naptári napján elérhető legutóbbi hivatalos MNB-jegyzéssel, a jegyzési egységet megőrizve. Egyetlen egész-HUF half-up kerekítés. A meglévő eseményben rögzül a jutalék, FX, jegyzési nap és policy; újraküldéskor nincs új árfolyam. Egyetlen half-up kerekítés, nincs szintszorzó.

Teljes refundkor actual=min(eredeti purchase pont, aktuális egyenleg); a hiány végleg forgiven. Nem keletkezik későbbi behajtható tartozás. Egy vásárlás a komplex eset után manual marad. A meglévő freeze-filter pozitív awardot held állapotba tesz, refundot nem tilt.

## Fájlok és adatút

`sharity-points.php`: explicit CLI-migráció, két új InnoDB-tábla; nullable UNIQUE subject digest a meglévő user_points táblában és szükséges purchase_reversal enum. Nincs HTTP-autóDDL, teljes historical seed, BIGINT-átépítés. Az ALTER INPLACE/LOCK=NONE, 5 másodperces lock-wait; nincs csendes COPY fallback. Részleges DDL után journal és explicit idempotens folytatás; aktiválás csak schema-verifikáció után.

`sharity-points-ledger-sync.php` + manager: tartós capture → egy GET_LOCK consumer → root/balance/history/event/level egyetlen tranzakció. Az exact event+payload ismétlése ugyanaz a sor; eltérő payload megőrzött konfliktus. Kapcsolatvesztéskor nincs reconnect a tranzakcióban; consumer a lock elvesztése után azonnal megáll, a következő cron olvassa vissza a tartós eredményt.

A két tábla settlements és changes; külön cursor/quarantine/outbox szolgáltatás nincs. Meglévő cronban legfeljebb200 elem. Rootcap100k, positive subject24h1M, runonként külön +/-1M; a run tartós, held esemény restarttal nem kap új keretet. INT-túllépés kézi hold. Meglévő postcommit hookok/bónuszok root-reconciliationnel folytathatók; a gazdasági history dedupe-olt, callback at-least-once, nem exactly-once külső kézbesítési ígéret.

`/admin/adjust`: review_event_id, expected_version, integer points és reason; meglévő manage_sharity_points/manage_options. A subject tárolt kötésből jön. Nulla korrekció megengedett, unbound subjectnek csak nulla. Admin, requested/actual/forgiven, outcome és lezárás atomikus; azonos replay eredeti válasz, eltérő409. Kézi lista: `wp sharity points exceptions list`, `... show <id>`; nincs új UI.

Attribution: owner-granttel hiteles normál handoff a meglévő SAT1 intentet használja és redirect előtt bindol. Pending capture után a root kötése megmarad tokenlejáratkor. CJ SID case-sensitive, nem sanitize-olt token. Dognet totals az opaque tokenből server-owned ngo_refet old fel; nem lesz token nevű NGO. Decay/vacation csak a közös egyenlegírási verseny javítását kapja.

## Provider-készültség és API

- Signed partner: optional, teljes HMAC-body által védett donation_amount_huf decimális string, donation_policy_version, original_purchase_id, original_purchase_at UTC, revision, attribution_token. Refundhoz külön refund_scope=full; partial/hiány kézi. A teljes meglévő validáció és idempotency-konfliktus vizsgálat capture ELŐTT történik; azonos replay ACK csak durable capture után.
- CJ follow-up: a hivatalos DecimalNumber USD mező és lockingDate alapján készül a 50%/MNB snapshot. Locked→closed azonos gazdasági esemény; a linked originalActionId + teljes negatív commission ÉS sale egyezés egyetlen teljes refund, más korrekció kézi. A meglévő 15 perces cron egy posting- és egy locking-date oldalt kér le, tárolt oldalcursorral, így a régi ID státuszváltozása is visszakérdezhető. A CJ saját kapcsolója a régi CLI-sync mellékágára is érvényes. Nincs általános korrekciós elszámoló.
- Dognet: meglévő raw-transactions transport, legfeljebb4 oldalas teljes cutover-window visszaolvasás, nem csak új ID. Missing eredeti idő/finalitás/adományszerződés mellett manual. Scan hiányában nulla kérés. A points-hívás letiltja a meglévő transport401-refresh retryját; 429-re nincs retry.
- CJ pénzügyi képzése jóváhagyott és implementált; szolgáltatói természetes esemény és aktiválás még nincs igazolva. Dognet eredeti idő/összeg/refund contract továbbra hiányos, csak az az adapter marad manual/off. Fixture nem provider acceptance.

## Kapcsolók és átállás

Default-off: `sharity_points_impactshop_producer_enabled`, `sharity_points_impactshop_consumer_enabled`. A legacy consumer default1 marad addig, amíg az előre egyeztetett átálláskor0-ra állítják. `sharity_points_impactshop_cutover_utc`: egyszer rögzített `YYYY-MM-DD HH:MM:SS` UTC. `sharity_points_impactshop_lanes`: readiness után engedélyezett stabil namespace-ek tömbje. `impactshop_cj_points_enabled`: default0, a két CJ-belépőt együtt vezérli; `impactshop_cj_points_scan`: a posting/locking lekérdezések haladása. `impactshop_dognet_points_scan_from`: kizárólag visszakérdezési contract igazolása után; alapból üres. Nincs automatikus lane-enable.

Aktiválás: explicit `wp sharity points migrate --dry-run` csak olvasó preflight, majd valódi migrate karbantartásban; séma/fájlok readback; legacy0, új capture/consumer külön, lane allowlist csak bizonyított contracttal. Signed webhook a karbantartási intervallumra igazolt replay/refetch nélkül nem állítható le veszteségmentesen.

## Éles út és maradó műveleti feltételek

Új fizikai host: we040.tarhely.com, user sharityh; production /home/sharityh/app, staging /home/sharityh/app-staging. Működő hozzáférés Air→mac-primary→meglévő Mini-kulcs, strict hostkey ellenőrzéssel. Régi s59 DNS nem aktuális. A két profil együtt frissül. A Mini meglévő megbízható IP-hostkeyjének azonossága ellenőrzött; FQDN belépést a kiadás előtt külön igazolni kell, nincs StrictHostKeyChecking=no.

Friss read-only metadata: prodWP6.9.4, stagingWP6.8.3, PHP8.4.26, MariaDB10.11.19. Közös DB, production wp_, staging stg_ prefix: NEM független adatbázisok. Backup/restore/SQL mindig pontos prefixű táblalistára; teljes közös DB restore tiltott. A nagy production history miatt nincs blanket ALTER vagy COUNT/scan. Accountkvóta külön ellenőrzendő.

Stagingen a statikus `.htaccess` 503-gate + tényleges process-drain, nyolc stagingtáblás mentés és izolált restore igazolt; 20 exact-file és migráció után az eredeti gate SHA visszaállt. Négy HTTP endpoint és verziózott JS-hash egyezik; minden points kapcsoló0. Ez nem bizonyít production drain/restore-t. Prod DISABLE_WP_CRON=true ellenére külső percenkénti wp-cron és CLI ütemezés is létezik. `.maintenance` önmagában nem elég; meglévő tárhelykarbantartás + pontos writerpause + tényleges drain szükséges. Ez az éles művelet feltétele, nem a helyi forrásmunka blokkja.

## Publikálás és meglévő release

Egy featurePR, célzott staged diff/secret scan/checkek, független review. Kötelező GitHub check: validate, safe-audit, Guard, canonical-source-guard. Merge és szükséges main-integráció rövid quietwindow; nincs mainüldözés. Staging is csak mergedmainből telepíthető, ezért postmerge/defaultoff. A release külön Git-adminisztrációjú klón, detached mergedSHA és saját origin/main; kiadás alatt nincs fetch.

A meglévő `bin/impactshop-guard-deploy.sh` exact-file út fut, fájlonként előzetes remoteSHA/backup/CAS/releaseID/0444 readback. Függőségek előbb, loader utoljára. Nincs új bundle engine vagy szélesrsync. A preflight hívás `PREFLIGHT_NO_RETRY=1`; maintenance alatt csak az előzetesen sikeres HTTP-preflight kihagyása `SKIP_PREFLIGHT=1`, a többi guard megmarad.

Rollback: capture/consumer kikapcsolás, események megőrzése; bizonytalan írás readback. Fájlrestore kizárólag ismert koherens állapot, régi clickreward/consumer nem élesíthető vissza. Éles elfogadott pontok után nincs régi DB-visszatöltés, schema/history/drop vagy automatikus kompenzáció. Részleges fileset karbantartásban marad; szükség esetén célzott előrejavítás.

## Elfogadás és kézi ellenőrzés

Local: exact money; cutoff/unknown/duplicate/conflict; trueSQL atomicity/rollback/lostACK/KILL CONNECTION; concurrent duplicate+refund/decay; adminzero/replay/unbound/role; frozenawardheld; caprunrestart; provider401/429; click0 és NGO/SID folytonosság. Fixturek csak szintetikus adatok.

Live: minden vállalt lane legalább1 természetes award és annak ismétlése; hiteles refund fixture izolált replay és természetes refund ahol van; legalább20 természetes eligible esemény összesen, mindenlane lefedve. Nincs paidbuy. UI: pont/szint/history, CTA0jutalom, redirect, video/JYSK érintett flow. Két hétig heti kivételszám és rendezésiidő Arnoldnak meglévő listából, nincs új monitor/scheduler. Source-ready, PR, merged, deployed, activated, production-accepted külön.

## Megőrzendő hivatkozás

A Substack-anyag nem végrehajtandó mellékfeladat. Következő handover őrizze: /Users/bujdosoarnold/ai-agent/SUBSTACK-HANDOVER.md, SUBSTACK-NOVEKEDESI-ES-SZERKESZTOI-KEZIKONYV.md és /Users/bujdosoarnold/blog-ai-tukor/drafts/STILUSJEGYZET-user.md. Betöltést csak tényleges olvasás után állítunk.

## Elfogadott MNB/CJ follow-up — 2026-10-29

Elsődleges források: [CJ schema](https://docs.cj.com/docs/pub-commissiondetail-parameters), [CJ scalar/correction contract](https://docs.cj.com/docs/pub-commissiondetail-api), [MNB saját HTTPS űrlap](https://www.mnb.hu/arfolyam-lekerdezes/). A dokumentált SOAP HTTPS POST két környezetben404; diagnózis után a működő natív MNB HTTPS GET tábla lett az egyetlen adatút. Nincs HTTP/fixárfolyam/alternatívprovider fallback. Hibás vagy elérhetetlen jegyzésnél CJ-cursor nem lép tovább; nincs vak ismétlés ugyanabban a futásban.

Tényleges follow-up ellenőrzés: új MNB és meglévő money/provider/state/Dognettransport fixturek sikeresek; saját MariaDB + az éles wpdb pontos forrása:82 SQL-check sikeres, benne a már beérkezett komplex korrekcióval megelőzött queued award. A candidate money-kód a we040 tényleges PHP8.3 környezetéből hiteles MNB HTTPS GET-tel329.93 HUF/USD jegyzést és10USD jutalékból1650pontot adott; ez kizárólag írásmentes számításpróba, nem telepítés vagy pontjóváírás. Független review egy CJ kill-switch P2-t talált; minimális javítás után a valódi régi syncet is futtató fixture független recheckje sikeres.

A már telepített f221 staging és a follow-up forrás külön állapot. Production, aktiválás és természetes élő elfogadás továbbra nyitott.

## 2026-10-29 — közös éles pontport megőrzése

A #237 `b9e24fb7` merge-elt; mind a 20 runtime fájl stagingen telepítve,
PHP8.3 lint és négy HTTP smoke sikeres, maintenance eltávolítva, új és régi
pontfeldolgozás kikapcsolva. Production nem módosult, aktiválás nincs.

A production három közös modulja (`sharity-points-manager.php`,
`sharity-level-manager.php`, `sharity-decay-manager.php`) byte-azonos az
`office-hue/ai-agent@79aa1e7d890d9b1dd47458db898f95c8421847e8/server-patches/`
kanonikus forrásával. A Notes ezeket változtatás nélkül követi: az éles
kiadásnál nincs szükség cseréjükre, csak az azonosság visszaolvasására.
Az Impact Shop már meglévő tranzakciós műveletei önálló
`Sharity_Impactshop_Points_Manager` osztályba kerülnek; a shared Offerwall-port
és a decay szabálya nem módosul. Az explicit level `success=false` rollbacket
vált ki. Az első vásárlás/shop/referral egyszeri jogosultságát a saját
reconciliation célpont+típus+összeg+üzleti dedupe alapján ismeri fel; az új
vásárlási root metaadata nem jelent új bónuszt. Részleges referral másik rootból
befejezhető. A közös port szigorú tartalom-deduplikációja megmarad.

Ellenőrzés: az éles wpdb pontos forrásával és saját MariaDB10.11.19-cel
118 valódi SQL-check sikeres; új esetek: két root/same shop, replay, hibás
bónuszösszeg, részleges referral, triggeres level-hiba rollback, JYSK
award/replay/conflict, valódi COMMIT utáni elveszett ACK tartós readbackje,
decay napi dedupe és pontpadló. A SQL fixture időfüggvénye most helyes
integer timestampet ad: a korábbi refund/decay tesztben a decay nem futott,
ezért annak korábbi eredménye nem bizonyított tényleges átfedő decayt.
A mostani teszt a tényleges levonást is ellenőrzi. A régi canonical wpdb-fake
belépő ezt a valódi SQL-suite-ot futtatja, hiányzó sandbox explicit hiba.
Vacation-fixture, admin/attribution/state/provider, 200 JYSK bridge és
19 eredményprotokoll ellenőrzés sikeres. A régi teljes JYSK SQL-suite
wpdb-query-hookos hibainjektálása nem igazolja a native portot; arra nem
állítunk friss PASS-t, a játék/eredmény/nyeremény tesztjeit megőrizzük.
Független delta-review referral P2 javítva és visszaellenőrizve.

Nincs új séma, queue, ütemező, release-rendszer vagy gazdasági szabály.
Új fájl kizárólag a már meglévő üzleti kód kiemelése a konkrét éles ütközés
elkerülésére; bekerül a meglévő Bástya inventory/hashlistába.
Rollback: Impact Shop lane off, események megőrzése, guarded exact-file
backup/CAS; a közös Offerwall-fájlok éles felülírása nem része a kiadásnak.
Production writer-pause/mentés, partner replay és Dognet-contract külön
ellenőrzendő a szükséges lépésnél. Új forrás még nem merge-elt/telepített.


## 2026-10-29 — Dognet normál jóváírás forráskész

Három runtime-fájl; channel+campaign:order_id vásárlás, rawid esemény;
jóváhagyott SAT1 alsó időkorlát, EUR jutalék50% × MNB, egyszeri kerekítés.
Négyoldalas folytatható poll, négy új FX-nap, meglévő consumer/schema.
Provider/transport/money/MNB fixturek, PHP-lintek és139 saját SQL-check sikeres.
Független review javításai célzottan visszaellenőrizve. Merge/deploy/aktiválás
még nyitott; Dognet korábbi OFF állapota ebben a körben nem változott.
Részletes működés/teszt/rollback/UI: `docs/protected-change-records/2026-10-29-impactshop-simple-points.md`;
végrehajtási terv: `docs/dev-plans/impactshop-dognet-confirmed-purchases-2026-10-29.md`.
