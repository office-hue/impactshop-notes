# Partner pénzügyi V2: approver-scope és eredeti korrekciós alap

## Cél és érintett fájlok

Jóváhagyott partner V2 szerződés, kizárólag saját worktree-ben. Alap: `f675e0a8fdaf07b56327265128244fce270b63a9`, ág: `feat/sharity-partner-finance-v2-20261118`. Érintett fájl: `wp-content/mu-plugins/impactshop-partner-finance.php`, két saját teszt, a két partner reconciliation dokumentum és e rekord. A közös B API az ai-agent saját munkája, itt nem módosul. Nincs main fetch, commit/push vagy élő írás ebben a csomagban.

## Koherencia, kockázat, funkciólista

Az új partner-statement könyvelés approver szerephez, V2 aláírt granthez és pontos provider/provider-scope/forráspolicy lenyomathoz kötődik. Collector előkészíthet, de nem hozhat létre NGO-jogosultságot. A korrekció az eredeti kimutatást előbb oldja fel; annak rögzített felosztási szabályát, banki claimjét, jogi személyét/módját és NGO-kötését használja, a mai partnerpolicy/alias nem irányíthatja át. Az eredeti policybizonyíték nélküli történeti sor megmarad, korrekciója félretett állapotú. Konkrét kockázat: jogosulatlan új jogosultság, megváltozott számlára/NGO-ra irányuló korrekció, történeti kapacitás újra létrehozása. Kizárólag saját CLI/handler/adatírási ág és ezek tesztje érintett; nincs publikus DTO, redirect, pontozás vagy UI-változás.

## Backup és rollback

A saját eredeti modul mentése: `tmp/partner-finance-v2-before/impactshop-partner-finance.php`; eredeti SHA-256: `e3245c9cf0614131f8cd100adb956fb738422f0bc0a417488d30340a842936f3`. A rögzített Git-alap a többi saját fájlt is megőrzi. Pénzügyi írás előtt kompatibilis forrás visszaállítható; írás után az új engedélyezés OFF, naplók/foglalások megőrzése és kompatibilis előre javítás szükséges. Nincs új DDL vagy történeti sorátírás.

## Smoke és kézi ellenőrzőlista

Tervezett célzott ellenőrzések: approver/collector különbség; pontos scope és forráspolicy; eredeti policy/alias megváltozása utáni korrekció; bank- és provider-scope felcserélés; történeti policybizonyíték hiánya; eredeti NGO/capacity/CAS; valódi izolált MySQL rollback és verseny. Nem futtatott ellenőrzés még nem PASS. Telepítés utáni kézi UI-checklist: Impact Shop vásárlási redirect működik, NGO Card nem állít banki teljesítést, profil pont/szint nézet elérhető. CLI állapot és alapból zárt writer stagingen ellenőrizendő; éles próbaátutalás nem része a tesztnek.

## Végrehajtott helyi ellenőrzés és fennmaradó függőség

- PHP 8.4.25: a módosított modul és a két saját teszt szintaxisa érvényes.
- `php tests/sharity-partner-finance.test.php`: 55 ellenőrzés PASS. Csak tiszta helyi szerződés, nincs szolgáltató vagy adatbázis.
- `git diff --check`: PASS, saját eltérésben nincs whitespace-hiba.
- A valódi SQL-futás egyetlen kapcsolati kísérlete `127.0.0.1:13317/owv2_test_payments` címen `Connection refused` hibával a fixture SQL-írása előtt megállt. A V2 SQL-készlet **NOT RUN**, nincs újraindítás vagy ismétlés. A frissített fixture a valódi B V2 API-t használja, nem helyettesíti mockkal a tranzakciós tulajdonost.
- Az SQL-készletben szerepel a pontos grant/scope, collector-elutasítás, eredeti szabály/alias megváltozása utáni korrekció új claim nélkül, hiányzó legacy policybizonyíték, valamint a megtartott valódi rollback és forkos konkurenciaellenőrzés. Ezek nem tekinthetők végrehajtott bizonyítéknak.
- A cél PHP 8.3 és teljes WordPress bootstrap nincs ellenőrizve; pénzügyi merge/deploy nincs V2 SQL PASS nélkül. Nincs commit/push/PR/merge/deploy/aktiválás e V2 csomagban.
- A négy meglévő védelmi inventory/hash fájl és a közös státusz integrációja a koordináló root külön scope-ja; itt nem változik. A sibling donation-finance forrás/teszt eltérés másik engedélyezett ügynök munkája, érintetlenül megőrzendő.


## P1 célzott javítás: bankfüggetlen forrástulajdon

