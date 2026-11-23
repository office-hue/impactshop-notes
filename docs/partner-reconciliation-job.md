# Partnerkimutatások pénzügyi egyeztetése

A `wp-content/mu-plugins/impactshop-partner-finance.php` additív adapter a közös
`ImpactShop_Sharity_Bank_Payout` modul aláírt `partner-statement` műveletét
regisztrálja, `approver` szerepkörrel, V2 pontos műveleti grant alapján. A beérkezett bankpénzt foglalja és a
szerződéshez kötött NGO-jogosultságot könyveli. A kifizetés külön, havi, devizánként
jóváhagyott B batch; az utalást Arnold indítja. A banki terhelés és az NGO-nál
igazolt jóváírás külön állapot.

## Bizonyíték és pénznem

A partner `paid` státusza önmagában nem banki beérkezés. A régi `impact_ledger`
HUF-összegei, kijelzési árfolyamai, pontozási szorzói és ±1%-os analitikai
összegvizsgálata nem használhatók e modul pénzügyi bemeneteként. Az alap a pontos
HUF/EUR-kimutatás, az eredeti forrás SHA-256 lenyomata és az igazolt banktétel.
Mindkét deviza skálája 100; nincs FX. A HUF-kifizetést B egész forintra korlátozza,
a könyvelt maradvány továbbvihető.

A privát B policy `partners[partner_id]` bejegyzése rögzíti a jóváhagyott
`revision`, `provider`, `bank_account_id`, `provider_scope_ref`, `source_kind`, `ngo_rate_bps` mezőket, továbbá
a kötelező `rounding=per_transaction_floor_minor` szabályt. Hiányzó vagy más
kerekítési szabály mellett az érintett kimutatás nem könyvelhető.
Az `allow_operator_evidence` alapértéke hamis. A `bank_account_id` a B policy
kanonikus, 64 hex számlaazonosítója; a `provider_scope_ref` minden forrásnál
kötelező, és a szolgáltatói elszámolási scope-ot jelenti. E kettő nem alias. A mintakonfiguráció nem partnerengedély, nincs alapértelmezett 50%.
Hiányzó vagy eltérő policy csak az érintett kimutatást utasítja el.

## Feldolgozás

1. A B `plan` létrehozza a pontos ellenőrizhető parancsot és aláírandó bájtokat.
   Collector az előkészítést az engedélyezett approver `key_id` megadásával végezheti;
   ez nem ad aláírási vagy apply-jogot a collector kulcsának. Az egzakt pénzügyi
   planhez a megfelelő grant már szükséges; importjelölt korábban is előkészíthető.
   A teljes `data` maga a kimutatás, nincs `data.statement` köztes csomagolás.
2. A B `apply` ellenőrzi az aláírást, szerepkört, célrendszert, jogi személyt,
   konfigurációt, előállapotot és lejáratot. Az adapter külön ellenőrzi, hogy
   éppen ez az engedélyezett művelet és pontos `financial_scope` fut. A V2
   aláírás kötött `grant_id`-ja a provider, provider-scope és a kiválasztott
   partnerforrásszabály kanonikus SHA-256 lenyomatát engedélyezi. Ez utóbbi
   nem a teljes B policy hash-e; azt a boríték `policy_sha256` külön köti.
3. Ugyanazon rögzített MySQL-kapcsolat és tranzakció foglalja a fizikai banktétel
   `impact_shop` részét, ellenőrzi a partnerhez tartozó számlát, rögzíti a
   kimutatást, majd a jogosultság változását. Nincs külön `BEGIN/COMMIT` vagy
   WordPress-kapcsolat újranyitásával történő írás.
4. Az NGO-összeg soronként `floor(net_minor × ngo_rate_bps / 10000)`, egész
   aritmetikával. Ez a kerekítés csak a dokumentált partnerpolicy kifejezett
   jóváhagyása esetén alkalmazható; az aggregált kerekítési szabályú partnerhez
   külön jóváhagyott implementáció kell. A felosztás a privát policyből származik. Az adott sorban
   fennmaradó minor egység nem kerül más NGO-hoz.
