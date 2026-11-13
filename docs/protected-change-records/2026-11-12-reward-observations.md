# Pontjutalom-megfigyelés — 2026-11-12

Forrás-előkészítés saját `fix/watchdog-observations-20261112` ágon, alap
`366df47e3572b27f71eb1efbfe52fcef0a00a955`. Ez forráscsomag; a publikálás és
telepítés külön visszaolvasást igényel. Nincs migráció vagy aktiválás.
Arnold engedélyezte a szükséges minimális forrásbizonylatot, változatlan
jutalmazással. A pénzügyi manager és a legacy retirement nem változik.

## Koherencia és forrásazonosság

A tárhelyen natív, írásmentes SSH-forráshash-ellenőrzés egyezett a saját ág
módosítás előtti bájtjaival. Ez forrásazonosság, nem runtime-flag vagy működési PASS.
Az ellenőrzés nem bootolt WordPresst, nem nyitott adatbázist és nem futtatott üzleti
próbát. A régebbi Air Notes több eltérő fájlja nem kiadási bemenet.

| Fájl | Ellenőrzött éles preimage SHA256 |
| --- | --- |
| sharity-points-events.php | 1048c656b1a3b149fc391648d426ac26dfd263f1f6884c4a60832c63aca3d3ef |
| sharity-points-api.php | ed34c5e036cac608290c9b235ff101c131b9979fd41ab020c6efd5e41c4fbbd6 |
| sharity-points-cron.php | b25056b549eab62b69e838343543bba2dd3dfff7ede9861392c1669566effe7b |

A változatlan `sharity-points-manager.php` vizsgált szerződése:
`97284633b1518a14f1a8aad83986f208c435da3cf819145db3b2d496c3b9f747`.
A null dedupe és a normalizálás megfigyelési adaptere ehhez, valamint a
`sharity-points-helpers.php` normalizálásához kötött. Utóbbi telepített hashét a
kiadás határán külön vissza kell olvasni; a helyi leltár önmagában nem éles bizonyíték.

## Protected files touched és funkcióleltár

- Új `wp-content/mu-plugins/sharity-reward-observation.php`: default-off
  `beforeAward`/`before`/`after`, explicit CLI `migrate`; nincs saját hook, route,
  ütemező, üzleti jóváírás vagy tranzakciókezdés.
- `sharity-points-events.php`: napi pseudo/user login, wallet_download, share,
  profile_complete, nickname, video_sponsor és video_ad. A meglévő kapuk után,
  a változatlan managerhívás előtt candidate, utána változatlan eredmény.
- `sharity-points-api.php`: pseudo_earn, aláírt earn, aláírt generikus purchase,
  user/pseudo feedback. Az admin_adjust külön adminisztratív korrekció, itt nincs
  új megfigyelés. A tiltott Impact Shop generikus webhook továbbra is tiltott.
- `sharity-points-cron.php`: user/pseudo heti és havi streak, összesen négy hívás.
  A purchase-reconcile meglévő tartós settlement/bonus/referral útja külön
  adapterforrás; itt nem írjuk át.
- Új `tests/sharity-reward-observation.test.php` és
  `tests/sharity-reward-producers.test.php` és opcionális
  `tests/sharity-reward-observation-mariadb.test.php`; `docs/impactshop-protected-files.json`
  csak az új helper pontos útját és az identity_and_points smoke-hozzárendelést
  veszi fel. A config/hash leltár csak az új helper és saját érintett hashjeit
  frissíti, a többi forrásdigest változatlan.
- Kapcsolódó állapot: `notes.md`, `system-status-snapshot.md`,
  `docs/bastion-guard-status.md`, ez a change record.

## Adat- és hibaszerződés, kockázat

Az új, saját InnoDB-táblát kizárólag kifejezett CLI migráció hozza létre;
webkérésen nincs DDL. A candidate nem bizonyít jogosultságot. Csak az ismert
`earning_blocked` eredmény excluded; ismert kapu utáni hiba jogosultsági bizonyíték;
elveszett válasz, összeomlás és commit_unconfirmed unknown. A fulfilled ragadós,
a reader ettől még külön ellenőrzi a pontos kanonikus ledgerbizonylatot.

