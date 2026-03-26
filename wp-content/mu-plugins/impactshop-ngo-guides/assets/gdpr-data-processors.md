# GDPR Adatfeldolgozói Nyilvántartás

> **Verzió**: 1.2  
> **Dátum**: 2026-02-25  
> **Státusz**: HATÁLYOS  
> **Vonatkozik**: ImpactShop teljes platform (SHARITEAM.COM, app.sharity.hu)  
> **Adatkezelő**: Sharity Adományszervező Alapítvány + Sharity Mobile Application Zrt.

---

## 1. Áttekintés

Ez a dokumentum tartalmazza az ImpactShop / Sharity platform által igénybe vett **adatfeldolgozók** (data processors) nyilvántartását a GDPR 30. cikk (2) bekezdésének megfelelően.

Az **adatkezelő** (data controller) minden esetben a Sharity (Alapítvány és/vagy Zrt.), amely meghatározza a személyes adatok kezelésének célját és eszközeit.

**Pseudo ID és személyes adat:** A Platform Pseudo ID-t (böngészőben generált online azonosító) használ regisztráció nélkül. A Pseudo ID a körülményektől függően személyes adatnak minősülhet (GDPR online azonosító; összekapcsolhatóság IP-vel, tranzakcióval, Stripe emaillel stb.) — ld. ÁSZF §5.2, §16.3. A Pseudo ID-t az Adatkezelési Tájékoztató külön nevesíti: tárolás (cookie/localStorage), jogalap, megőrzési idő és törlési/összekötési szabályok.

---

## 2. Adatfeldolgozók nyilvántartása 

### 2.1 Stripe Payments Europe, Limited

| Mező | Érték |
|------|-------|
| **Entitás** | Stripe Payments Europe, Limited |
| **Székhely** | 1 Grand Canal Street Lower, Dublin 2, Írország |
| **Szerep** | Adatfeldolgozó (fizetési szolgáltató) |
| **Kezelt adatok** | Kártyaadatok (soha nem érinti a Sharity szerverét), email cím, tranzakciós összeg, IP cím, device fingerprint |
| **Adatkezelés célja** | Impact Amplifier szavazatvásárlás fizetési tranzakciók feldolgozása |
| **DPA (adatfeldolgozói megállapodás)** | https://stripe.com/legal/dpa — automatikusan érvényes a Stripe Services Agreement elfogadásával |
| **Adatátvitel harmadik országba** | Igen — Stripe Inc. (USA) — EU–US Data Privacy Framework certified |
| **Adatmegőrzési idő** | Stripe saját policy: tranzakciós adatok a pénzmosás-megelőzési és számviteli kötelezettség szerint (min. 5 év) |
| **Kapcsolódó garancia** | PCI-DSS Level 1, SOC 2 Type II, ISO 27001 |

### 2.2 Dognet s.r.o.

| Mező | Érték |
|------|-------|
| **Entitás** | Dognet s.r.o. |
| **Székhely** | Bratislava, Szlovákia (EU) |
| **Szerep** | Adatfeldolgozó (affiliate hálózat szolgáltató) |
| **Kezelt adatok** | Cookie azonosítók, kattintási adatok (click tracking), tranzakciós visszaigazolások (affiliate conversion) |
| **Adatkezelés célja** | Affiliate jutalék nyomon követése az ImpactShop → partner webshop vásárlási folyamatban |
| **DPA** | Dognet Általános Partneri Szerződés melléklete (GDPR záradék) — egyedileg kötött |
| **Adatátvitel harmadik országba** | Nem — EU-n belüli feldolgozás |
| **Adatmegőrzési idő** | Cookie élettartam: 30 nap; tranzakciós adat: min. 5 év (könyvelési kötelezettség) |

