# Impact Art — részletes master koncepció

**Dátum:** 2026-08-12  
**Státusz:** koncepció / rendszerterv  
**Cél:** az Impact Art teljes termék-, AI-, jogi-, pénzügyi-, bizonyítékkezelési és integrációs koncepciójának egységes dokumentálása.  
**Kapcsolódó rendszerek:** Sharity / ImpactShop / ImpactHub, `ai-agent`, FactLens.  
**Megjegyzés:** a dokumentum tudatosan nem tartalmaz árbevételi előrejelzést, befektetési igényt vagy értékelést. A hangsúly a termékkoncepción, az újrahasznosítható technológiai alapokon és a megvalósítási architektúrán van.

---

## 1. Rövid definíció

Az **Impact Art** egy **AI-központú művészeti projektfinanszírozási, megvalósítási, jogkezelési és hasznosítási platform**, amely egy mű teljes életútját kezeli az ötlettől és a jogi-pénzügyi előkészítéstől a finanszírozáson és megvalósításon át a bemutatásig, értékesítésig, licencelésig és későbbi felhasználásokig.

A platform alapvető különbsége egy klasszikus crowdfunding oldallal szemben az, hogy nem a kampány lezárása a termék vége. A finanszírozás csak egy állomás. Az Impact Art központi objektuma maga a **mű / kreatív projekt**, amelyhez tartósan kapcsolódik:

- az alkotói és jogosulti struktúra;
- a szerződések és felhasználási jogok;
- a projekt költségvetése és pénzügyi eseményei;
- a finanszírozási kampány;
- a megvalósítási mérföldkövek;
- az állítások és bizonyítékok;
- a közösségi aktivitás;
- az elkészült mű státusza;
- az értékesítési és licencelési lehetőségek;
- a későbbi felhasználások és kapcsolódó szerződések.

Az Impact Art ezért nem egyszerűen „művészeti Kickstarter”, hanem egy **Creative IP Lifecycle Platform**: a mű létrejöttének, finanszírozásának, jogi rendezettségének és későbbi hasznosításának egységes operációs rendszere.

---

## 2. A megoldandó probléma

A művészeti projektek jelenlegi digitális infrastruktúrája jellemzően széttöredezett.

Egy alkotó vagy produkció külön rendszerben kezeli:

1. a projekt bemutatását;
2. a közösségi finanszírozást;
3. a szerződéseket;
4. a szerzői jogokat;
5. a költségvetést;
6. a számlákat;
7. a támogatók kommunikációját;
8. a projekt mérföldköveit;
9. a jegy- vagy termékértékesítést;
10. a későbbi licenceket;
11. a bizonyítékokat és projektállításokat;
12. a partneri és vállalati kampányokat.

A Patreon elsősorban alkotó–rajongó előfizetéses kapcsolatot kezel. A Kickstarter és hasonló crowdfunding platformok elsősorban egy projekt finanszírozási szakaszát kezelik. Ezek jellemzően nem építenek tartós, strukturált IP-dossziét, nem követik végig a jogláncot, nem kezelik integráltan a projekt pénzügyi truth layerét, és nem viszik tovább automatikusan az elkészült művet licencelési vagy későbbi felhasználási életciklusba.

Az Impact Art ezt a széttagoltságot kívánja megszüntetni.

---

## 3. Termékfilozófia

### 3.1. A központi objektum a mű

A platform központi adatobjektuma nem a crowdfunding kampány, hanem a **Creative Work / Creative Project**.

Egy kampány lezárulhat, de a mű megmarad. Ehhez a műhöz később új esemény, licenc, felhasználás, verzió, kiadás, fordítás, előadás vagy partnerkapcsolat kapcsolódhat.

### 3.2. Az AI a központi operációs réteg

Az AI nem egy külön chatablak vagy kiegészítő funkció, hanem a platform működését összefogó intelligens orchestrator.

Feladata többek között:

- a projekt intake irányítása;
- dokumentumok és szerződések feldolgozása;
- strukturált adatok kinyerése;
- jogi hiányok és kockázatok felismerése;
- pénzügyi adatok és költségvetési elemek értelmezése;
- mérföldkövek követése;
- bizonyítékok és projektállítások összekapcsolása;
- workflow routing;
- manuális ellenőrzést igénylő ügyek elkülönítése;
- licencelési lehetőségek előkészítése;
- támogató és partner felé megjelenő információk kontextusfüggő előállítása.

Az AI nem helyettesíti a jogi vagy pénzügyi felelőst. A cél a **human-in-the-loop**, auditálható működés: az AI feldolgoz, rendszerez, ellenőriz és javasol; kritikus pontokon ember hagy jóvá.

### 3.3. A finanszírozás csak egy modul

A crowdfunding fontos belépési pont, de nem a rendszer teljes értékajánlata.

A mű életciklusa:

`ötlet → intake → jogi/pénzügyi előkészítés → finanszírozás → megvalósítás → mérföldkő-ellenőrzés → bemutatás → értékesítés → licencelés → új felhasználások → archiválás / további életciklus`

---

## 4. A mű teljes életútja lépésenként

### 4.1. Projekt létrehozása

Az alkotó létrehozza a projektet, és megadja legalább:

- a projekt címét;
- a mű típusát;
- rövid és hosszú leírását;
- alkotók és közreműködők adatait;
- tervezett költségvetést;
- finanszírozási igényt;
- várható mérföldköveket;
- tervezett bemutatást vagy hasznosítást;
- rendelkezésre álló jogi dokumentumokat;
- meglévő és még hiányzó jogosultságokat.

### 4.2. AI intake és dokumentumfeldolgozás