Privát ugyanazon DB-beli subject/dedupe selector és ellenőrzött transaction_id
csak az indexelt lookuphoz marad meg; exportba nem kerülhet. Eseményazonosság
lane+subject+végső dedupe; a tartalom immutable digest, rendezett metadata-hash.
Nyers metadata, visszajelzési komment és source_id nem tárolódik. A meglévő
managerargumentum, dedupe, gate, filter, válasz, transient és referral viselkedés
változatlan. A megfigyelő nem futtatja újra a jogosultsági filtert. A null dedupe
fallback megfigyelési számítása nem helyettesíti a managernek átadott null értéket.
A metadata megfigyelési másolata a manager alapértelmezett JSON-tárolását és
asszociatív visszaolvasását követi (pl. 1.0 → 1); az eredeti átadott tömb változatlan.
A MariaDB request-path lekérdezések saját `SET STATEMENT max_statement_time=0.015
FOR` korlátot kapnak. Ez nem módosítja a caller session üzleti query timeoutját;
az explicit DDL nem kap ilyen request-path korlátot. Nem támogatott DB esetén a
recorder fail-open, a lefedettség unknown, és nem aktiválható teljesnek.

A recorder megtagadja az írást már nyitott caller-tranzakcióban; nincs nested
BEGIN/COMMIT, retry vagy backfill. Megőrzi a caller wpdb eredménymezőit. Hiba
esetén a jutalmazás folytatódik, csak titokmentes error_log marker keletkezik.
Opcionális, kizárólag operátori konfiguráció:
`SHARITY_REWARD_OBSERVATION_ERROR_LOG` kiválaszthat egy már meglévő abszolút,
kanonikus elérésű logot. A recorder csak hiba esetén ellenőrzi: olvasható/írható
regular fájl, nem symlink (szülőn át sem), tulajdonosa az aktuális POSIX euid.
Nem kérési paraméter és nincs szándékos log-létrehozás. A saját marker az adott
fájlba kerül natív `error_log(...,3,path)` appenddel; előtte/utána dev+inode kötés.
Hiányzó konstansnál megmarad a natív error_log. Hibás explicit cél vagy sikertelen
append esetén kizárólag natív `error_destination_unconfirmed` diagnosztika;
ez **nem** hallgatólagos, igazolt célváltás. A recorder minden loghibánál fail-open.
A natív type3 API nem ad atomikus no-create/O_NOFOLLOW lehetőséget: versenyző
logrotáció/inode-csere unconfirmed, nem tartós bizonyíték. A cél nem requestből
választható, és sem nyers payload, path, sem hibaüzenet nem kerül a markerbe.

A tárhelyen írásmentesen talált meglévő error_log nem bizonyítja a WebSAPI
alapértelmezett célját. A nagy régi debug.logot nem vizsgáltuk és nem módosítjuk.
**Az explicit cél WebSAPI alatti tartós appendje és bounded új-byte readbackje
még nincs igazolva.** A kiadáskor ugyanazt a konfigurációt és meglévő logútvonalat
kell kötni, CLI-próba csak ezután, majd webes természetes eseményből visszaolvasás;
addig a producer_complete unknown. Enélkül
nem engedélyezhető teljes coverage/üres=idle: a source unknown marad.
A csomag nem hoz létre journalt vagy új naplóinfrastruktúrát, és nem állít
teljes lefedettséget igazolt tartós hibafolyam nélkül.

## Aktiválási sorrend és rollback

1. Pontos kiadási lease, preimage/CAS, backup és rollback-bájtok; csak a meglévő
   guarded exact-file deploy. Nincs teljes MU/rsync vagy régi blob visszaírás.
2. Helper default-off, explicit additive séma; valódi MariaDB hibaablakok és
   indexek/EXPLAIN, rövid írási idő és meglévő writerekkel együttfutás vizsgálata.
3. SELECT-only exporter és privát selector-kizárás, stabil PK watermark + átfedő
   updated cursor + minden régi unresolved külön ellenőrzése; manager/helper és
   forrásgeneráció kötése, tartós recorder-hibafolyam olvasási bizonyítéka.
4. A három azonosított producer pontos kiadása kikapcsolt helperrel; MU betöltés
   végén a helper már elérhető, a hookok csak ezután hívják. Hiányzó helpernél a
   class_exists(..., false) guard megtartja a régi viselkedést.