**Cookie megfelelés:** A Dognet affiliate tracking cookie/azonosító alapú. A cookie/tároló tájékoztatás és az esetleges hozzájárulás-kezelés megfelel a hatályos e-privacy és hazai szabályoknak, és koherens a Pseudo ID leírással (ld. ÁSZF §5.2, §16.6 és Cookie Tájékoztató).

### 2.3 Cloudflare, Inc.

| Mező | Érték |
|------|-------|
| **Entitás** | Cloudflare, Inc. |
| **Székhely** | San Francisco, USA |
| **Szerep** | Adatfeldolgozó (CDN, WAF, DNS, DDoS védelem) |
| **Kezelt adatok** | IP cím, HTTP request/response metaadatok, Turnstile token (CAPTCHA) |
| **Adatkezelés célja** | Weboldal védelme, rate limiting, bot szűrés, SSL terminálás |
| **DPA** | https://www.cloudflare.com/cloudflare-customer-dpa/ — automatikusan érvényes Enterprise és Free/Pro/Business szinteken is |
| **Adatátvitel harmadik országba** | Igen — USA + globális edge hálózat — EU–US DPF + Binding Corporate Rules |
| **Adatmegőrzési idő** | Request logok: 72 óra (standard); Analytics: 30 nap |
| **Kapcsolódó garancia** | SOC 2 Type II, ISO 27001, C5 |

### 2.4 Ezit Kft. (tárhelyszolgáltató)

| Mező | Érték |
|------|-------|
| **Entitás** | Ezit Kft. |
| **Székhely** | Magyarország |
| **Szerep** | Adatfeldolgozó (tárhelyszolgáltató, szerver üzemeltetés) |
| **Kezelt adatok** | Teljes WordPress adatbázis (felhasználói adatok, tranzakciók), szervernaplók (IP, User-Agent) |
| **Adatkezelés célja** | Weboldal és alkalmazás hosztolása |
| **DPA** | Ezit Kft. ÁSZF GDPR melléklete — egyedileg kötött |
| **Adatátvitel harmadik országba** | Nem — magyar adatközpont |
| **Adatmegőrzési idő** | Szerver logok: 30 nap; backup: max 20 pillanatkép |

### 2.5 Google Cloud Platform (opcionális — AI Agent)

| Mező | Érték |
|------|-------|
| **Entitás** | Google Cloud EMEA Limited / Google LLC |
| **Székhely** | Dublin, Írország / Mountain View, USA |
| **Szerep** | Adatfeldolgozó (AI/ML szolgáltatások, Google Workspace API) |
| **Kezelt adatok** | Google Workspace-ból érkező email/drive adatok (csak az AI Agent funkciónál), service account credential |
| **Adatkezelés célja** | NGO adatgyűjtés automatizálása, Google Sheets szinkron |
| **DPA** | https://cloud.google.com/terms/data-processing-addendum |
| **Adatátvitel harmadik országba** | Igen — EU–US DPF + Standard Contractual Clauses (SCC) |
| **Adatmegőrzési idő** | Google policy szerint; Sharity: credential files titkosítva |

### 2.6 Vonage / Nexmo (SMS szolgáltató)

| Mező | Érték |
|------|-------|
| **Entitás** | Vonage Holdings Corp. / Vonage Business Limited |
| **Székhely** | Hollandia (Vonage V.B.) / Egyesült Királyság (Vonage Business Limited) / USA (Vonage Holdings Corp.) |
| **Szerep** | Adatfeldolgozó (SMS kézbesítés) |
| **Kezelt adatok** | Telefonszám, SMS tartalom (jegy/értesítés szöveg) |
| **Adatkezelés célja** | Digitális Tombola jegy/értesítés SMS kézbesítése |
| **DPA** | Vonage DPA — az API-szolgáltatási szerződés melléklete |
| **Adatátvitel harmadik országba** | Igen — UK/USA, az Ericsson csoporton belüli adattovábbítás |
| **Adatmegőrzési idő** | Vonage policy szerint; SMS kézbesítési logok: max 30 nap |
| **Megjegyzés** | UK/EGT érintettek esetén csoporttagok is kezelhetnek adatot: Ericsson; Vonage Business Limited (UK); Vonage V.B. (NL) — ld. ÁSZF §16.5 |