Az AI feldolgozza a feltöltött anyagokat:

- szerződéseket;
- költségvetéseket;
- forgatókönyvet;
- treatmentet;
- műleírást;
- pályázati dokumentumokat;
- jognyilatkozatokat;
- partneri dokumentumokat;
- árajánlatokat;
- ütemterveket.

A dokumentumokból strukturált adatot hoz létre, amely bekerül a projekt kanonikus modelljébe.

### 4.3. Legal gate

A Legal motor megvizsgálja például:

- ki a szerző;
- van-e több szerző vagy közös mű;
- ki rendelkezik a vagyoni jogokkal;
- milyen felhasználási jogok kerültek átadásra;
- van-e területi vagy időbeli korlátozás;
- van-e kizárólagosság;
- engedélyezett-e az átdolgozás;
- engedélyezett-e a további licencelés;
- megfelelően rendezettek-e a közreműködők jogai;
- szükséges-e harmadik személy engedélye;
- milyen jog hiányzik a tervezett későbbi hasznosításhoz.

A rendszer nem pusztán „pass/fail” eredményt ad, hanem strukturált státuszt:

- `VERIFIED`;
- `PARTIALLY_VERIFIED`;
- `MISSING_DOCUMENT`;
- `DISPUTED`;
- `EXPIRED`;
- `MANUAL_REVIEW_REQUIRED`;
- `BLOCKING_ISSUE`.

### 4.4. Finance gate

A Finance motor felépíti vagy validálja:

- a projekt költségvetését;
- a finanszírozási célokat;
- költségkategóriákat;
- mérföldkövekhez kapcsolódó összegeket;
- bejövő pénzügyi eseményeket;
- kifizetéseket;
- kapcsolódó bizonylatokat;
- eltéréseket a tervhez képest;
- manual-review tételeket.

A cél nem feltétlenül az, hogy a publikus felhasználó teljes könyvelési adatot lásson, hanem hogy a projekt mögött legyen auditálható és konzisztens pénzügyi truth layer.

### 4.5. FactLens evidence layer

A FactLens logikája a projekt állításait forrásokhoz és bizonyítékokhoz kapcsolja.

Példa:

**Állítás:** „A produkció rendelkezik a forgatókönyv megfilmesítési jogával.”  
**Forrás:** felhasználási szerződés.  
**Bizonyíték:** a szerződés meghatározott pontja.  
**Státusz:** `verified`.  
**Nyitott kérdés:** a streaming sublicence külön nincs szabályozva.

A FactLens szerepe, hogy az AI ne keverje össze:

- a tényt;
- az állítást;
- a következtetést;
- az ellentmondást;
- a bizonytalanságot;
- a nyitott kérdést.

A platform így nem egyszerű dokumentumtárat épít, hanem visszakövethető **claim–source–evidence–status** struktúrát.

### 4.6. Finanszírozási kampány

Ha a szükséges minimum jogi és pénzügyi gate-ek teljesülnek, a projekt publikálható és finanszírozható.

Az első verzióban támogatott finanszírozási modellek:

1. közvetlen támogatás;
2. jutalomalapú támogatás;
3. előrendelés;
4. vállalati matching;
5. ImpactShop-vásárlásokhoz kapcsolódó támogatás;
6. szponzorált aktivitásból keletkező támogatás;
7. meghatározott támogatói csomaghoz kapcsolódó, korlátozott felhasználási jogosultság.

### 4.7. Megvalósítás

A kampány sikerével nem zárul le a projekt. A rendszer átáll megvalósítási módba.

Követhető:

- mely mérföldkövek teljesültek;
- milyen dokumentum igazolja a teljesítést;
- milyen költségek kapcsolódnak hozzá;
- mi késik;
- milyen új kockázat jelent meg;
- szükséges-e jogi vagy pénzügyi újraellenőrzés.

### 4.8. Bemutatás és elsődleges hasznosítás

Az elkészült műhöz kapcsolódhat:

- premier;
- kiállítás;
- színházi előadás;
- könyvmegjelenés;
- koncert;
- fesztivál;
- digitális bemutató;
- jegyértékesítés;
- előrendelés teljesítése;
- kapcsolódó merchandise.

### 4.9. Licencelés és másodlagos hasznosítás

A mű a kampány lezárása után is aktív objektum marad.

Példák:

- film: iskolai vetítés, vállalati vetítés, fesztivál, streaming, televízió;
- színdarab: előadási jog, amatőr előadás, fordítás, közvetítés;
- képzőművészeti alkotás: könyvborító, plakát, reklám, csomagolás, nyomat;
- illusztráció: digitális és nyomtatott licenc;
- zene: esemény-, média- vagy szinkronlicenc;
- könyv: fordítási, hangoskönyv- vagy adaptációs jog.

A cél egy olyan piactér, ahol a felhasználási paraméterek részben standardizálhatók, az AI pedig segít megállapítani, hogy a kívánt licenc a rendelkezésre álló joglánc alapján kiadható-e.

---

## 5. Miért nem bevételrészesedéssel indulna

A korai koncepcióban felmerült, hogy a támogatók a mű későbbi bevételeiből vagy jogdíjaiból részesedjenek. Nemzetközileg léteznek royalty crowdfunding és royalty investment modellek, különösen zenében.

Az Impact Art első verziójában ezt tudatosan nem célszerű központi modellé tenni.

Okok:

1. pénzügyi és tőkepiaci szabályozási kockázat;
2. a konstrukció egyes formái pénzügyi eszköz vagy befektetési szolgáltatás minősítési kérdését vethetik fel;
3. másodpiac esetén tovább nő a szabályozási komplexitás;
4. számos művészeti ágban nehezen mérhető és auditálható a teljes bevételi lánc;
5. festménynél, színháznál vagy összetett produkciónál a „bevétel” fogalma és forrása sokkal kevésbé standardizált, mint streaming jogdíjaknál.

