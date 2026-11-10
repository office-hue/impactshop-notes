# Régi keretek végleges lezárása és auditált átvezetése

Arnold jóváhagyott terve: minden pozitív fennmaradó keret egyszer1:1 a közös
sharity_shared walletbe kerül; pont/spent nem változik. A régi játék nem nyílik
újra. Késői hiteles események csak kézi elszámolásra őrződnek. Üzenet/push nincs.
Saját ág feat/legacy-votes-retirement-20261110, alap a4b113d8.
Kapcsolt ai-agent ág feat/legacy-votes-transfer-20261110, alap16a0e302.

## Protected files touched és érintett funkciók

- impactshop-ads-watch.php: view/education/allocate belépők és alacsony szintű
  add/spend lezárása minden mellékhatás előtt; üres walletolvasás nem INSERT.
- impactshop-vote-purchase.php: új start és fulfill lezárva; hiteles Stripe
  completed callback a tartós receipt eredményét adja, tárolási hiba nem ACK.
- impactshop-ayet-offerwall.php: az aktuális élő ow2_/ow2s_ dispatch megőrzött;
  csak régi ág zár. Signed query és IP-ellenőrzés után conversion/reversal,
  chargeback megőrzése, nincs régi reward/points/completion.
- impactshop-offerwall.php: régi start/iframe/offers zár; hiteles callback
  ellenőrzött scalar receipt, régi tx-only signature nem igazol teljes payloadot.
- impactshop-offerwall-survey.php és impactshop-offerwall-article-quiz.php:
  submit/reward belépők zárva, közös fetch és új Offerwall változatlan.
- Új impactshop-sharity-legacy-votes-retirement.php: végleges closure,
  pontos REST/frontend handoff tiltás, nem-autoload options késői események.
- Additív zzz-impactshop-ui-lock.php: végleges lezárási szöveg; az aktuális
  live WP_HTTP_Response/tally/amount/lock_notice alak megőrzött.
- Meglévő config/protected modell/hashleltár csak saját új modul és érintett
  forráshash követésére bővül; guardlogika és más digest nem változik.

## Koherencia és konkrét kockázat

A központi contest-votes motor byte-változatlan. A privát ai-agent WP-CLI
migrátor pinned natív kapcsolaton ledger+wallet+legacy-nullázás egy InnoDB
tranzakcióját kezeli, profilonkénti audit a ledgerben. Options snapshot/seal
BEGIN előtt, visszaolvasva; immutable prepared rekord, applied hint nélkül.
MyISAM marad, nincs új séma vagy nyilvános jóváíró végpont.
A runtime modul telepítéskor végleg zár; hiányzó seal sem nyithatja vissza.
Seal nélkül capture503, így nem veszhet el hamisan sikeres partneres esemény.
Eseményegyezés idempotens, eltérő tartalom409, DB/readback hiba503.
Kritikus kockázat a dupla jóváírás, részleges commit, hitelesített mezők
keverése és V2 véletlen lezárása. Ezekre közvetlen teszt és szűk branch készül.
Az örökölt általános callback aláírása nem minden üzleti mezőt hitelesít;
verification szintje ezt jelzi, automatikus rendezés nincs.

## Tényleges teszt és smoke

-31 actual-function PHP-eset sikeres: lezárás mellékhatás előtt, V2 dispatch,
  signed AyeT chargeback, mixed-body elutasítás, generic/Stripe receipt.
- Ai-agent25 pure +27 fake-native +100 MyISAM-options +100 InnoDB-options
  natív ellenőrzés sikeres; az eredeti50 InnoDB-próba történeti eredmény.
- Valódi második process, hibás ledger, KILL CONNECTION, COMMIT előtti/utáni
  válaszvesztés, spent utáni replay, target/schema binding és tartós receipt.
- A három érintett production gazdasági tábla read-only exportja privát mentésbe,
  izolált RAM MariaDB-restore:3 CHECK TABLE OK,2852/330/501124 legacy,
  közös497/428/69,13 ledger; friss read-only forrással egyezik.
- Saját tesztkonténer eltávolítva, mentés webrooton kívül0700/0600, keep7days.
- NGO Card Bástya164/0issue. Független célzott implementation review lezárt,
  nyitott lelet nincs. Reviewer nem ismételte a natív DB-futást.
- A smoke tagek érintettségi körök, nem nem-futtatott éles böngésző-PASS.
  Régi flow-k helyes eredménye lezárt; új Offerwall/profil tovább működjön.

## Telepítés és rollback

Repo-nként egy PR; a meglévő commit-lane a protected/additive/ops változásokat
külön commitra bontja, ugyanabban a koherens PR-ben. Nincs új release-helper.
Csak sikeres merge/teszt/READY közös webartifact után rövid egyeztetett
hostingdrain. Friss exact targethash/mód/uid/gid/parentmode, privát file+DB-backup,
guarded/exact-file prepare/apply/inspect; fájlmentés keep7days explicit kivétel.
Az AyeT/session forráshash meglévő aláírt Offerwall-artifactkötését együtt kell
frissíteni; phase/expiry/provider/cap/finance állapot nem tágul. Csak ténylegesen
aktív VB-fogyasztó repin, inaktív candidate nem aktiválható. Egyetlen közös web
artifact az élő forrásból; csak sharity.hu/www alias, Legal változatlan.

COMMIT előtt SQL rollback, bizonytalan commit után friss status, nem újraírás.
COMMIT után nincs teljes DB-restore vagy tömeges visszaterhelés. A permanent
seal/audit/pending megmarad, régi nyitott writer nem állítható vissza.
Web/PHP rollback csak saját változatlan utóállapotból, kompatibilis artifacttal.
Esetleges gazdasági korrekció külön jóváhagyott naplózott helyesbítés.

## Kézi UI checklist és állapot

Jóváhagyott MyISAM-korrekció implementálva és két motorral natívan tesztelt.
Registryhiba mellett a ledgerdiagnózis megmarad, apply blokkolt, CLI nem nulla.
Nullás cohortdrift, COMMIT-válaszvesztés, immutable manifest és saját options
izolált restore lefedett. ON UPDATE timestamp és options SQL-read reviewlelet
javítva; független visszaellenőrzés lezárt. Nincs production seal/apply.

Friss Profil és Szívügyeim ugyanazon ellenőrzött profilon egyező közös keret;
pont/spent változatlan. Régi játék/start lezárási üzenetet ad, régi keret nem
költhető. Új Offerwall V2, profilreturn/save/restore, NGO Card, üzenetek/dokk
és más aktív termék működik. Nincs éles jutalom- vagy gazdasági tesztírás.
Ez helyi forrás/teszt/review és backup/restore bizonyíték, nem merge/deploy,
lezárás/átvezetés vagy éles elfogadás. Teljes futási szerződés:
ai-agent docs/user-card/LEGACY-VOTES-TRANSFER.md.