### 2.7 CJ (Commission Junction) — affiliate hálózat

| Mező | Érték |
|------|-------|
| **Entitás** | Commission Junction LLC (a Publicis Groupe tagja) |
| **Székhely** | Santa Barbara, CA, USA |
| **Szerep** | Önálló adatkezelő / közös adatkezelő (affiliate tracking) |
| **Kezelt adatok** | Cookie azonosítók, kattintási adatok, tranzakciós visszaigazolás (affiliate conversion), IP cím |
| **Adatkezelés célja** | Affiliate jutalék nyomon követése (publisher → advertiser tranzakciók) |
| **DPA / adatvédelmi tájékoztató** | Services Privacy Policy: https://www.cj.com/legal/privacy-policy-services |
| **Adatátvitel harmadik országba** | Igen — USA (Publicis Groupe); EU–US DPF |
| **Adatmegőrzési idő** | CJ policy szerint; cookie élettartam: kampányfüggő (jellemzően 30–45 nap) |
| **Megjegyzés** | A CJ a tracking cookie tekintetében önálló adatkezelőként is felléphet — az ÁSZF §16.6 ennek megfelelően tájékoztat |

### 2.8 TradeTracker — affiliate hálózat

| Mező | Érték |
|------|-------|
| **Entitás** | TradeTracker International B.V. |
| **Székhely** | Almere, Hollandia (EU) |
| **Szerep** | Önálló adatkezelő / közös adatkezelő (affiliate tracking) |
| **Kezelt adatok** | Cookie azonosítók, kattintási adatok, tranzakciós visszaigazolás, IP cím |
| **Adatkezelés célja** | Affiliate jutalék nyomon követése |
| **DPA / adatvédelmi tájékoztató** | Privacy Policy: https://tradetracker.com/privacy-policy/ |
| **Adatátvitel harmadik országba** | Nem — EU-n belüli feldolgozás |
| **Adatmegőrzési idő** | TradeTracker policy szerint; cookie élettartam: kampányfüggő |

### 2.9 Tradedoubler — affiliate hálózat

| Mező | Érték |
|------|-------|
| **Entitás** | Tradedoubler AB (reg.no 556575-7423) |
| **Székhely** | Stockholm, Svédország (EU) |
| **Szerep** | Adatfeldolgozó (affiliate tracking — brand megbízásából); egyes esetekben önálló adatkezelő |
| **Kezelt adatok** | Cookie azonosítók, kattintási adatok, IP cím, tranzakciós visszaigazolás |
| **Adatkezelés célja** | Affiliate jutalék nyomon követése (publisher hálózat) |
| **DPA / adatvédelmi tájékoztató** | Privacy Policy: https://www.tradedoubler.com/en/privacy-policy/ ; DPO: dpo@tradedoubler.com |
| **Adatátvitel harmadik országba** | Lehetséges — UK (adequacy decision); egyéb esetben SCC |
| **Adatmegőrzési idő** | Cookie: max 730 nap; tranzakciós adat: helyi könyvelési törvények szerint (5–10 év) |

### 2.10 Offerwall providerek (Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research)

Az Impact Challenge offerwall felületén harmadik fél szolgáltatók feladatokat (kérdőívek, alkalmazás-kipróbálás, regisztrációk) kínálnak a Felhasználók számára. Ezek a providerek jellemzően **önálló adatkezelőként** járnak el.

#### Bitlabs

