![1771358405029](image/CPX-RESEARCH-OFFERWALL-PLAN/1771358405029.png)![1771400028941](image/CPX-RESEARCH-OFFERWALL-PLAN/1771400028941.png)# CPX Research – Implementációs és integrációs terv

> **Státusz:** FINAL v1.1
> **Létrehozva:** 2026-02-17
> **Előzmény:** `AYET-OFFERWALL-UX-REWARD-PLAN.md` (FINAL v1.3), éles `impactshop-offerwall.php`, `impactshop-ayet-offerwall.php`, `impactshop-offerwall.js`
> **Cél:** A CPX Research survey/offerwall integrációja a meglévő multi-provider offerwall keretrendszerbe, az AyeT mellé – második aktív providerként.

---

> **REVIEW SUMMARY (2026-02-17):**
> A terv alapos és technikailag megvalósítható a jelenlegi kódbázison.
> **Fő erősségek:**
> 1. Maximális újrahasznosítás (generikus postback, iframe URL builder).
> 2. Hibrid megközelítés (Script Tag + IFrame fallback) robusztus UX-et ad.
> 3. Részletes jutalom-kalibráció.
> **Kritikus pontok (javítandó/figyelendő):**
> 1. **Kerekítési differencia:** A generikus handler `round()`-ot használ, míg az AyeT `ceil()`-t. Alacsony értékű survey-knél ez pontvesztést okozhat. **Döntés:** a generikus handler `ceil()`-re áll.
> 2. **AdBlock kockázat:** A Script Tag sérülékenyebb mint az API-alapú renderelés. A `no_surveys_available` fallback kritikus fontosságú.
> 3. **Unique User ID:** A CPX `ext_user_id` limitációi (max hossz, karakterkészlet) ellenőrzendők.
> 
> **Signature erősség:**
> Bár az MD5 gyengébb hash, a CPX nem kínál HMAC-SHA256 alternatívát.
> **Mitigáció:**
> 1. A postback URL nem publikus (csak a CPX dashboardon ismert).
> 2. IP Whitelist (Critical P1): Mivel az MD5 brute-force támadhatóbb, az IP szűrés kötelező biztonsági réteg.
> 3. CSP: A Script Tag betöltésénél a CSP fejlécek szigorú ellenőrzése szükséges (`frame-src` és `connect-src` korlátozások).
>
> **REVIEW:**
> **Identity Spoofing Kockázat:**
> Mivel a `config` objektum kliens oldalon (JavaScript) elérhető, egy támadó megváltozhatja a saját `ext_user_id`-ját egy másik felhasználóéra (`window.config.general_config.ext_user_id = "victim123"`), majd kér egy survey-t.
> **Védelem (Critical):** A `secure_hash` paraméter (ami a szerveren generálódik a user ID és a titkos kulcs alapján) megvédi ezt. Ha a user ID-t megváltoztatják kliens oldalon, a hash érvénytelen lesz, és a CPX elutasítja a kérést (vagy a postback validáció bukik el).
> **Feltétel:** A CPX Dashboardon a **Secure Hash** beállítása KÖTELEZŐ!
>

> **Implementációs döntések (végleges):**
> - **UI:** Egy tab, két szekció (Saját kérdőívek + CPX survey) a `📊 Kérdőív` panelen.
> - **CPX Script Tag:** `script_tag_v2.0.js`, dark theme `style_config`.
> - **Secure hash:** CPX Script Tag esetén `md5(ext_user_id + secret)` szerveroldalon generálva.
> - **Postback kerekítés:** `ceil()` használata a generikus handlerben.
> - **CSP:** CPX domain-ek hozzáadva (`cdn.cpx-research.com`, `wall.cpx-research.com`, `offers.cpx-research.com`, `api.cpx-research.com`).

> **Implementációs státusz (v1.1):**
> - CPX survey panel beépítve a Kérdőív tabba.
> - CPX Script Tag init JS + fallback üzenet.
> - CSP bővítések.
> - Postback `ceil()` kerekítés.

## Állapotfelvétel (2026-02-17)

### Meglévő rendszer – mit használunk újra?

| Elem | Fájl | Állapot | Újrahasználható? |
|---|---|---|---|
| Multi-provider config (admin UI + DB) | `impactshop-offerwall.php` | ✅ Éles | **Igen** – CPX már definiálva `impactshop_offerwall_default_providers()` alatt |
| Provider postback handler (generikus) | `impactshop-offerwall.php` → `impactshop_offerwall_handle_postback()` | ✅ Éles | **Igen** – `provider=cpx` kulccsal hívható |
| Offerwall completions tábla | `wp_impactshop_offerwall_completions` | ✅ Éles | **Igen** – provider-agnosztikus séma |
| Pontrendszer (`Sharity_Points_Manager`) | Külső MU plugin | ✅ Éles | **Igen** |
| Szavazat rendszer (`impactshop_ads_watch_add_votes`) | Külső MU plugin | ✅ Éles | **Igen** |
| Toast rendszer (JS) | `impactshop-offerwall.js` | ✅ Éles | **Igen** |
| History polling + stats | `impactshop-offerwall.php/js` | ✅ Éles | **Igen** |
| IFrame URL builder (`impactshop_offerwall_build_iframe_url`) | `impactshop-offerwall.php` | ✅ Éles | **Igen** – CPX-specifikus `ext_user_id`/`subid_1` kezelés már benne van |
| CSP header bővítés | `impactshop-offerwall.php` | ✅ Éles | **Bővítendő** CPX domainekkel |
| Signature validáció | `impactshop-offerwall.php` → `impactshop_offerwall_signature_valid()` | ✅ Éles | **Bővítendő** CPX hash formátummal |
| Admin panel (provider toggles) | `impactshop-offerwall.php` → `impactshop_offerwall_admin_page()` | ✅ Éles | **Igen** |

### Mi hiányzik?

| Hiányzó elem | Prioritás | Leírás |
|---|---|---|
| CPX-specifikus callback handler | P0 | CPX saját postback formátum, eltérő paraméternevek |
| CPX embeddelhető survey fal (Script Tag / iFrame) | P0 | Natív JS widget VAGY iframe integrálás |
| CPX Reward formula kalibrálás | P0 | Illeszkedés a meglévő pont/szavazat arányokhoz |
| CPX postback signature formátum | P0 | Hash validálás a CPX-specifikus módon |
| CPX IP whitelist | P1 | Callback IP-k engedélyezése |
| CPX CSP domain hozzáadás | P1 | `cdn.cpx-research.com`, `offers.cpx-research.com`, `wall.cpx-research.com` |
| CPX admin beállítások | P1 | Dashboard konfig, Secure Hash, App ID |
| JS frontend CPX tab/panel | P2 | Ha nem iFrame, hanem natív kártya módot választunk |

---

## 0. CPX Research – Szolgáltatás áttekintés

### Mi a CPX Research?

A CPX Research egy **survey monetizációs platform**, amely publishereknek biztosít kérdőíveket (survey-ket), amelyeket a felhasználók kitölthetnek jutalom fejében. A CPX nem klasszikus offerwall (nem tartalmaz app install / CPI feladatokat), hanem **kizárólag survey-kra** specializálódott.

### CPX vs AyeT – összehasonlítás