5. Csak igazolt reader/liveness/coverage_start után engedélyezés. Természetes
   forgalmon insert p95 cél ≤5ms; oldal p95 regresszió ≤max(100ms,10%). Megszegés
   esetén adapter kikapcsolás + unknown; a jutalmazás nem állítható le.

Rollback: először `SHARITY_REWARD_OBSERVATION_ENABLED` kikapcsolása, utána szükség
esetén pontos korábbi három producer visszaállítása CAS-szal. A helper és additive
tábla/adatok megtarthatók; nincs pénzügyi adatvisszaállítás, jóváírásismétlés,
retrospektív feltöltés vagy régi sender. A reader coverage unknown-ra áll.

## Smoke és validáció

Natív PHP lint: helper, három producer és a célzott tesztek. A protokoll/SQL-port
stub és hat izolált log-konfiguráció (létező/missing/symlink/relative/directory/unset) ellenőrzi default-off, privacy, fallback dedupe, sticky/out-of-order result,
transaction-refusal és fail-open eseteket. A producer parity 16 izolált futásban
összehasonlítja a jelenlegi kódot a Gitben meglevő éles preimage-ekkel: mind a 17
earning hívás argumentumai, eredményei és mellékhatásai azonosak sikeres, blocked,
ledger-hibás és commit_unconfirmed válasz esetén; az admin adjustment külön marad.

**Valódi, elkülönített MariaDB 10.11.19 próba sikeres.** A meglévő helyi image,
512MiB memória/256MiB tmpfs és kizárólag ideiglenes 127.0.0.1 port használata;
függőség/image letöltés, WordPress bootstrap és termelési adat nélkül. A saját
`watchdog-rewards-20261112` konténer a próba után törölve, visszaellenőrizve.

A valódi SQL-próba: explicit/idempotens DDL, dedupe/immutable/sticky és későn
visszatérő eredmények; caller tranzakció megőrzése és rollback; szerver-triggerrel
előidézett candidate/result íráshiba mellett megmaradó szintetikus target credit;
32 párhuzamos megfigyelési próbálkozás egy eseményen; SELECT-only user tiltott
DB-írása; privát selectorok kizárása; point-events/point-api/point-cron külön
hozzárendelés; elveszett result melletti kanonikus readback; régi pending a PK és
updated ablakon kívül; régi insertion ID új eredményének átfedő olvasása; közben
commitoló kanonikus writer csak a következő konzisztens snapshotban; részleges
cursor nem committable; valódi statement megszakítás és lejárt reader-határidő.
A 1.0 → 1 metadata-tárolási eltérés valódi DB-ben reprodukálva, a csak megfigyelési
normalizálással javítva. A logmarker biztonságos, tartós fájl-readbackje csak a
fixture saját ideiglenes fájljában igazolt, **nem a tárhelyen**.

EXPLAIN: új sor PRIMARY/range, updated/range, pending és csoportos összesítő
pending/range, pseudo/user owner+dedupe uniq index/const, egy sor. Ez kis
szintetikus fixture, nem éles kardinalitási vagy teljes történeti munkakorlát-PASS.
Az örökölt lockwait mellett a szintetikus recorder-sorzár 1011ms várakozást
okozott; a saját 15ms statement-limit után candidate 20.1ms/result 17.3ms alatt
fail-open, a korábban commitolt credit és a caller session timeout változatlan.
Utolsó kis fixture-minta: candidate 2.49ms, result 0.94ms, teljes export 9.02ms,
reader SELECT-cancel 55.9ms. **Ezek egyedi helyi minták, nem p95 vagy éles
terhelési elfogadás.**

Újrafuttatás csak saját disposable fixture ellen: meglévő image-ből indított
konténer, majd `REWARD_TEST_DISPOSABLE=watchdog-rewards-20261112`,
`REWARD_TEST_MARIADB_PORT=<a saját ideiglenes localhost port>` és
`REWARD_TEST_EXPORTER=<a konkrét read-only-export.php útja>` környezettel
`php tests/sharity-reward-observation-mariadb.test.php`; végül saját konténer
eltávolítása. A teszt nem indít infrastruktúrát, nem keres credentialt és nem
használ alapértelmezett adatbázisportot.

**Éles integráció/UI/terhelési elfogadás továbbra is függő:**
belépés, profil/nickname, share, wallet, video, feedback és streak; ugyanaz a
jóváírás és HTTP-válasz, helper off/on, elveszett megfigyelésnél is. A smoke scope
tagek kijelölik a meglévő protected ellenőrzési felületet; a helyi guard PASS nem
bizonyítja az összes élő UI-smoke lefutását.

