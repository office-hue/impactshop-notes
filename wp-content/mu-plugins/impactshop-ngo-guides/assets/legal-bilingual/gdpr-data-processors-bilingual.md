<!-- 🌐 Nyelv / Language: [Magyar](#hu) | [English](#en) -->

<div id="hu">

# 🇭🇺 MAGYAR VERZIÓ

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

</div>

---

<div id="en">

# 🇬🇧 ENGLISH VERSION

# GDPR Record of Data Processors

> **Version**: 1.2  
> **Date**: 2026-02-25  
> **Status**: IN FORCE  
> **Scope**: ImpactShop full platform (SHARITEAM.COM, app.sharity.hu)  
> **Data Controller**: Sharity Donation Organising Foundation + Sharity Mobile Application Zrt.

---

## 1. Overview

This document contains the record of **data processors** engaged by the ImpactShop / Sharity platform in accordance with Article 30(2) of the GDPR.

The **data controller** in all cases is Sharity (Foundation and/or Zrt.), which determines the purposes and means of the processing of personal data.

**Pseudo ID and personal data:** The Platform uses a Pseudo ID (an online identifier generated in the browser) without registration. Depending on the circumstances, the Pseudo ID may qualify as personal data (GDPR online identifier; linkability with IP address, transaction, Stripe email, etc.) — see Terms & Conditions §5.2, §16.3. The Pseudo ID is specifically named in the Privacy Notice: storage (cookie/localStorage), legal basis, retention period, and deletion/linking rules.

---

## 2. Record of Data Processors

### 2.1 Stripe Payments Europe, Limited

| Field | Value |
|-------|-------|
| **Entity** | Stripe Payments Europe, Limited |
| **Registered office** | 1 Grand Canal Street Lower, Dublin 2, Ireland |
| **Role** | Data Processor (payment service provider) |
| **Data processed** | Card data (never touches Sharity's servers), email address, transaction amount, IP address, device fingerprint |
| **Purpose of processing** | Processing payment transactions for Impact Amplifier vote purchases |
| **DPA (Data Processing Agreement)** | https://stripe.com/legal/dpa — automatically effective upon acceptance of the Stripe Services Agreement |
| **Transfer to third country** | Yes — Stripe Inc. (USA) — EU–US Data Privacy Framework certified |
| **Retention period** | Per Stripe's own policy: transaction data per anti-money laundering and accounting obligations (min. 5 years) |
| **Related certification** | PCI-DSS Level 1, SOC 2 Type II, ISO 27001 |

### 2.2 Dognet s.r.o.

| Field | Value |
|-------|-------|
| **Entity** | Dognet s.r.o. |
| **Registered office** | Bratislava, Slovakia (EU) |
| **Role** | Data Processor (affiliate network provider) |
| **Data processed** | Cookie identifiers, click tracking data, transaction confirmations (affiliate conversion) |
| **Purpose of processing** | Affiliate commission tracking in the ImpactShop → partner webshop purchase flow |
| **DPA** | Annex to the Dognet General Partner Agreement (GDPR clause) — individually concluded |
| **Transfer to third country** | No — processing within the EU |
| **Retention period** | Cookie lifetime: 30 days; transaction data: min. 5 years (accounting obligation) |

**Cookie compliance:** Dognet affiliate tracking is cookie/identifier-based. The cookie/storage notice and any consent management comply with applicable e-Privacy and local regulations and are consistent with the Pseudo ID description (see Terms & Conditions §5.2, §16.6 and Cookie Notice).

### 2.3 Cloudflare, Inc.

| Field | Value |
|-------|-------|
| **Entity** | Cloudflare, Inc. |
| **Registered office** | San Francisco, USA |
| **Role** | Data Processor (CDN, WAF, DNS, DDoS protection) |
| **Data processed** | IP address, HTTP request/response metadata, Turnstile token (CAPTCHA) |
| **Purpose of processing** | Website protection, rate limiting, bot filtering, SSL termination |
| **DPA** | https://www.cloudflare.com/cloudflare-customer-dpa/ — automatically effective for Enterprise and Free/Pro/Business tiers |
| **Transfer to third country** | Yes — USA + global edge network — EU–US DPF + Binding Corporate Rules |
| **Retention period** | Request logs: 72 hours (standard); Analytics: 30 days |
| **Related certification** | SOC 2 Type II, ISO 27001, C5 |

### 2.4 Ezit Kft. (hosting provider)

| Field | Value |
|-------|-------|
| **Entity** | Ezit Kft. |
| **Registered office** | Hungary |
| **Role** | Data Processor (hosting provider, server operation) |
| **Data processed** | Full WordPress database (user data, transactions), server logs (IP, User-Agent) |
| **Purpose of processing** | Hosting of the website and application |
| **DPA** | GDPR annex to the Ezit Kft. Terms of Service — individually concluded |
| **Transfer to third country** | No — Hungarian data centre |
| **Retention period** | Server logs: 30 days; backup: max 20 snapshots |

### 2.5 Google Cloud Platform (optional — AI Agent)

| Field | Value |
|-------|-------|
| **Entity** | Google Cloud EMEA Limited / Google LLC |
| **Registered office** | Dublin, Ireland / Mountain View, USA |
| **Role** | Data Processor (AI/ML services, Google Workspace API) |
| **Data processed** | Email/Drive data from Google Workspace (AI Agent function only), service account credentials |
| **Purpose of processing** | Automation of NGO data collection, Google Sheets synchronisation |
| **DPA** | https://cloud.google.com/terms/data-processing-addendum |
| **Transfer to third country** | Yes — EU–US DPF + Standard Contractual Clauses (SCC) |
| **Retention period** | Per Google policy; Sharity: credential files encrypted |

### 2.6 Vonage / Nexmo (SMS provider)

| Field | Value |
|-------|-------|
| **Entity** | Vonage Holdings Corp. / Vonage Business Limited |
| **Registered office** | Netherlands (Vonage V.B.) / United Kingdom (Vonage Business Limited) / USA (Vonage Holdings Corp.) |
| **Role** | Data Processor (SMS delivery) |
| **Data processed** | Phone number, SMS content (ticket/notification text) |
| **Purpose of processing** | SMS delivery of Digital Raffle tickets/notifications |
| **DPA** | Vonage DPA — annex to the API service agreement |
| **Transfer to third country** | Yes — UK/USA, intra-Ericsson group data transfer |
| **Retention period** | Per Vonage policy; SMS delivery logs: max 30 days |
| **Note** | For UK/EEA data subjects, group entities may also process data: Ericsson; Vonage Business Limited (UK); Vonage V.B. (NL) — see Terms & Conditions §16.5 |

### 2.7 CJ (Commission Junction) — affiliate network

| Field | Value |
|-------|-------|
| **Entity** | Commission Junction LLC (a member of the Publicis Groupe) |
| **Registered office** | Santa Barbara, CA, USA |
| **Role** | Independent Data Controller / Joint Data Controller (affiliate tracking) |
| **Data processed** | Cookie identifiers, click data, transaction confirmation (affiliate conversion), IP address |
| **Purpose of processing** | Affiliate commission tracking (publisher → advertiser transactions) |
| **DPA / Privacy notice** | Services Privacy Policy: https://www.cj.com/legal/privacy-policy-services |
| **Transfer to third country** | Yes — USA (Publicis Groupe); EU–US DPF |
| **Retention period** | Per CJ policy; cookie lifetime: campaign-dependent (typically 30–45 days) |
| **Note** | CJ may also act as an independent data controller with respect to the tracking cookie — the Terms & Conditions §16.6 inform accordingly |

### 2.8 TradeTracker — affiliate network

| Field | Value |
|-------|-------|
| **Entity** | TradeTracker International B.V. |
| **Registered office** | Almere, Netherlands (EU) |
| **Role** | Independent Data Controller / Joint Data Controller (affiliate tracking) |
| **Data processed** | Cookie identifiers, click data, transaction confirmation, IP address |
| **Purpose of processing** | Affiliate commission tracking |
| **DPA / Privacy notice** | Privacy Policy: https://tradetracker.com/privacy-policy/ |
| **Transfer to third country** | No — processing within the EU |
| **Retention period** | Per TradeTracker policy; cookie lifetime: campaign-dependent |

### 2.9 Tradedoubler — affiliate network

| Field | Value |
|-------|-------|
| **Entity** | Tradedoubler AB (reg.no 556575-7423) |
| **Registered office** | Stockholm, Sweden (EU) |
| **Role** | Data Processor (affiliate tracking — on behalf of the brand); in certain cases, Independent Data Controller |
| **Data processed** | Cookie identifiers, click data, IP address, transaction confirmation |
| **Purpose of processing** | Affiliate commission tracking (publisher network) |
| **DPA / Privacy notice** | Privacy Policy: https://www.tradedoubler.com/en/privacy-policy/ ; DPO: dpo@tradedoubler.com |
| **Transfer to third country** | Possible — UK (adequacy decision); otherwise SCC |
| **Retention period** | Cookie: max 730 days; transaction data: per local accounting laws (5–10 years) |

### 2.10 Offerwall providers (Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research)

On the Impact Challenge offerwall interface, third-party providers offer tasks (surveys, app trials, registrations) to Users. These providers typically act as **independent data controllers**.

#### Bitlabs

| Field | Value |
|-------|-------|
| **Entity** | BitBurst GmbH (Bitlabs) |
| **Registered office** | Germany (EU) |
| **Role** | Independent Data Controller (offerwall / survey provider) |
| **Data processed** | User identifier (publisher user ID), response data, IP address, device info |
| **Purpose of processing** | Provision of offerwall tasks, commission settlement |
| **DPA / Privacy notice** | Privacy Policy: https://bitlabs.ai/privacy |
| **Transfer to third country** | Not typical — processing within the EU |

#### Pollfish

| Field | Value |
|-------|-------|
| **Entity** | Pollfish LLC (a member of the Prodege group) |
| **Registered office** | USA |
| **Role** | Independent Data Controller (survey provider) |
| **Data processed** | Demographic data, response data, device info, IP address |
| **Purpose of processing** | Survey completion, market research, commission settlement |
| **DPA / Privacy notice** | Respondent Terms: https://www.pollfish.com/terms/respondent |
| **Transfer to third country** | Yes — USA |

#### Cint

| Field | Value |
|-------|-------|
| **Entity** | Cint AB |
| **Registered office** | Stockholm, Sweden (EU) |
| **Role** | Independent Data Controller (research marketplace / survey routing) |
| **Data processed** | Demographic data, response data, device info, IP address, cookie ID |
| **Purpose of processing** | Survey routing, provision of market research sample |
| **DPA / Privacy notice** | Privacy Notice: https://www.cint.com/privacy-notice ; DPO: privacy@cint.com |
| **Transfer to third country** | Data stored on EU servers; however, transfer within Cint group's global subsidiaries is possible |
| **Retention period** | Per Cint policy |

#### ayeT-Studios

| Field | Value |
|-------|-------|
| **Entity** | ayeT-Studios GmbH |
| **Registered office** | Große Düwelstraße 28, 30171 Hannover, Germany (EU) |
| **Company registration no.** | HRB 211094 (Amtsgericht Hannover); VAT: DE294415615 |
| **Role** | Independent Data Controller (offerwall / survey / rewarded ad provider) |
| **Data processed** | User identifier (publisher user ID), device ID (GAID/IDFA), IP address, response data, demographic data |
| **Purpose of processing** | Offerwall tasks (Gametastic), survey routing (Polltastic), commission settlement |
| **DPA / Privacy notice** | Privacy Policy: https://www.ayetstudios.com/privacy-policy ; DPO: privacy@ayetstudios.com (external: activelaw Offenhausen.Wolter, Hannover) |
| **Transfer to third country** | Yes — AWS US servers (based on EU SCC) |
| **Retention period** | Duration of use + 90 days; survey data: per MR partner policy |

#### CPX Research (Make Opinion)

| Field | Value |
|-------|-------|
| **Entity** | Make Opinion GmbH (d/b/a CPX Research) |
| **Registered office** | Elfenallee 5, 13127 Berlin, Germany (EU) |
| **Role** | Independent Data Controller (survey / offerwall provider) |
| **Data processed** | User identifier, demographic data (age, gender, postal code), response data, IP address, device fingerprint |
| **Purpose of processing** | Survey matching and completion, market research, commission settlement |
| **DPA / Privacy notice** | Privacy Policy: https://cpx-research.com/main/en/privacy-user/ ; DPO: privacy@makeopinion.com (Nana Gabelia) |
| **Transfer to third country** | Primarily Germany (EU); via survey partners possible: USA, Canada, France, Romania, Israel, Pakistan, India |
| **Retention period** | Per Make Opinion policy |

> **Important note regarding offerwall providers:** These providers may use cookies or similar technologies as **independent data controllers** pursuant to the Terms & Conditions §16.6. The User is informed via the Cookie Notice and each provider's own privacy notice. Sharity, as publisher, passes the user identifier (Pseudo ID / publisher user ID) to the offerwall API — see Terms & Conditions §5.2 Action/To-do.

---

## 3. Personal Data Flow Map

```
User (browser)
    │
    ├─── [HTTPS/TLS] ──→ Cloudflare (CDN, WAF, Turnstile)
    │                         │
    │                         └──→ Ezit server (WordPress)
    │                                  │
    │                                  ├── Affiliate click → Dognet / CJ / TradeTracker / Tradedoubler cookie
    │                                  │     └── Purchase confirmation → affiliate API
    │                                  │
    │                                  ├── Impact Challenge offerwall → Bitlabs / Pollfish / Cint / ayeT / CPX Research API
    │                                  │     └── Survey / task → Points credited
    │                                  │
    │                                  └── Vote purchase (checkout)
    │                                        │
    │                                        └──→ Stripe Checkout (hosted)
    │                                              ├── Card data → Stripe (never Sharity)
    │                                              └── Webhook → Ezit server → DB
    │
    ├─── [SMS] Digital Raffle ticket/notification → Vonage (Nexmo) API
    │
    └─── [Optional] AI Agent → Google Cloud API
```

---

## 4. Handling of Data Subject Rights

| Right | Implementation | Responsible |
|-------|---------------|-------------|
| **Right of Access** (Article 15) | WP Admin → user data export | Sharity DPO |
| **Right to Erasure** (Article 17) | Soft-delete (corporate data 30 days), anonymisation | Sharity Developer |
| **Data Portability** (Article 20) | JSON export from WP Admin | Sharity DPO |
| **Right to Object** (Article 21) | Email → privacy@sharity.hu | Sharity DPO |
| **Data stored at Stripe** | Sharity requests from Stripe, or data subject contacts Stripe directly | Sharity DPO + Stripe Support |

### 4.1 Erasure Procedure

1. **Data subject requests erasure** → office@sharity.hu
2. **Sharity verifies**: whether a legal retention obligation exists (accounting: 8 years, VAT: 5 years)
3. **If no obligation exists**: deletion/anonymisation of personal data ≤ 30 days
4. **If an obligation exists**: notification to the data subject, data locked (cannot be deleted but cannot be used)
5. **At Stripe**: Sharity cannot delete directly → data subject may proceed per Stripe's privacy policy
6. **Logging**: erasure request + execution date + reason → GDPR requests log

---

## 5. Status of Data Processing Agreements

| Processor | DPA status | Last review | Next review |
|-----------|-----------|-------------|-------------|
| **Stripe** | ✅ Automatic (upon acceptance of SSA) | 2026-02-25 | 2026-08-25 |
| **Dognet** | ⚠️ Review required — based on the Lead Media s.r.o. partner agreement GDPR clause; public privacy notice: https://www.dognet.sk/wp-content/uploads/2023/11/gdpr-sk.pdf | — | **2026-03-15** (urgent) |
| **Cloudflare** | ✅ Automatic (Customer DPA) | 2026-02-25 | 2026-08-25 |
| **Ezit** | ⚠️ Review required — Ezit Kft. is part of the Magyar Hosting (mhosting.hu) group; effective Terms of Service: https://www.mhosting.hu/aszf/ (2025.08.04.); Privacy Notice: https://www.mhosting.hu/adatkezelesi-tajekoztato/ (2025.07.17.); Data Transfer Declaration: https://www.mhosting.hu/adattovabbitasi-nyilatkozat/ | — | **2026-03-15** (urgent) |
| **Google Cloud** | ✅ Automatic (Cloud DPA) | 2026-02-25 | 2026-08-25 |
| **Vonage (Nexmo)** | ⚠️ To be verified — DPA annex to the API service agreement | — | **2026-03-15** |
| **CJ (Commission Junction)** | ⚠️ To be verified — independent data controller; Services Privacy Policy: https://www.cj.com/legal/privacy-policy-services | — | **2026-03-15** |
| **TradeTracker** | ⚠️ To be verified — independent data controller; Privacy Policy: https://tradetracker.com/privacy-policy/ | — | **2026-03-15** |
| **Tradedoubler** | ⚠️ To be verified — data processor/independent data controller; Privacy Policy + DPO: dpo@tradedoubler.com | — | **2026-03-15** |
| **Bitlabs** | ℹ️ Independent data controller — Privacy Policy: https://bitlabs.ai/privacy | — | **2026-06-25** |
| **Pollfish** | ℹ️ Independent data controller — Respondent Terms: https://www.pollfish.com/terms/respondent | — | **2026-06-25** |
| **Cint** | ℹ️ Independent data controller — Privacy Notice: https://www.cint.com/privacy-notice | — | **2026-06-25** |
| **ayeT-Studios** | ℹ️ Independent data controller — Privacy Policy: https://www.ayetstudios.com/privacy-policy ; DPO: privacy@ayetstudios.com | — | **2026-06-25** |
| **CPX Research (Make Opinion)** | ℹ️ Independent data controller — Privacy Policy: https://cpx-research.com/main/en/privacy-user/ ; DPO: privacy@makeopinion.com | — | **2026-06-25** |

The Dognet and Ezit DPAs must be formally reviewed and, where necessary, a supplementary data processing agreement must be concluded based on the references above.

---

## 6. Supervisory Authority Contact — in case of complaint

The data subject may also submit a complaint regarding personal data to the following authority:

| Authority | Contact details |
|-----------|----------------|
| **Hungarian National Authority for Data Protection and Freedom of Information (NAIH)** | 1055 Budapest, Falk Miksa utca 9–11. |
| Phone | +36 1 391-1400 |
| Email | ugyfelszolgalat@naih.hu |
| Website | https://www.naih.hu |
| Online submission | https://www.naih.hu/panaszbejelentes |

> This section is consistent with the Terms & Conditions §19.4(d), which also refers to NAIH.

---

## 7. Data Breach Incident Register

Pursuant to Article 33(5) of the GDPR, the data controller is obliged to maintain a record of data breaches.

### 7.1 Data to be recorded

The following must be recorded for every data breach incident:

| Field | Description |
|-------|-------------|
| **Incident ID** | Unique serial number (e.g. GDPR-INC-2026-001) |
| **Date/time of detection** | Timestamp when the incident was detected |
| **Incident description** | Nature of the incident, affected system(s), circumstances |
| **Categories of data affected** | What personal data is affected (Pseudo ID, email, transaction data, etc.) |
| **Estimated number of data subjects affected** | How many data subjects' personal data was compromised/affected |
| **Affected data processor(s)** | Stripe, Dognet, Cloudflare, Ezit, Google Cloud, Vonage, CJ, TradeTracker, Tradedoubler, Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research — whichever is involved in the incident |
| **Severity** | Low / Medium / High / Critical |
| **Measures taken** | Immediate and subsequent corrective actions |
| **NAIH notification** | Required (within 72 hours); if yes, date of notification |
| **Notification of data subjects** | Required; if yes, date and method of notification |
| **Closure date** | Closure of the incident handling |

### 7.2 Procedure

1. **Detection**: automatic monitoring or manual report (office@sharity.hu).
2. **Assessment** (≤ 24 hours): DPO / Ops Squad determines severity and the scope of data subjects affected.
3. **NAIH notification** (≤ 72 hours): if the incident is likely to result in a risk to the rights of data subjects — GDPR Article 33.
4. **Notification of data subjects**: if the incident is likely to result in a high risk — GDPR Article 34.
5. **Corrective measures**: technical and organisational measures to prevent recurrence.
6. **Closure and documentation**: incident log closure, lessons learned recorded.

### 7.3 Retention

The Service Provider shall retain incident log entries for a minimum of 5 years.

---

## 8. Review

- **Frequency**: semi-annually, or upon engagement of a new data processor
- **Responsible**: DPO / Ops Squad
- **Trigger**: new partner integration, change in existing partner's terms, GDPR audit

---

## Changelog

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2026-02-25 | Initial version — draft; 5 data processors recorded |
| 1.1 | 2026-02-25 | NAIH supervisory authority contact added (§6); consistency with Terms & Conditions §19.4(d) |
| 1.2 | 2026-02-25 | Finalisation: Pseudo ID as personal data incorporated (§1), cookie compliance recorded (§2.2), Vonage/Nexmo SMS added (§2.6), affiliate alternatives added — CJ (§2.7), TradeTracker (§2.8), Tradedoubler (§2.9), offerwall providers — Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research (Make Opinion) (§2.10), internal file references removed, DPA status extended to 14 data processors/controllers (§5), data breach incident register added (§7) |

---

## Related documents and guides

- [Terms & Conditions (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [Access Control Matrix](./access-control-matrix.md)
- [Stripe Responsibility Matrix](./stripe-responsibility-matrix.md)
- [SLA](./sla-policy.md)
- [About Us](https://app.sharity.hu/rolunk/)
- [NGO Card Guide](https://app.sharity.hu/ngo-guides/ngo-card/)

</div>
