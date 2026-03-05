# ImpactShop Survey Collection - Batch 5 (50 Kérdőívek)

## Áttekintés

**Összes kérdőív:** 50 darab  
**Kérdések száma:** 5 kérdés/kérdőív (összesen 250 kérdés)
**Válasz formátum:** Single choice (A-D)  
**Jutalom:** 10 pont + 10 szavazat kérdőívenként  
**Target ID:** impactad  
**Forrás:** `sharity_questions_batch5_250.csv`

> **Megjegyzés:** Ez a teljes használható kérdésbank. A korábbi master batch (1250 kérdés) nem működött és nem használható.

## Konfigurációs Template

```json
{
  "provider_id": "internal_survey",
  "target_id": "impactad", 
  "reward": {"points": 10, "votes": 10},
  "max_questions": 5,
  "format": "single_choice_a_d",
  "rate_limit": "10_per_user_per_hour",
  "consent_required": true
}
```

## Postback Payload Template

```json
{
  "transaction_id": "survey-YYYYMMDD-XXX",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136800,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batchX-bY",
  "question_count": 5,
  "categories": ["CATEGORY1", "CATEGORY2", "CATEGORY3", "CATEGORY4", "CATEGORY5"],
  "answers": {
    "CATEGORY1": "A",
    "CATEGORY2": "B", 
    "CATEGORY3": "C",
    "CATEGORY4": "D",
    "CATEGORY5": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-generated"
}
```

---
## Kérdőív 01

**Survey ID:** `impactad-v1-batch5-b01`
**Kategóriák:** KN_WASTE, KN_CSR, KN_EFFI, KN_SDG, KN_CARE

### 1. KN - KN-WASTE-1001

**Kérdés:** Az EU hulladékhierarchiája szerint melyik a legjobb első lépés a hulladék kezelésében?

A) Újrahasználat (ugyanazt a tárgyat újra használni)
B) Lerakás vagy elégetés
C) **Hulladék megelőzése (kevesebb hulladék keletkezzen)**
D) Újrahasznosítás (anyagában feldolgozás)

**Helyes válasz:** C
**Magyarázat:** A hulladékhierarchia tetején a megelőzés áll: a legzöldebb hulladék az, ami létre sem jön.

### 2. KN - KN-WASTE-1002

**Kérdés:** Emlékszel? A hulladékhierarchiában mi következik közvetlenül a megelőzés után?

A) Lerakás
B) **Újrahasználat / újrafelhasználásra előkészítés**
C) Újrahasznosítás
D) Energia-visszanyerés (égetés)

**Helyes válasz:** B
**Magyarázat:** A sorrend: megelőzés → újrahasználat → újrahasznosítás → energia-visszanyerés → ártalmatlanítás.

### 3. KN - KN-CSR-1003

**Kérdés:** Mit jelent a greenwashing (zöldre mosás) a marketingben?

A) Amikor egy cég csak zöld színű csomagolást használ
B) Amikor egy cég a termékeit tényleg környezetbaráttá alakítja
C) **Amikor egy cég túlzó vagy félrevezető módon állítja magáról, hogy környezetbarát**
D) Amikor egy cég kizárólag faültetésre költ

**Helyes válasz:** C
**Magyarázat:** A greenwashing lényege a megtévesztő zöld állítás: jól hangzik, de nincs mögötte valós bizonyíték.

### 4. KN - KN-CSR-1004

**Kérdés:** Emlékszel a greenwashing-ra: melyik jel a leggyanúsabb egy „zöld” állításnál?

A) **Túl általános kifejezések (pl. „eco”, „zöld”), konkrét bizonyíték vagy mérőszám nélkül**
B) Ha a terméknek van részletes fenntarthatósági jelentése
C) Ha van független tanúsítvány és pontos állítás
D) Ha a cég megmutatja a számítás módszerét is

**Helyes válasz:** A
**Magyarázat:** A homályos, bizonyíték nélküli állítások tipikus greenwashing jelek.

### 5. KN - KN-SDG-1005

**Kérdés:** Hány Fenntartható Fejlődési Cél (SDG) van az ENSZ 2030 Agendájában?

A) 10
B) **17**
C) 12
D) 25

**Helyes válasz:** B
**Magyarázat:** Az SDG-k száma 17, ezek 2030-ig kijelölik a globális fenntarthatósági célokat.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-001",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136801,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b01",
  "question_count": 5,
  "categories": [
    "KN_WASTE",
    "KN_CSR",
    "KN_EFFI",
    "KN_SDG",
    "KN_CARE"
  ],
  "answers": {
    "KN_WASTE": "C",
    "KN_CSR": "C",
    "KN_EFFI": "C",
    "KN_SDG": "B",
    "KN_CARE": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-001"
}
```

---

## Kérdőív 02

**Survey ID:** `impactad-v1-batch5-b02`
**Kategóriák:** KN_SDG, KN_CARE, BEH_FOOD, BEH_WASTE, KN_FOOD

### 1. KN - KN-SDG-1006

**Kérdés:** Emlékszel az SDG-kre: mi a fő üzenetük röviden?

A) **A társadalmi, gazdasági és környezeti célok együtt kezelendők, 2030-ig**
B) Csak a természetvédelemre fókuszálnak
C) Csak a gazdasági növekedést ösztönzik
D) Csak a fejlett országokra vonatkoznak

**Helyes válasz:** A
**Magyarázat:** Az SDG-k integrált célrendszer: szegénység, egyenlőtlenség, klíma, természeti erőforrások egyszerre.

### 2. BEH - BEH-FOOD-1007

**Kérdés:** Mi a leghatékonyabb otthoni lépés az élelmiszerpazarlás csökkentésére?

A) Mindent azonnal lefagyasztani, ellenőrzés nélkül
B) Mindig nagy kiszerelést venni, mert olcsóbb
C) **Bevásárlás előtt tervezés (menüterv, lista) és a készletek átnézése**
D) Csak a lejárati dátum alapján kidobni mindent

**Helyes válasz:** C
**Magyarázat:** A tervezés és készletellenőrzés a legjobb megelőző lépés: kevesebb felesleg kerül a kukába.

### 3. KN - KN-FOOD-1008

**Kérdés:** Emlékszel? Miért segít a menüterv és bevásárlólista az ételpazarlás ellen?

A) **Mert kisebb eséllyel veszel olyat, ami már van otthon vagy nem fog elfogyni**
B) Mert így hosszabb lesz a bevásárlás
C) Mert így mindig drágább termékeket választasz
D) Mert így több ételt veszel egyszerre

**Helyes válasz:** A
**Magyarázat:** A tervezés a túlzott vásárlás és a párhuzamos készletek ellen hat.

### 4. KN - KN-WASTE-1009

**Kérdés:** Mi a legpontosabb különbség a „biológiailag lebomló” és a „komposztálható” között?

A) A komposztálható soha nem műanyag
B) **A komposztálható anyag meghatározott feltételek mellett, meghatározott időn belül komposzttá alakul**
C) A lebomló mindig gyorsabban bomlik le, mint a komposztálható
D) A lebomló csak vízben bomlik le

**Helyes válasz:** B
**Magyarázat:** A komposztálhatóság általában konkrét, ellenőrzött feltételekhez és időkerethez kötött.

### 5. KN - KN-WASTE-1010

**Kérdés:** Emlékszel? Ha valami „komposztálható”, akkor automatikusan bedobható a kerti komposztba?

A) Igen, de csak télen
B) Csak akkor, ha előbb műanyagba csomagolod
C) **Nem feltétlen: sok csomagolás csak ipari komposztálóban bomlik le megfelelően**
D) Igen, minden komposztálható csomagolás otthon is gond nélkül lebomlik

**Helyes válasz:** C
**Magyarázat:** A „komposztálható” gyakran ipari körülményekre vonatkozik, nem feltétlen házi komposztra.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-002",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136802,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b02",
  "question_count": 5,
  "categories": [
    "KN_SDG",
    "KN_CARE",
    "BEH_FOOD",
    "BEH_WASTE",
    "KN_FOOD"
  ],
  "answers": {
    "KN_SDG": "A",
    "KN_CARE": "A",
    "BEH_FOOD": "C",
    "BEH_WASTE": "C",
    "KN_FOOD": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-002"
}
```

---

## Kérdőív 03

**Survey ID:** `impactad-v1-batch5-b03`
**Kategóriák:** KN_WASTE, KN_CLIM, KN_BIOD, KN_CARE, KN_ENER

### 1. KN - KN-WASTE-1011

**Kérdés:** Melyik a mikroplasztik egyik gyakori forrása a mindennapokban?

A) **Gumikopás az utakon (autógumikból leváló apró részecskék)**
B) Kizárólag a banánhéj
C) Csak a fém dobozok
D) Csak a papírzacskók

**Helyes válasz:** A
**Magyarázat:** Mikroplasztik nem csak palackból jön: a gumiabroncs-kopás is jelentős forrás.

### 2. KN - KN-WASTE-1012

**Kérdés:** Emlékszel? Miért nehéz a mikroplasztik problémát „csak szelektív gyűjtéssel” megoldani?

A) Mert a mikroplasztik kizárólag üvegből van
B) Mert a szelektív gyűjtés növeli a mikroplasztikot
C) **Mert sok mikroplasztik nem hulladékként, hanem használat közben keletkezik (kopás, textilszálak)**
D) Mert a mikroplasztik túl nagy ahhoz, hogy szűrhető legyen

**Helyes válasz:** C
**Magyarázat:** A források egy része diffúz, nem „kidobott tárgy” formájában jelenik meg.

### 3. KN - KN-BIOD-1013

**Kérdés:** Mit jelent a biodiverzitás (biológiai sokféleség) kifejezés?

A) A hegyek magasságát
B) **Az élőlények sokféleségét (fajok, élőhelyek és genetikai változatosság)**
C) Az évszakok számát
D) A tenger sótartalmát

**Helyes válasz:** B
**Magyarázat:** A biodiverzitás több szinten értendő: faj, élőhely, genetika.

### 4. KN - KN-BIOD-1014

**Kérdés:** Emlékszel? Miért fontos a biodiverzitás a mindennapi életben is?

A) Mert csak a múzeumokban számít
B) **Mert az ökoszisztémák szolgáltatásai (beporzás, talaj, víz) az élelmiszerhez és egészséghez is kellenek**
C) Mert csak a turistáknak fontos
D) Mert kizárólag esztétikai kérdés

**Helyes válasz:** B
**Magyarázat:** A biodiverzitás csökkenése az élelmiszer- és vízbiztonságot is érinti.

### 5. KN - KN-ENER-1015

**Kérdés:** Egy átlagos lakásban általában mi viszi el a legtöbb energiát éves szinten?

A) Wi‑Fi router
B) Telefon töltése
C) **Fűtés és melegvíz készítés**
D) LED izzók