| Szempont | AyeT Studios | CPX Research |
|---|---|---|
| **Típus** | Offerwall (CPI, CPA, CPE, survey) | Survey platform (kizárólag kérdőív) |
| **Integrálás** | Offerwall API (JSON) → natív kártyák | Script Tag (JS widget) VAGY Survey Wall URL (iframe) |
| **Jutalom trigger** | Server-to-server callback (postback) | Server-to-server callback (postback) |
| **User ID** | `external_identifier` | `ext_user_id` |
| **Signature** | HMAC-SHA256 (header: `x-ayetstudios-security-hash`) | MD5 hash query param (`hash` vagy `secure_hash`) |
| **Reversal** | `is_chargeback=1` vagy `reversal=1` | `status=2` (reversed) |
| **Jutalom adat** | `payout_usd` + `currency_amount` | `amount_local` (publisher currency) + `amount_usd` |
| **Survey típusok** | – | Profiling, Standard, Recontact |
| **CPE multi-step** | ✅ Igen | ❌ Nem (egy survey = egy tranzakció) |
| **Geolocation** | API `language=hu` param | Dashboard country targeting |
| **Magyar survey-k** | Nem releváns (offer-based) | Korlátozott (HU piac kicsi, angol survey-k is jönnek) |

---

## 1. Integrációs mód – Döntés

### 1.1 Két lehetőség

**A) Script Tag / JS Widget (ajánlott)**
- CPX biztosít egy JS könyvtárat (`script_tag_v2.0.js`)
- Megjelenik egy beágyazott survey lista a mi oldalunkon belül
- Designolható (színek, layout: fullscreen / sidebar / single / notification)
- A survey megnyitásakor CPX iFrame-ben jelenik meg
- **Előny:** Vizuálisan integrálható a mi dark theme-ünkbe, CPX kezeli a survey listát
- **Hátrány:** Korlátozott kontroll a kártyák felett, a survey lista a CPX widget-ben renderelődik

**B) Survey Wall URL (iFrame)**
- `https://wall.cpx-research.com/index.php?app_id={APP_ID}&ext_user_id={PSEUDO_ID}`
- Teljes survey fal egy iFrame-ben
- **Előny:** Egyszerű, gyors integráció
- **Hátrány:** Nem illeszkedik a mi kártya UI-unkhoz

### 1.2 Döntés: **Hibrid megoldás**

1. **Elsődleges:** Script Tag integráció a `Kérdőív` tab-ban (Design 1 – fullscreen widget), a mi dark theme színeinkkel konfigurálva
2. **Fallback:** iFrame URL a meglévő `impactshop_offerwall_build_iframe_url()` rendszeren keresztül (ha a Script Tag nem töltődik be, pl. AdBlock)
3. **Jutalom:** Server-to-server postback (mindkét esetben)

**Indoklás:** A Script Tag widget a mi oldalunkon belül renderelődik, így a user nem hagyja el a platformot. A survey lista CPX-oldalon kezelt, friss survey-ket mutat, nincs szükség API proxy-ra (nem is létezik publikus CPX survey listing API). A postback server-to-server megy, tehát a jutalom megbízhatóan érkezik.

---

## 2. Jutalmazási rendszer – CPX Reward kalibrálás

### 2.1 Alapelv

A CPX survey-k általában **2-15 perc** időigényűek, és **$0.20–$2.00** közötti payout-ot generálnak a publisher számára. Az AyeT-hez hasonlóan az idő-alapú normalizálás a cél.

### 2.2 CPX payout jellemzők

| Survey típus | Tipikus payout (USD) | Tipikus időigény | Példa |
|---|---|---|---|
| Profiling survey | $0.02–$0.10 | 30 sec – 2 perc | Demográfiai kérdések |
| Rövid survey | $0.10–$0.50 | 2–5 perc | 10-15 kérdéses kérdőív |
| Közepes survey | $0.50–$1.00 | 5–10 perc | 20-30 kérdéses kérdőív |
| Hosszú survey | $1.00–$2.00+ | 10–20 perc | 40+ kérdéses kutatás |

### 2.3 Javasolt CPX reward formula

A CPX postback-ben érkezik az `amount_local` (a publisher dashboard-on beállított valuta konverzió alapján) és az `amount_usd` (USD payout).

**A generikus postback handler (`impactshop_offerwall_handle_postback`) jelenlegi logikája:**
```php
$points_awarded = $payout > 0 ? max(1, (int) round($payout * 100 * $points_multiplier)) : 0;
$votes_awarded  = $payout > 0 ? max(1, (int) round($payout * 10  * $votes_multiplier))  : 0;
```

> **REVIEW:**
> **Valuta-koherencia:** A payout számításnál célszerű **mindig az `amount_usd` mezőt** használni, még akkor is, ha a CPX dashboard pénzneme HUF. Ha a CPX nem küldi az `amount_usd`-t, akkor szükség van egy stabil FX konverzióra (vagy a CPX dashboardon USD-re állításra). Ennek hiányában a pont/szavazat arány HUF beállításnál torzulhat.

**Probléma:** A jelenlegi generikus handler `payout × 100 × multiplier` formulát használ, ami eltér az AyeT-specifikus `payout × 50` (kalibrált) formulától. A CPX-hez igazított multiplier szükséges.

### 2.4 CPX multiplier értékek

Az admin panelen a CPX provider beállításainál:

| Beállítás | Érték | Eredmény |
|---|---|---|
| `points_multiplier` | **0.5** | `payout × 100 × 0.5 = payout × 50` → megegyezik az AyeT kalibrálással |
| `votes_multiplier` | **1.0** | `payout × 10 × 1.0 = payout × 10` → megegyezik az AyeT kalibrálással |

**Ezzel a CPX reward formula effektíve:**
```
pont     = round(payout_usd × 50)   → $0.50 → 25 pont
szavazat = round(payout_usd × 10)   → $0.50 → 5 szavazat
```

### 2.5 Összehasonlítás a belső rendszerrel és AyeT-tel

| Tevékenység | Becsült idő | Pont | Szavazat | Pont/perc |
|---|---|---|---|---|
| Reklámvideó (belső) | ~0.5 perc | 1 | 1 | 2 |
| Edukációs videó (belső) | ~2 perc | 20 | 20 | 10 |
| Belső survey | ~3 perc | 10 | 10 | 3.3 |
| AyeT könnyű ($0.30) | ~3 perc | 15 | 3 | 5 |
| **CPX profiling ($0.05)** | **~1 perc** | **3** | **1** | **3** |
| **CPX rövid ($0.30)** | **~4 perc** | **15** | **3** | **3.8** |
| **CPX közepes ($0.70)** | **~8 perc** | **35** | **7** | **4.4** |
| **CPX hosszú ($1.50)** | **~15 perc** | **75** | **15** | **5** |

**Értékelés:**
- A CPX pont/perc arány (3-5 pont/perc) illeszkedik a belső rendszer és az AyeT arányaihoz
- A szavazat/perc hasonlóan alacsony (~1 szav/perc), összhangban az AyeT szándékos döntéssel (donation pool védelem)
- A profiling survey-k alacsony jutalma elfogadható, mert a CPX ezeket „warm-up"-nak használja a jobb survey-khez