| Mező | Érték |
|------|-------|
| **Entitás** | BitBurst GmbH (Bitlabs) |
| **Székhely** | Németország (EU) |
| **Szerep** | Önálló adatkezelő (offerwall / kérdőív szolgáltató) |
| **Kezelt adatok** | Felhasználói azonosító (publisher user ID), válaszadatok, IP cím, device info |
| **Adatkezelés célja** | Offerwall feladatok biztosítása, jutalék-elszámolás |
| **DPA / adatvédelmi tájékoztató** | Privacy Policy: https://bitlabs.ai/privacy |
| **Adatátvitel harmadik országba** | Nem jellemző — EU-n belüli feldolgozás |

#### Pollfish

| Mező | Érték |
|------|-------|
| **Entitás** | Pollfish LLC (a Prodege csoport tagja) |
| **Székhely** | USA |
| **Szerep** | Önálló adatkezelő (kérdőív / survey szolgáltató) |
| **Kezelt adatok** | Demográfiai adatok, válaszadatok, device info, IP cím |
| **Adatkezelés célja** | Kérdőív kitöltés, piackutatás, jutalék-elszámolás |
| **DPA / adatvédelmi tájékoztató** | Respondent Terms: https://www.pollfish.com/terms/respondent |
| **Adatátvitel harmadik országba** | Igen — USA |

#### Cint

| Mező | Érték |
|------|-------|
| **Entitás** | Cint AB |
| **Székhely** | Stockholm, Svédország (EU) |
| **Szerep** | Önálló adatkezelő (research marketplace / survey routing) |
| **Kezelt adatok** | Demográfiai adatok, válaszadatok, device info, IP cím, cookie ID |
| **Adatkezelés célja** | Kérdőív routing, piackutatási minta biztosítása |
| **DPA / adatvédelmi tájékoztató** | Privacy Notice: https://www.cint.com/privacy-notice ; DPO: privacy@cint.com |
| **Adatátvitel harmadik országba** | Az adatokat EU-n belüli szervereken tárolja; de a Cint csoport globális leányvállalatain belül lehetséges továbbítás |
| **Adatmegőrzési idő** | Cint policy szerint |

#### ayeT-Studios

| Mező | Érték |
|------|-------|
| **Entitás** | ayeT-Studios GmbH |
| **Székhely** | Große Düwelstraße 28, 30171 Hannover, Németország (EU) |
| **Cégjegyzékszám** | HRB 211094 (Amtsgericht Hannover); ÁFA: DE294415615 |
| **Szerep** | Önálló adatkezelő (offerwall / kérdőív / rewarded ad szolgáltató) |
| **Kezelt adatok** | Felhasználói azonosító (publisher user ID), device ID (GAID/IDFA), IP cím, válaszadatok, demográfiai adatok |
| **Adatkezelés célja** | Offerwall feladatok (Gametastic), kérdőív routing (Polltastic), jutalék-elszámolás |
| **DPA / adatvédelmi tájékoztató** | Privacy Policy: https://www.ayetstudios.com/privacy-policy ; DPO: privacy@ayetstudios.com (külső: activelaw Offenhausen.Wolter, Hannover) |
| **Adatátvitel harmadik országba** | Igen — AWS US szerverek (EU SCC alapján) |
| **Adatmegőrzési idő** | Használat ideje + 90 nap; survey adat: MR partner policy szerint |

#### CPX Research (Make Opinion)

| Mező | Érték |
|------|-------|
| **Entitás** | Make Opinion GmbH (d/b/a CPX Research) |
| **Székhely** | Elfenallee 5, 13127 Berlin, Németország (EU) |
| **Szerep** | Önálló adatkezelő (survey / offerwall szolgáltató) |
| **Kezelt adatok** | Felhasználói azonosító, demográfiai adatok (kor, nem, irányítószám), válaszadatok, IP cím, device fingerprint |
| **Adatkezelés célja** | Kérdőív matching és kitöltés, piackutatás, jutalék-elszámolás |
| **DPA / adatvédelmi tájékoztató** | Privacy Policy: https://cpx-research.com/main/en/privacy-user/ ; DPO: privacy@makeopinion.com (Nana Gabelia) |
| **Adatátvitel harmadik országba** | Elsődlegesen Németország (EU); survey partnereken keresztül lehetséges: USA, Kanada, Franciaország, Románia, Izrael, Pakisztán, India |
| **Adatmegőrzési idő** | Make Opinion policy szerint |