Az önálló review konkrét kettős tőkefoglalási utat jelzett: a korábbi
provider/bankszámla/tranzakció kulcs más fogadó bankon új sort engedett volna.
Az új kulcs `partner-source-v2`, provider, provider-scope, jogi személy,
test/live mód, provider-tranzakció tuple SHA-256-ja. Bank, policyverzió és
felosztási arány nem kulcstag. A meglévő tranzakciótábla marad a tulajdonosi
nyilvántartás; az azonos helyi ID jelöltjei zárolva, saját megtartott
kontextusuk alapján ellenőrződnek. Nem bizonyítható legacy scope mellett az
érintett kimutatás megáll. Bizonyított külön provider-scope azonos helyi ID-t
használhat. A korrekció `transaction_keys` szervermezője a tényleges régi
sorazonosítót őrzi; bizonyított korábbi csomagolt kontextus bankkulcsú sorát
nem kulcsoljuk át. Nincs új tábla, DDL vagy történeti adatmódosítás.

A tiszta 55 ellenőrzés közt a bank/policy/felosztás függetlenség, provider-scope,
jogi személy/mód és provider-ID különbsége ténylegesen futott. Az új natív SQL
bankváltás/scope/legacy/historikus sorazonosság forgatókönyvek szintaxisa érvényes,
de az adatbázis hiányában továbbra is NOT RUN. Független célzott source review
visszaolvasása a root koordinációjában szükséges; a megnevezett reviewer jelenleg
nem szerepelt az elérhető ügynökfában, ezt jeleztem, review PASS nem állítható.

## Koordinált review és Direct HUF/EUR javítás

A root által elért független reviewer a bankfüggetlen tulajdonlás javítását célzott forrás-visszaolvasással elfogadta; új P1-et nem jelzett. Ez nem natív SQL-elfogadás. A korábbi, elérhetetlen reviewerre vonatkozó checkpoint ezzel lezárt.

További érintett fájl: `wp-content/mu-plugins/sharity-ngo-donations/finance-ledger.php` és `tests/sharity-donation-finance-target-mysql.test.php`. A Direct kézi zárás azonos fizetési/elszámolási devizájú HUF/EUR pontos minor-stringeket enged; a fund0, deficit-, CAS- és történeti ellenőrzés megmarad. Nem készül banki elfogadás vagy amplifier-feloldás ebből a zárásból. Konkrét kockázat: deviza összekeverése vagy nem egyeztetett nettó véglegesítése; USD/GBP, eltérő settlement és amplifier továbbra elutasított. A Git-alap őrzi az eredeti forrást. A fenti pénzügyi rollback-szabály e modulra is érvényes.

Tényleges helyi teszt: partner55, Direct-close25, donationCLI461 és HTTP356 ellenőrzés sikeres; érintett PHP-lint és meglévő affiliate-runtime Bástya tamper teszt sikeres. A CLI/HTTP a már meglévő SDK támogatott autoload-beállításával futott. A natív EUR SQL és teljes WordPress-bootstrap NOT RUN; targetPHP nincs igazolva. Kézi UI: HUF/EUR saját adomány pontos deviza/nettó; idegen rendelés nem olvasható; refund után régi jóváhagyás nem él; NGO Card ne állítson banki teljesítést puszta kézi zárásból.

Csak e két saját termékfájl inventory-digestje és a manifest checksum frissült. Nincs idegen hash-rebaseline, guardprogram-módosítás vagy runtime-aktiválás. A korábbi négy inventoryfájlt említő terv helyett a meglévő két hashfájl elegendő.

Érintett protected leltárfájlok: `docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`, `docs/bastion-guard-status.md`. A manifestben kizárólag a két fent megnevezett saját PHP SHA-256 értéke változott; a többi hash és a történeti timestamp megmaradt. A célzott két hash/manifest-checksum readback ténylegesen PASS, ez nem teljes inventory- vagy éles Bástya-elfogadás.

## Rögzített-main integráció és előzménymegőrzés

Az egyszer rögzített main `6737b60fef223cc92a278402c162ba5a1b3c13c3`; a saját PHP-k és három teszt byteazonosak az első publikált jelölttel. A main új kupon/CJ/TradeTracker és más változásai megmaradtak; a teljes main-hashleltárhoz képest csak a két saját PHP digestje különbözik. A normál Git recovery a publikált `eccf05cacdf06bf27618ca6c04912e3627d2f7fd` előzményt is megőrzi. Független review előre elfogadta az exact `7f18ccfac7ad46311eea33e374e643ed3a0997e5` tree-t; a recovery commit tree/parent visszaolvasása egyezett. Az elutasított nem-fast-forward push nem került megkerülésre; az ezt követő publikálás normál fast-forward, history-átírás nélkül.

A mainből átvett guard inventory miatt a teljes outgoing-range smoke-scope `deploy:checksum-verify,deploy:guard-preflight` címkét is igényel. Tényleges ellenőrzés: `tests/impactshop-guard-preflight-detached.test.sh` izolált mock PASS; a két saját hash, minden main-hash megőrzése és manifest-checksum readback PASS. Ez nem élő host-preflight, remote deploy, staging UI vagy pénzügyi elfogadás. A source-grant és rollback változatlan.