## Kézi UI-ellenőrzési lista az aktiváláshoz

- Az app.sharity.hu meglévő profil/User Card felületén a pontszám és az előzmény
  a szokott módon jelenjen meg; az új megfigyelőnek nincs új UI-eleme.
- A szokásos belépés/profil- vagy névkitöltés, megosztás/wallet és egy támogatott
  videófolyamat után a meglévő jutalom és válasz maradjon azonos. Egy ismételt
  kérés ne adjon második jutalmat; ezt a helyi parity már külön ellenőrizte.
- A feedback visszaigazolása és szövege változatlan maradjon; a szöveg nem
  kerülhet a megfigyelési exportba. Streaknél a természetes háttérfutás eredménye
  ellenőrizendő, külön éles próbafuttatás nélkül.
- Megállási jel: új hibaüzenet, eltérő jutalom, hibás egyenleg vagy a megengedettnél
  nagyobb lassulás. Ilyenkor előbb a megfigyelő kapcsolóját kell kikapcsolni.

A meglévő CI-ben a négy PHP-forrás lintje és a két izolált protokoll/parity teszt
fut. A MariaDB-teszt külön disposable környezetet kér; CI nem tesz úgy, mintha
éles adatbázisos vagy UI-ellenőrzés történt volna.


## Vásárlási bónuszok célzott kiegészítése — 2026-11-12

Az önálló forrásreview feltárta, hogy a `sharity_points_impactshop_reconcile`
aktív schema/consumer kapui után a first_purchase, shop_discovery és referral
bónuszok a `sharity_points_impactshop_bonus_once` függvénybe futnak. Az aktív
referral pseudo és user referrer ága, valamint referred_bonus is ezen halad át.
A legacy impact_ledger fogyasztó külön kikapcsolt út; abba nem kerül új hívás.

Koherencia/érintett funkciók: a meglévő bonus_once korai kanonikus
entitlement/dedupe ellenőrzése és return értéke változatlan. Csak az ezután
következő, változatlan argumentumú shared managerhívás körül lesz megfigyelés.
Így a másik rooton már teljesített egyszeri bónusz metadata-különbsége nem
keletkeztet hamis candidate/content-conflict rekordot. A cron fájl és callback
sorrend, meglévő root/version/needs_reconciliation írások változatlanok.
Kockázat: recorder hiba csak unknown lefedettséget adhat; jutalom, manager
válasz és a referral lezárás nem változhat. A helper továbbra is default-off;
webes tartós logfolyam és a teljes vásárlási callback-család lefedettsége nincs
ettől igazolva.

Előkép: Git `91b8cc6835859168dcdfd5cbc742fd2b985ae912`, ledger-sync SHA256
`9e55e8ff2ae43bd10c640b407005053b2e5b05ccc93256074a1ff7fd6016637b`;
a rögzített 366df47e parity-preimage ugyanennek a fájlnak azonos bájtjait adja.
Privát, egyfájlos lokális backup: `.codex/backups/watchdog-purchase-observations-20261112/`.
Rollback: kizárólag e fájl ellenőrzött előképe, a release határán friss CAS és a
meglévő guarded exact-file út; nincs runtime visszaírás ebben a munkában.
A saját lokális másolat legfeljebb két napig őrzendő; teljes backup nem érintett.

Funkcióellenőrzés: eredeti 17 earning + admin parity golden megőrzendő;
first_purchase/shop_discovery, referral pseudo/user és referred bonus exact
argumentumai/eredményei; meglévő entitlement return esetén nincs új megfigyelés.
Kézi UI lista kiadás után: Profil ponttörténetben természetes vásárlás bónuszai
legfeljebb egyszer szerepeljenek; ajánló/ajánlott jóváírás ne ismétlődjön;
adminisztratív jutalomkapu, összegek és meglévő profil-egyenleg viselkedése
maradjon változatlan. Ezt most nem futtattuk éles gazdasági eseménnyel.