5. Negatív korrekció után ugyanabban a tranzakcióban frissül a még el nem indított
   batch-ek fedezetvizsgálata. Az engedélyezett/elindított/bizonytalan utalás
   foglalását csak a B banki bizonyítékhoz kötött folyamata oldhatja fel.

## Az NGO-hozzárendelés eredete

- `partner_tx`: a rögzített kapcsolaton zárolt `impact_partner_tx` sor,
  `(partner_code,event_id)` szerint. Az eredeti `ngo_code` a hozzárendelés;
  kezdeti elfogadáskor pontos deviza, `payment_status=paid`, `status=approved`
  szükséges. Az ottani kosár-/HUF-összeg nem számítási alap.
- `dognet_intent`: az eredeti `impactshop_affiliate_intents` sor tokenlenyomata,
  `provider_key=dognet`, partner, jóváhagyott scope és `redirected` állapot.
  A kimutatás adja a providertranzakció és token bizonyítéki kapcsolatát.
  A megtartott eredeti sor használható; a már törölt/hiányzó hozzárendelés
  nem található ki. A modul nem módosítja az intent-retenciót.
- `operator_evidence`: kizárólag kifejezetten engedélyezett policy esetén,
  aláírt NGO-hozzárendeléssel, bizonyítékhivatkozással és a kimutatáséval egyező
  forráslenyomattal. Más alapforrás mellett a sorban explicit
  `source_kind=operator_evidence` szükséges.

Hiányzó hozzárendelés vagy hiányzó forrástábla/oszlop esetén csak az adott sor
`held`; nem kap jogosultságot, összegét nem osztjuk újra. A kapcsolat elvesztése
vagy egyéb DB-hiba az egész tranzakciót megszakítja. Későbbi aláírt revízió a
félretett sort feloldhatja. A már igazolt NGO és eredeti hozzárendelés nem
cserélhető le; további korrekció a rögzített eredeti kapcsolatot használja.

## Revíziók és közös pénzazonosság

Két saját InnoDB-tábla készül: `sharity_partner_statements` az összes változat
megváltoztathatatlan kanonikus bemenetével/eredményével; valamint
`sharity_partner_transactions` az eredeti kapacitás és aktuális verzió zárolt
vetületével. Az új providertranzakció tulajdonosi kulcsa a provider +
provider-scope + jogi személy + test/live mód + providertranzakció; a fogadó bankszámla, partnernév,
szabályverzió és felosztási arány nem része. Bankváltás ezért nem hoz létre új
provider-tőkét vagy második jogosultságot. Két bizonyított, külön szolgáltatói
scope ugyanazt a helyi tranzakcióazonosítót használhatja.

Új sor előtt az adapter az azonos `provider_tx_id` történeti jelöltjeit zárolja,
és megtartott változatlan pénzügyi kontextusukból bizonyítja a forrásscope-ot.
Bizonyíthatatlan legacy jelölt mellett az érintett kimutatás
`partner_source_ownership_unproven` ütközéssel megáll; a fogadó bankból nem
következtet külön forrásra. Nincs új főkönyv vagy történeti újrakulcsolás.
A `financial_context.transaction_keys` soronként rögzíti a tényleges vetületsor
azonosítóját. Korrekció ezt használja; régebbi csomagolt, bizonyított kontextus
esetén a megőrzött eredeti bankkulcsú sor pontosan visszaolvasható és változatlan
marad. Csomagolatlan, scope-bizonyíték nélküli sor nem jogosít fel új írásra.

A V2 ugyanazokat a táblákat, történeti statement-/tranzakció-/entry-kulcsokat
és CAS-t tartja meg; az új sorok bankfüggetlen tulajdonosi kulcsot kapnak. Nincs új DDL
vagy történeti sorátírás. Az új `original_json` kizárólag
szerver által készített `{statement, financial_context}` csomag: az eredeti
kiválasztott szabály, annak hash-e, B által visszaadott pontos claim, jogi
személy/mód, eredeti soronkénti kapacitás és a már bizonyított NGO-kötések
megőrződnek. A bemenet ilyen kontextust nem adhat meg.