### 2.6 Minimum/maximum cap (CPX)

A generikus handler jelenleg `max(1, ...)` minimumot használ. A CPX-nél ez elegendő, mert:
- A napi cap a `wp_impactshop_offerwall_completions` táblán cross-provider aggregálódik a stats endpointban
- Per-tranzakció cap nincs szükség CPX-nél (survey payout ritkán haladja meg a $3-at)
- Ha mégis kell: az AyeT daily cap logikát bővíthetjük provider-agnosztikussá

---

## 3. CPX Postback (Callback) – Részletes specifikáció

### 3.1 CPX Postback URL formátum

A CPX publisher dashboardon beállítandó postback URL:

```
https://app.sharity.hu/wp-json/impact/v1/offerwall/callback/cpx
  ?transaction_id={trans_id}
  &user_id={ext_user_id}
  &amount_usd={amount_usd_raw}
  &amount_local={amount_local}
  &offer_id={survey_id}
  &offer_name=CPX+Survey
  &status={status}
  &hash={hash}
  &subid_1={subid_1}
  &subid_2={subid_2}
  &ip={user_ip}
```

### 3.2 CPX Postback paraméterek

| Paraméter | CPX Macro | Típus | Leírás |
|---|---|---|---|
| `transaction_id` | `{trans_id}` | string | Egyedi tranzakció azonosító |
| `user_id` / `ext_user_id` | `{ext_user_id}` | string | A mi `pseudo_id`-nk |
| `amount_usd` | `{amount_usd_raw}` | float | Publisher payout USD-ben |
| `amount_local` | `{amount_local}` | float | Publisher payout helyi valutában (dashboard beállítás) |
| `status` | `{status}` | int | `1` = approved, `2` = reversed/screenout |
| `hash` | `{hash}` | string | Biztonsági hash (MD5) |
| `offer_id` / `survey_id` | `{survey_id}` | string | Survey azonosító |
| `subid_1` | `{subid_1}` | string | Alnézetazonosító (= pseudo_id mirror) |
| `subid_2` | `{subid_2}` | string | Másodlagos tracking (opcionális) |
| `ip` | `{user_ip}` | string | User IP (CPX adja) |

### 3.3 CPX Signature (hash) validálás

A CPX MD5-alapú hash-t használ (nem HMAC-SHA256 mint az AyeT):

```
hash = md5(trans_id + secure_hash_key)
```

Ahol a `secure_hash_key` a CPX publisher dashboardon beállítandó kulcs.

**Ez már támogatott a meglévő `impactshop_offerwall_signature_valid()` függvényben:**
```php
// Már bent van a kódban:
$candidates = [
    hash_hmac('sha256', $transaction_id, $secret),
    md5($transaction_id . $secret),      // ← CPX formátum #1
    md5($secret . $transaction_id),      // ← CPX formátum #2
    md5($transaction_id . ':' . $secret),
    md5($secret . ':' . $transaction_id),
];
```

**Koherencia megállapítás:** ✅ A `md5($transaction_id . $secret)` sor **pont a CPX hash formátumát** fedi le. Nincs szükség kód módosításra a signature validáláshoz!

### 3.4 CPX Status kezelés

| CPX `status` | Jelentés | Kezelés |
|---|---|---|
| `1` | Approved (elfogadott) | Pont + szavazat jóváírás |
| `2` | Reversed / Screenout | `impactshop_offerwall_handle_postback()` reversal ág (`$is_reversal = true`) |

**Koherencia megállapítás:** ✅ A generikus handler a `status=2` értéket a `'2'` stringként kezeli, és a `$is_reversal` array-ban benne van:
```php
$is_reversal = in_array($raw_status, ['2', 'reversed', 'reversal', 'canceled', 'cancelled', 'rejected'], true);
```
Ez **pont illeszkedik** a CPX formátumhoz.

### 3.5 Paraméter mapping (CPX → generikus handler)

A generikus handler az alábbi paraméterneveket keresi:

| Handler paraméter | CPX-ben megvan? | CPX paraméternév | Megjegyzés |
|---|---|---|---|
| `transaction_id \| tx_id \| transaction \| trans_id` | ✅ | `transaction_id` | Közvetlen match |
| `pseudo_id \| sub_id \| user_id \| ext_user_id \| subid_1 \| subid1` | ✅ | `user_id` vagy `ext_user_id` vagy `subid_1` | Több fallback → biztos match |
| `payout \| amount \| amount_usd` | ✅ | `amount_usd` | Közvetlen match |
| `offer_id \| offerid` | ✅ | `offer_id` (survey_id) | Közvetlen match |
| `offer_name \| offer` | ✅ | `offer_name` (hardcoded: "CPX Survey") | Statikus |
| `status \| event` | ✅ | `status` | Közvetlen match |
| `signature \| hash` | ✅ | `hash` | A `signature_param` = `'hash'` a CPX config-ban |

**Koherencia megállapítás:** ✅ A CPX paraméternevek **teljes mértékben illeszkednek** a generikus handler lookup logikájához. Nincs szükség új paraméter mapping-re.

---

## 4. CPX Dashboard beállítások

### 4.1 Kötelező beállítások a CPX Publisher Dashboardon

| Beállítás | Érték | Megjegyzés |
|---|---|---|
| App ID | `{CPX_APP_ID}` | A CPX által generált alkalmazás azonosító |
| Postback URL | Lásd §3.1 | Teljes URL a makrókkal |
| Secure Hash | ✅ Bekapcsolva | A hash kulcs beállítandó |
| Currency | HUF vagy USD | Ha HUF: `amount_local` HUF-ban jön. Ha USD: USD-ben |
| User Identification | `ext_user_id` | A mi `pseudo_id`-nk |
| Country Targeting | HU (+ opcionálisan globális) | Magyar + nemzetközi survey-k is jöhetnek |

### 4.2 ImpactShop Admin Panel beállítások

Az `Offerwall beállítások` (Settings → Offerwall) oldalon a CPX provider sor:

| Mező | Érték | Megjegyzés |
|---|---|---|
| **Aktív** | ✅ | Bekapcsolva |
| **IFrame URL** | `https://wall.cpx-research.com/index.php` | Survey wall base URL |
| **User param** | `ext_user_id` | Már be van állítva a default config-ban |
| **IFrame hash secret** | `{CPX_SECURE_HASH}` | A CPX dashboardról |
| **Hash param** | `secure_hash` | CPX Script Tag/iframe |
| **Hash format** | `{user}{secret}` | `md5(ext_user_id + secret)` |
| **API kulcs** | `{CPX_APP_ID}` | A Script Tag-hoz kell |
| **Survey token secret** | `{CPX_SECURE_HASH}` | Script Tag `secure_hash` generáláshoz |
| **Postback secret** | `{CPX_SECURE_HASH}` | Postback hash validáláshoz |
| **IP allowlist** | Lásd §8 | CPX callback IP-k |
| **Pont szorzó** | `0.5` | `payout × 100 × 0.5 = payout × 50` |
| **Szavazat szorzó** | `1.0` | `payout × 10 × 1.0 = payout × 10` |