**Helyes válasz:** C
**Magyarázat:** A fűtés és melegvíz tipikusan nagyságrendekkel nagyobb tétel, mint a világítás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-003",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136803,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b03",
  "question_count": 5,
  "categories": [
    "KN_WASTE",
    "KN_CLIM",
    "KN_BIOD",
    "KN_CARE",
    "KN_ENER"
  ],
  "answers": {
    "KN_WASTE": "A",
    "KN_CLIM": "A",
    "KN_BIOD": "B",
    "KN_CARE": "B",
    "KN_ENER": "C"
  },
  "consent_pers": 1,
  "request_id": "uuid-003"
}
```

---

## Kérdőív 04

**Survey ID:** `impactad-v1-batch5-b04`
**Kategóriák:** BEH_ENER, BEH_EFFI, KN_ENER, KN_CLIM, DON_TRUST

### 1. BEH - BEH-ENER-1016

**Kérdés:** Emlékszel? Ha gyorsan akarsz energiát spórolni otthon, melyik irány a legnagyobb hatású?

A) A sütő ajtaját naponta kétszer kinyitni
B) Minden telefontöltő kihúzása éjjel
C) **Fűtési veszteségek csökkentése (szigetelés, termosztát, nyílászáró)**
D) A TV távirányítójának elemeit cserélni

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételeket érdemes először optimalizálni: fűtés, melegvíz, nagy gépek.

### 2. KN - KN-ENER-1017

**Kérdés:** Ha télen 1°C-kal lejjebb veszed a fűtést, mi történik legvalószínűbben?

A) Csak a világítás fogyasztása változik
B) Nő az energiafogyasztás
C) Semmi, mert 1°C sosem számít
D) **Érezhetően csökkenhet az energiafogyasztás, ha tartósan így marad**

**Helyes válasz:** D
**Magyarázat:** Kis változtatás is számíthat, ha hosszú időn át és sok háztartásban történik.

### 3. BEH - BEH-ENER-1018

**Kérdés:** Emlékszel? Miért jobb a „tartósan kicsit lejjebb” megoldás, mint a „néha nagyon”?

A) Mert a termosztát csak dísz
B) **Mert a hosszabb ideig fenntartott csökkentés összeadódik a teljes szezonban**
C) Mert így mindig drágább lesz a fűtés
D) Mert így több szén-dioxid termelődik

**Helyes válasz:** B
**Magyarázat:** A megtakarítás a tartósságon múlik: az órák és napok számítanak.

### 4. DON - DON-TRUST-1019

**Kérdés:** Mi ad a legjobb támpontot arra, hogy egy adománygyűjtő szervezet átláthatóan működik?

A) Minél több hangzatos szlogen
B) **Nyilvános beszámoló, pénzügyi jelentés és ellenőrizhető projekteredmények**
C) Csak szép fotók a közösségi médiában
D) Az, ha sosem válaszol kérdésekre

**Helyes válasz:** B
**Magyarázat:** Az átláthatóság jele a nyilvános, számszerű beszámoló és a visszakereshető eredmény.

### 5. DON - DON-TRUST-1020

**Kérdés:** Emlékszel? Mi a leggyanúsabb jel adománygyűjtésnél?

A) Átlátható költségarányok
B) **Sürgetés és homályos cél, ellenőrizhető adatok nélkül**
C) Hivatalos elérhetőségek és szervezeti adatok
D) Többféle, biztonságos fizetési mód

**Helyes válasz:** B
**Magyarázat:** A nyomásgyakorlás és a bizonyíték nélküli állítások csalásra utalhatnak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-004",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136804,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b04",
  "question_count": 5,
  "categories": [
    "BEH_ENER",
    "BEH_EFFI",
    "KN_ENER",
    "KN_CLIM",
    "DON_TRUST"
  ],
  "answers": {
    "BEH_ENER": "C",
    "BEH_EFFI": "C",
    "KN_ENER": "D",
    "KN_CLIM": "D",
    "DON_TRUST": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-004"
}
```

---

## Kérdőív 05

**Survey ID:** `impactad-v1-batch5-b05`
**Kategóriák:** DON_ALT, DON_COMM, DON_CSR, SOC_ANIMAL, SOC_CARE

### 1. DON - DON-ALT-1021

**Kérdés:** Tárgyi adományozásnál mi a legjobb első lépés, hogy tényleg hasznos legyen?

A) **Előre egyeztetni a szervezettel, mire van valóban szükség**
B) Csak lejárt élelmiszert vinni, mert az is adomány
C) Bármit gyorsan odavinni, válogatás nélkül
D) Olyat vinni, amit már kidobnál

**Helyes válasz:** A
**Magyarázat:** Az igényfelmérés csökkenti a felesleget és a szervezet terheit.

### 2. DON - DON-ALT-1022

**Kérdés:** Emlékszel? Miért fontos egyeztetni tárgyi adomány előtt?

A) Mert így biztosan kevesebb ember kap segítséget
B) Mert így drágább lesz az adomány
C) **Mert a nem megfelelő adomány logisztikai terhet és plusz költséget okozhat**
D) Mert így tilos adományozni

**Helyes válasz:** C
**Magyarázat:** A célzott adomány értékesebb, mint a „kiselejtezés” jellegű adomány.

### 3. DON - DON-ALT-1023

**Kérdés:** Mi számít „nem pénzbeli” adománynak a legtipikusabban?

A) Csak a hitelkártyás fizetés
B) Csak a banki átutalás
C) **Idő és tudás felajánlása (önkéntesség, szakmai segítség)**
D) Csak a készpénz

**Helyes válasz:** C
**Magyarázat:** A nem pénzbeli segítség lehet idő, munka, tudás, tárgy, infrastruktúra.

### 4. DON - DON-ALT-1024

**Kérdés:** Emlékszel? Miért lehet különösen értékes a szakmai önkéntesség?

A) Mert csak reklám
B) **Mert olyan hiányzó kompetenciát pótolhat, amit a szervezet nem tud megfizetni**
C) Mert nem segít a működésben
D) Mert mindig kevesebbet ér, mint a pénz

**Helyes válasz:** B
**Magyarázat:** A pro bono munka (pl. jog, design, IT) közvetlenül növelheti a szervezet kapacitását.

### 5. SOC - SOC-ANIMAL-1025

**Kérdés:** Ha valaki felelős módon szeretne segíteni a kóbor állatokon, mi a legerősebb lépés?

A) Impulzusból állatot venni ajándékba
B) Etetni bárhol, szervezés nélkül
C) Elfordítani a fejét, mert nem az ő dolga
D) **Örökbefogadás vagy ideiglenes befogadás, ha erre alkalmas a körülménye**

**Helyes válasz:** D
**Magyarázat:** A felelős befogadás csökkenti a menhelyi túlterheltséget és tartós megoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-005",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136805,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b05",
  "question_count": 5,
  "categories": [
    "DON_ALT",
    "DON_COMM",
    "DON_CSR",
    "SOC_ANIMAL",
    "SOC_CARE"
  ],
  "answers": {
    "DON_ALT": "A",
    "DON_COMM": "A",
    "DON_CSR": "B",
    "SOC_ANIMAL": "D",
    "SOC_CARE": "D"
  },
  "consent_pers": 1,
  "request_id": "uuid-005"
}
```

---

## Kérdőív 06

**Survey ID:** `impactad-v1-batch5-b06`
**Kategóriák:** SOC_ANIMAL, SOC_CARE, KN_WASTE, KN_ENER, KN_WATER

### 1. SOC - SOC-ANIMAL-1026

**Kérdés:** Emlékszel? Miért kockázatos az impulzus-állatvásárlás?

A) Mert így biztosan olcsóbb lesz
B) Mert így kevesebb gond lesz vele
C) **Mert gyakran felkészületlenül történik, és később elhanyagoláshoz vagy leadáshoz vezethet**
D) Mert minden állat veszélyes

**Helyes válasz:** C
**Magyarázat:** Az állattartás hosszú távú felelősség, nem pillanatnyi döntés.

### 2. KN - KN-WASTE-1027

**Kérdés:** Hova való leginkább a régi, nem működő elektronikai eszköz (pl. telefon, kábel)?

A) A komposztba
B) **E-hulladék gyűjtőpontba vagy visszavételi rendszerbe**
C) A vegyes kukába
D) A szelektív papírba

**Helyes válasz:** B
**Magyarázat:** Az e-hulladék veszélyes és értékes anyagokat is tartalmaz, ezért külön gyűjtendő.

### 3. KN - KN-WASTE-1028

**Kérdés:** Emlékszel? Miért probléma az e-hulladék vegyes kukába dobása?

A) Mert így tisztább lesz a levegő
B) Mert így olcsóbb lesz a telefon
C) Mert így több madár lesz a városban
D) **Mert veszélyes anyagok szivároghatnak, és az újrahasznosítható fémek is elvesznek**

**Helyes válasz:** D
**Magyarázat:** Az e-hulladék külön kezelése védi a környezetet és erőforrást ment.

### 4. KN - KN-WASTE-1029

**Kérdés:** Mi a helyes eljárás a használt sütőolajjal?

A) Összekeverni a komposzttal
B) **Leadni használtolaj-gyűjtő ponton (pl. kijelölt gyűjtőnél)**
C) Kitenni az utcára nyitott edényben
D) Kiönteni a lefolyóba

**Helyes válasz:** B
**Magyarázat:** A lefolyóba öntött olaj eltömíthet és szennyez, ezért külön gyűjtendő.

### 5. KN - KN-WASTE-1030

**Kérdés:** Emlékszel? Miért rossz ötlet a lefolyóba önteni a használt olajat?

A) Mert így több lesz a víz
B) **Mert dugulást és vízszennyezést okozhat, a tisztítás pedig költséges**
C) Mert így tisztább lesz a csatorna
D) Mert így gyorsabban lehűl

**Helyes válasz:** B
**Magyarázat:** A csatornában zsírdugó és szennyezés keletkezhet, ezt elkerülni a legjobb.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-006",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136806,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b06",
  "question_count": 5,
  "categories": [
    "SOC_ANIMAL",
    "SOC_CARE",
    "KN_WASTE",
    "KN_ENER",
    "KN_WATER"
  ],
  "answers": {
    "SOC_ANIMAL": "C",
    "SOC_CARE": "C",
    "KN_WASTE": "B",
    "KN_ENER": "B",
    "KN_WATER": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-006"
}
```

---

## Kérdőív 07

**Survey ID:** `impactad-v1-batch5-b07`
**Kategóriák:** BEH_TRAN, BEH_CLIM, KN_TRAN, KN_CLIM, BEH_WATER

### 1. BEH - BEH-TRAN-1031

**Kérdés:** Ha 10 km-re mész, és van több közlekedési opció, melyik csökkenti leginkább az egy főre jutó kibocsátást?

A) **Tömegközlekedés vagy telekocsi (többen utaztok egy járműben)**
B) Egyedül autóval menni
C) Autóval menni üres csomagtartóval
D) Motorral gyorsítani a pirosnál

**Helyes válasz:** A
**Magyarázat:** Az egy főre jutó kibocsátást az osztozás (több utas) és a hatékony módok csökkentik.

### 2. KN - KN-TRAN-1032

**Kérdés:** Emlékszel? Miért számít az utasszám az autó környezeti terhelésénél?

A) Mert így a kerekek nem kopnak
B) **Mert ugyanaz a kibocsátás több ember között oszlik meg**
C) Mert így az út rövidebb lesz
D) Mert így az autó kevesebb üzemanyagot termel

**Helyes válasz:** B
**Magyarázat:** A telekocsi nem varázslat, de az egy főre jutó terhelést csökkenti.

### 3. BEH - BEH-WATER-1033

**Kérdés:** Mi a leghatékonyabb víztakarékossági lépés egy csöpögő csap esetén?

A) Csak gyorsabban mosni kezet
B) **Megjavítani a szivárgást (tömítés csere), mert folyamatosan pazarol**
C) A csapot erősebben meghúzni és kész
D) Mindig forró vizet használni

**Helyes válasz:** B
**Magyarázat:** A szivárgás folyamatos veszteség, ezért a javítás nagy hatású.

### 4. KN - KN-WATER-1034

**Kérdés:** Emlékszel? Miért számít egy „apró” csöpögés is?

A) Mert ettől kevesebb lesz az eső
B) Mert ettől tisztább lesz a mosogató
C) **Mert idővel nagy mennyiségű víz folyik el feleslegesen**
D) Mert ettől gyorsabban forr a víz