> **Fontos megjegyzés az offerwall providerekhez:** Ezek a szolgáltatók az ÁSZF §16.6 értelmében **önálló adatkezelőként** alkalmazhatnak sütiket vagy hasonló technológiákat. A Felhasználót a Cookie Tájékoztató és az egyes providerek saját adatvédelmi tájékoztatója informálja. A Sharity mint publisher a felhasználói azonosítót (Pseudo ID / publisher user ID) adja át az offerwall API-nak — ld. ÁSZF §5.2 Parancs/Teendő.

---

## 3. Személyes adatok áramlási térkép (Data Flow Map)

```
Felhasználó (böngésző)
    │
    ├─── [HTTPS/TLS] ──→ Cloudflare (CDN, WAF, Turnstile)
    │                         │
    │                         └──→ Ezit szerver (WordPress)
    │                                  │
    │                                  ├── Affiliate kattintás → Dognet / CJ / TradeTracker / Tradedoubler cookie
    │                                  │     └── Vásárlás visszaigazolás → affiliate API
    │                                  │
    │                                  ├── Impact Challenge offerwall → Bitlabs / Pollfish / Cint / ayeT / CPX Research API
    │                                  │     └── Kérdőív / feladat → Pont jóváírás
    │                                  │
    │                                  └── Szavazatvásárlás (checkout)
    │                                        │
    │                                        └──→ Stripe Checkout (hosted)
    │                                              ├── Kártyaadat → Stripe (soha Sharity)
    │                                              └── Webhook → Ezit szerver → DB
    │
    ├─── [SMS] Digitális Tombola jegy/értesítés → Vonage (Nexmo) API
    │
    └─── [Opcionális] AI Agent → Google Cloud API
```

---

## 4. Érintetti jogok kezelése

| Jog | Implementáció | Felelős |
|-----|--------------|---------|
| **Hozzáférés joga** (15. cikk) | WP Admin → felhasználói adatok export | Sharity DPO |
| **Törlés joga** (17. cikk) | Soft-delete (céges adatok 30 nap), anonimizálás | Sharity fejlesztő |
| **Adathordozhatóság** (20. cikk) | JSON export WP Admin-ból | Sharity DPO |
| **Tiltakozás** (21. cikk) | Email → privacy@sharity.hu | Sharity DPO |
| **Stripe-nál tárolt adat** | Sharity kéri Stripe-tól, vagy érintett közvetlenül Stripe-nál | Sharity DPO + Stripe Support |

### 4.1 Törlési eljárás

1. **Érintett kéri a törlést** → office@sharity.hu
2. **Sharity ellenőrzi**: van-e jogi megőrzési kötelezettség (számviteli: 8 év, ÁFA: 5 év)
3. **Ha nincs kötelezettség**: személyes adatok törlése/anonimizálása ≤ 30 nap
4. **Ha van kötelezettség**: tájékoztatás az érintettnek, adatok zárolása (nem törölhetők, de nem használhatók)
5. **Stripe-nál**: Sharity nem tudja közvetlenül törölni → érintett a Stripe privacy policy szerint járhat el
6. **Naplózás**: törlési kérés + végrehajtás dátuma + ok → GDPR kérések naplója

---

## 5. Adatfeldolgozói szerződések státusza