> **REVIEW:**
> **IFrame URL és hash koherencia:** A CPX dokumentáció jellemzően a `wall.cpx-research.com/index.php` URL-t használja `app_id` + `ext_user_id` paraméterekkel. A `offers.cpx-research.com/index.php` gyakran redirectel. Javasolt ellenőrizni, hogy az `impactshop_offerwall_build_iframe_url()` melyik bázist várja, és ehhez igazítani az admin beállítást.
> **Hash format:** A táblában szereplő `{user}-{secret}` formátum nem biztos, hogy CPX-hez illik. A CPX Script Tag esetén a `secure_hash` **MD5(ext_user_id + secret)**, míg a postback hash **MD5(trans_id + secret)**. Érdemes külön rögzíteni, hogy **iframe hash** és **postback hash** nem ugyanaz a logika.

---

## 5. Frontend integráció – CPX Script Tag

> **REVIEW (KRITIKUS – UI/UX döntés szükséges):**
>
> **Probléma:** A jelenlegi `📊 Kérdőív` tab a **saját belső kérdőívünket** (`[impactshop_internal_survey]` shortcode) tartalmazza. A terv jelenlegi formájában a CPX widget **lecserélné** ezt, ami azt jelenti, hogy a belső kérdőívünk eltűnik.
>
> **Megoldási opciók (UI):**
>
> **A) Egy tab, két szekció (ajánlott):**
> A `📊 Kérdőív` tab-on belül két vizuálisan elkülönített blokk:
> ```
> ┌─────────────────────────────────────────────┐
> │  📊 Kérdőív tab                             │
> │  ┌───────────────────────────────────────┐   │
> │  │ 🏠 Saját kérdőíveink                  │   │
> │  │ [impactshop_internal_survey output]   │   │
> │  └───────────────────────────────────────┘   │
> │  ┌───────────────────────────────────────┐   │
> │  │ 🌐 Külső kérdőívek (CPX Research)     │   │
> │  │ [CPX Script Tag widget]               │   │
> │  │ „Töltsd ki és gyűjts extra pontokat!" │   │
> │  └───────────────────────────────────────┘   │
> │                                             │
> └─────────────────────────────────────────────┘
> ```
> **Előny:** Egy helyen van minden kérdőív, a user nem keres. A belső kérdőív elsőbbséget kap (fent van). A CPX „extra" lehetőségként jelenik meg alatta.
> **Hátrány:** Ha sok belső kérdőív + sok CPX survey → hosszú scroll.
>
> **B) Külön tab:**
> Új tab: `🌐 Külső kérdőív` (vagy `📊 CPX Kérdőív`).
> ```
> ┌──────────┬──────────┬──────────────┬──────────┬────────┐
> │ 🎁 Offer │ 📋 Kvíz  │ 📊 Kérdőív   │ 🌐 Külső │ ✅ Akt │
> │  (AyeT)  │  (belső) │   (belső)    │  (CPX)   │        │
> └──────────┴──────────┴──────────────┴──────────┴────────┘
> ```
> **Előny:** Tiszta szeparáció.
> **Hátrány:** 5 tab már sok (mobil scroll), és a user nem érti miért van kétféle kérdőív.
>
> **C) Provider-aware toggle a tab-on belül:**
> A `📊 Kérdőív` tab-on belül egy mini pill-toggle: `Saját | CPX`.
> **Előny:** Kompakt, egy tab marad.
> **Hátrány:** Extra JS logika, a CPX surveys-t a user nem látja amíg nem kattint.
>
> **Javaslat: Opció A** – a `📊 Kérdőív` tab-on belül két szekció, `<section>` elemekkel és vizuális elválasztóval. A belső kérdőív felül, a CPX alatta. Ha a belső kérdőív nincs (shortcode nem létezik), csak a CPX jelenik meg. Ha a CPX sem aktív, a jelenlegi „nem elérhető" üzenet marad.
>
> **Implementáció vázlat (§6.1.1 módosítás):**
> ```php
> // Belső survey (ha van)
> $internal_survey = shortcode_exists('impactshop_internal_survey')
>     ? '<div class="offerwall-survey-section">'
>       . '<h3 class="offerwall-section-title">🏠 Saját kérdőíveink</h3>'
>       . do_shortcode('[impactshop_internal_survey]')
>       . '</div>'
>     : '';
>
> // CPX survey (ha provider aktív)
> $cpx_survey = '';
> if ($cpx_active) {
>     $cpx_survey = '<div class="offerwall-survey-section offerwall-survey-cpx">'
>         . '<h3 class="offerwall-section-title">🌐 Külső kérdőívek – extra pontokért</h3>'
>         . '<div class="offerwall-note">Töltsd ki és gyűjts extra pontokat!</div>'
>         . '<div id="cpx-survey-container" style="min-height:300px"></div>'
>         . '</div>';
> }
>
> // Összefűzés
> $survey_html = $internal_survey . $cpx_survey;
> if ($survey_html === '') {
>     $survey_html = '<div class="offerwall-empty">A kérdőív modul jelenleg nem elérhető.</div>';
> }
> ```

### 5.1 Script Tag embed

A CPX Script Tag-ot a `Kérdőív` tab-ban helyezzük el. Az `impactshop_offerwall_shortcode()` funkció már létrehozza a `<div class="offerwall-panel" data-panel="survey">` panelt.

**Jelenlegi állapot:**
```php
$survey_html = shortcode_exists('impactshop_internal_survey')
    ? do_shortcode('[impactshop_internal_survey]')
    : '<div class="offerwall-empty">A kérdőív modul jelenleg nem elérhető.</div>';
```

**Módosítás:** Ha a CPX provider aktív, a survey panel-be rendereljük a CPX Script Tag widget-et.

### 5.2 Script Tag konfiguráció (dark theme)

```javascript
const cpxScript = {
    div_id: "cpx-survey-container",
    theme_style: 1,         // fullscreen widget
    order_by: 2,            // best money first
    limit_surveys: 10
};

const cpxConfig = {
    general_config: {
        app_id: CPX_APP_ID,           // number – admin beállításból
        ext_user_id: PSEUDO_ID,       // string – cookie-ból
        secure_hash: CPX_SECURE_HASH, // string – md5(ext_user_id + secret)
        subid_1: PSEUDO_ID,           // string – tracking backup
        subid_2: "",
    },
    style_config: {
        text_color: "#f8fafc",              // --ow-text
        survey_box: {
            topbar_background_color: "#2563eb",  // --ow-primary
            box_background_color: "#111827",       // --ow-card-bg
            rounded_borders: true,
            stars_filled: "#f59e0b",               // --ow-warning (rating stars)
        },
    },
    script_config: [cpxScript],
    debug: false,
    useIFrame: true,
    iFramePosition: 1,
    functions: {
        no_surveys_available: function() {
            // Fallback üzenet mutatása
            var container = document.getElementById('cpx-survey-container');
            if (container) {
                container.innerHTML = '<div class="offerwall-empty">Jelenleg nincs elérhető kérdőív. Nézz vissza később!</div>';
            }
        },
        count_new_surveys: function(count) {
            // Opcionális: badge frissítés a Kérdőív tab-on
            var surveyTab = document.querySelector('[data-target="survey"]');
            if (surveyTab && count > 0) {
                surveyTab.textContent = '📊 Kérdőív (' + count + ')';
            }
        },
        get_transaction: function(transactions) {
            // Ha a CPX JS SDK tranzakció adatot küld (opcionális, a postback az elsődleges)
            if (transactions && transactions.length) {
                // Toast mutatása a legutolsó tranzakcióra
                var last = transactions[transactions.length - 1];
                window.dispatchEvent(new CustomEvent('cpx-transaction', { detail: last }));
            }
        }
    }
};

window.config = cpxConfig;
```