Ezért az MVP elsődleges logikája:

- támogatás;
- reward;
- preorder;
- élmény;
- státusz;
- limitált vagy standard felhasználási jogosultság;
- később önálló licencértékesítés.

Egy későbbi, külön jogi projektként vizsgálható szabályozott vagy partneren keresztül megvalósított royalty / investment lane, de ezt nem szükséges az Impact Art indulásához megoldani.

---

## 6. Az AI központi szerepe

### 6.1. AI mint orchestrator

Az AI a rendszer különálló motorjai között routing rétegként működik.

Például:

```text
Projekt intake
    ↓
AI dokumentum- és kontextusfeldolgozás
    ↓
┌───────────────┬───────────────┬────────────────┐
│ Legal motor   │ Finance motor │ FactLens motor │
└───────────────┴───────────────┴────────────────┘
    ↓
AI összesített project state
    ↓
Automatikus lépés / dokumentumpótlás / human review
```

### 6.2. AI-feladatok

Az AI képes lehet:

- projektinterjút vezetni;
- hiányzó adatokat bekérni;
- dokumentumtípust felismerni;
- dokumentumot kivonatolni;
- szerződésből jogokat és kötelezettségeket kinyerni;
- költségvetést strukturálni;
- határidőket felismerni;
- mérföldkövet javasolni;
- eltéréseket és ellentmondásokat jelezni;
- licencparamétereket értelmezni;
- releváns jogi workflow-t meghívni;
- pénzügyi státuszt lekérni;
- FactLens evidence státuszt figyelembe venni;
- kockázati score-t készíteni;
- operátori briefet generálni;
- támogató számára érthető státuszfrissítést készíteni;
- alkotói és partneri dashboardon kontextusfüggő segítséget adni.

### 6.3. Human-in-the-loop

A rendszerben külön kell választani:

**Automatizálható:**

- adatkinyerés;
- formai ellenőrzés;
- összevetés;
- hiányjelzés;
- routing;
- draft;
- riport;
- státuszösszesítés.

**Emberi jóváhagyást igénylő:**

- vitatott szerzői jog;
- jelentős jogi kockázat;
- atipikus licenc;
- nagy értékű vagy összetett pénzügyi eltérés;
- szerződéses kivétel;
- projektpublikálást blokkoló ügy;
- végső szakmai/jogi döntés.

---

## 7. Legal motor szerepe

A meglévő `ai-agent` legal infrastruktúra az Impact Art egyik legerősebb újrahasznosítható komponense.

A meglévő rendszerben már rendelkezésre áll többek között:

- legal KB;
- RAG pipeline;
- jogi kérdés-válasz;
- dokumentum- és szerződéselemzés;
- legal tool routing;
- strukturált memória;
- task és matter jellegű workflow;
- dokumentum ingest;
- jogi agent modulok;
- auditálható eszközhívások.

Az Impact Artban ehhez új kreatív/IP domain szabályok szükségesek.

### 7.1. Javasolt kreatív jogi modulok

- `creative_ip_intake`;
- `authorship_check`;
- `rights_chain_analysis`;
- `licence_scope_analysis`;
- `third_party_material_check`;
- `performer_rights_check`;
- `music_rights_check`;
- `adaptation_rights_check`;
- `territory_term_check`;
- `sublicensing_check`;
- `ai_generated_content_check`;
- `creative_project_legal_gate`.

### 7.2. Jogi dosszié

A rendszerben minden műhöz strukturált jogi dosszié tartozik.

Példa minimum mezők:

- szerző(k);
- szerzőtársak;
- vagyoni jogok jogosultja;
- előadóművészek;
- producer / kiadó / társulat;
- forrásművek;
- harmadik személytől származó elemek;
- licencek;
- területi hatály;
- időtartam;
- kizárólagosság;
- sublicence;
- átdolgozás;
- online/streaming jog;
- dokumentumok;
- bizonyítékok;
- nyitott kérdések;
- vitatott pontok.

---

## 8. Finance motor szerepe

A meglévő finance rendszer jelenlegi kanonikus iránya input → processing → company state → KB → könyvelési bridge logikára épül. Az Impact Artban ezt nem kell újraépíteni, hanem egy projekt-szintű creative finance adapterrel kell bővíteni.

### 8.1. Creative project finance truth layer

Javasolt elemek:

- `ProjectBudget`;
- `BudgetLine`;
- `FundingSource`;
- `SupporterPayment`;
- `CorporateContribution`;
- `AffiliateContribution`;
- `SupplierInvoice`;
- `ArtistFee`;
- `LicenceFee`;
- `MilestoneAllocation`;
- `Refund`;
- `Variance`;
- `ManualReviewItem`.

### 8.2. Három külön ledger

Fontos, hogy ne keverjük össze a különböző truth layereket.

#### Activity ledger

Azt rögzíti, hogy a felhasználó mit tett:

- támogatott;
- vásárolt;
- videót nézett;
- szavazott;
- eseményen vett részt;
- megosztott;
- feladatot teljesített.

#### Project funding ledger

Azt rögzíti, hogy a projekt finanszírozási oldalán mi történt:

- közvetlen támogatás;
- affiliate támogatás;
- szponzorált aktivitás;
- vállalati keret;
- preorder;
- reward purchase.

#### Accounting truth layer

A pénzügyi/számviteli valóság:

- beérkező összeg;
- díjak;
- kifizetés;
- bizonylat;
- számla;
- visszatérítés;
- elszámolás;
- manuális review.

A három réteg közös `event_id`, `project_id` és megfelelő kapcsolati azonosítók alapján kapcsolható össze.

---

## 9. FactLens motor szerepe

A FactLens legfontosabb újrahasznosítható eleme nem önmagában a publikus fact-check oldal, hanem a strukturált bizonyítéki gondolkodás.

A FactLens célarchitektúrájának alapelvei:

- tény és következtetés elkülönítése;
- elsődleges és másodlagos forrás elkülönítése;
- nyitott kérdés nem zárható le automatikusan;
- ellentmondás explicit státuszt kap;
- bizonytalanság nem konvertálható hamis bizonyossággá;
- a rendszer kanonikus strukturált tudásrétegből képezhet publikus kimenetet.

### 9.1. Impact Art evidence modell

Javasolt entitások:

```text
Claim
Source
Evidence
VerificationStatus
Contradiction
OpenQuestion
Provenance
ReviewDecision
```

### 9.2. Hol használjuk

FactLens evidence rekord kapcsolódhat:

- szerzői jogi állításhoz;
- partneri vállaláshoz;
- finanszírozási állításhoz;
- mérföldkő teljesítéséhez;
- költséghez;
- eseményhez;
- alkotói credentialhöz;
- licencjoghoz;
- projektzáró eredményhez.

### 9.3. AI guardrail

Az AI csak olyan erős állítást tehet, amilyen erős a mögötte álló evidence státusz.

Például:

- `verified` → tényszerűen kommunikálható;
- `partially_verified` → korlátozással kommunikálható;
- `disputed` → vitatottként kell megjeleníteni;
- `open` → nyitott kérdésként kell kezelni;
- `missing_evidence` → nem kommunikálható igazolt tényként.

---

## 10. Sharity / ImpactHub / ImpactShop szerepe

Az Impact Art nem önállóan, nulláról induló közösségi platformként épülne.

A meglévő Sharity ökoszisztéma több fontos alapot ad:

- felhasználói identitás / pseudo-ID;
- közösségi aktivitás;
- pontok;
- szintek;
- badge-ek;
- ranglisták;
- szavazás;
- kampánylogika;
- affiliate attribution;
- támogatási mechanikák;
- partneri kapcsolódás;
- corporate experience irány;
- fraud monitor;
- audit log;
- feature flag és rollout guardok.

### 10.1. Impact Art aktivitások

Új aktivitástípusok lehetnek:

- első művészeti projekt támogatása;
- több különböző alkotó támogatása;
- projekt megosztása;
- kulturális eseményen részvétel;
- projekt mérföldkő elérése;
- community vote;
- előrendelés;
- licenc vagy jegy vásárlása.

### 10.2. Badge-ek

Példák:

- `Founding Patron`;
- `First Film Support`;
- `Culture Explorer`;
- `Local Patron`;
- `Project Finisher`;
- `Creative Ambassador`.

A badge-ek nem pénzügyi jogot testesítenek meg. Közösségi státuszt, mérföldkövet és élményt jelentenek.

---

## 11. Beágyazható, valós idejű Card Engine

Az ImpactShop NGO Card technológiájának fontos eleme, hogy egy entitás adatai API-n keresztül, valós időben vagy kontrollált cache-eléssel jeleníthetők meg más weboldalon. Ez az Impact Artban általánosítható **Impact Card Engine** formájában.

A kártya nem a platform központi terméke, hanem a platform adatainak hordozható, tranzakcióképes megjelenítési rétege.

### 11.1. Kártyatípusok

- `Project Card`;
- `Artwork Card`;
- `Artist Card`;
- `Licence Card`;
- `Event Card`;
- `Patron Card`;
- `Corporate Culture Card`.

### 11.2. Beágyazási helyek

- alkotó saját weboldala;
- fesztivál;
- kulturális portál;
- sajtócikk;
- önkormányzati oldal;
- iskola;
- vállalati partneroldal;
- rendezvényoldal;
- blog;
- QR-kód mögötti landing.

### 11.3. Real-time adat

A kártya frissítheti:

- finanszírozási állapot;
- mérföldkövek;
- projektstátusz;
- következő cél;
- esemény;
- jegy;
- reward;
- licencelhetőség;
- ellenőrzött státusz;
- CTA.

### 11.4. Tranzakciós CTA-k

A kártyáról indítható:

- támogatás;
- előrendelés;
- jegyvásárlás;
- reward kiválasztás;
- licencigénylés;
- projektmegosztás;
- eseményregisztráció.

### 11.5. Attribution

Minden embed és QR kapcsolat kaphat:

- `campaign_key`;
- `partner_id`;
- `placement_id`;
- `referral_source`;
- `utm_*` paramétereket.

Így a platform nemcsak elosztja a tartalmat, hanem vissza is méri, hogy melyik partner, oldal vagy kampány generált érdeklődést és aktivitást.

---

## 12. Javasolt kanonikus adatmodell

### 12.1. CreativeWork

```typescript
type CreativeWork = {
  id: string;
  slug: string;
  title: string;
  workType: WorkType;
  status: WorkStatus;
  creators: ContributorRef[];
  versions: WorkVersion[];
  rightsClaims: RightsClaim[];
  licences: Licence[];
  evidenceRefs: EvidenceRef[];
  createdAt: string;
  updatedAt: string;
};
```

### 12.2. RightsClaim