**Helyes válasz:** C
**Magyarázat:** A kis, de állandó veszteség összeadódik napok-hetek alatt.

### 5. KN - KN-CIRC-1035

**Kérdés:** Mi a fast fashion (gyorsdivat) környezeti hatásának egyik fő oka?

A) Mert csak természetes anyagból készül
B) Mert kizárólag helyben gyártják
C) **Sok olcsó, rövid életű ruha gyorsan hulladékká válik, és nagy az erőforrás-igénye**
D) Mert a ruhák mindig komposztálhatók

**Helyes válasz:** C
**Magyarázat:** A gyorsdivat rövid használati idővel és nagy volumenű gyártással terheli a környezetet.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-007",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136807,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b07",
  "question_count": 5,
  "categories": [
    "BEH_TRAN",
    "BEH_CLIM",
    "KN_TRAN",
    "KN_CLIM",
    "BEH_WATER"
  ],
  "answers": {
    "BEH_TRAN": "A",
    "BEH_CLIM": "A",
    "KN_TRAN": "B",
    "KN_CLIM": "B",
    "BEH_WATER": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-007"
}
```

---

## Kérdőív 08

**Survey ID:** `impactad-v1-batch5-b08`
**Kategóriák:** BEH_CIRC, BEH_WASTE, KN_AIR, KN_CLIM, KN_WASTE

### 1. BEH - BEH-CIRC-1036

**Kérdés:** Emlékszel? Melyik döntés csökkenti legjobban a gyorsdivat hatását?

A) Minden héten új kollekciót venni
B) Csak egyszer felvenni egy ruhát
C) **Kevesebbet venni, tartósabbat választani, és használt / csere lehetőségeket használni**
D) Kidobni mindent, ami tavalyi

**Helyes válasz:** C
**Magyarázat:** A legnagyobb hatás gyakran a fogyasztás mérséklése és a termék élettartamának növelése.

### 2. KN - KN-AIR-1037

**Kérdés:** Melyik tevékenység rontja leginkább a helyi levegőminőséget egy településen?

A) Faültetés
B) **Hulladék vagy nedves avar égetése a kertben**
C) Komposztálás
D) Kerékpározás

**Helyes válasz:** B
**Magyarázat:** A nyílt égetés erős helyi légszennyező, egészségkárosító hatású lehet.

### 3. KN - KN-AIR-1038

**Kérdés:** Emlékszel? Miért különösen problémás a nedves növényi hulladék égetése?

A) Mert így kevesebb füst lesz
B) **Mert több füst és káros anyag keletkezhet, mint száraz anyagnál**
C) Mert így oxigén termelődik
D) Mert így a fák gyorsabban nőnek

**Helyes válasz:** B
**Magyarázat:** A nedves anyag rosszabbul ég, több szennyező és füst keletkezhet.

### 4. KN - KN-WASTE-1039

**Kérdés:** Szelektív gyűjtésnél mi az egyik leggyakoribb hiba, ami rontja az újrahasznosítást?

A) Papír síkba hajtása
B) **Erősen szennyezett csomagolás (pl. zsíros ételmaradékos doboz) bedobása**
C) Üveg külön gyűjtése
D) Kupak visszacsavarása a palackra

**Helyes válasz:** B
**Magyarázat:** A szennyezett hulladék szennyezheti az egész frakciót, rontva a hasznosíthatóságot.

### 5. KN - KN-WASTE-1040

**Kérdés:** Emlékszel? Mit érdemes tenni, ha egy csomagolás nagyon zsíros vagy ételmaradékos?

A) **Ha nem tisztítható ésszerűen, akkor a helyi szabály szerint gyakran a vegyesbe kerül**
B) Mindig a papírba kell dobni
C) Mindig a veszélyes hulladékba kell dobni
D) Mindig a komposztba kell dobni

**Helyes válasz:** A
**Magyarázat:** A cél a tiszta, jól feldolgozható szelektív: a szennyezett anyag sokszor nem hasznosítható.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-008",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136808,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b08",
  "question_count": 5,
  "categories": [
    "BEH_CIRC",
    "BEH_WASTE",
    "KN_AIR",
    "KN_CLIM",
    "KN_WASTE"
  ],
  "answers": {
    "BEH_CIRC": "C",
    "BEH_WASTE": "C",
    "KN_AIR": "B",
    "KN_CLIM": "B",
    "KN_WASTE": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-008"
}
```

---

## Kérdőív 09

**Survey ID:** `impactad-v1-batch5-b09`
**Kategóriák:** KN_CIRC, KN_WASTE, KN_CLIM, KN_EFFI, KN_BIOD

### 1. KN - KN-CIRC-1041

**Kérdés:** Mi a körforgásos gazdaság lényege?

A) Csak a papír újrahasznosítása
B) **A termékek és anyagok minél tovább körforgásban tartása (javítás, újrahasználat, újrahasznosítás)**
C) Minél több új termék gyártása rövid idő alatt
D) A hulladék gyors lerakása

**Helyes válasz:** B
**Magyarázat:** A körforgás a lineáris „vedd–használd–dobd” modell alternatívája.

### 2. KN - KN-CLIM-1042

**Kérdés:** Melyik a legjobb rövid magyarázat a karbonlábnyomra?

A) A lábnyom mérete a homokban
B) Az erdők által termelt oxigén mennyisége
C) **Egy tevékenységhez kapcsolódó üvegházhatású gázok összhatása**
D) A Föld mágneses terének változása

**Helyes válasz:** C
**Magyarázat:** A karbonlábnyom a kibocsátások összegzett hatását írja le.

### 3. KN - KN-WASTE-1043

**Kérdés:** Melyik állítás igaz az újratölthető (refill) rendszerekről?

A) **Csökkenthetik a csomagolási hulladékot, ha tényleg újratöltés történik**
B) Csak a nagyvárosokban működnek
C) Mindig több hulladékot termelnek
D) Soha nem higiénikusak

**Helyes válasz:** A
**Magyarázat:** A refill lényege a csomagolás többszöri használata.

### 4. KN - KN-BIOD-1044

**Kérdés:** Miért különösen fontosak a beporzók (méhek, lepkék)?

A) **Mert sok haszonnövény terméséhez szükséges a beporzásuk**
B) Mert ők termelik a szén-dioxidot
C) Mert ők bontják le a műanyagot
D) Mert ők hűtik a légkört

**Helyes válasz:** A
**Magyarázat:** A beporzás az élelmiszer-termelés egyik alapja.

### 5. KN - KN-ATTEFFI-1045

**Kérdés:** Melyik választás csökkenti leginkább a „rebound” hatás esélyét?

A) **Ha a megtakarított pénzt/energiát nem költöd el plusz fogyasztásra**
B) Ha többet repülsz, mert „LED-em van”
C) Ha a hatékony eszközt folyamatosan maximumon használod
D) Ha a megtakarítást rögtön több vásárlásra fordítod

**Helyes válasz:** A
**Magyarázat:** Hatékonyság mellett a fogyasztási szokások is számítanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-009",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136809,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b09",
  "question_count": 5,
  "categories": [
    "KN_CIRC",
    "KN_WASTE",
    "KN_CLIM",
    "KN_EFFI",
    "KN_BIOD"
  ],
  "answers": {
    "KN_CIRC": "B",
    "KN_WASTE": "B",
    "KN_CLIM": "C",
    "KN_EFFI": "C",
    "KN_BIOD": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-009"
}
```

---

## Kérdőív 10

**Survey ID:** `impactad-v1-batch5-b10`
**Kategóriák:** KN_CLIM, KN_ENER, KN_AGRI

### 1. KN - KN-CLIM-1046

**Kérdés:** Melyik gáz üvegházhatású gáz?

A) Nitrogén (N₂)
B) **Metán (CH₄)**
C) Oxigén (O₂)
D) Argon (Ar)

**Helyes válasz:** B
**Magyarázat:** A metán erős üvegházhatású gáz.

### 2. KN - KN-CLIM-1047

**Kérdés:** Melyik gáz üvegházhatású gáz?

A) **Szén-dioxid (CO₂)**
B) Hélium (He)
C) Neon (Ne)
D) Nitrogén (N₂)

**Helyes válasz:** A
**Magyarázat:** A CO₂ a klímaváltozás egyik fő hajtóereje.

### 3. KN - KN-CLIM-1048

**Kérdés:** Melyik gáz üvegházhatású gáz?

A) Oxigén (O₂)
B) Argon (Ar)
C) **Dinitrogén-oxid (N₂O)**
D) Hidrogén (H₂)

**Helyes válasz:** C
**Magyarázat:** Az N₂O üvegházhatású és részben mezőgazdasághoz kötődik.

### 4. KN - KN-ENER-1049

**Kérdés:** Melyik energiaforrás megújuló?

A) Kőszén
B) **Szélenergia**
C) Földgáz
D) Kőolaj

**Helyes válasz:** B
**Magyarázat:** A szélenergia megújuló forrás.

### 5. KN - KN-ENER-1050

**Kérdés:** Melyik energiaforrás megújuló?

A) Dízel
B) **Napenergia**
C) Földgáz
D) Barnaszén

**Helyes válasz:** B
**Magyarázat:** A napenergia megújuló forrás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-010",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136810,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b10",
  "question_count": 5,
  "categories": [
    "KN_CLIM",
    "KN_ENER",
    "KN_AGRI"
  ],
  "answers": {
    "KN_CLIM": "B",
    "KN_ENER": "B",
    "KN_AGRI": "C"
  },
  "consent_pers": 1,
  "request_id": "uuid-010"
}
```

---

## Kérdőív 11

**Survey ID:** `impactad-v1-batch5-b11`
**Kategóriák:** KN_ENER, KN_CLIM, DON_TRUST, DON_CSR, DON_FREQ

### 1. KN - KN-ENER-1051

**Kérdés:** Melyik energiaforrás nem megújuló?

A) **Földgáz**
B) Vízenergia
C) Szélenergia
D) Napenergia

**Helyes válasz:** A
**Magyarázat:** A földgáz fosszilis, nem megújuló.

### 2. DON - DON-TRUST-1052

**Kérdés:** Ha egy adománygyűjtő poszt csak egy személyes bankszámlaszámot ad meg, mi a legbiztonságosabb első lépés?

A) Megosztani tovább ellenőrzés nélkül
B) **Ellenőrizni a szervezet hivatalos csatornáit és csak azon keresztül adományozni**
C) Azonnal utalni, mert sürgős
D) Készpénzt küldeni postán

**Helyes válasz:** B
**Magyarázat:** Az ellenőrzött csatorna csökkenti a csalás kockázatát.

### 3. DON - DON-FREQ-1053

**Kérdés:** Melyik állítás a leginkább igaz a rendszeres (havi) adományozásról?

A) Csak a nagy szervezeteknek jó
B) **Kiszámíthatóbb bevételt ad a szervezetnek, így jobban tud tervezni**
C) Nincs semmilyen előnye
D) Mindig rosszabb, mint az egyszeri adomány

**Helyes válasz:** B
**Magyarázat:** A rendszeresség stabilitást ad, még kis összegnél is.

### 4. DON - DON-ALT-1054

**Kérdés:** Ha nem pénzzel adnál, melyik a legjobb „alap” segítség sok szervezetnél?

A) Csak új ruhák márkásan
B) **Idő: önkéntes munka vagy feladatvállalás**
C) Semmi, mert pénz nélkül nem lehet segíteni
D) Csak drága ajándéktárgyak

**Helyes válasz:** B
**Magyarázat:** Sok szervezetnél az idő és a munka legalább olyan értékes, mint a pénz.