> **REVIEW:**
> **CSP + inline script:** A fenti inline JS miatt a CSP-ben vagy `script-src 'unsafe-inline'` szükséges, vagy nonce-alapú inline script engedélyezés. Javasolt a `wp_add_inline_script()` + nonce kombináció, hogy ne lazítsuk a CSP-t. Ha nonce-t használunk, a `impactshop_offerwall_extend_csp()`-t is bővíteni kell.
> **Globális `window.config` ütközés:** Ha más CPX widget is fut az oldalon, a `window.config` globális objektum felülíródhat. Javasolt biztosítani, hogy egy oldalon csak egy CPX widget töltődjön be (vagy ellenőrzött init).

### 5.3 Dark theme CSS override

A CPX widget CSS-ét a mi inline CSS-ünkben felüldefiniáljuk:

```css
/* CPX widget dark theme override */
#cpx-survey-container {
    min-height: 300px;
    background: #0f172a;
    border-radius: 16px;
    padding: 8px;
}
#cpx-survey-container * {
    font-family: inherit !important;
}
/* CPX widget card-ok */
.cpx-survey-card, .cpx_card {
    background: #111827 !important;
    color: #f8fafc !important;
    border-radius: 12px !important;
    border: 1px solid rgba(148,163,184,.2) !important;
}
.cpx-survey-card:hover, .cpx_card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,.3);
}
```

> **Megjegyzés:** A CPX widget belső CSS-je változhat verziónként. A felüldefiniálás `!important`-tal történik, ami törékennyé teszi. Alternatíva: a CPX `style_config` használata (lásd §5.2) a legtöbb vizuális elemhez elegendő.

### 5.4 Survey tab jelenlegi vs javasolt állapot

**Jelenlegi shortcode HTML:**
```html
<div class="offerwall-panel" data-panel="survey">
  <!-- belső survey shortcode VAGY "nem elérhető" -->
</div>
```

**Javasolt (CPX aktív esetén):**
```html
<div class="offerwall-panel" data-panel="survey">
  <div class="offerwall-note">
    📊 Töltsd ki az alábbi kérdőíveket és gyűjts pontokat! 
    A jutalom a kitöltés után automatikusan jóváírásra kerül.
  </div>
  <div id="cpx-survey-container"></div>
  <script src="https://cdn.cpx-research.com/assets/js/script_tag_v2.0.js"></script>
</div>
```

> **REVIEW:**
> A fenti HTML **lecseréli** a belső kérdőívet. A §5 elején javasolt Opció A szerint a helyes HTML:
> ```html
> <div class="offerwall-panel" data-panel="survey">
>   <!-- 1. Saját kérdőívek (ha van shortcode) -->
>   <div class="offerwall-survey-section">
>     <h3 class="offerwall-section-title">🏠 Saját kérdőíveink</h3>
>     <!-- [impactshop_internal_survey] output ide -->
>   </div>
>   <!-- 2. CPX külső kérdőívek (ha provider aktív) -->
>   <div class="offerwall-survey-section offerwall-survey-cpx">
>     <h3 class="offerwall-section-title">🌐 Külső kérdőívek – extra pontokért</h3>
>     <div class="offerwall-note">Töltsd ki és gyűjts extra pontokat!</div>
>     <div id="cpx-survey-container"></div>
>     <script src="https://cdn.cpx-research.com/assets/js/script_tag_v2.0.js"></script>
>   </div>
> </div>
> ```
> A `offerwall-survey-section` CSS class-hoz szükséges stílus: háttér, border-radius, padding, és vizuális elválasztó a két szekció között.

---

## 6. Backend implementáció – Szükséges módosítások

### 6.1 `impactshop-offerwall.php` módosítások

#### 6.1.1 CPX Script Tag rendering (shortcode bővítés)

A `impactshop_offerwall_shortcode()` funkciót bővítjük: ha a CPX provider aktív, a survey panel-be a CPX widget HTML-t rendereljük a belső survey helyett (vagy mellé).

> **REVIEW:**
> Az alábbi kód **lecseréli** a `$survey_html`-t → a belső kérdőív eltűnik. A §5 elején javasolt Opció A megoldást kell alkalmazni: a belső survey HTML-t megtartjuk, és a CPX widget-et **hozzáfűzzük** alá. Lásd a §5 elején lévő implementáció vázlatot.

```php
// A shortcode-ban:
$cpx_provider = $providers['cpx'] ?? [];
$cpx_active = !empty($cpx_provider['enabled']);

if ($cpx_active) {
    $cpx_app_id = (string) ($cpx_provider['api_key'] ?? '');
    $pseudo_id = impactshop_offerwall_get_pseudo_id();
    $cpx_hash_secret = (string) ($cpx_provider['iframe_hash_secret'] ?? '');
    $cpx_secure_hash = $cpx_hash_secret !== '' ? md5($pseudo_id . $cpx_hash_secret) : '';
    
    $survey_html = '<div class="offerwall-note">'
        . '📊 Töltsd ki az alábbi kérdőíveket és gyűjts pontokat! '
        . 'A jutalom a kitöltés után automatikusan jóváírásra kerül.</div>'
        . '<div id="cpx-survey-container" style="min-height:300px"></div>';
    
    // A CPX Script Tag config inline JS-ként (wp_add_inline_script alternatíva MU plugin-ban)
    $cpx_inline_js = sprintf(
        'var cpxScript={div_id:"cpx-survey-container",theme_style:1,order_by:2,limit_surveys:10};'
        . 'var cpxConfig={general_config:{app_id:%d,ext_user_id:"%s",secure_hash:"%s",subid_1:"%s",subid_2:""},'
        . 'style_config:{text_color:"#f8fafc",survey_box:{topbar_background_color:"#2563eb",'
        . 'box_background_color:"#111827",rounded_borders:true,stars_filled:"#f59e0b"}},'
        . 'script_config:[cpxScript],debug:false,useIFrame:true,iFramePosition:1,'
        . 'functions:{no_surveys_available:function(){var c=document.getElementById("cpx-survey-container");'
        . 'if(c)c.innerHTML=\'<div class="offerwall-empty">Jelenleg nincs elérhető kérdőív. Nézz vissza később!</div>\';},'
        . 'count_new_surveys:function(n){var t=document.querySelector("[data-target=survey]");'
        . 'if(t&&n>0)t.textContent="📊 Kérdőív ("+n+")";}}};'
        . 'window.config=cpxConfig;',
        (int) $cpx_app_id,
        esc_js($pseudo_id),
        esc_js($cpx_secure_hash),
        esc_js($pseudo_id)
    );
    
    $survey_html .= '<script>' . $cpx_inline_js . '</script>';
    $survey_html .= '<script src="https://cdn.cpx-research.com/assets/js/script_tag_v2.0.js"></script>';
}
```

#### 6.1.2 CSP header bővítés