```typescript
type RightsClaim = {
  id: string;
  workId: string;
  claimantId: string;
  rightType: RightType;
  territory: string[];
  validFrom?: string;
  validUntil?: string;
  exclusive: boolean;
  sublicensable: boolean;
  status:
    | "claimed"
    | "verified"
    | "partially_verified"
    | "disputed"
    | "expired"
    | "manual_review";
  evidenceRefs: string[];
};
```

### 12.3. FundingProject

```typescript
type FundingProject = {
  id: string;
  workId: string;
  targetAmount: number;
  currency: "HUF" | "EUR";
  fundingModel: "donation" | "reward" | "preorder" | "licence";
  milestones: Milestone[];
  supporterOffers: SupporterOffer[];
  legalGateStatus: GateStatus;
  financeGateStatus: GateStatus;
  evidenceGateStatus: GateStatus;
};
```

### 12.4. Licence

```typescript
type Licence = {
  id: string;
  workId: string;
  licenseeId: string;
  useTypes: UseType[];
  territory: string[];
  startDate: string;
  endDate: string;
  exclusive: boolean;
  sublicensable: boolean;
  feeModel: "fixed" | "usage_based" | "revenue_based";
  status: LicenceStatus;
  legalReviewStatus: GateStatus;
};
```

### 12.5. EvidenceRecord

```typescript
type EvidenceRecord = {
  id: string;
  projectId: string;
  claimId: string;
  sourceType: string;
  sourceRef: string;
  status: "verified" | "partial" | "disputed" | "open" | "missing";
  reviewedBy?: string;
  reviewedAt?: string;
};
```

### 12.6. ImpactEvent

```typescript
type ImpactEvent = {
  eventId: string;
  userId?: string;
  projectId: string;
  campaignId?: string;
  partnerId?: string;
  placementId?: string;
  activityType: string;
  grossValue?: number;
  allocatedImpactValue?: number;
  accountingDocumentId?: string;
  occurredAt: string;
};
```

---

## 13. Felhasználói szerepkörök

### 13.1. Alkotó / projektgazda

- projektet hoz létre;
- dokumentumot tölt fel;
- finanszírozási célt állít be;
- mérföldkövet vállal;
- státuszt frissít;
- rewardot hoz létre;
- licencelhető jogokat jelöl;
- AI segítséget kap;
- hiánypótlást teljesít.

### 13.2. Támogató / mecénás

- projektet keres;
- támogat;
- rewardot választ;
- előrendel;
- követi a projektet;
- státuszt és mérföldkövet lát;
- eseményhez csatlakozik;
- badge-et és státuszt szerez.

### 13.3. Vállalati partner

- kampányt finanszíroz;
- projektlistát vagy keretet biztosít;
- matchinget ad;
- saját oldalába cardot ágyaz;
- aktivitást mér;
- projektstátuszt követ.

### 13.4. Licencvásárló

- művet keres;
- felhasználási módot választ;
- licencet konfigurál;
- AI előzetes jogosultság-ellenőrzést kap;
- ajánlatot kér vagy standard licencet vásárol;
- szerződést és használati státuszt kezel.

### 13.5. Operátor

- projektet review-z;
- jogi és pénzügyi gate-et lát;
- FactLens evidence státuszt ellenőriz;
- manual-review queue-t kezel;
- publikálást vagy mérföldkövet jóváhagy;
- kivételt dokumentál.

### 13.6. Jogi / pénzügyi szakértő

- AI által előkészített ügyet review-z;
- döntést rögzít;
- evidence-t erősít vagy vitat;
- blokkoló státuszt felold;
- audit trailt hoz létre.

---

## 14. Műfaj-specifikus alkalmazás

### 14.1. Film

Jól standardizálható, ezért jó MVP-terület.

Kezelhető:

- forgatókönyv jogai;
- szereplők;
- zene;
- archív anyag;
- forgatási mérföldkövek;
- utómunka;
- fesztivál;
- premier;
- oktatási vetítés;
- vállalati vetítés;
- streaming licencek.

### 14.2. Színház

Kezelhető:

- eredeti mű vagy adaptáció;
- fordítás;
- előadási jog;
- zene;
- rendezői és közreműködői szerződések;
- helyszín;
- főpróba;
- premier;
- felvétel;
- stream;
- más társulatnak adott előadási jog.

### 14.3. Képzőművészet

Kezelhető:

- mű adatai;
- alkotó;
- provenance;
- kiállítás;
- eladás;
- reprodukció;
- könyvborító;
- plakát;
- csomagolás;
- merchandising;
- limitált nyomat.

### 14.4. Könyv

Kezelhető:

- kézirat;
- szerző;
- szerkesztő;
- illusztrátor;
- kiadói jog;
- előrendelés;
- első kiadás;
- fordítás;
- hangoskönyv;
- adaptáció.

### 14.5. Zene

Kezelhető:

- szerző;
- szövegíró;
- előadó;
- master;
- kiadó;
- streaming;
- esemény;
- szinkronlicenc;
- később akár külön royalty lane.

---

## 15. Licencpiactér

### 15.1. Cél

A licencpiactér feladata, hogy a mű létrejötte után egyszerűbbé tegye a jogszerű felhasználást.

Nem minden licenc automatizálható, de sok tipikus felhasználás részben standardizálható.

### 15.2. Standard licenctermékek

Példák:

- egyszeri iskolai filmvetítés;
- nonprofit rendezvényi vetítés;
- vállalati belső vetítés;
- könyvborító licenc;
- egyszeri plakátfelhasználás;
- limitált nyomat;
- amatőr színházi előadási jog;
- digitális illusztráció felhasználás.

### 15.3. Licenc konfigurátor

A vevő megadja:

- felhasználási mód;
- terület;
- időtartam;
- példányszám / nézőszám;
- médium;
- kereskedelmi vagy nem kereskedelmi cél;
- kizárólagosság;
- átdolgozás;
- sublicence igény.

Az AI ezután:

1. összeveti a kért jogot a kanonikus rights modellel;
2. jelzi, ha kiadható;
3. jelzi, ha emberi review kell;
4. előkészíti a szerződést;
5. létrehozza a licence recordot;
6. figyeli a hatályt és lejáratot.

---

## 16. Közösségi és vállalati finanszírozás

### 16.1. Közvetlen támogatás

A legegyszerűbb modell: a támogató hozzájárul a projekthez, pénzügyi részesedés nélkül.

### 16.2. Reward

Példák:

- dedikált példány;
- premier;
- főpróba;
- műteremlátogatás;
- workshop;
- név a stáblistán;
- limitált nyomat.

### 16.3. Preorder

A támogatás konkrét későbbi termékhez vagy hozzáféréshez kapcsolódik.

### 16.4. ImpactShop-alapú támogatás

A felhasználó vásárlása affiliate értéket generál, amely meghatározott szabály alapján művészeti projekthez allokálható.

### 16.5. Szponzorált aktivitás

Vállalat finanszírozhat:

- videómegtekintést;
- kérdőívet;
- kulturális kvízt;
- kampányaktivitást;
- közösségi kihívást.

A felhasználói aktivitásból támogatási érték keletkezik, amely projekt(ek)hez rendelhető.

### 16.6. Corporate matching

A vállalat vállalhatja, hogy egy meghatározott időszakban vagy keretig megduplázza a közösségi támogatást.

---

## 17. Projekttranszparencia

A platform célja nem az, hogy minden belső dokumentumot publikussá tegyen, hanem az, hogy a támogató számára ellenőrizhető és érthető projektállapotot adjon.

Publikus lehet például:

- finanszírozási százalék;
- fő mérföldkövek;
- teljesült / folyamatban / késik státusz;
- rövid indokolás;
- evidence-backed státusz;
- következő cél;
- projektfrissítés;
- fontos jogi vagy megvalósítási korlátozás, ha releváns.

Belső maradhat:

- érzékeny szerződés;
- személyes adat;
- bankszámlaadat;
- üzleti titok;
- részletes számviteli rekord;
- operátori megjegyzés.

---

## 18. Technikai architektúra

### 18.1. Javasolt rendszerhatárok

#### `impactshop-notes` / Sharity runtime

Szerep:

- publikus és közösségi UX;
- kampány;
- activity;
- pont/badge;
- attribution;
- támogatási entrypoint;
- embed/card runtime;
- partneri és corporate surface.

#### `ai-agent`

Szerep:

- AI orchestration;
- legal workflow;
- finance workflow;
- document extraction;
- RAG / KB;
- rights analysis;
- project gates;
- manual review;
- licensing logic;
- internal operator intelligence.

#### `factlens-site`

A publikus FactLens terméket nem szükséges összekeverni az Impact Art frontenddel. A közösíthető rész a structured evidence model és a hozzá tartozó guardrail-logika.

### 18.2. Javasolt közös csomag

Hosszabb távon célszerű lehet:

```text
packages/evidence-model/
  claim.ts
  source.ts
  evidence.ts
  verification-status.ts
  contradiction.ts
  open-question.ts
  provenance.ts
```

### 18.3. Javasolt Impact Art domain az AI Agentben

```text
src/creative-ip/
  intake/
  domain/
  rights/
  contracts/
  funding/
  finance/
  evidence/
  milestones/
  licensing/
  risk/
  workflows/
  operator/
```

---

## 19. Biztonság és audit

A meglévő ImpactShop és AI Agent rendszerek guard, audit és manual-review szemléletét meg kell tartani.

Alapelvek:

- fail-closed kritikus jogi vagy pénzügyi bizonytalanságnál;
- minden státuszváltás auditálható;
- emberi override indokolással;
- érzékeny adat nem kerül publikus kártyára;
- publikált projekt csak megfelelő gate után;
- licenc csak igazolt jogkörből adható;
- AI output nem írhatja felül a kanonikus truth layert;
- evidence nélküli állítás nem válik automatikusan ténnyé;
- feature flag és staged rollout.

---

## 20. Mi létezik már

Az Impact Art legfontosabb stratégiai előnye, hogy a szükséges alaptechnológia jelentős része már más célra elkészült.

### 20.1. AI Agent — meglévő alap

A `ai-agent` rendszerben már léteznek:

- agent orchestration elemek;
- legal domain modulok;
- legal KB és RAG;
- dokumentum ingest;
- tool rendszer;
- memória;
- gateway;
- worker;
- web frontend;
- szerződéselemzési és legal workflow elemek;
- finance lane.

**Impact Art feladat:** új creative/IP domain, projekt state és adapterek hozzáadása.

### 20.2. Legal motor — meglévő alap

Létezik jogi kérdés-válasz, dokumentumelemzés, KB, RAG és workflow infrastruktúra.

**Impact Art feladat:** szerzői jogi és kreatívipari taxonómia, gate-ek, licencek, rights chain.

### 20.3. Finance motor — meglévő alap

Létezik canonical finance terv, NAV state, accounting truth layer irány, manual review, health check és RLB bridge.

**Impact Art feladat:** projekt-szintű költségvetési és funding adapter.

### 20.4. FactLens — meglévő alap

Létezik működő FactLens termék és kidolgozott strukturált evidence/knowledge architektúra.

**Impact Art feladat:** a reusable evidence modellt és guardrail elveket leválasztani és creative project domainre alkalmazni.

### 20.5. ImpactHub / ImpactShop — meglévő alap