### 5. DON - DON-ALT-1055

**Kérdés:** Mitől lesz egy tárgyi adomány „jó adomány”?

A) Minél nagyobb, annál jobb
B) **A fogadó szervezet igényeihez illeszkedik, és jó állapotú**
C) Ha lejárt, mert úgyis elviszik
D) Neked felesleges, bármi jó

**Helyes válasz:** B
**Magyarázat:** A célzott, használható adomány valóban segítség, nem plusz teher.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-011",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136811,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b11",
  "question_count": 5,
  "categories": [
    "KN_ENER",
    "KN_CLIM",
    "DON_TRUST",
    "DON_CSR",
    "DON_FREQ"
  ],
  "answers": {
    "KN_ENER": "A",
    "KN_CLIM": "A",
    "DON_TRUST": "B",
    "DON_CSR": "B",
    "DON_FREQ": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-011"
}
```

---

## Kérdőív 12

**Survey ID:** `impactad-v1-batch5-b12`
**Kategóriák:** DON_TRUST, DON_DIGI, SOC_CSR, SOC_CARE, SOC_EFFI

### 1. DON - DON-TRUST-1056

**Kérdés:** Mi a legjobb rövid szabály, ha online adománygyűjtésnél linket kapsz?

A) **Ha lehet, hivatalos weboldalról/alkalmazásból menj, ne ismeretlen rövidített linkről**
B) Mindig a legrövidebb link a legbiztonságosabb
C) Ha sok lájk van, biztosan igaz
D) A kommentben küldött link mindig hiteles

**Helyes válasz:** A
**Magyarázat:** A hivatalos, ellenőrizhető csatorna biztonságosabb, mint a láncüzenet.

### 2. SOC - SOC-CSR-1057

**Kérdés:** Mit jelent a CSR (vállalati társadalmi felelősségvállalás) lényege a gyakorlatban?

A) A cég csak reklámozza, hogy jó
B) A cég csak a dolgozókra figyel, minden más mindegy
C) **A cég a működésével és döntéseivel a társadalmi és környezeti hatásait is figyelembe veszi**
D) A cég csak adót fizet

**Helyes válasz:** C
**Magyarázat:** A CSR a hatások tudatos kezeléséről szól, nem csak kampányról.

### 3. SOC - SOC-CSR-1058

**Kérdés:** Egy cég „zöld” terméket hirdet, de semmilyen adatot nem ad. Mi a legjobb fogyasztói reakció?

A) **Kérni konkrét információt (mérőszám, tanúsítvány) vagy fenntartással kezelni a claimet**
B) Elhinni, mert biztos igaz
C) Boikottra buzdítani információ nélkül
D) Megvenni, mert zöld a csomagolás

**Helyes válasz:** A
**Magyarázat:** A kritikus, adat-alapú kérdezés segít elkerülni a greenwashing csapdát.

### 4. SOC - SOC-COMM-1059

**Kérdés:** Közösségi akciót szerveztek (pl. szemétszedés). Mi a legjobb első lépés a hatékonysághoz?

A) Csak posztolni róla, terv nélkül
B) **Egyértelmű helyszín, időpont, eszközök és felelősök kijelölése**
C) Minél több helyszínt egyszerre lefedni koordináció nélkül
D) Várni, hogy valaki más megszervezze

**Helyes válasz:** B
**Magyarázat:** A szervezés és szerepek tisztázása teszi hatékonnyá az önkéntes akciót.

### 5. SOC - SOC-COMM-1060

**Kérdés:** Melyik a legjobb példa esélyegyenlőséget támogató, mindennapi lépésre?

A) Kirekesztő viccek erősítése
B) **Akadálymentes információ és tiszteletteljes kommunikáció mindenkivel**
C) Mások problémáinak elbagatellizálása
D) Csak a saját csoportod támogatása

**Helyes válasz:** B
**Magyarázat:** Az inkluzív, tiszteletteljes hozzáállás a mindennapi esélyegyenlőség alapja.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-012",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136812,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b12",
  "question_count": 5,
  "categories": [
    "DON_TRUST",
    "DON_DIGI",
    "SOC_CSR",
    "SOC_CARE",
    "SOC_EFFI"
  ],
  "answers": {
    "DON_TRUST": "A",
    "DON_DIGI": "A",
    "SOC_CSR": "C",
    "SOC_CARE": "C",
    "SOC_EFFI": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-012"
}
```

---

## Kérdőív 13

**Survey ID:** `impactad-v1-batch5-b13`
**Kategóriák:** KN_WASTE, KN_CIRC, BEH_WASTE, BEH_CIRC, BEH_ENER

### 1. KN - KN-WASTE-1061

**Kérdés:** Egy üdítős PET palack (kiöblítve) hova kerül a legtöbb helyen?

A) Üveg szelektívbe
B) Komposztba
C) **Műanyag szelektív gyűjtőbe**
D) Papír szelektívbe

**Helyes válasz:** C
**Magyarázat:** A PET palack tipikusan a műanyag frakcióba tartozik.

### 2. KN - KN-WASTE-1062

**Kérdés:** Egy kartondoboz akkor hasznosítható a legjobban, ha…

A) **Síkra hajtva, tisztán kerül a papír gyűjtőbe**
B) Összezsírozva kerül a papírba
C) Vizesen és szétázva dobod be
D) Tele van ételmaradékkal

**Helyes válasz:** A
**Magyarázat:** A tiszta, száraz papír jobban hasznosítható.

### 3. BEH - BEH-WASTE-1063

**Kérdés:** Mi a legjobb módja annak, hogy kevesebb csomagolási hulladék keletkezzen?

A) **Újratölthető / többször használható csomagolás választása, amikor elérhető**
B) Csak díszdobozos termékeket venni
C) Mindenből egyadagos csomagolás választása
D) Mindig külön zacskózni mindent

**Helyes válasz:** A
**Magyarázat:** A többször használható megoldások csökkentik az egyszer használatos hulladékot.

### 4. BEH - BEH-ENER-1064

**Kérdés:** Nyáron a klímát 26°C körül állítod, és árnyékolót használsz. Miért lehet ez jó kompromisszum?

A) Mert így a klíma mindig maximumon megy
B) **Mert csökkenti a fogyasztást, mégis komfortosabb, mint klíma nélkül**
C) Mert így nincs szükség szellőztetésre soha
D) Mert így biztosan több áramot fogyaszt

**Helyes válasz:** B
**Magyarázat:** A mérsékelt hűtés és árnyékolás csökkentheti a csúcsterhelést.

### 5. BEH - BEH-WATER-1065

**Kérdés:** Fogmosás közben elzárod a csapot. Ez miért számít?

A) **Mert a vízmegtakarítás sok kicsi szokás összegeként jelentős lehet**
B) Mert ettől romlik a fogmosás minősége
C) Mert így több vizet használsz
D) Mert ettől nő a CO₂ kibocsátás

**Helyes válasz:** A
**Magyarázat:** A rutinok sokszor nagy összhatást adnak hosszabb távon.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-013",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136813,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b13",
  "question_count": 5,
  "categories": [
    "KN_WASTE",
    "KN_CIRC",
    "BEH_WASTE",
    "BEH_CIRC",
    "BEH_ENER"
  ],
  "answers": {
    "KN_WASTE": "C",
    "KN_CIRC": "C",
    "BEH_WASTE": "A",
    "BEH_CIRC": "A",
    "BEH_ENER": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-013"
}
```

---

## Kérdőív 14

**Survey ID:** `impactad-v1-batch5-b14`
**Kategóriák:** BEH_ENER, BEH_EFFI, BEH_OFFLINE, BEH_COMM, BEH_CIRC

### 1. BEH - BEH-ENER-1066

**Kérdés:** Egy elektromos eszközt standby módban hagysz egész nap. Mi a legjobb általános szabály?

A) A standby sosem fogyaszt semmit
B) **Amit nem használsz, érdemes teljesen kikapcsolni, főleg sok eszköznél összeadódik**
C) Minél több standby, annál jobb
D) A standby csak akkor fogyaszt, ha a TV be van kapcsolva

**Helyes válasz:** B
**Magyarázat:** Az apró fogyasztások sok eszköznél összeadódhatnak.

### 2. BEH - BEH-OFFLINE-1067

**Kérdés:** Egy parkban látsz egy kihelyezett NFC taget egy szemétszedő kihíváshoz. Mi a helyes első lépés a feladat hitelesítéséhez?

A) **A helyszínen leolvasod az NFC-t, és a megnyíló oldalon a saját Sharity fiókodba lépsz be**
B) Képernyőképet készítesz, és az elég a teljesítéshez
C) Fotózol róla, és később otthon beírod a kódot bárhonnan
D) Csak elküldöd a linket másnak, ő is teljesíti

**Helyes válasz:** A
**Magyarázat:** A helyszíni leolvasás és bejelentkezés együtt ad erős hitelesítést.

### 3. BEH - BEH-CIRC-1068

**Kérdés:** Egy boltban van refill (utántöltő) pont. Mi a legjobb első lépés, ha fenntarthatóbb módon szeretnél vásárolni?

A) Mindenből dupla csomagolást kérsz
B) **Megnézed, van‑e saját tárolód, vagy kérsz többször használható megoldást az egyszer használatos helyett**
C) Azonnal több egyszer használatos palackot veszel
D) Nem nézed, csak a legszínesebb csomagolást választod

**Helyes válasz:** B
**Magyarázat:** A csomagolás többszöri használata az egyik legdirektebb hulladékcsökkentő lépés.

### 4. DON - DON-TRUST-1069

**Kérdés:** Egy jótékonysági standnál QR kódot látsz adományozásra. Mi a legbiztonságosabb első lépés?

A) **Megnézed, a QR a hivatalos szervezet domainjére vezet-e, és van-e egyértelmű azonosító**
B) Bárhova vezet, mindegy, csak gyors legyen
C) Csak fotózod a kódot és megosztod
D) Azonnal fizetsz, mert „jó ügy”, ellenőrzés nélkül

**Helyes válasz:** A
**Magyarázat:** A domain és az azonosíthatóság gyorsan csökkenti a csalás esélyét.

### 5. BEH - BEH-WATER-1070

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) víztakarékosság terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-014",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136814,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b14",
  "question_count": 5,
  "categories": [
    "BEH_ENER",
    "BEH_EFFI",
    "BEH_OFFLINE",
    "BEH_COMM",
    "BEH_CIRC"
  ],
  "answers": {
    "BEH_ENER": "B",
    "BEH_EFFI": "B",
    "BEH_OFFLINE": "A",
    "BEH_COMM": "A",
    "BEH_CIRC": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-014"
}
```

---

## Kérdőív 15

**Survey ID:** `impactad-v1-batch5-b15`
**Kategóriák:** BEH_WATER, BEH_EFFI, BEH_ENER, KN_TRAN, KN_CLIM

### 1. BEH - BEH-WATER-1071

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) víztakarékosság terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. BEH - BEH-ENER-1072

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Semmin nem változtatni, mert „úgyis mindegy”
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-TRAN-1073

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. KN - KN-TRAN-1074

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) Az, ha nem gondolkozol rajta
B) Csak a márka logója a csomagoláson
C) A minimális, ritka változtatások
D) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**

**Helyes válasz:** D
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. KN - KN-BIOD-1075

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) természetvédelem terén?