A `impactshop_offerwall_extend_csp()` funkció bővítése CPX domainekkel:

```php
// Meglévő AyeT domain-ek mellé:
$csp = impactshop_offerwall_csp_append($csp, 'connect-src', 'https://cdn.cpx-research.com');
$csp = impactshop_offerwall_csp_append($csp, 'connect-src', 'https://offers.cpx-research.com');
$csp = impactshop_offerwall_csp_append($csp, 'connect-src', 'https://wall.cpx-research.com');
$csp = impactshop_offerwall_csp_append($csp, 'script-src', 'https://cdn.cpx-research.com');
$csp = impactshop_offerwall_csp_append($csp, 'frame-src', 'https://offers.cpx-research.com');
$csp = impactshop_offerwall_csp_append($csp, 'frame-src', 'https://wall.cpx-research.com');
$csp = impactshop_offerwall_csp_append($csp, 'img-src', 'https://cdn.cpx-research.com');
$csp = impactshop_offerwall_csp_append($csp, 'img-src', 'https://offers.cpx-research.com');
```

#### 6.1.3 CPX-specifikus postback bővítés (opcionális)

A generikus handler **már képes** a CPX postback-et kezelni a meglévő paraméter mapping-gel. Ha mégis finomhangolás kell:

```php
// impactshop_offerwall_handle_postback() bővítés:
// CPX-specifikus: az amount_usd paraméternév is elfogadott mint payout
// Ez már bent van: $payout = (float) ($params['payout'] ?? $params['amount'] ?? $params['amount_usd'] ?? 0);
// ✅ Nem kell módosítás
```

### 6.2 Új fájl NEM szükséges

Az AyeT-tel ellentétben (ahol a `impactshop-ayet-offerwall.php` egy 956 soros dedikált fájl), a CPX-hez **nem kell új PHP fájl**, mert:
1. Nincs CPX-specifikus API proxy (a Script Tag kliens-oldalon kezeli a survey listát)
2. A postback a generikus handleren megy keresztül
3. A reward kiszámítás a generikus handler + admin multiplier-eken alapul
4. A CPX Script Tag konfigurálása a shortcode-ban történik

---

## 7. Frontend (JS) módosítások

### 7.1 `impactshop-offerwall.js` – CPX transaction event kezelés

A CPX Script Tag képes tranzakció eseményt küldeni a `get_transaction` callback-en keresztül. Ezt a mi toast rendszerünkkel összekötjük:

```javascript
// Az initOfferwall() funkción belül:
window.addEventListener('cpx-transaction', function(e) {
    var tx = e.detail;
    if (!tx) return;
    var points = tx.amount_local || tx.amount_usd || 0;
    showToast('🎉 +' + Math.round(points * 50) + ' pont – Kérdőív teljesítve!');
    // Stats panel frissítés
    var statsPanel = root.querySelector('.offerwall-stats');
    if (statsPanel) fetchStats(statsPanel);
    // History frissítés
    fetchHistory();
});
```

### 7.2 Tab badge frissítés

A CPX `count_new_surveys` callback frissíti a Kérdőív tab szövegét az elérhető survey-k számával. Ez a §5.2 config-ban már definiálva van.

### 7.3 Nincs offers-mód bővítés

A CPX-t **nem** kell az `fetchOffers()` / `renderOfferCards()` rendszerbe integrálni, mert:
- A CPX Script Tag önálló widget, saját survey listával
- Az offer kártyás megjelenítés AyeT-specifikus (API JSON → natív card)
- A CPX iFrame/widget a „Kérdőív" tab-ban él, nem az „Offerwall" tab-ban

---

## 8. Biztonsági vizsgálat

### 8.1 Postback signature

| Szempont | Eredmény | Megjegyzés |
|---|---|---|
| Hash validálás | ✅ PASS | `md5($transaction_id . $secret)` már implementálva |
| Brute force védelem | ⚠️ MEDIUM | MD5 gyengébb mint HMAC-SHA256. De a secret nem publikus és a postback URL nem ismert. |
| Replay attack | ✅ PASS | `INSERT IGNORE` a `UNIQUE KEY uniq_provider_tx` indexen → idempotens |

**Javaslat:** Ha a CPX támogat HMAC-SHA256-ot (ellenőrizendő a dashboardon), váltsunk arra. Ha nem, az MD5 + secret kombináció elfogadható, mert:
- A postback URL nem publikus
- IP whitelist másodlagos védelmet ad
- Rate limiting aktív

### 8.2 IP Whitelist

A CPX callback IP-ket a CPX support-tól kell kérni. Amíg nincs végleges lista:

```php
// Ideiglenes: üres lista = nincs IP szűrés (HMAC véd)
'allow_ips' => [],
```

**FONTOS:** Az élesítés előtt a CPX support-tól kérjük a callback IP listát, és állítsuk be az admin panelen.

### 8.3 User identity spoofing

| Szempont | Eredmény | Megjegyzés |
|---|---|---|
| `ext_user_id` manipuláció | ✅ PASS | A `ext_user_id` a postback-ben jön (server-to-server), nem a user manipulálja |
| `secure_hash` (embed) | ✅ PASS | A `secure_hash = md5(pseudo_id + secret)` a frontendben van, de a secret maga szerveren generálódik, csak a hash kerül a kliensre |
| Cookie-based pseudo_id | ⚠️ ACCEPTED RISK | Ugyanaz mint AyeT-nél – auth nélkül ez a legjobb megoldás |

### 8.4 XSS / injection

| Szempont | Eredmény | Megjegyzés |
|---|---|---|
| CPX Script Tag injection | ⚠️ MEDIUM | A CPX JS fájl (`script_tag_v2.0.js`) harmadik féltől töltődik. Ha kompromittálódik → XSS. Mitigáció: SRI hash (ha CPX biztosít) vagy CSP strict-dynamic. |
| Postback paraméterek | ✅ PASS | `sanitize_text_field()` minden bemeneten |
| SQL injection | ✅ PASS | `$wpdb->prepare()` minden query-ben |

**Javaslat:** Adjunk SRI (Subresource Integrity) hash-t a CPX script tag-hoz, ha a CPX biztosítja a hash-t:
```html
<script src="https://cdn.cpx-research.com/assets/js/script_tag_v2.0.js"
        integrity="sha384-{hash}" crossorigin="anonymous"></script>
```

### 8.5 Rate limiting

| Szempont | Eredmény | Megjegyzés |
|---|---|---|
| Postback rate limit (user) | ✅ PASS | 50 req/hour/pseudo_id (generikus handler) |
| Postback rate limit (IP) | ✅ PASS | 200 req/hour/IP (generikus handler) |
| Frontend rate limit | ✅ PASS | CPX Script Tag kezeli saját maga |

### 8.6 Data privacy (GDPR)

| Szempont | Eredmény | Megjegyzés |
|---|---|---|
| Pseudo_id (nem PII) | ✅ PASS | Nincs email, név, vagy valódi user ID a CPX felé |
| CPX 3rd party script | ⚠️ CONSENT NEEDED | A cookie banner / consent manager-nek tartalmaznia kell a CPX Research-t mint 3rd party |
| Survey válaszok | ✅ PASS | A survey válaszokat a CPX tárolja, mi csak a jutalmat kapjuk |