Ellenőrzés: PHP producer parity ugyanabban a meglévő tesztben, az eredeti
17 earning + admin golden16 izolált esete változatlan és sikeres; külön16
izolált bonus eset az eredeti366df47e fájllal egyező managerargumentumot,
returnt és referral update-et bizonyít. Hiányzó/off/on/failed recorder és
success/earning_blocked/ledger_write_failed/commit_unconfirmed eredmények;
a már teljesített vagy eltérő tartalmú entitlement return nem hív recordert.
Új fix lane-ek: `cron.purchase_bonus.pseudo`, `cron.purchase_bonus.user`.
A reader tulajdonosi térképe és a cron-forrás ledger-sync kötése külön R2
integráció; ezek nélkül nincs teljes coverage állítás.

Új várható ledger-sync artifact SHA256:
`330fdd7d529a840dd32e84ad359bd433604609084c26a394c1b418526b7fd6cb`.
A cron továbbra is `9654842eebe87208d5bcde153c1aed5ba5892000f08da89cf6a2eebdb0033d5a`;
manager, séma, pénzszámítás és attribution bájtjai változatlanok.
A leltárban csak a már meglévő ledger-sync hash és a manifest külső
checksumja frissült; nincs új protected út vagy guardmodell.

Érintett fájlok: `wp-content/mu-plugins/sharity-points-ledger-sync.php`,
`tests/sharity-reward-producers.test.php`, `docs/impactshop-guard-hashes.json`,
`docs/impactshop-guard-hashes.sha256` és a saját notes/snapshot/change-record.


A célzott PHP lint és a meglévő helper protokoll/SQL-stub teszt sikeres.
A protected-touch guard a dokumentált, már jóváhagyott scope override-dal,
konkrét rollbackkel és a modell szerinti nyolc identity_and_points smoke
címkével sikeres; ez scope-ellenőrzés, nem a címkék éles UI-futtatása.
A manifest checksum sikeres, minden idegen digest byte-azonos, diff-check tiszta.
Új MariaDB/éles gazdasági futás nem indult; a korábbi helper eredmény nem
minősül e bónuszok éles aktiválásának. A delta a meglévő Notes PR278 része; élesítés és aktiválás továbbra sem történt.

## Éles aktiválás — 2026-11-12 22:00 Budapest

Az öt kiválasztott fájl exact-file CAS-telepítése és az additív tábla explicit migrációja kész. A globális CI/hash-lista eltérését Arnold csak az öt ellenőrzött fájl kiadására engedte kivételként; közös baseline-t nem írtunk át. A két monitor-konstans hozzáadásán kívül a wp-config korábbi byte-jai változatlanok: előző SHA `888220f875b917218bda805b01bf30cc3df21f2824be521f5bfc2f4e9fc69992`, új SHA `f168576087edb72b6fe91e1e362b43eda3e5ff19f3e7552b99da93d65d0ebf47`. A privát hibanapló 0600, írás/visszaolvasás és kezdőcursor igazolt; a megfigyelési hibát nem rejtjük el.

Tizenkét regisztrált producer-hook és 25 forráshash ellenőrzött. A runtime-ACK SHA `4f91518e7b687da78217ab5306209818ac5d1f8593280f50f6945ae91daaff1c`; a reader-private utó-SHA `9053cd4fb4bfa75a4e199208773249a2bcc8c17bac291982bda049b872cc70f2`. Titok és raw üzleti selector nem része ennek a rekordnak. A három új forrás lefedettségi kezdete 21:57:14 Budapest. A 22:00-s természetes R2-kör point-events receipt_confirmed, point-api/cron idle_verified; a hibafolyam gapje null. Nincs mesterséges jutalom és a manager változatlan.

Rollback: csak a két hozzáadott define pontos, friss SHA-val ellenőrzött visszavonása; a privát wp-config-mentés és a hozzá illő reader-private előzmény megmaradt. A megfigyelési táblát, jutalmat és kézbesítési előzményt nem töröljük. Idegen konfigurációváltozásra nem telepítünk régi teljes fájlt. A Mini R2→R1 visszaállítása külön koordinátoros művelet, előbb heartbeat-cadence3600, utána órás cron.

Nyitott kézi/forgalmi elfogadás: webes/API p95, recorder p95≤5ms; UI-t nem nyilvánítunk teszteltnek egy CLI- vagy idle-eredményből. A VB fogadó kikapcsoltsága, JYSK/PITR/profil-korlát és a Mini valós tárhelyhiánya nem lett csendben megoldottnak jelölve. A source-ready, merged, installed, activated és teljes production-accepted állapot külön marad.