A) Az, ha nem gondolkozol rajta
B) Csak a márka logója a csomagoláson
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) A minimális, ritka változtatások

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-015",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136815,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b15",
  "question_count": 5,
  "categories": [
    "BEH_WATER",
    "BEH_EFFI",
    "BEH_ENER",
    "KN_TRAN",
    "KN_CLIM"
  ],
  "answers": {
    "BEH_WATER": "B",
    "BEH_EFFI": "B",
    "BEH_ENER": "A",
    "KN_TRAN": "B",
    "KN_CLIM": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-015"
}
```

---

## Kérdőív 16

**Survey ID:** `impactad-v1-batch5-b16`
**Kategóriák:** KN_WASTE, KN_CIRC, KN_ENER, KN_EFFI, KN_TRAN

### 1. KN - KN-WASTE-1076

**Kérdés:** Melyik állítás félrevezető a(z) hulladékcsökkentés kapcsán?

A) „A szokások hosszú távon számítanak.”
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-ENER-1077

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) otthoni energiahasználat terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) A minimális, ritka változtatások
C) Csak a márka logója a csomagoláson
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. KN - KN-TRAN-1078

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) „A szokások hosszú távon számítanak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. KN - KN-WATER-1079

**Kérdés:** Melyik állítás félrevezető a(z) víztakarékosság kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 5. KN - KN-FOOD-1080

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) A minimális, ritka változtatások
C) Csak a márka logója a csomagoláson
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-016",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136816,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b16",
  "question_count": 5,
  "categories": [
    "KN_WASTE",
    "KN_CIRC",
    "KN_ENER",
    "KN_EFFI",
    "KN_TRAN"
  ],
  "answers": {
    "KN_WASTE": "C",
    "KN_CIRC": "C",
    "KN_ENER": "A",
    "KN_EFFI": "A",
    "KN_TRAN": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-016"
}
```

---

## Kérdőív 17

**Survey ID:** `impactad-v1-batch5-b17`
**Kategóriák:** KN_TRAN, KN_CLIM, KN_FOOD, KN_WASTE, BEH_WATER

### 1. KN - KN-TRAN-1081

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A szokások hosszú távon számítanak.”
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-FOOD-1082

**Kérdés:** Melyik állítás félrevezető a(z) élelmiszerpazarlás kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-FOOD-1083

**Kérdés:** Melyik állítás félrevezető a(z) élelmiszerpazarlás kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) „A szokások hosszú távon számítanak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. BEH - BEH-WATER-1084

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) víztakarékosság terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-CLIM-1085

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) „A szokások hosszú távon számítanak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-017",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136817,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b17",
  "question_count": 5,
  "categories": [
    "KN_TRAN",
    "KN_CLIM",
    "KN_FOOD",
    "KN_WASTE",
    "BEH_WATER"
  ],
  "answers": {
    "KN_TRAN": "A",
    "KN_CLIM": "A",
    "KN_FOOD": "C",
    "KN_WASTE": "C",
    "BEH_WATER": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-017"
}
```

---

## Kérdőív 18

**Survey ID:** `impactad-v1-batch5-b18`
**Kategóriák:** KN_TRAN, KN_CLIM, BEH_TRAN, BEH_CLIM, BEH_BIOD

### 1. KN - KN-TRAN-1086

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Az, ha nem gondolkozol rajta
D) Csak a márka logója a csomagoláson

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. BEH - BEH-TRAN-1087

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) közlekedés terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak akkor változtatni, ha mások is látják
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. BEH - BEH-BIOD-1088

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 4. BEH - BEH-FOOD-1089

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) élelmiszerpazarlás terhelésén?

A) Csak a „kis kényelmi” dolgokon változtatni néha
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak akkor változtatni, ha mások is látják
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. BEH - BEH-WATER-1090

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) víztakarékosság terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak akkor változtatni, ha mások is látják
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-018",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136818,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b18",
  "question_count": 5,
  "categories": [
    "KN_TRAN",
    "KN_CLIM",
    "BEH_TRAN",
    "BEH_CLIM",
    "BEH_BIOD"
  ],
  "answers": {
    "KN_TRAN": "B",
    "KN_CLIM": "B",
    "BEH_TRAN": "B",
    "BEH_CLIM": "B",
    "BEH_BIOD": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-018"
}
```

---

## Kérdőív 19

**Survey ID:** `impactad-v1-batch5-b19`
**Kategóriák:** KN_TRAN, KN_CLIM, BEH_FOOD, BEH_WASTE, KN_BIOD

### 1. KN - KN-TRAN-1091

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) „A szokások hosszú távon számítanak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. BEH - BEH-FOOD-1092

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) élelmiszerpazarlás terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-BIOD-1093

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) természetvédelem terén?

A) Csak a márka logója a csomagoláson
B) A minimális, ritka változtatások
C) Az, ha nem gondolkozol rajta
D) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**

**Helyes válasz:** D
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. BEH - BEH-WASTE-1094

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. BEH - BEH-WASTE-1095

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-019",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136819,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b19",
  "question_count": 5,
  "categories": [
    "KN_TRAN",
    "KN_CLIM",
    "BEH_FOOD",
    "BEH_WASTE",
    "KN_BIOD"
  ],
  "answers": {
    "KN_TRAN": "B",
    "KN_CLIM": "B",
    "BEH_FOOD": "B",
    "BEH_WASTE": "B",
    "KN_BIOD": "D"
  },
  "consent_pers": 1,
  "request_id": "uuid-019"
}
```

---

## Kérdőív 20

**Survey ID:** `impactad-v1-batch5-b20`
**Kategóriák:** KN_CLIM, KN_CARE, KN_WATER, KN_EFFI, BEH_TRAN

### 1. KN - KN-CLIM-1096

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) „A szokások hosszú távon számítanak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-WATER-1097

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) víztakarékosság terén?

A) A minimális, ritka változtatások
B) Az, ha nem gondolkozol rajta
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) Csak a márka logója a csomagoláson

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. KN - KN-CLIM-1098

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. BEH - BEH-TRAN-1099

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) közlekedés terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-FOOD-1100

**Kérdés:** Melyik állítás félrevezető a(z) élelmiszerpazarlás kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A szokások hosszú távon számítanak.”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-020",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136820,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b20",
  "question_count": 5,
  "categories": [
    "KN_CLIM",
    "KN_CARE",
    "KN_WATER",
    "KN_EFFI",
    "BEH_TRAN"
  ],
  "answers": {
    "KN_CLIM": "C",
    "KN_CARE": "C",
    "KN_WATER": "C",
    "KN_EFFI": "C",
    "BEH_TRAN": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-020"
}
```

---

## Kérdőív 21

**Survey ID:** `impactad-v1-batch5-b21`
**Kategóriák:** BEH_BIOD, BEH_CARE, KN_TRAN, KN_CLIM, KN_BIOD

### 1. BEH - BEH-BIOD-1101

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. KN - KN-TRAN-1102

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Az, ha nem gondolkozol rajta
D) Csak a márka logója a csomagoláson

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. KN - KN-TRAN-1103

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) „A szokások hosszú távon számítanak.”
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**

**Helyes válasz:** D
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. KN - KN-BIOD-1104

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 5. BEH - BEH-TRAN-1105

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) közlekedés terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-021",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136821,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b21",
  "question_count": 5,
  "categories": [
    "BEH_BIOD",
    "BEH_CARE",
    "KN_TRAN",
    "KN_CLIM",
    "KN_BIOD"
  ],
  "answers": {
    "BEH_BIOD": "C",
    "BEH_CARE": "C",
    "KN_TRAN": "B",
    "KN_CLIM": "B",
    "KN_BIOD": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-021"
}
```

---

## Kérdőív 22

**Survey ID:** `impactad-v1-batch5-b22`
**Kategóriák:** KN_BIOD, KN_CARE, BEH_CLIM, BEH_CARE, KN_WATER

### 1. KN - KN-BIOD-1106

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) természetvédelem terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Csak a márka logója a csomagoláson
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. BEH - BEH-CLIM-1107

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) klímavédelem terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Semmin nem változtatni, mert „úgyis mindegy”
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-WATER-1108

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) víztakarékosság terén?

A) Csak a márka logója a csomagoláson
B) Az, ha nem gondolkozol rajta
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) A minimális, ritka változtatások

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. KN - KN-FOOD-1109

**Kérdés:** Melyik állítás félrevezető a(z) élelmiszerpazarlás kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 5. KN - KN-FOOD-1110

**Kérdés:** Melyik állítás félrevezető a(z) élelmiszerpazarlás kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A szokások hosszú távon számítanak.”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-022",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136822,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b22",
  "question_count": 5,
  "categories": [
    "KN_BIOD",
    "KN_CARE",
    "BEH_CLIM",
    "BEH_CARE",
    "KN_WATER"
  ],
  "answers": {
    "KN_BIOD": "A",
    "KN_CARE": "A",
    "BEH_CLIM": "A",
    "BEH_CARE": "A",
    "KN_WATER": "C"
  },
  "consent_pers": 1,
  "request_id": "uuid-022"
}
```

---

## Kérdőív 23

**Survey ID:** `impactad-v1-batch5-b23`
**Kategóriák:** KN_ENER, KN_EFFI, KN_TRAN, KN_CLIM, KN_WASTE

### 1. KN - KN-ENER-1111

**Kérdés:** Melyik állítás félrevezető a(z) otthoni energiahasználat kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-TRAN-1112

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) „A szokások hosszú távon számítanak.”
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**

**Helyes válasz:** D
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-ENER-1113

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) otthoni energiahasználat terén?

A) Csak a márka logója a csomagoláson
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Az, ha nem gondolkozol rajta
D) A minimális, ritka változtatások

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. KN - KN-WASTE-1114

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) hulladékcsökkentés terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) A minimális, ritka változtatások
C) Csak a márka logója a csomagoláson
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. KN - KN-TRAN-1115

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-023",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136823,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b23",
  "question_count": 5,
  "categories": [
    "KN_ENER",
    "KN_EFFI",
    "KN_TRAN",
    "KN_CLIM",
    "KN_WASTE"
  ],
  "answers": {
    "KN_ENER": "B",
    "KN_EFFI": "B",
    "KN_TRAN": "D",
    "KN_CLIM": "D",
    "KN_WASTE": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-023"
}
```

---

## Kérdőív 24

**Survey ID:** `impactad-v1-batch5-b24`
**Kategóriák:** KN_WASTE, KN_CIRC, BEH_TRAN, BEH_CLIM, KN_TRAN

### 1. KN - KN-WASTE-1116

**Kérdés:** Melyik állítás félrevezető a(z) hulladékcsökkentés kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. BEH - BEH-TRAN-1117

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) közlekedés terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) Semmin nem változtatni, mert „úgyis mindegy”
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-TRAN-1118

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Csak a márka logója a csomagoláson
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. BEH - BEH-ENER-1119

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak akkor változtatni, ha mások is látják
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. BEH - BEH-FOOD-1120

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) élelmiszerpazarlás terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) Csak akkor változtatni, ha mások is látják
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-024",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136824,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b24",
  "question_count": 5,
  "categories": [
    "KN_WASTE",
    "KN_CIRC",
    "BEH_TRAN",
    "BEH_CLIM",
    "KN_TRAN"
  ],
  "answers": {
    "KN_WASTE": "A",
    "KN_CIRC": "A",
    "BEH_TRAN": "C",
    "BEH_CLIM": "C",
    "KN_TRAN": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-024"
}
```

---

## Kérdőív 25

**Survey ID:** `impactad-v1-batch5-b25`
**Kategóriák:** KN_FOOD, KN_WASTE, BEH_FOOD, BEH_WASTE, KN_CLIM