---

## 9. Koherencia vizsgálat

### 9.1 Cross-provider konzisztencia

| Szempont | AyeT | CPX | Konzisztens? |
|---|---|---|---|
| Pont formula | `ceil(payout × 50)` | `round(payout × 100 × 0.5) = round(payout × 50` | ✅ Igen (enyhe eltérés: `ceil` vs `round`, max 1 pont) |
| Szavazat formula | `ceil(payout × 10)` | `round(payout × 10 × 1.0) = round(payout × 10)` | ✅ Igen (enyhe eltérés: `ceil` vs `round`) |
| Minimum pont | 5 (AyeT konstans) | 1 (generikus handler `max(1,...)`) | ⚠️ Eltérő |
| Minimum szavazat | 1 (AyeT konstans) | 1 (generikus handler `max(1,...)`) | ✅ Igen |
| Max pont/tx | 2000 (AyeT konstans) | Nincs explicit cap | ⚠️ Eltérő (CPX survey-nél nem jellemző a magas payout) |
| Napi cap | 2000pt / 200szav / 50tx (AyeT) | Nincs provider-specifikus napi cap | ⚠️ Eltérő |
| Reversal kezelés | Pont/szavazat visszavonás + üzenet | Generikus reversal (status='reversed') | ✅ Funkcionálisan megegyezik |
| Toast formátum | `🎉 +{pont} pont +{szav} szavazat – {offer_name}` | `🎉 +{pont} pont – Kérdőív teljesítve!` | ⚠️ Enyhe eltérés (a szavazat nem jelenik meg a toast-ban) |

### 9.2 Javaslatok a konzisztencia javítására

1. **Minimum pont eltérés:** A generikus handler `max(1, ...)` elfogadható a CPX-nél (profiling survey-k alacsony payout-ja miatt nem szabad 5 pontra emelni → aránytalanság)
2. **Napi cap:** A stats endpoint (`/offerwall/stats`) cross-provider aggregálódik → az AyeT napi cap effektíve a CPX-re is vonatkozik (az összesített stats-ban). Dedikált CPX cap nem szükséges.
3. **Toast szavazat:** A CPX toast-ot igazítsuk az AyeT formátumhoz (pont + szavazat megjelenítés)
4. **`ceil` vs `round`:** Elfogadható 1 pontos eltérés. Ha kritikus → a generikus handler-t is `ceil`-re kell váltani, de ez breaking change minden provider-re.

### 9.3 DB séma kompatibilitás

A `wp_impactshop_offerwall_completions` tábla provider-agnosztikus:

```sql
UNIQUE KEY uniq_provider_tx (provider, transaction_id)
```

A CPX completion-ök `provider='cpx'` értékkel kerülnek be. A stats endpoint a `provider` oszlop alapján tud szűrni. ✅ Nincs séma módosítás szükséges.

### 9.4 Frontend routing konzisztencia

| Endpoint | AyeT | CPX | Megjegyzés |
|---|---|---|---|
| Offer lista | `/offerwall/offers/ayet` | N/A (Script Tag kezeli) | CPX-nek nincs offers endpoint |
| Callback | `/ayet-callback` (dedikált) | `/offerwall/callback/cpx` (generikus) | Két különböző route |
| Reward Status | `/offerwall/reward-status` (AyeT API proxy) | N/A | CPX-nek nincs reward status API |
| History | `/offerwall/history` | `/offerwall/history` | Közös – CPX completion-ök is megjelennek |
| Stats | `/offerwall/stats` | `/offerwall/stats` | Közös – CPX is aggregálódik |
| Health | `/offerwall/health` | `/offerwall/health` | Közös – CPX provider is látszik |
| Config | `/offerwall/config` | `/offerwall/config` | CPX provider info is benne |

---

## 10. Implementációs checklist

### Fázis 1 – CPX Dashboard konfiguráció (P0, ~30 perc)

- [ ] CPX Publisher fiók: App ID és Secure Hash kulcs beszerzése
- [ ] CPX Dashboard: Postback URL beállítása (§3.1 formátum)
- [ ] CPX Dashboard: `ext_user_id` mint user identification bekapcsolása
- [ ] CPX Dashboard: Secure Hash bekapcsolása
- [ ] CPX Dashboard: Country targeting = HU (+ opcionálisan globális)
- [ ] CPX Dashboard: Currency = USD (konzisztens payout)
- [ ] CPX Support: Callback IP lista kérése

### Fázis 2 – Backend módosítások (P0, ~2 óra)

- [ ] `impactshop-offerwall.php` → `impactshop_offerwall_shortcode()`: CPX Script Tag renderelés a survey panelbe (§6.1.1)
- [ ] `impactshop-offerwall.php` → `impactshop_offerwall_extend_csp()`: CPX domain-ek hozzáadása (§6.1.2)
- [ ] `impactshop-offerwall.php` → `impactshop_offerwall_inline_css()`: CPX dark theme override CSS hozzáadása (§5.3)
- [ ] WordPress Admin → Offerwall beállítások: CPX provider aktíválása + paraméterek kitöltése (§4.2)
- [ ] `php -l` syntax check

### Fázis 3 – Frontend módosítások (P1, ~1 óra)

- [ ] `impactshop-offerwall.js`: CPX transaction event listener hozzáadása (§7.1)
- [ ] `impactshop-offerwall.js`: Tab badge frissítés CPX survey count-tal (§7.2)

### Fázis 4 – Tesztelés (P1, ~1 nap)

- [ ] Staging deploy: `bash scripts/hotfix-sync.sh impactshop-offerwall.php --dry-run`
- [ ] CPX Script Tag betöltés ellenőrzése (Network tab)
- [ ] CPX survey lista megjelenítése a Kérdőív tab-ban
- [ ] Dark theme ellenőrzés (színek illeszkednek-e)
- [ ] CPX postback szimuláció (manuális curl teszt):
  ```bash
  curl "https://staging.sharity.hu/wp-json/impact/v1/offerwall/callback/cpx\
    ?transaction_id=test-cpx-001\
    &user_id=testpseudo123\
    &amount_usd=0.50\
    &status=1\
    &hash=$(echo -n 'test-cpx-001{SECURE_HASH}' | md5)"
  ```
- [ ] Completion megjelenik a history-ban
- [ ] Pont és szavazat jóváírás helyes (25 pont, 5 szavazat $0.50-ra)
- [ ] Reversal teszt (`status=2`)
- [ ] Toast megjelenés survey kitöltés után
- [ ] `~/bin/impactall` → guard pass

### Fázis 5 – Production deploy (P2, ~1 óra)

- [ ] Backup: timestamped copy a módosított fájlokról
- [ ] `bash scripts/hotfix-sync.sh impactshop-offerwall.php`
- [ ] `bash scripts/hotfix-sync.sh impactshop-offerwall.js`
- [ ] WordPress Admin: CPX provider bekapcsolása prod-on
- [ ] `~/bin/impactall` → guard pass
- [ ] 1 hét monitoring: `guard-events.log` + `/offerwall/health` endpoint CPX stat-ok

---

## 11. Kockázatok és mitigáció

