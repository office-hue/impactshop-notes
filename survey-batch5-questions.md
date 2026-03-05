# ImpactShop Saját Kérdőív - Batch 5 Survey

## Kérdőív Specifikáció

**Survey ID:** `impactad-v1-batch5-b1`  
**Verzió:** v1  
**Kérdések száma:** 5  
**Válasz formátum:** Single choice (A-D)  
**Jutalom:** 10 pont + 10 szavazat  
**Target ID:** impactad  

## Kérdések

### 1. Hulladékgazdálkodás - Tudás (KN_WASTE)

**Kérdés:** Az EU hulladékhierarchiája szerint melyik a legjobb első lépés a hulladék kezelésében?

A) Újrahasználat (ugyanazt a tárgyat újra használni)  
B) Lerakás vagy elégetés  
C) Hulladék megelőzése (kevesebb hulladék keletkezzen)  
D) Újrahasznosítás (anyagában feldolgozás)  

**Helyes válasz:** C  
**Kategória:** KN_WASTE  
**Magyarázat:** A hulladékhierarchia tetején a megelőzés áll: a legzöldebb hulladék az, ami létre sem jön.

### 2. Energiahatékonyság - Viselkedés (BEH_ENER)

**Kérdés:** Ha gyorsan akarsz energiát spórolni otthon, melyik irány a legnagyobb hatású?

A) A sütő ajtaját naponta kétszer kinyitni  
B) Minden telefontöltő kihúzása éjjel  
C) Fűtési veszteségek csökkentése (szigetelés, termosztát, nyílászáró)  
D) A TV távirányítójának elemeit cserélni  

**Helyes válasz:** C  
**Kategória:** BEH_ENER  
**Magyarázat:** A legnagyobb tételeket érdemes először optimalizálni: fűtés, melegvíz, nagy gépek.

### 3. Adományozás - Bizalom (DON_TRUST)

**Kérdés:** Mi ad a legjobb támpontot arra, hogy egy adománygyűjtő szervezet átláthatóan működik?

A) Minél több hangzatos szlogen  
B) Nyilvános beszámoló, pénzügyi jelentés és ellenőrizhető projekteredmények  
C) Csak szép fotók a közösségi médiában  
D) Az, ha sosem válaszol kérdésekre  

**Helyes válasz:** B  
**Kategória:** DON_TRUST  
**Magyarázat:** Az átláthatóság jele a nyilvános, számszerű beszámoló és a visszakereshető eredmény.

### 4. Biodiverzitás - Tudás (KN_BIOD)

**Kérdés:** Mit jelent a biodiverzitás (biológiai sokféleség) kifejezés?

A) A hegyek magasságát  
B) Az élőlények sokféleségét (fajok, élőhelyek és genetikai változatosság)  
C) Az évszakok számát  
D) A tenger sótartalmát  

**Helyes válasz:** B  
**Kategória:** KN_BIOD  
**Magyarázat:** A biodiverzitás több szinten értendő: faj, élőhely, genetika.

### 5. Közlekedés - Viselkedés (BEH_TRAN)

**Kérdés:** Ha 10 km-re mész, és van több közlekedési opció, melyik csökkenti leginkább az egy főre jutó kibocsátást?

A) Tömegközlekedés vagy telekocsi (többen utaztok egy járműben)  
B) Egyedül autóval menni  
C) Autóval menni üres csomagtartóval  
D) Motorral gyorsítani a pirosnál  

**Helyes válasz:** A  
**Kategória:** BEH_TRAN  
**Magyarázat:** Az egy főre jutó kibocsátást az osztozás (több utas) és a hatékony módok csökkentik.

## Postback Payload Példa

```json
{
  "transaction_id": "survey-20260205-001",
  "pseudo_id": "pseudo123",
  "payout": 1,
  "timestamp": 1707136800,
  "signature": "HMAC_SHA256(transaction_id|pseudo_id|payout|timestamp, secret)",
  "survey_id": "impactad-v1-batch5-b1",
  "question_count": 5,
  "categories": ["KN_WASTE", "BEH_ENER", "DON_TRUST", "KN_BIOD", "BEH_TRAN"],
  "answers": {
    "KN_WASTE": "C",
    "BEH_ENER": "C", 
    "DON_TRUST": "B",
    "KN_BIOD": "B",
    "BEH_TRAN": "A"
  },
  "consent_pers": 1,
  "request_id": "uuid-generated"
}
```

## Segment Mapping

A kérdések a következő szegmenseket érintik a terv szerint:

- **KN_WASTE** → Hulladékgazdálkodási tudás
- **BEH_ENER** → Energiahatékonysági viselkedés  
- **DON_TRUST** → Adományozási bizalom
- **KN_BIOD** → Biodiverzitás tudás
- **BEH_TRAN** → Közlekedési viselkedés

Minden kategória `targetable=safe` és `sensitivity=low` besorolású a partner célzáshoz.

## Technikai Megjegyzések

1. **Kérdés-válasz integritás:** JSON kulcsok (categories) egyeznek a válasz kulcsokkal
2. **Idempotencia kulcs:** `internal_survey + transaction_id + survey_id`
3. **HMAC canonical string:** `transaction_id|pseudo_id|payout|timestamp`
4. **Consent gate:** Segment update csak `consent_pers=1` esetén történik
5. **Rate limiting:** Maximum 10 completion/user/óra