Korrekcióhoz a visszaadott `original_statement_key` és az aktuális
`expected_revision` kötelező; az adapter először e kulcs szerint zárolja a
legutóbbi változatot, és csak az abban fagyasztott szabályt használja. A mai
partnerpolicy hiánya, új felosztása vagy bankaliasa nem írhatja ezt felül.
A B `assert_original_claim` az eredeti foglalást olvassa vissza; korrekció nem
hív új `claim_cash`-t. Az eredeti hash-hez tartozó grantnek és approvernek a
mai B policyben továbbra is engedélyezettnek kell lennie. Visszavont grantet
nem kerül meg a történeti szabály.

A régi csomagolatlan sorok megmaradnak olvasható történeti bizonyítékként.
Eredeti változatlan policy-/claim-bizonyíték hiányában korrekciójuk
`partner_immutable_policy_proof_required` ütközéssel megáll; a mai policyből
nem készül találgatott történeti engedély.

Az első revízió sorösszege pontosan a lefoglalt bankrész nettó összege. A
korrekció az előző revízióra mutat, ugyanazt a teljes tranzakciókészletet és
változatlan eredeti cash/part/deviza/policy kapcsolatot tartalmazza. Soronként
legfeljebb az eredeti kapacitás könyvelhető. Új bankpénz vagy új providertranzakció
nem rejthető korrekcióba. Az azonos változat ismétlése hatástalan; eltérő tartalom
ütközés. A B receipt-foglalása az Offerwall/legacy ággal közös, egy fizikai pénzt
nem lehet újra lekönyvelni. A legacy banktétel eredeti egyszeri foglalását előbb
igazolni kell; analitikai sorok tömeges másolása nem import. Eredetileg
`unassigned` vagy `legacy` részhez csak a B külön approver által aláírt,
eredeti kapacitáson belüli `cash-reclassify` művelete adhat egyszeri célkötést.
Az adapter az eredeti cash payloadot továbbítja, nem gyárt új részt.

Ez az adapter teljes kimutatás és egy pontosan egyező bankrész párosítását kezeli.
Hiányos/több beérkezésre még fel nem osztott kimutatás kézi egyeztetésre vár;
tranzakcióazonosító átnevezésével nem kerülhető meg az egyediség.

## Migráció, kiadás és visszaállítás

A betöltés csak késleltetett handler-/CLI-regisztráció. A `status` és a handler
nem futtat DDL-t. Az explicit `wp sharity partner-finance migrate` a B tranzakción
kívül készíti el a két táblát, lépésenként ellenőrzi az InnoDB-t, oszlopokat és
kulcsokat. A MySQL DDL nem visszagörgethető közös tranzakcióként; részleges vagy
eltérő séma esetén a writer zárva marad. A migráció nem aktivál kifizetést.

Kiadás előtt konzisztens DB-mentés, a meglévő B policy és pluginfájl saját mentése,
majd a két repo összehangolt release-azonosságának ellenőrzése szükséges. A modul
csak merge-elt forrásból, a meglévő exact-file guarded deploy úton telepíthető;
B nélkül nem regisztrál írót. A banki adat, aláíró magánkulcs és valódi privát
policy nem kerül DOCSYNC-be vagy publikus státuszba.

Pénzügyi írás után az új jóváhagyás leállítása és kompatibilis előre javítás az
alap. Napló, jogosultság vagy foglalás törlése és régi DB visszatöltése nem
rollback. A forrás, merge, telepítés, aktiválás, banki és címzetti elfogadás külön
állapot. A helyi szintetikus teszt nem partneri vagy banki éles elfogadás.

## Bástya: célzott meglévő leltárbővítés