### 1. KN - KN-FOOD-1121

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Az, ha nem gondolkozol rajta
D) Csak a márka logója a csomagoláson

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. BEH - BEH-FOOD-1122

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) élelmiszerpazarlás terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak akkor változtatni, ha mások is látják
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-CLIM-1123

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) klímavédelem terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Az, ha nem gondolkozol rajta
C) A minimális, ritka változtatások
D) Csak a márka logója a csomagoláson

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. KN - KN-WASTE-1124

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) hulladékcsökkentés terén?

A) Csak a márka logója a csomagoláson
B) Az, ha nem gondolkozol rajta
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) A minimális, ritka változtatások

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. KN - KN-ENER-1125

**Kérdés:** Melyik állítás félrevezető a(z) otthoni energiahasználat kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-025",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136825,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b25",
  "question_count": 5,
  "categories": [
    "KN_FOOD",
    "KN_WASTE",
    "BEH_FOOD",
    "BEH_WASTE",
    "KN_CLIM"
  ],
  "answers": {
    "KN_FOOD": "B",
    "KN_WASTE": "B",
    "BEH_FOOD": "B",
    "BEH_WASTE": "B",
    "KN_CLIM": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-025"
}
```

---

## Kérdőív 26

**Survey ID:** `impactad-v1-batch5-b26`
**Kategóriák:** KN_TRAN, KN_CLIM, BEH_WASTE, BEH_CIRC, BEH_TRAN

### 1. KN - KN-TRAN-1126

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) Az, ha nem gondolkozol rajta
B) A minimális, ritka változtatások
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) Csak a márka logója a csomagoláson

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. BEH - BEH-WASTE-1127

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) Csak akkor változtatni, ha mások is látják
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. BEH - BEH-TRAN-1128

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) közlekedés terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 4. KN - KN-WASTE-1129

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) hulladékcsökkentés terén?

A) A minimális, ritka változtatások
B) Az, ha nem gondolkozol rajta
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) Csak a márka logója a csomagoláson

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. BEH - BEH-WASTE-1130

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-026",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136826,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b26",
  "question_count": 5,
  "categories": [
    "KN_TRAN",
    "KN_CLIM",
    "BEH_WASTE",
    "BEH_CIRC",
    "BEH_TRAN"
  ],
  "answers": {
    "KN_TRAN": "C",
    "KN_CLIM": "C",
    "BEH_WASTE": "C",
    "BEH_CIRC": "C",
    "BEH_TRAN": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-026"
}
```

---

## Kérdőív 27

**Survey ID:** `impactad-v1-batch5-b27`
**Kategóriák:** KN_ENER, KN_EFFI, KN_CLIM, KN_CARE, KN_WATER

### 1. KN - KN-ENER-1131

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) otthoni energiahasználat terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) A minimális, ritka változtatások
C) Csak a márka logója a csomagoláson
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. KN - KN-CLIM-1132

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) klímavédelem terén?

A) Az, ha nem gondolkozol rajta
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) A minimális, ritka változtatások
D) Csak a márka logója a csomagoláson

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. KN - KN-WATER-1133

**Kérdés:** Melyik állítás félrevezető a(z) víztakarékosság kapcsán?

A) „A szokások hosszú távon számítanak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. BEH - BEH-FOOD-1134

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) élelmiszerpazarlás terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-BIOD-1135

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „A szokások hosszú távon számítanak.”
D) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**

**Helyes válasz:** D
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-027",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136827,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b27",
  "question_count": 5,
  "categories": [
    "KN_ENER",
    "KN_EFFI",
    "KN_CLIM",
    "KN_CARE",
    "KN_WATER"
  ],
  "answers": {
    "KN_ENER": "A",
    "KN_EFFI": "A",
    "KN_CLIM": "B",
    "KN_CARE": "B",
    "KN_WATER": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-027"
}
```

---

## Kérdőív 28

**Survey ID:** `impactad-v1-batch5-b28`
**Kategóriák:** KN_ENER, KN_EFFI, KN_FOOD, KN_WASTE, KN_TRAN

### 1. KN - KN-ENER-1136

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) otthoni energiahasználat terén?

A) Csak a márka logója a csomagoláson
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. KN - KN-FOOD-1137

**Kérdés:** Melyik állítás félrevezető a(z) élelmiszerpazarlás kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-FOOD-1138

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Csak a márka logója a csomagoláson
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. KN - KN-TRAN-1139

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Csak a márka logója a csomagoláson
C) Az, ha nem gondolkozol rajta
D) A minimális, ritka változtatások

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. BEH - BEH-BIOD-1140

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) Csak a „kis kényelmi” dolgokon változtatni néha
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-028",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136828,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b28",
  "question_count": 5,
  "categories": [
    "KN_ENER",
    "KN_EFFI",
    "KN_FOOD",
    "KN_WASTE",
    "KN_TRAN"
  ],
  "answers": {
    "KN_ENER": "B",
    "KN_EFFI": "B",
    "KN_FOOD": "C",
    "KN_WASTE": "C",
    "KN_TRAN": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-028"
}
```

---

## Kérdőív 29

**Survey ID:** `impactad-v1-batch5-b29`
**Kategóriák:** BEH_ENER, BEH_EFFI, BEH_FOOD, BEH_WASTE, KN_FOOD

### 1. BEH - BEH-ENER-1141

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. BEH - BEH-FOOD-1142

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) élelmiszerpazarlás terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak akkor változtatni, ha mások is látják
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-FOOD-1143

**Kérdés:** Melyik állítás félrevezető a(z) élelmiszerpazarlás kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. KN - KN-ENER-1144

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) otthoni energiahasználat terén?

A) Az, ha nem gondolkozol rajta
B) A minimális, ritka változtatások
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) Csak a márka logója a csomagoláson

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. KN - KN-CLIM-1145

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „A szokások hosszú távon számítanak.”
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-029",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136829,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b29",
  "question_count": 5,
  "categories": [
    "BEH_ENER",
    "BEH_EFFI",
    "BEH_FOOD",
    "BEH_WASTE",
    "KN_FOOD"
  ],
  "answers": {
    "BEH_ENER": "C",
    "BEH_EFFI": "C",
    "BEH_FOOD": "A",
    "BEH_WASTE": "A",
    "KN_FOOD": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-029"
}
```

---

## Kérdőív 30

**Survey ID:** `impactad-v1-batch5-b30`
**Kategóriák:** KN_ENER, KN_EFFI, KN_TRAN, KN_CLIM, BEH_WATER

### 1. KN - KN-ENER-1146

**Kérdés:** Melyik állítás félrevezető a(z) otthoni energiahasználat kapcsán?

A) „A szokások hosszú távon számítanak.”
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-TRAN-1147

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) A minimális, ritka változtatások
C) Az, ha nem gondolkozol rajta
D) Csak a márka logója a csomagoláson

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. BEH - BEH-WATER-1148

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) víztakarékosság terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 4. KN - KN-CLIM-1149

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) „A szokások hosszú távon számítanak.”
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 5. KN - KN-CLIM-1150

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) klímavédelem terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Csak a márka logója a csomagoláson
C) Az, ha nem gondolkozol rajta
D) A minimális, ritka változtatások

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-030",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136830,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b30",
  "question_count": 5,
  "categories": [
    "KN_ENER",
    "KN_EFFI",
    "KN_TRAN",
    "KN_CLIM",
    "BEH_WATER"
  ],
  "answers": {
    "KN_ENER": "C",
    "KN_EFFI": "C",
    "KN_TRAN": "A",
    "KN_CLIM": "A",
    "BEH_WATER": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-030"
}
```

---

## Kérdőív 31

**Survey ID:** `impactad-v1-batch5-b31`
**Kategóriák:** KN_WATER, KN_EFFI, KN_CLIM, KN_CARE, BEH_CLIM

### 1. KN - KN-WATER-1151

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) víztakarékosság terén?

A) Az, ha nem gondolkozol rajta
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Csak a márka logója a csomagoláson
D) A minimális, ritka változtatások

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. KN - KN-CLIM-1152

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. BEH - BEH-CLIM-1153

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) klímavédelem terhelésén?

A) Csak a „kis kényelmi” dolgokon változtatni néha
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 4. BEH - BEH-BIOD-1154

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-FOOD-1155

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Az, ha nem gondolkozol rajta
C) A minimális, ritka változtatások
D) Csak a márka logója a csomagoláson

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-031",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136831,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b31",
  "question_count": 5,
  "categories": [
    "KN_WATER",
    "KN_EFFI",
    "KN_CLIM",
    "KN_CARE",
    "BEH_CLIM"
  ],
  "answers": {
    "KN_WATER": "B",
    "KN_EFFI": "B",
    "KN_CLIM": "B",
    "KN_CARE": "B",
    "BEH_CLIM": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-031"
}
```

---

## Kérdőív 32

**Survey ID:** `impactad-v1-batch5-b32`
**Kategóriák:** KN_CLIM, KN_CARE, KN_WASTE, KN_CIRC, BEH_TRAN

### 1. KN - KN-CLIM-1156

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) klímavédelem terén?

A) Csak a márka logója a csomagoláson
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Az, ha nem gondolkozol rajta
D) A minimális, ritka változtatások

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. KN - KN-WASTE-1157

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) hulladékcsökkentés terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Az, ha nem gondolkozol rajta
C) A minimális, ritka változtatások
D) Csak a márka logója a csomagoláson

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. KN - KN-CLIM-1158

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A szokások hosszú távon számítanak.”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. BEH - BEH-TRAN-1159

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) közlekedés terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-ENER-1160

**Kérdés:** Melyik állítás félrevezető a(z) otthoni energiahasználat kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) „A szokások hosszú távon számítanak.”
D) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**

**Helyes válasz:** D
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-032",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136832,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b32",
  "question_count": 5,
  "categories": [
    "KN_CLIM",
    "KN_CARE",
    "KN_WASTE",
    "KN_CIRC",
    "BEH_TRAN"
  ],
  "answers": {
    "KN_CLIM": "B",
    "KN_CARE": "B",
    "KN_WASTE": "A",
    "KN_CIRC": "A",
    "BEH_TRAN": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-032"
}
```

---

## Kérdőív 33

**Survey ID:** `impactad-v1-batch5-b33`
**Kategóriák:** BEH_WATER, BEH_EFFI, KN_BIOD, KN_CARE, BEH_WASTE

### 1. BEH - BEH-WATER-1161

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) víztakarékosság terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) Csak akkor változtatni, ha mások is látják
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**

**Helyes válasz:** D
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. KN - KN-BIOD-1162

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) természetvédelem terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Csak a márka logója a csomagoláson
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. BEH - BEH-WASTE-1163

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 4. BEH - BEH-BIOD-1164

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Semmin nem változtatni, mert „úgyis mindegy”
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-WASTE-1165

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) hulladékcsökkentés terén?