Léteznek vagy erősen kialakítottak:

- támogatási mechanikák;
- affiliate pipeline;
- user identity;
- pontok;
- badge-ek;
- voting;
- campaign;
- ledger gondolkodás;
- corporate direction;
- fraud / audit;
- real-time API felületek.

**Impact Art feladat:** művészeti projekt entitás és támogatási use case hozzáadása.

### 20.6. Embed/Card — meglévő alap

Az NGO Card már bizonyítja a technikai mintát:

- slug alapú objektum;
- REST API;
- valós idejű / cache-elt adat;
- több megjelenési variant;
- beágyazható JS;
- CTA;
- share;
- QR;
- rate limiting;
- approval;
- widget használat más oldalon.

**Impact Art feladat:** NGO-specifikus adatmodellt általános Impact Card Engine-re cserélni/adapterezni.

---

## 21. Készültségi becslés

A százalékok **nem formális code audit eredményei**, hanem a jelenlegi repók, dokumentált rendszerek és az Impact Art célállapot összevetéséből származó architekturális becslések.

### 21.1. Újrahasznosítható technológiai alap

**Kb. 60–65% rendelkezésre áll.**

Ez azt jelenti, hogy a szükséges általános motorok nagy része már létezik, de nem Impact Art domainre konfigurálva.

### 21.2. Piacra vihető Impact Art termék

**Kb. 30–35% készültségűnek tekinthető koncepcionális/technológiai értelemben.**

A különbség oka, hogy még nincs egységes Impact Art termék, közös creative project adatmodell és összekötött end-to-end workflow.

### 21.3. Komponensenkénti durva becslés

| Komponens | Meglévő alap | Impact Art-specifikus készültség |
|---|---:|---:|
| AI orchestration | magas | közepes |
| Legal motor | magas | közepes |
| Finance motor | magas/közepes | közepes/alacsony |
| FactLens evidence logika | közepes/magas | közepes |
| Sharity community/campaign | magas | közepes |
| Embed Card technológia | magas | közepes/magas |
| CreativeWork adatmodell | alacsony | alacsony |
| IP dosszié | alacsony | alacsony |
| Creative legal gate | alacsony | alacsony |
| Milestone workflow | részben meglévő minták | alacsony |
| Licence marketplace | alacsony | alacsony |
| End-to-end Impact Art UX | alacsony | alacsony |

A lényeg: **nem az AI, legal, finance, evidence, community vagy embed alaprendszert kell nulláról megépíteni; a fő munka ezek Impact Art-specifikus domainbe szervezése és összekötése.**

---

## 22. MVP javaslat

### 22.1. Első fókusz: dokumentumfilm / rövidfilm

Indokok:

- projektalapú;
- jól kommunikálható;
- mérföldkövek egyértelműek;
- több szerzői jogi réteg van, így a Legal motor értéke látható;
- vállalati és közösségi támogatás is életszerű;
- elkészülés után standardizálható vetítési licencek adhatók;
- a mű digitálisan bemutatható.

### 22.2. MVP funkciók

**Projekt:**

- projektlétrehozás;
- alkotói profil;
- alap creative work entity;
- költségvetés;
- mérföldkő;
- kampány.

**AI:**

- dokumentum intake;
- szerződés extraction;
- project brief;
- legal risk summary;
- finance summary;
- evidence summary;
- operator routing.

**Legal:**

- rights checklist;
- szerzői/jogosulti státusz;
- third-party content;
- blocking issue;
- manual review.

**Finance:**

- target;
- source;
- funding ledger;
- milestone allocation;
- basic variance;
- manual review.

**FactLens:**

- claim/source/evidence;
- verification status;
- open question;
- contradiction.

**Funding:**

- közvetlen támogatás;
- reward;
- preorder;
- ImpactShop contribution;
- corporate matching.

**Card:**

- Project Card;
- real-time progress;
- status;
- CTA;
- embed;
- share;
- attribution.

**Hasznosítás:**

- egyszeri iskolai vetítés;
- nonprofit rendezvényi vetítés;
- vállalati belső vetítés;
- licencigénylés.

### 22.3. Amit az MVP-be nem tennénk

- bevételrészesedés;
- tokenizált royalty;
- blockchain;
- másodlagos piac;
- szabadon forgalmazható befektetési jog;
- teljes, általános művészeti licenctőzsde;
- minden művészeti ág egyszerre.

---

## 23. Fejlesztési fázisok

### Phase 0 — Canonical design

- CreativeWork schema;
- Rights schema;
- Project schema;
- Evidence schema mapping;
- finance mapping;
- user/partner roles;
- gate state machine;
- repo ownership.

### Phase 1 — IP dosszié + AI intake

- projekt létrehozás;
- dokumentumfeltöltés;
- AI extraction;
- rights claim;
- FactLens evidence;
- legal review queue;
- basic operator dashboard.

### Phase 2 — Funding

- campaign page;
- supporter offers;
- direct support;
- ImpactShop support;
- corporate matching;
- project funding ledger;
- project card.

### Phase 3 — Megvalósítás és mérföldkövek

- milestone state;
- evidence upload;
- finance variance;
- legal re-check;
- supporter updates;
- embedded real-time progress.

### Phase 4 — Licence MVP

- standard licence products;
- rights compatibility check;
- licence request;
- legal review;
- contract draft;
- licence record;
- expiry monitoring.

### Phase 5 — Marketplace expansion

- több műfaj;
- keresés;
- partner API;
- corporate procurement surface;
- institution accounts;
- white-label cards;
- broader European localization.

### Phase 6 — Optional regulated finance lane

