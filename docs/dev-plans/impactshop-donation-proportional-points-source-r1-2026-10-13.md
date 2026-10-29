# Impact Shop egyszerűsített pontozás — implementáció és kiadás

Dátum: 2026-10-29. Alap: d953e525; donor: 369e227d, csak célzott újrahasználat.
Állapot: implementált helyi candidate, célzott ellenőrzés/review folyamatban; nem merged/deployed/activated.

## Elfogadott működés

Az átállás UTC-időpontjától kezdődő vásárlás végleges, igazolt adománya 1 egész HUF = 1 purchase pont. Egyszeri jóváírás és egyszeri teljes refund automatikus; részleges, ismételt, ellentmondó, történeti és ismeretlen eredeti idejű eset kézi. A régi egyenleg és történet megmarad. Nincs gazdasági backfill.

Provider/account/purchase azonosítja az immutable rootot, nem credential-generáció. A hiteles adapter pontos adományt, eredeti UTC-időt és meglévő SAT1-kötést ad. Kattintás, jutalék, kosárérték, pending státusz vagy nyers pseudo nem pontozási authority. Egyetlen half-up kerekítés, nincs szintszorzó.

Teljes refundkor actual=min(eredeti purchase pont, aktuális egyenleg); a hiány végleg forgiven. Nem keletkezik későbbi behajtható tartozás. Egy vásárlás a komplex eset után manual marad. A meglévő freeze-filter pozitív awardot held állapotba tesz, refundot nem tilt.

## Fájlok és adatút

`sharity-points.php`: explicit CLI-migráció, két új InnoDB-tábla; nullable UNIQUE subject digest a meglévő user_points táblában és szükséges purchase_reversal enum. Nincs HTTP-autóDDL, teljes historical seed, BIGINT-átépítés. Az ALTER INPLACE/LOCK=NONE, 5 másodperces lock-wait; nincs csendes COPY fallback. Részleges DDL után journal és explicit idempotens folytatás; aktiválás csak schema-verifikáció után.

`sharity-points-ledger-sync.php` + manager: tartós capture → egy GET_LOCK consumer → root/balance/history/event/level egyetlen tranzakció. Az exact event+payload ismétlése ugyanaz a sor; eltérő payload megőrzött konfliktus. Kapcsolatvesztéskor nincs reconnect a tranzakcióban; consumer a lock elvesztése után azonnal megáll, a következő cron olvassa vissza a tartós eredményt.

A két tábla settlements és changes; külön cursor/quarantine/outbox szolgáltatás nincs. Meglévő cronban legfeljebb200 elem. Rootcap100k, positive subject24h1M, runonként külön +/-1M; a run tartós, held esemény restarttal nem kap új keretet. INT-túllépés kézi hold. Meglévő postcommit hookok/bónuszok root-reconciliationnel folytathatók; a gazdasági history dedupe-olt, callback at-least-once, nem exactly-once külső kézbesítési ígéret.

`/admin/adjust`: review_event_id, expected_version, integer points és reason; meglévő manage_sharity_points/manage_options. A subject tárolt kötésből jön. Nulla korrekció megengedett, unbound subjectnek csak nulla. Admin, requested/actual/forgiven, outcome és lezárás atomikus; azonos replay eredeti válasz, eltérő409. Kézi lista: `wp sharity points exceptions list`, `... show <id>`; nincs új UI.

Attribution: owner-granttel hiteles normál handoff a meglévő SAT1 intentet használja és redirect előtt bindol. Pending capture után a root kötése megmarad tokenlejáratkor. CJ SID case-sensitive, nem sanitize-olt token. Dognet totals az opaque tokenből server-owned ngo_refet old fel; nem lesz token nevű NGO. Decay/vacation csak a közös egyenlegírási verseny javítását kapja.

## Provider-készültség és API

- Signed partner: optional, teljes HMAC-body által védett donation_amount_huf decimális string, donation_policy_version, original_purchase_id, original_purchase_at UTC, revision, attribution_token. Refundhoz külön refund_scope=full; partial/hiány kézi. A teljes meglévő validáció és idempotency-konfliktus vizsgálat capture ELŐTT történik; azonos replay ACK csak durable capture után.
- CJ: eredeti order/commission kötés és pending capture megvan. A commission locked/closed státusza önmagában nem végleges adomány; policy/FX/donation authority hiányában a final és delta correction manual. Nincs hiányzó filter-producerre alapozott képzelt működés.
- Dognet: meglévő raw-transactions transport, legfeljebb4 oldalas teljes cutover-window visszaolvasás, nem csak új ID. Missing eredeti idő/finalitás/adományszerződés mellett manual. Scan hiányában nulla kérés. A points-hívás letiltja a meglévő transport401-refresh retryját; 429-re nincs retry.
- CJ és Dognet automatikus aktiválása továbbra TILOS valódi, jóváhagyott adományauthority nélkül. Fixture nem provider acceptance.

## Kapcsolók és átállás