A) Az, ha nem gondolkozol rajta
B) Csak a márka logója a csomagoláson
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) A minimális, ritka változtatások

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-033",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136833,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b33",
  "question_count": 5,
  "categories": [
    "BEH_WATER",
    "BEH_EFFI",
    "KN_BIOD",
    "KN_CARE",
    "BEH_WASTE"
  ],
  "answers": {
    "BEH_WATER": "D",
    "BEH_EFFI": "D",
    "KN_BIOD": "B",
    "KN_CARE": "B",
    "BEH_WASTE": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-033"
}
```

---

## Kérdőív 34

**Survey ID:** `impactad-v1-batch5-b34`
**Kategóriák:** BEH_FOOD, BEH_WASTE, KN_FOOD, KN_WASTE, KN_TRAN

### 1. BEH - BEH-FOOD-1166

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) élelmiszerpazarlás terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Semmin nem változtatni, mert „úgyis mindegy”
C) Csak akkor változtatni, ha mások is látják
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. KN - KN-FOOD-1167

**Kérdés:** Melyik állítás félrevezető a(z) élelmiszerpazarlás kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A szokások hosszú távon számítanak.”
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-TRAN-1168

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Csak a márka logója a csomagoláson
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. KN - KN-BIOD-1169

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „A szokások hosszú távon számítanak.”
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 5. KN - KN-WATER-1170

**Kérdés:** Melyik állítás félrevezető a(z) víztakarékosság kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) „A szokások hosszú távon számítanak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-034",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136834,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b34",
  "question_count": 5,
  "categories": [
    "BEH_FOOD",
    "BEH_WASTE",
    "KN_FOOD",
    "KN_WASTE",
    "KN_TRAN"
  ],
  "answers": {
    "BEH_FOOD": "A",
    "BEH_WASTE": "A",
    "KN_FOOD": "A",
    "KN_WASTE": "A",
    "KN_TRAN": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-034"
}
```

---

## Kérdőív 35

**Survey ID:** `impactad-v1-batch5-b35`
**Kategóriák:** BEH_WASTE, BEH_CIRC, BEH_ENER, BEH_EFFI, KN_CLIM

### 1. BEH - BEH-WASTE-1171

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. BEH - BEH-ENER-1172

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) Csak a „kis kényelmi” dolgokon változtatni néha
B) Semmin nem változtatni, mert „úgyis mindegy”
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-CLIM-1173

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A szokások hosszú távon számítanak.”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. BEH - BEH-ENER-1174

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak akkor változtatni, ha mások is látják
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. BEH - BEH-BIOD-1175

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak akkor változtatni, ha mások is látják
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-035",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136835,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b35",
  "question_count": 5,
  "categories": [
    "BEH_WASTE",
    "BEH_CIRC",
    "BEH_ENER",
    "BEH_EFFI",
    "KN_CLIM"
  ],
  "answers": {
    "BEH_WASTE": "B",
    "BEH_CIRC": "B",
    "BEH_ENER": "C",
    "BEH_EFFI": "C",
    "KN_CLIM": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-035"
}
```

---

## Kérdőív 36

**Survey ID:** `impactad-v1-batch5-b36`
**Kategóriák:** KN_CLIM, KN_CARE, KN_TRAN, KN_WASTE, KN_CIRC

### 1. KN - KN-CLIM-1176

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-TRAN-1177

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) Csak a márka logója a csomagoláson
B) A minimális, ritka változtatások
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. KN - KN-WASTE-1178

**Kérdés:** Melyik állítás félrevezető a(z) hulladékcsökkentés kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. KN - KN-FOOD-1179

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) Az, ha nem gondolkozol rajta
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Csak a márka logója a csomagoláson
D) A minimális, ritka változtatások

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. BEH - BEH-BIOD-1180

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak akkor változtatni, ha mások is látják
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-036",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136836,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b36",
  "question_count": 5,
  "categories": [
    "KN_CLIM",
    "KN_CARE",
    "KN_TRAN",
    "KN_WASTE",
    "KN_CIRC"
  ],
  "answers": {
    "KN_CLIM": "C",
    "KN_CARE": "C",
    "KN_TRAN": "C",
    "KN_WASTE": "A",
    "KN_CIRC": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-036"
}
```

---

## Kérdőív 37

**Survey ID:** `impactad-v1-batch5-b37`
**Kategóriák:** KN_TRAN, KN_CLIM, KN_CARE, BEH_CLIM, BEH_CARE

### 1. KN - KN-TRAN-1181

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Csak a márka logója a csomagoláson
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. KN - KN-TRAN-1182

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A szokások hosszú távon számítanak.”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-CLIM-1183

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) klímavédelem terén?

A) Csak a márka logója a csomagoláson
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. BEH - BEH-CLIM-1184

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) klímavédelem terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) Semmin nem változtatni, mert „úgyis mindegy”
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**

**Helyes válasz:** D
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-WATER-1185

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) víztakarékosság terén?

A) Csak a márka logója a csomagoláson
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-037",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136837,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b37",
  "question_count": 5,
  "categories": [
    "KN_TRAN",
    "KN_CLIM",
    "KN_CARE",
    "BEH_CLIM",
    "BEH_CARE"
  ],
  "answers": {
    "KN_TRAN": "B",
    "KN_CLIM": "B",
    "KN_CARE": "B",
    "BEH_CLIM": "D",
    "BEH_CARE": "D"
  },
  "consent_pers": 1,
  "request_id": "uuid-037"
}
```

---

## Kérdőív 38

**Survey ID:** `impactad-v1-batch5-b38`
**Kategóriák:** KN_FOOD, KN_WASTE, BEH_WATER, BEH_EFFI, KN_WATER

### 1. KN - KN-FOOD-1186

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Az, ha nem gondolkozol rajta
C) A minimális, ritka változtatások
D) Csak a márka logója a csomagoláson

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. BEH - BEH-WATER-1187

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) víztakarékosság terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-FOOD-1188

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Csak a márka logója a csomagoláson
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. KN - KN-WATER-1189

**Kérdés:** Melyik állítás félrevezető a(z) víztakarékosság kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) „A szokások hosszú távon számítanak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 5. KN - KN-CLIM-1190

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) klímavédelem terén?

A) A minimális, ritka változtatások
B) Csak a márka logója a csomagoláson
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-038",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136838,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b38",
  "question_count": 5,
  "categories": [
    "KN_FOOD",
    "KN_WASTE",
    "BEH_WATER",
    "BEH_EFFI",
    "KN_WATER"
  ],
  "answers": {
    "KN_FOOD": "A",
    "KN_WASTE": "A",
    "BEH_WATER": "C",
    "BEH_EFFI": "C",
    "KN_WATER": "C"
  },
  "consent_pers": 1,
  "request_id": "uuid-038"
}
```

---

## Kérdőív 39

**Survey ID:** `impactad-v1-batch5-b39`
**Kategóriák:** BEH_ENER, BEH_EFFI, KN_FOOD, KN_WASTE, BEH_BIOD

### 1. BEH - BEH-ENER-1191

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) Csak a „kis kényelmi” dolgokon változtatni néha
B) Csak akkor változtatni, ha mások is látják
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. KN - KN-FOOD-1192

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Az, ha nem gondolkozol rajta
D) Csak a márka logója a csomagoláson

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. BEH - BEH-BIOD-1193

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) Semmin nem változtatni, mert „úgyis mindegy”
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 4. KN - KN-ENER-1194

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) otthoni energiahasználat terén?

A) Csak a márka logója a csomagoláson
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. BEH - BEH-WASTE-1195

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-039",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136839,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b39",
  "question_count": 5,
  "categories": [
    "BEH_ENER",
    "BEH_EFFI",
    "KN_FOOD",
    "KN_WASTE",
    "BEH_BIOD"
  ],
  "answers": {
    "BEH_ENER": "C",
    "BEH_EFFI": "C",
    "KN_FOOD": "B",
    "KN_WASTE": "B",
    "BEH_BIOD": "C"
  },
  "consent_pers": 1,
  "request_id": "uuid-039"
}
```

---

## Kérdőív 40

**Survey ID:** `impactad-v1-batch5-b40`
**Kategóriák:** KN_TRAN, KN_CLIM, BEH_WASTE, BEH_CIRC, KN_BIOD

### 1. KN - KN-TRAN-1196

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „A szokások hosszú távon számítanak.”
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. BEH - BEH-WASTE-1197

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. BEH - BEH-WASTE-1198

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Csak a „kis kényelmi” dolgokon változtatni néha
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 4. KN - KN-BIOD-1199

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) természetvédelem terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Az, ha nem gondolkozol rajta
D) Csak a márka logója a csomagoláson

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. KN - KN-WASTE-1200

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) hulladékcsökkentés terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) A minimális, ritka változtatások
C) Az, ha nem gondolkozol rajta
D) Csak a márka logója a csomagoláson

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-040",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136840,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b40",
  "question_count": 5,
  "categories": [
    "KN_TRAN",
    "KN_CLIM",
    "BEH_WASTE",
    "BEH_CIRC",
    "KN_BIOD"
  ],
  "answers": {
    "KN_TRAN": "A",
    "KN_CLIM": "A",
    "BEH_WASTE": "C",
    "BEH_CIRC": "C",
    "KN_BIOD": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-040"
}
```

---

## Kérdőív 41

**Survey ID:** `impactad-v1-batch5-b41`
**Kategóriák:** KN_ENER, KN_EFFI, KN_WASTE, KN_CIRC, KN_FOOD

### 1. KN - KN-ENER-1201

**Kérdés:** Melyik állítás félrevezető a(z) otthoni energiahasználat kapcsán?

A) „A szokások hosszú távon számítanak.”
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**

**Helyes válasz:** D
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-WASTE-1202

**Kérdés:** Melyik állítás félrevezető a(z) hulladékcsökkentés kapcsán?

A) „A szokások hosszú távon számítanak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-FOOD-1203

**Kérdés:** Melyik állítás félrevezető a(z) élelmiszerpazarlás kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. KN - KN-FOOD-1204

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Csak a márka logója a csomagoláson
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. KN - KN-WATER-1205

**Kérdés:** Melyik állítás félrevezető a(z) víztakarékosság kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-041",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136841,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b41",
  "question_count": 5,
  "categories": [
    "KN_ENER",
    "KN_EFFI",
    "KN_WASTE",
    "KN_CIRC",
    "KN_FOOD"
  ],
  "answers": {
    "KN_ENER": "D",
    "KN_EFFI": "D",
    "KN_WASTE": "B",
    "KN_CIRC": "B",
    "KN_FOOD": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-041"
}
```

---

## Kérdőív 42

**Survey ID:** `impactad-v1-batch5-b42`
**Kategóriák:** KN_FOOD, KN_WASTE, BEH_ENER, BEH_EFFI, BEH_TRAN

### 1. KN - KN-FOOD-1206

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) élelmiszerpazarlás terén?

A) Csak a márka logója a csomagoláson
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. BEH - BEH-ENER-1207

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) Semmin nem változtatni, mert „úgyis mindegy”
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**

**Helyes válasz:** D
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. BEH - BEH-TRAN-1208

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) közlekedés terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak akkor változtatni, ha mások is látják
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 4. BEH - BEH-WASTE-1209

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-BIOD-1210

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-042",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136842,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b42",
  "question_count": 5,
  "categories": [
    "KN_FOOD",
    "KN_WASTE",
    "BEH_ENER",
    "BEH_EFFI",
    "BEH_TRAN"
  ],
  "answers": {
    "KN_FOOD": "B",
    "KN_WASTE": "B",
    "BEH_ENER": "D",
    "BEH_EFFI": "D",
    "BEH_TRAN": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-042"
}
```

---

## Kérdőív 43

**Survey ID:** `impactad-v1-batch5-b43`
**Kategóriák:** KN_BIOD, KN_CARE, KN_CLIM, KN_TRAN, KN_WATER

### 1. KN - KN-BIOD-1211

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) „A szokások hosszú távon számítanak.”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-CLIM-1212

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A szokások hosszú távon számítanak.”
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-BIOD-1213

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. KN - KN-TRAN-1214

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Az, ha nem gondolkozol rajta
C) Csak a márka logója a csomagoláson
D) A minimális, ritka változtatások

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. KN - KN-WATER-1215

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) víztakarékosság terén?