| Kockázat | Valószínűség | Hatás | Mitigáció |
|---|---|---|---|
| CPX Script Tag nem töltődik (AdBlock) | Magas | Üres Kérdőív tab | `no_surveys_available` callback → fallback üzenet. Alternatíva: iFrame link gomb |
| Kevés HU survey elérhető | Magas | Gyenge user engagement | Country targeting = globális (angol survey-k is). Szűrő: profiling survey-k is jelenjenek meg. |
| CPX CDN JS kompromittálódik (supply chain) | Nagyon alacsony | XSS | SRI hash (ha CPX biztosít) vagy CSP strict-dynamic. |
| MD5 signature collision | Nagyon alacsony | Hamis postback | IP whitelist + rate limiting + transaction_id egyediség |
| CPX payout volatilitás | Közepes | Eltérő pont/perc arány | Multiplier admin panelről finomhangolható, 4 hetente KPI review |
| Dupla jutalom (Script Tag JS callback + server postback) | Alacsony | Dupla pont | A JS callback toast-only (nem ad jutalmat), a postback `INSERT IGNORE` → idempotens |
| CPX iFrame breakout (XSS) | Nagyon alacsony | Session hijack | `sandbox` attribútum az iFrame-en (a mi shortcode-unk már beállítja: `allow-forms allow-popups allow-same-origin allow-scripts`) |

---

## 12. CPX vs AyeT – Együttműködési stratégia

### 12.1 Tab elrendezés

```
┌──────────┬──────────┬──────────┬────────────────┐
│ 📋 Kvíz  │ 📊 Kérdőív│ 🎁 Offerwall│ ✅ Aktívak     │
│  (belső) │  (CPX)   │  (AyeT)  │  (összesített) │
└──────────┴──────────┴──────────┴────────────────┘
```

- **Kérdőív tab** → CPX Research survey widget (Script Tag)
- **Offerwall tab** → AyeT Offerwall API (natív kártyák)
- **Aktívak tab** → Összesített nézet (AyeT aktív feladatok, CPX-nek nincs "aktív" állapot)

> **REVIEW:**
> A fenti ábra pontatlan: a Kérdőív tab-ot úgy mutatja, mintha kizárólag CPX lenne. Valójában a Kérdőív tab **két szekciót** tartalmaz (lásd §5 Opció A):
> ```
> ┌──────────┬──────────────────────┬──────────┬────────┐
> │ 📋 Kvíz  │ 📊 Kérdőív           │ 🎁 Offer │ ✅ Akt │
> │  (belső) │ (belső + CPX együtt) │  (AyeT)  │        │
> └──────────┴──────────────────────┴──────────┴────────┘
> ```
> A Kérdőív tab-on belül: **felül** a saját kérdőíveink, **alul** a CPX külső kérdőívek szekció.

### 12.2 Stat panel bővítés

A stats panel (`/offerwall/stats`) már cross-provider aggregál (`total_points`, `total_votes`). A CPX completion-ök automatikusan bekerülnek. Opcionálisan a stat panelt bővíthetjük:

```html
<div class="offerwall-stat">
  <span class="offerwall-stat-value" data-role="stat-cpx-points">—</span>
  <span class="offerwall-stat-label">CPX pont</span>
</div>
```

Ehhez a stats endpointot kell bővíteni egy `cpx_points_total` mezővel (hasonlóan az `ayet_points_total`-hoz).

### 12.3 History megjelenítés

A history lista (`/offerwall/history`) provider oszloppal jön → a CPX completion-ök `provider=cpx` jelzéssel jelennek meg. A jelenlegi renderHistory() funkció a provider-t is megjeleníti: `(item.offer_name || 'Offer') + ' · ' + (item.provider || '')`.

---

## 13. Jövőbeli bővítési lehetőségek

| Bővítés | Prioritás | Leírás |
|---|---|---|
| CPX Survey API (ha elérhető) | P3 | Ha a CPX kiad publikus survey listing API-t → natív kártya renderelés (mint AyeT) |
| CPX Notification widget | P3 | Design 4/5 – értesítés "Új kérdőív elérhető" (Script Tag config) |
| CPX iframe fallback gomb | P2 | AdBlock esetén "Kérdőívek megnyitása külső ablakban" link |
| Multi-provider napi cap | P2 | Cross-provider aggregált napi cap (jelenleg csak AyeT-re dedikált) |
| CPX stats bővítés | P2 | `cpx_points_total`, `cpx_votes_total`, `cpx_tx_total` mezők a stats endpointban |
| CPX profiling survey jelzés | P3 | Ha a CPX megadja a survey típusát → "📋 Profiling" badge |

---

## 14. Deployment terv

### 14.1 Módosított fájlok

| Fájl | Változás típusa | Méret |
|---|---|---|
| `impactshop-offerwall.php` | Bővítés (shortcode + CSP + CSS) | ~80 sor hozzáadás |
| `impactshop-offerwall.js` | Bővítés (CPX event listener) | ~15 sor hozzáadás |

### 14.2 Nem módosított fájlok

| Fájl | Miért nem kell módosítani |
|---|---|
| `impactshop-ayet-offerwall.php` | AyeT-specifikus, CPX nem érinti |
| DB séma | A meglévő tábla provider-agnosztikus |
| Ledger | A generikus handler automatikusan kezeli |

### 14.3 Rollback terv

A CPX integráció rollback-je **admin panel szintjén** is lehetséges:
1. WordPress Admin → Offerwall → CPX provider → **Aktív** checkbox kikapcsolása
2. Ez azonnal kikapcsolja a CPX Script Tag renderelést és a postback fogadást
3. A meglévő CPX completion-ök megmaradnak a DB-ben (nem törlődnek)
4. Teljes rollback: fájlok visszaállítása backup-ból

### 14.4 Staged deploy sorrend

```
1. Staging: php fájl deploy (--dry-run → apply)
2. Staging: js fájl deploy
3. Staging: Admin panel CPX konfig
4. Staging: ~/bin/impactall
5. Staging: Manuális QA (§10 checklist)
6. Prod: php fájl deploy
7. Prod: js fájl deploy  
8. Prod: Admin panel CPX konfig
9. Prod: ~/bin/impactall
10. 1 hét monitoring
```

---

## 15. CPX Kapcsolattartó – Kommunikációs checklist

A CPX Research kapcsolattartónak küldendő infók:

- [ ] Postback URL (§3.1 – teljes URL a makrókkal)
- [ ] Platform típus: Website (app.sharity.hu)
- [ ] User identification módszer: `ext_user_id` (cookie-based pseudo ID)
- [ ] Kívánt integrációs mód: Script Tag (Design 1, fullscreen)
- [ ] Currency preference: USD
- [ ] Country targeting: HU (+global)
- [ ] Secure Hash: kérjük bekapcsolni
- [ ] Kérdés: callback IP whitelist kérése
- [ ] Kérdés: támogatott-e HMAC-SHA256 a postback hash-hez (MD5 helyett)?
- [ ] Kérdés: elérhető-e SRI hash a `script_tag_v2.0.js` CDN fájlhoz?
- [ ] Kérdés: van-e survey listing API (natív kártyás integráláshoz)?

---

> **Dokumentum státusz: DRAFT v1.0**
> Frissítve: 2026-02-17
> Következő lépés: CPX Dashboard konfiguráció + backend implementáció