Koherencia és érintett fájlok: az új pénzügyi writer egyetlen útja bekerül az
`impactshop-guard-config.json` és `impactshop-guard-hashes.json` meglévő leltárába;
a config-checksum saját beágyazott hivatkozása és a két meglévő külső checksum követi. Guardkód, protected-modell és CI nem változik.
A teljes korábbi védett fájllista és minden más forráshash megmarad. A közös
`docs/bastion-guard-status.md` és governance státusz a tényleges helyi bizonyítékot
rögzíti. Kockázat az új út kihagyása vagy más fájl téves újrahash-elése; ezt a
korábbi JSON-nal végzett célzott strukturális összehasonlítás ellenőrzi.

Backup: `tmp/partner-finance-release-inventory-before/` az eredeti négy fájl saját helyi
másolata, valamint a rögzített Git-alap. Rollback: pénzügyi írás előtt a saját
négy fájl eredeti bájtjai visszaállíthatók; éles fájlhoz az exact-file saját
release-mentése szükséges. Pénzügyi írás után a fenti naplómegőrző út érvényes.

Smoke scope: signed partner statement / cashclaim / exact amounts / immutable
NGO / native race / schema readiness / unchanged affiliate bastion. Ezek helyi
ellenőrzések, hosted CI és telepítés utáni funkcióellenőrzés még függő.
Kézi UI-checklist telepítés után: az Impact Shop vásárlási redirect működik; az
NGO Card támogatási adata nem jelenik meg banki teljesítési igazolásként; a
profil pont/szint nézete elérhető. Stagingen saját szintetikus inputtal külön
ellenőrizendő a CLI állapot és writer alapból zárt állapota; élő tesztvásárlás
vagy próbaátutalás nem része e checklistnek.

## V2 helyi ellenőrzés állapota — 2026-11-18

A PHP 8.4.25 szintaxisellenőrzés és a tiszta, szolgáltatómentes teszt 55
ellenőrzéssel sikeres. A V2 tényleges MariaDB-tesztje nem futott: az explicit
`127.0.0.1:13317` fixture kapcsolatát a host visszautasította; nincs daemon-restart
vagy vak ismétlés. A célzott SQL-sorozat V2 grant/aláírás, eredeti szabályt és
aliasváltást túlélő korrekció, legacy bizonyítékhiány, rollback és párhuzamos
CAS, bankváltásos ismétlés, külön provider-scope azonos helyi tranzakciója és
történeti sorazonosító megőrzésének ellenőrzéseit tartalmazza, de ezek eredménye **SQL NOT RUN**. A korábbi V1
39/55 ellenőrzés nem V2 PASS. PHP 8.3, teljes WordPress bootstrap, CI, telepítés
és aktiválás külön függő; pénzügyi merge/telepítéshez a V2 SQL PASS szükséges.

## Történeti Dognet-bankegyeztetés

A privát kimutatást az eredeti Dognet ID, a számla, a tényleges bankjóváírás
és a megőrzött NGO-választás alapján készítsd elő. A partner pontos
elszámolási szabályát és a teljes eredeti banki felosztást az első parancshoz
rögzítsd. A részletek a [bemeneti szerződésben](partner-reconcile-export-spec.md)
vannak. A kiválasztott sort csak akkor rendeld numerikus NGO-hoz, ha az eredeti
forrás és a jóváhagyás bizonyítja. Szabály- vagy azonosítási eltérésnél tartsd
vissza a sort.

A célrendszeren a sharity partner-finance status és a sharity bank-payout
status mutatja a séma és a jogosultság állapotát. A bank-payout plan
privát aláírandó bemenetet készít. A bank-payout apply az aláírt műveletet
végrehajtja. Bizonytalan eredménynél ugyanazon parancsazonosító status
visszaolvasása kell. A fennmaradó korábbi utalási átfedést a banki
nyitóállomány és a korábbi teljesítés pontos egyezése alapján rendezd.

Az ellenőrzött boltok átirányítását az impactshop_dognet_verified_ngo_shops
opció engedélyezi. Minden útvonalon külön igazold a tényleges szolgáltatói
visszaadott jelölőt, a hozzárendelés visszaolvasását és a Card jóváírását.
