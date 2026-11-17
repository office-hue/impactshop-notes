# Partnerkimutatások pénzügyi egyeztetése

A `wp-content/mu-plugins/impactshop-partner-finance.php` additív adapter a közös
`ImpactShop_Sharity_Bank_Payout` modul aláírt `partner-statement` műveletét
regisztrálja, `collector` szerepkörrel. A beérkezett bankpénzt foglalja és a
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
`revision`, `provider`, `account_id`, `source_kind`, `ngo_rate_bps` mezőket, továbbá
a kötelező `rounding=per_transaction_floor_minor` szabályt. Hiányzó vagy más
kerekítési szabály mellett az érintett kimutatás nem könyvelhető.
Az `allow_operator_evidence` alapértéke hamis. Dognetnél a `provider_scope_ref`
is kötelező. A mintakonfiguráció nem partnerengedély, nincs alapértelmezett 50%.
Hiányzó vagy eltérő policy csak az érintett kimutatást utasítja el.

## Feldolgozás

1. A B `plan` létrehozza a pontos ellenőrizhető parancsot és aláírandó bájtokat.
   A teljes `data` maga a kimutatás, nincs `data.statement` köztes csomagolás.
2. A B `apply` ellenőrzi az aláírást, szerepkört, célrendszert, jogi személyt,
   konfigurációt, előállapotot és lejáratot. Az adapter külön ellenőrzi, hogy
   éppen ez az engedélyezett művelet fut.
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
vetületével. A provider/számla/providertranzakció kulcs egyetlen kimutatáshoz
köthető; másik partnernév vagy kimutatás nem hozhat létre második jogosultságot.

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
a két meglévő külső checksum követi. Guardkód, protected-modell és CI nem változik.
A teljes korábbi védett fájllista és minden más forráshash megmarad. A közös
`docs/bastion-guard-status.md` és governance státusz a tényleges helyi bizonyítékot
rögzíti. Kockázat az új út kihagyása vagy más fájl téves újrahash-elése; ezt a
korábbi JSON-nal végzett célzott strukturális összehasonlítás ellenőrzi.

Backup: `tmp/partner-finance-inventory-before/` az eredeti négy fájl saját helyi
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