| Feldolgozó | DPA státusz | Utolsó felülvizsgálat | Következő felülvizsgálat |
|-----------|-------------|----------------------|-------------------------|
| **Stripe** | ✅ Automatikus (SSA elfogadásával) | 2026-02-25 | 2026-08-25 |
| **Dognet** | ⚠️ Felülvizsgálat szükséges — a Lead Media s.r.o. partneri szerződés GDPR záradéka alapján; publikus adatvédelmi tájékoztató: https://www.dognet.sk/wp-content/uploads/2023/11/gdpr-sk.pdf | — | **2026-03-15** (sürgős) |
| **Cloudflare** | ✅ Automatikus (Customer DPA) | 2026-02-25 | 2026-08-25 |
| **Ezit** | ⚠️ Felülvizsgálat szükséges — az Ezit Kft. a Magyar Hosting (mhosting.hu) csoport része; hatályos ÁSZF: https://www.mhosting.hu/aszf/ (2025.08.04.); Adatkezelési tájékoztató: https://www.mhosting.hu/adatkezelesi-tajekoztato/ (2025.07.17.); Adattovábbítási nyilatkozat: https://www.mhosting.hu/adattovabbitasi-nyilatkozat/ | — | **2026-03-15** (sürgős) |
| **Google Cloud** | ✅ Automatikus (Cloud DPA) | 2026-02-25 | 2026-08-25 |
| **Vonage (Nexmo)** | ⚠️ Ellenőrizendő — API-szolgáltatási szerződés DPA melléklete | — | **2026-03-15** |
| **CJ (Commission Junction)** | ⚠️ Ellenőrizendő — önálló adatkezelő; Services Privacy Policy: https://www.cj.com/legal/privacy-policy-services | — | **2026-03-15** |
| **TradeTracker** | ⚠️ Ellenőrizendő — önálló adatkezelő; Privacy Policy: https://tradetracker.com/privacy-policy/ | — | **2026-03-15** |
| **Tradedoubler** | ⚠️ Ellenőrizendő — adatfeldolgozó/önálló adatkezelő; Privacy Policy + DPO: dpo@tradedoubler.com | — | **2026-03-15** |
| **Bitlabs** | ℹ️ Önálló adatkezelő — Privacy Policy: https://bitlabs.ai/privacy | — | **2026-06-25** |
| **Pollfish** | ℹ️ Önálló adatkezelő — Respondent Terms: https://www.pollfish.com/terms/respondent | — | **2026-06-25** |
| **Cint** | ℹ️ Önálló adatkezelő — Privacy Notice: https://www.cint.com/privacy-notice | — | **2026-06-25** |
| **ayeT-Studios** | ℹ️ Önálló adatkezelő — Privacy Policy: https://www.ayetstudios.com/privacy-policy ; DPO: privacy@ayetstudios.com | — | **2026-06-25** |
| **CPX Research (Make Opinion)** | ℹ️ Önálló adatkezelő — Privacy Policy: https://cpx-research.com/main/en/privacy-user/ ; DPO: privacy@makeopinion.com | — | **2026-06-25** |

A Dognet és Ezit DPA-kat formálisan felül kell vizsgálni, és szükség esetén kiegészítő adatfeldolgozói megállapodást kell kötni a fenti hivatkozások alapján.

---

## 6. Hatósági kapcsolattartó — panasz esetén

Az érintett személyes adatokkal kapcsolatos panaszát az alábbi hatósághoz is benyújthatja:

| Hatóság | Elérhetőség |
|---------|-------------|
| **Nemzeti Adatvédelmi és Információszabadság Hatóság (NAIH)** | 1055 Budapest, Falk Miksa utca 9–11. |
| Telefon | +36 1 391-1400 |
| E-mail | ugyfelszolgalat@naih.hu |
| Weboldal | https://www.naih.hu |
| Online beadvány | https://www.naih.hu/panaszbejelentes |

> Ez a szakasz összhangban áll az ÁSZF §19.4(d) pontjával, amely szintén hivatkozik a NAIH-ra.

---

## 7. Adatvédelmi incidens naplóvezetés

A GDPR 33. cikk (5) bekezdése alapján az adatkezelő köteles nyilvántartást vezetni az adatvédelmi incidensekről.

### 7.1 Naplózandó adatok

Minden adatvédelmi incidens esetén rögzíteni kell:

| Mező | Leírás |
|------|--------|
| **Incidens azonosító** | Egyedi sorszám (pl. GDPR-INC-2026-001) |
| **Észlelés dátuma/ideje** | Az incidens észlelésének időpontja |
| **Incidens leírása** | Az incidens jellege, érintett rendszer(ek), körülmények |
| **Érintett adatok köre** | Milyen személyes adatokat érint (Pseudo ID, email, tranzakciós adat stb.) |
| **Érintettek becsült száma** | Hány érintett személyes adata kompromittálódott/sérült |
| **Érintett adatfeldolgozó(k)** | Stripe, Dognet, Cloudflare, Ezit, Google Cloud, Vonage, CJ, TradeTracker, Tradedoubler, Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research — amelyiknél az incidens felmerült |
| **Súlyosság** | Alacsony / Közepes / Magas / Kritikus |
| **Megtett intézkedések** | Azonnali és utólagos korrekciós lépések |
| **NAIH bejelentés** | Szükséges-e (72 órán belül); ha igen, bejelentés dátuma |
| **Érintettek értesítése** | Szükséges-e; ha igen, értesítés dátuma és módja |
| **Lezárás dátuma** | Az incidens kezelésének lezárása |

### 7.2 Eljárásrend

1. **Észlelés**: automatikus monitoring vagy manuális bejelentés (office@sharity.hu).
2. **Értékelés** (≤ 24 óra): DPO / Ops Squad megállapítja a súlyosságot és az érintettek körét.
3. **NAIH bejelentés** (≤ 72 óra): ha az incidens valószínűsíthetően kockázattal jár az érintettek jogaira — GDPR 33. cikk.
4. **Érintettek értesítése**: ha az incidens valószínűsíthetően magas kockázattal jár — GDPR 34. cikk.
5. **Korrekciós intézkedések**: technikai és szervezési intézkedések az ismétlődés megelőzésére.
6. **Lezárás és dokumentálás**: incidens napló lezárása, tanulságok rögzítése.

### 7.3 Megőrzés

Az incidens naplóbejegyzéseket a Szolgáltató legalább 5 évig megőrzi.

---

## 8. Felülvizsgálat

- **Gyakoriság**: félévente, vagy új adatfeldolgozó bevonásakor
- **Felelős**: DPO / Ops Squad
- **Trigger**: új partner integráció, meglévő partner feltétel-változás, GDPR audit

---

## Változásnapló

| Verzió | Dátum | Változás |
|--------|-------|---------|
| 1.0 | 2026-02-25 | Kezdeti verzió — draft; 5 adatfeldolgozó nyilvántartása |
| 1.1 | 2026-02-25 | NAIH hatósági elérhetőség hozzáadva (§6); koherencia az ÁSZF §19.4(d)-vel |
| 1.2 | 2026-02-25 | Véglegesítés: Pseudo ID mint személyes adat beépítve (§1), cookie megfelelés rögzítve (§2.2), Vonage/Nexmo SMS hozzáadva (§2.6), affiliate alternatívák hozzáadva — CJ (§2.7), TradeTracker (§2.8), Tradedoubler (§2.9), offerwall szolgáltatók — Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research (Make Opinion) (§2.10), belső fájlhivatkozások eltávolítva, DPA státusz kiterjesztve 14 adatfeldolgozóra/adatkezelőre (§5), adatvédelmi incidens naplóvezetés hozzáadva (§7) |

---

## Kapcsolódó dokumentumok és guide-ok

- [Általános Szerződési Feltételek (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [Hozzáférés-kezelési mátrix](./access-control-matrix.md)
- [Stripe felelősségmegosztás](./stripe-responsibility-matrix.md)
- [SLA](./sla-policy.md)
- [Rólunk](https://app.sharity.hu/rolunk/)
- [NGO Card útmutató](https://app.sharity.hu/ngo-guides/ngo-card/)