Csak külön jogi és szabályozási projektként:

- royalty participation;
- revenue share;
- regulated crowdfunding partner;
- financial instrument classification;
- secondary market.

---

## 24. Mi különbözteti meg a Patreontól / Kickstartertől

### Patreon

Fókusz:

- alkotó;
- közösség;
- előfizetés;
- exkluzív tartalom.

Impact Art többlet:

- projekt és mű mint tartós objektum;
- IP dosszié;
- legal gate;
- finance truth;
- evidence;
- mérföldkő;
- licencelés;
- későbbi hasznosítás.

### Kickstarter

Fókusz:

- kampány;
- target;
- reward;
- finanszírozás.

Impact Art többlet:

- kampány előtt jogi/pénzügyi intake;
- kampány alatt evidence-backed transzparencia;
- kampány után megvalósítási workflow;
- elkészült mű tartós nyilvántartása;
- licencpiactér;
- AI-orchestrált jogi és pénzügyi motor.

---

## 25. Stratégiai pozicionálás

Az Impact Artot nem célszerű pusztán „crowdfunding platformként” pozicionálni.

Erősebb definíciók:

- **AI-native Creative IP Lifecycle Platform**;
- **művészeti projekt operációs rendszer**;
- **finanszírozás + jog + megvalósítás + licencelés egy rendszerben**;
- **trusted infrastructure for creative projects**.

A fő állítás:

> Az Impact Art nemcsak segít létrehozni és finanszírozni egy művet, hanem digitálisan végigkíséri és ellenőrizhetővé teszi annak teljes jogi, pénzügyi és üzleti életútját.

---

## 26. Nyitott termékdöntések

A fejlesztés előtt külön döntést igényel:

1. első műfaj végleges kiválasztása;
2. Impact Art külön brand vagy Sharity albrand;
3. publikus projekt és belső IP dosszié adatmegosztási határa;
4. alkotói onboarding követelményei;
5. human legal review SLA;
6. finance gate minimum követelménye;
7. reward vs preorder pontos szerződéses modellje;
8. standard licencek első listája;
9. partneri embed API authentication modell;
10. FactLens reusable evidence package ownership;
11. Impact Art domain melyik repóban legyen kanonikus;
12. WordPress surface hosszú távon marad-e frontend/runtime vagy külön service kerül elé;
13. vállalati kampány és cultural CSR workflow részletei;
14. európai terjeszkedésnél lokalizáció és jogi domain adapterek.

---

## 27. Javasolt repo- és dokumentációs ownership

### `impactshop-notes`

Kanonikus legyen itt:

- Sharity frontend integráció;
- campaign/community/card use case;
- partneri és attribution contract;
- rollout és guard;
- cross-system product documentation.

### `ai-agent`

Kanonikus legyen itt:

- Impact Art AI orchestration;
- Legal motor creative adapter;
- Finance motor creative adapter;
- workflow state machine;
- operator review;
- licence intelligence;
- domain schema backend oldala.

### `factlens-site`

Kanonikus marad:

- FactLens saját termék;
- evidence architecture eredeti domainje.

A reusable evidence contractot célszerű vagy közös csomagba, vagy egyértelműen verziózott API/schema contractba kivezetni.

---

## 28. Kapcsolódó meglévő dokumentumok és komponensek

### ImpactShop / ImpactHub

- `impact-hub-system-v1.3.md`
- `docs/rewarding-system.md`
- `wp-content/mu-plugins/impactshop-ngo-card.php`
- `wp-content/mu-plugins/impactshop-ngo-card.js`
- `docs/VB2026-SHARITY-NGO-CATALOG-AND-SELECTION-PLAN-2026-06-23.md`
- `docs/impactshop-governance-system-plan-2026-06-16.md`

### AI Agent / Legal / Finance

- `docs/AI-AGENT-FULL-REFERENCE.md`
- `docs/legal-agent-architecture-phase28.md`
- `docs/legal-research-playbook.md`
- `docs/finance/finance-canonical-system-plan.md`
- `docs/finance/finance-step1-input-gathering.md`
- `docs/dev-memory/accounting-tax-canonical-context.md`

### FactLens

- `docs/FACTLENS_AI_KNOWLEDGE_ARCHITECTURE_PLAN.md`
- `FACTLENS_COMPONENTS_SPEC.md`
- `scripts/factlens_source_workflow.py`

---

## 29. Összefoglaló célállapot

Az Impact Art célállapotában egy alkotó nem külön crowdfunding-, dokumentum-, jogi-, pénzügyi- és licencrendszereket használ.

Egy projekt létrejön az Impact Artban, az AI végigvezeti az intake-en, a Legal motor ellenőrzi a rights chain-t, a Finance motor felépíti és követi a projekt pénzügyi truth layerét, a FactLens evidence modellje biztosítja, hogy minden fontos állítás visszakövethető legyen, a Sharity közösségi motor finanszírozást és aktivitást generál, a real-time Card Engine pedig a projektet az internet bármely partnerfelületére kiviszi.

A finanszírozás után a rendszer tovább követi a megvalósítást. Az elkészült mű megmarad kanonikus CreativeWork objektumként, amelyhez események, értékesítések, licencek és új felhasználások kapcsolhatók.

A hosszú távú termékígéret ezért nem az, hogy **„segítünk pénzt gyűjteni egy műre”**, hanem az, hogy:

> **„Egyetlen AI-központú rendszerben kezeljük a mű teljes életútját: jogilag rendezzük, finanszírozhatóvá tesszük, ellenőrizhetően végigkísérjük a megvalósítást, majd támogatjuk az értékesítését és jogszerű további felhasználását.”**