Default-off: `sharity_points_impactshop_producer_enabled`, `sharity_points_impactshop_consumer_enabled`. A legacy consumer default1 marad addig, amíg az előre egyeztetett átálláskor0-ra állítják. `sharity_points_impactshop_cutover_utc`: egyszer rögzített `YYYY-MM-DD HH:MM:SS` UTC. `sharity_points_impactshop_lanes`: readiness után engedélyezett stabil namespace-ek tömbje. `impactshop_dognet_points_scan_from`: kizárólag visszakérdezési contract igazolása után; alapból üres. Nincs automatikus lane-enable.

Aktiválás: explicit `wp sharity points migrate --dry-run` csak olvasó preflight, majd valódi migrate karbantartásban; séma/fájlok readback; legacy0, új capture/consumer külön, lane allowlist csak bizonyított contracttal. Signed webhook a karbantartási intervallumra igazolt replay/refetch nélkül nem állítható le veszteségmentesen.

## Éles út és maradó műveleti feltételek

Új fizikai host: we040.tarhely.com, user sharityh; production /home/sharityh/app, staging /home/sharityh/app-staging. Működő hozzáférés Air→mac-primary→meglévő Mini-kulcs, strict hostkey ellenőrzéssel. Régi s59 DNS nem aktuális. A két profil együtt frissül. A Mini meglévő megbízható IP-hostkeyjének azonossága ellenőrzött; FQDN belépést a kiadás előtt külön igazolni kell, nincs StrictHostKeyChecking=no.

Friss read-only metadata: prodWP6.9.4, stagingWP6.8.3, PHP8.4.26, MariaDB10.11.19. Közös DB, production wp_, staging stg_ prefix: NEM független adatbázisok. Backup/restore/SQL mindig pontos prefixű táblalistára; teljes közös DB restore tiltott. A nagy production history miatt nincs blanket ALTER vagy COUNT/scan. Accountkvóta külön ellenőrzendő.

A karbantartás/drain még nem igazolt. Prod DISABLE_WP_CRON=true ellenére külső percenkénti wp-cron és CLI ütemezés is létezik. `.maintenance` önmagában nem elég; meglévő tárhelykarbantartás + pontos writerpause + tényleges drain szükséges. Ez az éles művelet feltétele, nem a helyi forrásmunka blokkja.

## Publikálás és meglévő release

Egy featurePR, célzott staged diff/secret scan/checkek, független review. Kötelező GitHub check: validate, safe-audit, Guard, canonical-source-guard. Merge és szükséges main-integráció rövid quietwindow; nincs mainüldözés. Staging is csak mergedmainből telepíthető, ezért postmerge/defaultoff. A release külön Git-adminisztrációjú klón, detached mergedSHA és saját origin/main; kiadás alatt nincs fetch.

A meglévő `bin/impactshop-guard-deploy.sh` exact-file út fut, fájlonként előzetes remoteSHA/backup/CAS/releaseID/0444 readback. Függőségek előbb, loader utoljára. Nincs új bundle engine vagy szélesrsync. A preflight hívás `PREFLIGHT_NO_RETRY=1`; maintenance alatt csak az előzetesen sikeres HTTP-preflight kihagyása `SKIP_PREFLIGHT=1`, a többi guard megmarad.

Rollback: capture/consumer kikapcsolás, események megőrzése; bizonytalan írás readback. Fájlrestore kizárólag ismert koherens állapot, régi clickreward/consumer nem élesíthető vissza. Éles elfogadott pontok után nincs régi DB-visszatöltés, schema/history/drop vagy automatikus kompenzáció. Részleges fileset karbantartásban marad; szükség esetén célzott előrejavítás.

## Elfogadás és kézi ellenőrzés

Local: exact money; cutoff/unknown/duplicate/conflict; trueSQL atomicity/rollback/lostACK/KILL CONNECTION; concurrent duplicate+refund/decay; adminzero/replay/unbound/role; frozenawardheld; caprunrestart; provider401/429; click0 és NGO/SID folytonosság. Fixturek csak szintetikus adatok.

Live: minden vállalt lane legalább1 természetes award és annak ismétlése; hiteles refund fixture izolált replay és természetes refund ahol van; legalább20 természetes eligible esemény összesen, mindenlane lefedve. Nincs paidbuy. UI: pont/szint/history, CTA0jutalom, redirect, video/JYSK érintett flow. Két hétig heti kivételszám és rendezésiidő Arnoldnak meglévő listából, nincs új monitor/scheduler. Source-ready, PR, merged, deployed, activated, production-accepted külön.

## Megőrzendő hivatkozás

A Substack-anyag nem végrehajtandó mellékfeladat. Következő handover őrizze: /Users/bujdosoarnold/ai-agent/SUBSTACK-HANDOVER.md, SUBSTACK-NOVEKEDESI-ES-SZERKESZTOI-KEZIKONYV.md és /Users/bujdosoarnold/blog-ai-tukor/drafts/STILUSJEGYZET-user.md. Betöltést csak tényleges olvasás után állítunk.