A) Az, ha nem gondolkozol rajta
B) A minimális, ritka változtatások
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) Csak a márka logója a csomagoláson

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-043",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136843,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b43",
  "question_count": 5,
  "categories": [
    "KN_BIOD",
    "KN_CARE",
    "KN_CLIM",
    "KN_TRAN",
    "KN_WATER"
  ],
  "answers": {
    "KN_BIOD": "A",
    "KN_CARE": "A",
    "KN_CLIM": "A",
    "KN_TRAN": "A",
    "KN_WATER": "C"
  },
  "consent_pers": 1,
  "request_id": "uuid-043"
}
```

---

## Kérdőív 44

**Survey ID:** `impactad-v1-batch5-b44`
**Kategóriák:** KN_WATER, KN_EFFI, KN_TRAN, KN_CLIM, BEH_CLIM

### 1. KN - KN-WATER-1216

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) víztakarékosság terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Csak a márka logója a csomagoláson
C) Az, ha nem gondolkozol rajta
D) A minimális, ritka változtatások

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 2. KN - KN-TRAN-1217

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-TRAN-1218

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) közlekedés terén?

A) Csak a márka logója a csomagoláson
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Az, ha nem gondolkozol rajta
D) A minimális, ritka változtatások

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. BEH - BEH-CLIM-1219

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) klímavédelem terhelésén?

A) Csak a „kis kényelmi” dolgokon változtatni néha
B) Csak akkor változtatni, ha mások is látják
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. BEH - BEH-BIOD-1220

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Semmin nem változtatni, mert „úgyis mindegy”
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-044",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136844,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b44",
  "question_count": 5,
  "categories": [
    "KN_WATER",
    "KN_EFFI",
    "KN_TRAN",
    "KN_CLIM",
    "BEH_CLIM"
  ],
  "answers": {
    "KN_WATER": "A",
    "KN_EFFI": "A",
    "KN_TRAN": "C",
    "KN_CLIM": "C",
    "BEH_CLIM": "C"
  },
  "consent_pers": 1,
  "request_id": "uuid-044"
}
```

---

## Kérdőív 45

**Survey ID:** `impactad-v1-batch5-b45`
**Kategóriák:** KN_BIOD, KN_CARE, BEH_BIOD, BEH_CARE, KN_WATER

### 1. KN - KN-BIOD-1221

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. BEH - BEH-BIOD-1222

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) Semmin nem változtatni, mert „úgyis mindegy”
D) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**

**Helyes válasz:** D
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-WATER-1223

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) víztakarékosság terén?

A) Csak a márka logója a csomagoláson
B) Az, ha nem gondolkozol rajta
C) A minimális, ritka változtatások
D) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**

**Helyes válasz:** D
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. BEH - BEH-BIOD-1224

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak akkor változtatni, ha mások is látják
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. BEH - BEH-CLIM-1225

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) klímavédelem terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) Semmin nem változtatni, mert „úgyis mindegy”
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-045",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136845,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b45",
  "question_count": 5,
  "categories": [
    "KN_BIOD",
    "KN_CARE",
    "BEH_BIOD",
    "BEH_CARE",
    "KN_WATER"
  ],
  "answers": {
    "KN_BIOD": "C",
    "KN_CARE": "C",
    "BEH_BIOD": "D",
    "BEH_CARE": "D",
    "KN_WATER": "D"
  },
  "consent_pers": 1,
  "request_id": "uuid-045"
}
```

---

## Kérdőív 46

**Survey ID:** `impactad-v1-batch5-b46`
**Kategóriák:** BEH_WASTE, BEH_CIRC, KN_TRAN, KN_CLIM, KN_CARE

### 1. BEH - BEH-WASTE-1226

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Csak a „kis kényelmi” dolgokon változtatni néha
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. KN - KN-TRAN-1227

**Kérdés:** Melyik állítás félrevezető a(z) közlekedés kapcsán?

A) „A szokások hosszú távon számítanak.”
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-CLIM-1228

**Kérdés:** Melyik állítás félrevezető a(z) klímavédelem kapcsán?

A) „A szokások hosszú távon számítanak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. KN - KN-CLIM-1229

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) klímavédelem terén?

A) A minimális, ritka változtatások
B) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
C) Csak a márka logója a csomagoláson
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** B
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 5. KN - KN-ENER-1230

**Kérdés:** Melyik állítás félrevezető a(z) otthoni energiahasználat kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-046",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136846,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b46",
  "question_count": 5,
  "categories": [
    "BEH_WASTE",
    "BEH_CIRC",
    "KN_TRAN",
    "KN_CLIM",
    "KN_CARE"
  ],
  "answers": {
    "BEH_WASTE": "B",
    "BEH_CIRC": "B",
    "KN_TRAN": "C",
    "KN_CLIM": "C",
    "KN_CARE": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-046"
}
```

---

## Kérdőív 47

**Survey ID:** `impactad-v1-batch5-b47`
**Kategóriák:** KN_BIOD, KN_CARE, BEH_ENER, BEH_EFFI

### 1. KN - KN-BIOD-1231

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) „A szokások hosszú távon számítanak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-BIOD-1232

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
C) „A szokások hosszú távon számítanak.”
D) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. KN - KN-BIOD-1233

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) természetvédelem terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Az, ha nem gondolkozol rajta
C) Csak a márka logója a csomagoláson
D) A minimális, ritka változtatások

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. BEH - BEH-ENER-1234

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-BIOD-1235

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-047",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136847,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b47",
  "question_count": 5,
  "categories": [
    "KN_BIOD",
    "KN_CARE",
    "BEH_ENER",
    "BEH_EFFI"
  ],
  "answers": {
    "KN_BIOD": "B",
    "KN_CARE": "B",
    "BEH_ENER": "B",
    "BEH_EFFI": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-047"
}
```

---

## Kérdőív 48

**Survey ID:** `impactad-v1-batch5-b48`
**Kategóriák:** BEH_FOOD, BEH_WASTE, KN_WATER, KN_EFFI, BEH_WATER

### 1. BEH - BEH-FOOD-1236

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) élelmiszerpazarlás terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. BEH - BEH-FOOD-1237

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) élelmiszerpazarlás terhelésén?

A) Csak akkor változtatni, ha mások is látják
B) Semmin nem változtatni, mert „úgyis mindegy”
C) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** C
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 3. KN - KN-WATER-1238

**Kérdés:** Melyik állítás félrevezető a(z) víztakarékosság kapcsán?

A) „A szokások hosszú távon számítanak.”
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** C
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 4. BEH - BEH-WATER-1239

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) víztakarékosság terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak akkor változtatni, ha mások is látják
C) Csak a „kis kényelmi” dolgokon változtatni néha
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-WATER-1240

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) víztakarékosság terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) A minimális, ritka változtatások
C) Az, ha nem gondolkozol rajta
D) Csak a márka logója a csomagoláson

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-048",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136848,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b48",
  "question_count": 5,
  "categories": [
    "BEH_FOOD",
    "BEH_WASTE",
    "KN_WATER",
    "KN_EFFI",
    "BEH_WATER"
  ],
  "answers": {
    "BEH_FOOD": "A",
    "BEH_WASTE": "A",
    "KN_WATER": "C",
    "KN_EFFI": "C",
    "BEH_WATER": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-048"
}
```

---

## Kérdőív 49

**Survey ID:** `impactad-v1-batch5-b49`
**Kategóriák:** KN_BIOD, KN_CARE, KN_WATER, KN_EFFI, KN_WASTE

### 1. KN - KN-BIOD-1241

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”
B) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
C) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
D) „A szokások hosszú távon számítanak.”

**Helyes válasz:** B
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 2. KN - KN-WATER-1242

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) víztakarékosság terén?

A) A minimális, ritka változtatások
B) Az, ha nem gondolkozol rajta
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) Csak a márka logója a csomagoláson

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 3. KN - KN-WASTE-1243

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) hulladékcsökkentés terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) Csak a márka logója a csomagoláson
C) A minimális, ritka változtatások
D) Az, ha nem gondolkozol rajta

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### 4. BEH - BEH-ENER-1244

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) Semmin nem változtatni, mert „úgyis mindegy”
D) Csak akkor változtatni, ha mások is látják

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-BIOD-1245

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) természetvédelem terén?

A) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
B) A minimális, ritka változtatások
C) Az, ha nem gondolkozol rajta
D) Csak a márka logója a csomagoláson

**Helyes válasz:** A
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-049",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136849,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b49",
  "question_count": 5,
  "categories": [
    "KN_BIOD",
    "KN_CARE",
    "KN_WATER",
    "KN_EFFI",
    "KN_WASTE"
  ],
  "answers": {
    "KN_BIOD": "B",
    "KN_CARE": "B",
    "KN_WATER": "C",
    "KN_EFFI": "C",
    "KN_WASTE": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-049"
}
```

---

## Kérdőív 50

**Survey ID:** `impactad-v1-batch5-b50`
**Kategóriák:** BEH_ENER, BEH_EFFI, KN_BIOD, KN_CARE, BEH_BIOD

### 1. BEH - BEH-ENER-1246

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) otthoni energiahasználat terhelésén?

A) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
B) Csak a „kis kényelmi” dolgokon változtatni néha
C) Csak akkor változtatni, ha mások is látják
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** A
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 2. KN - KN-BIOD-1247

**Kérdés:** Melyik állítás félrevezető a(z) természetvédelem kapcsán?

A) **„Egyetlen apró lépés mindent megold, a többi nem számít.”**
B) „A sok kis lépés összeadódik, de a nagy tételek a leghatékonyabbak.”
C) „A szokások hosszú távon számítanak.”
D) „Érdemes a helyi szabályokat ismerni (pl. szelektív).”

**Helyes válasz:** A
**Magyarázat:** A fenntarthatóság több tényezőn múlik: nincs egyetlen csodamegoldás.

### 3. BEH - BEH-BIOD-1248

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) természetvédelem terhelésén?

A) Semmin nem változtatni, mert „úgyis mindegy”
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak akkor változtatni, ha mások is látják
D) Csak a „kis kényelmi” dolgokon változtatni néha

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 4. BEH - BEH-WASTE-1249

**Kérdés:** Egy hétköznapi döntésben melyik lépés csökkenti általában a legtöbbet a(z) hulladékcsökkentés terhelésén?

A) Csak a „kis kényelmi” dolgokon változtatni néha
B) **A legnagyobb tétel csökkentése (például ritkábban, de tudatosabban fogyasztani)**
C) Csak akkor változtatni, ha mások is látják
D) Semmin nem változtatni, mert „úgyis mindegy”

**Helyes válasz:** B
**Magyarázat:** A legnagyobb tételek és a rendszeresség adják a legnagyobb összhatást.

### 5. KN - KN-WASTE-1250

**Kérdés:** Két jó szokás közül melyik hoz általában nagyobb hatást a(z) hulladékcsökkentés terén?

A) A minimális, ritka változtatások
B) Az, ha nem gondolkozol rajta
C) **A ritkább, de nagy hatású döntések (pl. kevesebb autózás) gyakran többet számítanak**
D) Csak a márka logója a csomagoláson

**Helyes válasz:** C
**Magyarázat:** Az összhatás gyakran az arányokon múlik: a nagy tételek gyorsabban látszanak.

### Postback Payload

```json
{
  "transaction_id": "survey-20260205-050",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136850,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b50",
  "question_count": 5,
  "categories": [
    "BEH_ENER",
    "BEH_EFFI",
    "KN_BIOD",
    "KN_CARE",
    "BEH_BIOD"
  ],
  "answers": {
    "BEH_ENER": "A",
    "BEH_EFFI": "A",
    "KN_BIOD": "A",
    "KN_CARE": "A",
    "BEH_BIOD": "B"
  },
  "consent_pers": 1,
  "request_id": "uuid-050"
}
```

---
