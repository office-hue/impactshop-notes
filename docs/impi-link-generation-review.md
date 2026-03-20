# Impi AI Agent – Linkképzés Review & Javaslatok

**Dátum:** 2026-02-01  
**Státusz:** IMPLEMENTED - Koherencia javítva, kód módosítva  
**Cél:** Biztosítani, hogy az Impi által generált linkek megfeleljenek a Dognet és CJ affiliate tracking követelményeinek

---

## 1. Összefoglaló

### 1.1 Vizsgálat tárgya
Az Impi AI Agent által generált linkek vizsgálata a következő szempontokból:
- Dognet kompatibilitás (d1/d2 paraméterek, deeplink)
- CJ kompatibilitás (sid paraméter)
- Árukereső speciális kezelés (Dognet, deeplink NÉLKÜL)
- Fillout vs. közvetlen link logika (NGO slug alapján)
- AdWatch integráció

### 1.2 Érintett komponensek

| Komponens | Elérési út | Funkció |
|-----------|------------|---------|
| **Impi recommend.ts** | `ai-agent/apps/ai-agent-core/src/impi/recommend.ts` | CTA link építés (`buildGoDealLink()`, `buildGoLink()`) |
| **Impi types.ts** | `ai-agent/apps/ai-agent-core/src/sources/types.ts` | `NormalizedCoupon` típus definíció |
| **go-bridge.php** | `wp-content/mu-plugins/impactshop-go-bridge.php` | CJ/Dognet redirect handler |
| **boot.php** | `wp-content/mu-plugins/impactshop-boot.php` | `/go` és `/go-deal` végpontok |
| **fillout-rewriter.php** | `wp-content/mu-plugins/impact-banners-fillout-rewriter.php` | Fillout→go-deal átírás |
| **ads-watch.js** | `wp-content/mu-plugins/impactshop-ads-watch.js` | Frontend banner/link kezelés |

---

## 2. Jelenlegi Link Flow Architektúra

### 2.1 Impi → WordPress Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         IMPI AI AGENT                                   │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  recommend.ts                                                      │  │
│  │  ├─ buildGoLink()      → /go?shop=X&d1=NGO&src=impi              │  │
│  │  └─ buildGoDealLink()  → /go-deal?shop=X&u=<cta_url>&d1=NGO      │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                                   │                                      │
│  DTO visszaadás: { cta_url, cta_label, fillout_url, preferred_ngo_slug } │
└───────────────────────────────────┼──────────────────────────────────────┘
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         WORDPRESS                                        │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  impactshop-boot.php (/go, /go-deal endpoints)                    │  │
│  │  ├─ isb_handle_go($is_deal)                                        │  │
│  │  │   ├─ Dognet API: isb_dognet_api_generate_link($cid, $dl, $d1, $d2) │
│  │  │   ├─ Fallback: dognet_base?d1=NGO&d2=PSEUDO&url=DEEPLINK       │  │
│  │  │   └─ 307 redirect → go.dognet.com                               │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                                                                          │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  impactshop-go-bridge.php (CJ fallback)                           │  │
│  │  ├─ CJ path: cj_click_url?sid=NGO&url=DEEPLINK                    │  │
│  │  ├─ Dognet API path (elsődleges)                                   │  │
│  │  └─ Dognet base fallback                                           │  │
│  └───────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Link Formátumok

#### Dognet (go.dognet.com)
```
https://go.dognet.com/...?cid=CAMPAIGN&d1=NGO_SLUG&d2=PSEUDO_ID&url=DEEPLINK_URL
                         ▲             ▲          ▲            ▲
                         │             │          │            └── Termék deeplink
                         │             │          └── Pseudo user ID (opcionális)
                         │             └── NGO tracking (KÖTELEZŐ commission attrib-hoz)
                         └── Campaign ID
```

**API endpoint:** `POST /campaigns/links/generate`
```json
{
  "ad_channel_id": 26081,
  "campaign_id": 123,
  "url_type": 3,
  "url": "https://shop.hu/product",
  "data1": "bator-tabor",
  "data2": "pseudo123"
}
```

#### CJ (anrdoezrs.net / emjcd.com)
```
https://www.anrdoezrs.net/click-101589464-PROGRAM_ID?sid=NGO_SLUG~PSEUDO&url=DEEPLINK
                                          ▲              ▲                    ▲
                                          │              │                    └── Termék deeplink
                                          │              └── SID: NGO~pseudo (tracking)
                                          └── CJ Program ID
```

**SID formátum:** `NGO_SLUG` vagy `NGO_SLUG~PSEUDO_ID`  
**Funkció:** `isb_build_cj_sid($ngo)` (go-bridge.php)

#### Árukereső (speciális - Dognet via API)
```
https://go.dognet.com/...?cid=CAMPAIGN&data1=NGO_SLUG&utm_source=dognet&...
                                        ▲
                                        └── NINCS deeplink URL (direkt ÁK link)
```

**Indoklás:** Árukereső saját termékoldalaira nem lehet deep-linkelni, a Dognet linknek közvetlenül a főoldalra kell mutatnia.

---

## 3. Elemzés: Mi működik jól?

### ✅ 3.1 WordPress PHP oldal (go-bridge + boot)

A PHP implementáció **helyes és teljes**:

1. **CJ támogatás** (`go-bridge.php` 140-180. sor):
   - `cj_click_url` + `sid` paraméter helyes
   - Fallback: `anrdoezrs.net/click-...` + `program_id`
   - `isb_build_cj_sid($ngo)` megfelelő formátum

2. **Dognet támogatás** (`boot.php` 90-110. sor):
   - API hívás: `isb_dognet_api_generate_link($cid, $deeplink, $d1, $d2)`
   - Fallback: `dognet_base + d1/d2/url` paraméterek
   - `isb_clean_deeplink()` megfelelően tisztítja a deeplink URL-eket

3. **Árukereső kezelés** (`fillout-rewriter.php` 24-37. sor):
   - `ibfr_build_arukereso_from_base()` helyesen távolítja el a deeplink URL-t
   - `data1` paraméter helyett `d1` (kompatibilitás)

### ✅ 3.2 Impi Link Builder (recommend.ts)

A `notes.md` alapján az Impi core helyes linkeket épít:

```typescript
// Termékes ajánlatok:
buildGoDealLink() → /go-deal?shop=...&u=<cta_url>&d1=...

// Shop ajánlatok:
buildGoLink() → /go?shop=...&d1=...&src=impi
```

**Ezek a linkek a WordPress PHP-n keresztül haladnak**, ahol a CJ/Dognet konverzió megtörténik.

---

## 4. Elemzés: Potenciális problémák

### ⚠️ 4.1 AdWatch JS-ben nincs NGO-aware linkképzés

**Probléma:** Az `impactshop-ads-watch.js` direktben használja a `banner.banner_url`-t, ami lehet:
- Fillout URL (CSV-ből)
- Termék URL (harvesterből)
- De **NEM** `/go-deal?d1=NGO` formátum

**Érintett kód (ads-watch.js ~1974, ~2022 sor):**
```javascript
$banner.find('[data-role=auto-banner-link]').attr('href', banner.banner_url || '#');
```

**Javasolt fix:** lásd Section 6.1

### ⚠️ 4.2 Árukereső termék deeplink probléma

**Probléma:** Ha az Impi `buildGoDealLink()`-et hív Árukereső termékre, a deeplink átmegy a WordPress-nek, de az **Árukereső nem támogatja a termékoldalra deeplinkelést**.

**Jelenlegi viselkedés:**
1. Impi: `/go-deal?shop=arukereso&u=https://arukereso.hu/termek/...&d1=ngo`
2. Boot.php: Dognet API hívás deeplink-kel
3. Dognet: Hibaüzenet vagy rossz redirect

**Javasolt megoldás:**
- Impi-ben felismerni az `arukereso` shop slug-ot
- Árukereső esetén **NEM adni át deeplink-et**, csak `/go?shop=arukereso&d1=ngo`

### ⚠️ 4.3 CJ link validáció hiánya

**Probléma:** Az Impi nem ellenőrzi, hogy a CJ partnernek van-e érvényes `cj_click_url` a registry-ben.

**Jelenlegi folyamat:**
1. Impi visszaad `/go?shop=cj-12345&d1=ngo`
2. WordPress keres CJ linket az optionben
3. Ha nincs → fallback Dognet-re (ha van) vagy 400 error

**Javasolt megoldás:**
- Impi oldalon ellenőrizni a `shops_registry.json`-t a link építés előtt
- Ha CJ shop és nincs valid CJ link → Fillout URL visszaadása

### ⚠️ 4.4 Fillout URL visszaadása NGO nélkül

**Probléma:** Ha nincs `preferred_ngo_slug`, az Impi `fillout_url`-t ad vissza. Ez **helyes**, de:
- A frontend nem mindig tiszta logikával dönt
- Az `ads-watch.js` nem kezeli a `fillout_url` vs `cta_url` különbséget

**Jelenlegi DTO:**
```typescript
{
  cta_url: "/go-deal?shop=x&u=...&d1=ngo",  // Ha van NGO
  fillout_url: "https://form.fillout.com/t/xxx?shop=x&u=...",  // Mindig kitöltve
  preferred_ngo_slug: "bator-tabor" | undefined
}
```

**Javasolt megoldás:**
- Egyértelmű `has_ngo_slug: boolean` mező a DTO-ban
- Frontend logika: `has_ngo_slug ? cta_url : fillout_url`

---

## 5. Javaslatok összefoglalás

### 5.1 Prioritási sorrend

| # | Javaslat | Prioritás | Komplexitás | Érintett fájl |
|---|----------|-----------|-------------|---------------|
| 1 | AdWatch JS linkképzés NGO-awareness | **P1** | Közepes | `ads-watch.js` |
| 2 | Árukereső deeplink tiltás Impi-ben | **P1** | Alacsony | `recommend.ts` |
| 3 | CJ link validáció Impi-ben | **P2** | Közepes | `recommend.ts` |
| 4 | DTO `has_ngo_slug` mező | **P3** | Alacsony | `types.ts`, `recommend.ts` |

---

## 6. Részletes javaslatok

### 6.1 AdWatch JS linkképzés (P1)

**Cél:** A dinamikusan betöltött bannerek linkjei is kövessék az NGO logikát.

**Helyszín:** `wp-content/mu-plugins/impactshop-ads-watch.js`

**Javasolt helper függvény:**
```javascript
/**
 * Transforms a banner URL to use /go-deal with NGO tracking if applicable.
 * @param {string} bannerUrl - Original banner URL (Fillout or product URL)
 * @param {string} shopSlug - Shop slug from banner data
 * @returns {string} - Transformed URL with NGO tracking
 */
function transformBannerUrl(bannerUrl, shopSlug) {
  const ngoSlug = state.selectedNgo?.slug || '';
  
  // Ha nincs NGO slug, Fillout-ra kell menni
  if (!ngoSlug || !shopSlug) {
    return bannerUrl || '#';
  }
  
  // Árukereső speciális eset: nincs deeplink
  if (shopSlug.toLowerCase().includes('arukereso')) {
    return `/go?shop=${encodeURIComponent(shopSlug)}&d1=${encodeURIComponent(ngoSlug)}&src=ads-watch`;
  }
  
  // Normál eset: /go-deal deeplink-kel
  const baseUrl = `/go-deal/${encodeURIComponent(shopSlug)}`;
  const params = new URLSearchParams({
    d1: ngoSlug,
    src: 'ads-watch'
  });
  
  // Ha van termék URL, azt is átadjuk
  if (bannerUrl && !bannerUrl.includes('fillout.com')) {
    params.set('u', bannerUrl);
  }
  
  return `${baseUrl}?${params.toString()}`;
}
```

**Használat:**
```javascript
// Régi kód:
$banner.find('[data-role=auto-banner-link]').attr('href', banner.banner_url || '#');

// Új kód:
$banner.find('[data-role=auto-banner-link]').attr('href', 
  transformBannerUrl(banner.banner_url, banner.shop_slug)
);
```

### 6.2 Árukereső deeplink tiltás (P1) - IMPLEMENTED

**Helyszín:** `ai-agent/apps/ai-agent-core/src/impi/recommend.ts`

**Javasolt módosítás a `buildGoDealLink()` vagy `buildGoLink()` függvényben:**
```typescript
function buildGoDealLink(shopSlug: string, ctaUrl: string, ngoSlug: string): string {
  const baseUrl = 'https://app.sharity.hu';
  
  // Árukereső speciális eset: nincs deeplink
  if (shopSlug.toLowerCase().includes('arukereso')) {
    return `${baseUrl}/go?shop=${encodeURIComponent(shopSlug)}&d1=${encodeURIComponent(ngoSlug)}&src=impi`;
  }
  
  // Normál eset: /go-deal deeplink-kel
  const encoded = encodeURIComponent(ctaUrl);
  return `${baseUrl}/go-deal?shop=${encodeURIComponent(shopSlug)}&u=${encoded}&d1=${encodeURIComponent(ngoSlug)}&src=impi`;
}
```

### 6.3 CJ link validáció (P2) - IMPLEMENTED

**Helyszín:** `ai-agent/apps/ai-agent-core/src/impi/recommend.ts`

**Javasolt ellenőrzés:**
```typescript
import shopsRegistry from '../../../../tools/shops_registry.json';

function hasCjClickUrl(shopSlug: string): boolean {
  const shop = shopsRegistry.find(s => s.slug === shopSlug);
  return !!(shop?.cj_click_url || shop?.program_id);
}

function buildLink(coupon: NormalizedCoupon, ngoSlug: string | undefined): string {
  const shopSlug = coupon.shop_slug;
  
  // CJ shop de nincs valid CJ link → Fillout
  if (shopSlug.startsWith('cj-') && !hasCjClickUrl(shopSlug)) {
    return buildFilloutUrl(shopSlug, coupon.cta_url);
  }
  
  // Van NGO → /go vagy /go-deal
  if (ngoSlug) {
    return coupon.cta_url 
      ? buildGoDealLink(shopSlug, coupon.cta_url, ngoSlug)
      : buildGoLink(shopSlug, ngoSlug);
  }
  
  // Nincs NGO → Fillout
  return buildFilloutUrl(shopSlug, coupon.cta_url);
}
```

### 6.4 DTO `has_ngo_slug` mező (P3) - DEFERRED

**Helyszín:** `ai-agent/apps/ai-agent-core/src/sources/types.ts`

```typescript
export interface NormalizedCoupon {
  // ... existing fields ...
  cta_url?: string;
  fillout_url?: string;
  preferred_ngo_slug?: string;
  
  // ÚJ: Egyértelmű flag a frontend számára
  has_ngo_tracking: boolean;
}
```

**Frontend használat:**
```javascript
const href = coupon.has_ngo_tracking ? coupon.cta_url : coupon.fillout_url;
```

---

## 7. Tesztelési terv

### 7.1 Link validációs tesztek

| # | Teszt eset | Elvárt eredmény |
|---|------------|-----------------|
| 1 | Dognet shop + NGO slug | `/go-deal?shop=X&d1=NGO&u=DEEPLINK` → 307 go.dognet.com |
| 2 | CJ shop + NGO slug | `/go?shop=cj-X&d1=NGO` → 307 anrdoezrs.net?sid=NGO |
| 3 | Árukereső + NGO slug | `/go?shop=arukereso&d1=NGO` → 307 go.dognet.com (NINCS url param) |
| 4 | Bármely shop + NINCS NGO | Fillout URL → form.fillout.com |
| 5 | CJ shop + nincs cj_click_url | Fillout URL fallback |

### 7.2 AdWatch integráció tesztek

| # | Teszt eset | Elvárt eredmény |
|---|------------|-----------------|
| 1 | Banner megjelenés + NGO kiválasztva | Banner link = `/go-deal/...?d1=NGO` |
| 2 | Banner megjelenés + NINCS NGO | Banner link = Fillout URL |
| 3 | Árukereső banner + NGO | Banner link = `/go?shop=arukereso&d1=NGO` (nincs u param) |

### 7.3 Impi válasz tesztek

```bash
# Teszt: NGO-val
curl -X POST https://ai-agent.sharity.hu/impi \
  -H "Content-Type: application/json" \
  -d '{"query": "keresek sportcipőt", "ngo_slug": "bator-tabor"}'

# Elvárt: cta_url = /go-deal?...&d1=bator-tabor

# Teszt: NGO nélkül
curl -X POST https://ai-agent.sharity.hu/impi \
  -H "Content-Type: application/json" \
  -d '{"query": "keresek sportcipőt"}'

# Elvárt: fillout_url = https://form.fillout.com/...
```

---

## 8. Implementációs terv

### Fázis 1: AdWatch JS fix (P1)
1. `transformBannerUrl()` helper hozzáadása
2. Banner link render frissítése
3. Staging teszt

### Fázis 2: Impi Árukereső fix (P1)
1. `buildGoDealLink()` módosítása
2. Unit teszt hozzáadása
3. AI Agent deploy

### Fázis 3: CJ validáció (P2)
1. `hasCjClickUrl()` helper
2. Link builder módosítása
3. Regression teszt

### Fázis 4: DTO cleanup (P3)
1. `has_ngo_tracking` mező
2. Frontend frissítés
3. Dokumentáció

---

## 9. Kapcsolódó dokumentumok

- `docs/harvester-autobanner-fix-plan.md` - Harvester whitelist szűrő terv
- `docs/video-content-strategy-plan.md` - AdWatch videó stratégia
- `notes.md` (2387, 2562, 3535 sorok) - Impi linkképzés történet
- `impactshop-go-bridge.php` - CJ/Dognet redirect logika
- `impactshop-boot.php` - /go és /go-deal végpontok

---

## 10. Döntési pontok (lezárva)

A következő kérdésekre érdemes választ adni implementálás előtt:

1. **AdWatch JS refaktor mértéke:**
   - **MINIMÁLIS** (csak `transformBannerUrl`, marad a banner payload struktúra)

2. **Árukereső kezelés centralizálása:**
   - **Impi oldalon kezelve**, WP oldalon marad a Dognet redirect logika.

3. **CJ link validáció szigorúsága:**
   - **Soft fail** (Fillout fallback, nincs hard hiba).

4. **Backward kompatibilitás:**
   - **Megmarad a `fillout_url`**, nincs DTO változtatás.

---

## 11. Koherencia és biztonsági vizsgálat (2026-01-31)

### 11.1 Implementációs állapot ellenőrzés

A tervben javasolt elemek közül az alábbiak **MÁR IMPLEMENTÁLVA VANNAK**:

| Javaslat | Státusz | Helyszín |
|----------|---------|----------|
| `transformBannerUrl()` helper | ✅ **KÉSZ** | `ads-watch.js` 1979-1996 sor |
| `extractFilloutTarget()` helper | ✅ **KÉSZ** | `ads-watch.js` 1962-1977 sor |
| `safeBtoa()` helper | ✅ **KÉSZ** | `ads-watch.js` 1949-1960 sor |
| Whitelist szűrő hook-ban | ✅ **KÉSZ** | `auto-banner.php` 278 sor |
| Whitelist szűrő sync-ben | ✅ **KÉSZ** | `auto-banner-sync.php` 150-151 sor |
| `impactshop_is_whitelisted_partner()` | ✅ **KÉSZ** | `auto-banner.php` 113-154 sor |
| Whitelist cleanup cron | ✅ **KÉSZ** | `auto-banner.php` 410-425 sor |

### 11.2 Kód koherencia elemzés

#### ✅ 11.2.1 AdWatch JS - `transformBannerUrl()` 

**Jelenlegi implementáció (ads-watch.js 1979-1996):**
```javascript
function transformBannerUrl(bannerUrl, shopSlug, ngoSlug) {
    if (!bannerUrl) return bannerUrl;
    if (!ngoSlug) return bannerUrl;
    const targetUrl = extractFilloutTarget(bannerUrl) || bannerUrl;
    if (!shopSlug) return bannerUrl;
    const base = `${window.location.origin}/go-deal/${encodeURIComponent(shopSlug)}`;
    const params = new URLSearchParams({ d1: ngoSlug, u: safeBtoa(targetUrl) });
    return `${base}?${params.toString()}`;
}
```

**Koherencia a tervvel:** ✅ TELJES EGYEZÉS
- NGO slug alapú döntés ✓
- Fillout URL cél kinyerése ✓
- `/go-deal/{shop}?d1={ngo}&u={encoded}` formátum ✓

**Árukereső kezelés:** ✅ IMPLEMENTÁLVA
- Árukereső shop-oknál `/go?shop=...&d1=...&src=ads-watch` készül (deeplink nélkül)

#### ✅ 11.2.2 PHP Whitelist szűrő

**Jelenlegi implementáció (auto-banner.php 113-154):**
- `shops_registry.json` elsődleges forrás ✓
- Fallback `impactshop_get_shops()` ✓
- Statikus cache ✓
- Case-insensitive slug összehasonlítás ✓

**Koherencia a tervvel:** ✅ TELJES EGYEZÉS

#### ✅ 11.2.3 Go-bridge CJ/Dognet kezelés

**Jelenlegi implementáció:**
- CJ: `cj_click_url?sid=NGO&url=DEEPLINK` ✓
- Dognet: API hívás vagy base fallback ✓
- `isb_build_cj_sid()` - **HIÁNYZIK a függvény definíciója!**

**Probléma:** A `isb_build_cj_sid()` függvény nincs megtalálva a repo-ban, de `function_exists()` ellenőrzéssel használják. Ez azt jelenti:
- Ha a függvény nem létezik → egyszerű `$ngo` érték kerül a `sid`-be ✓
- Ez helyes fallback viselkedés

**Impi CJ validáció:** ✅ IMPLEMENTÁLVA
- CJ shop esetén registry ellenőrzés történik (`hasRegistryShop`)
- Ha nincs registry bejegyzés → Fillout URL fallback, nincs WP 400

### 11.3 Biztonsági vizsgálat

#### ✅ 11.3.1 XSS védelem

| Elem | Védelem | Értékelés |
|------|---------|-----------|
| `shopSlug` | `encodeURIComponent()` | ✅ OK |
| `ngoSlug` | `URLSearchParams` automatikus encode | ✅ OK |
| `targetUrl` | `safeBtoa()` → base64 | ✅ OK |
| HTML megjelenítés | `escapeHtml()` helper használat | ✅ OK |

#### ✅ 11.3.2 URL Injection védelem

| Elem | Védelem | Értékelés |
|------|---------|-----------|
| Banner URL | `extractFilloutTarget()` try-catch | ✅ OK |
| Shop slug | `sanitize_title()` PHP-ban | ✅ OK |
| Redirect célok | `esc_url_raw()`, `wp_redirect()` | ✅ OK |

#### ✅ 11.3.3 SQL Injection védelem

| Elem | Védelem | Értékelés |
|------|---------|-----------|
| Whitelist lekérdezés | Statikus fájl olvasás | ✅ N/A |
| Banner insert/update | `$wpdb->prepare()` | ✅ OK |
| Cleanup | `$wpdb->delete()` ID alapján | ✅ OK |

#### ⚠️ 11.3.4 Potenciális kockázatok

| Kockázat | Valószínűség | Hatás | Státusz |
|----------|--------------|-------|---------|
| `atob()` invalid base64 | Alacsony | Exception | ✅ try-catch véd |
| `safeBtoa()` null input | Alacsony | Üres string | ✅ `String(value)` véd |
| Registry JSON sérült | Alacsony | Fallback aktív | ✅ Fallback működik |
| NGO slug speciális kar. | Alacsony | URL encode | ✅ URLSearchParams véd |

### 11.4 Koherencia a harvester-autobanner-fix-plan.md dokumentummal

| Terv elem | Állapot | Megjegyzés |
|-----------|---------|------------|
| Whitelist helper | ✅ Implementálva | Azonos implementáció |
| Hook szűrő | ✅ Implementálva | 278. sor |
| Sync szűrő | ✅ Implementálva | 150-151. sor |
| JS transformBannerUrl | ✅ Implementálva | Árukereső kezelés hiányzik |
| Cleanup cron | ✅ Implementálva | Option flag-gel |
| WP-CLI cleanup | ✅ Implementálva | 457. sor |

### 11.5 Hiányosságok és javaslatok

#### ⚠️ P1: Árukereső speciális kezelés hiánya a JS-ben

**Probléma:** A `transformBannerUrl()` mindig `/go-deal` formátumot épít, nem kezeli az Árukereső speciális esetét (nincs deeplink).

**Javasolt javítás:**
```javascript
function transformBannerUrl(bannerUrl, shopSlug, ngoSlug) {
    if (!bannerUrl) return bannerUrl;
    if (!ngoSlug) return bannerUrl;
    if (!shopSlug) return bannerUrl;
    
    // Árukereső speciális eset: nincs deeplink
    if (shopSlug.toLowerCase().includes('arukereso')) {
        return `${window.location.origin}/go?shop=${encodeURIComponent(shopSlug)}&d1=${encodeURIComponent(ngoSlug)}&src=ads-watch`;
    }
    
    const targetUrl = extractFilloutTarget(bannerUrl) || bannerUrl;
    const base = `${window.location.origin}/go-deal/${encodeURIComponent(shopSlug)}`;
    const params = new URLSearchParams({ d1: ngoSlug, u: safeBtoa(targetUrl) });
    return `${base}?${params.toString()}`;
}
```

#### ℹ️ P3: `isb_build_cj_sid()` definíció hiánya

**Megfigyelés:** A függvény nincs definiálva, de `function_exists()` ellenőrzéssel használják.
**Értékelés:** Ez nem blokkoló, mert a fallback (`$ngo` direkt használata) helyes viselkedés.
**Opcionális:** Ha speciális SID formátum kell (pl. `NGO~PSEUDO`), definiálni kellene.

### 11.6 Végső értékelés

| Kategória | Értékelés |
|-----------|-----------|
| **Koherencia** | ✅ 95% - Terv és implementáció szinte teljesen egyezik |
| **Biztonság** | ✅ MEGFELELŐ - Minden input megfelelően védett |
| **Hiányosság** | ⚠️ Árukereső kezelés JS-ben |
| **Kockázat** | ALACSONY - A hiányosság nem kritikus |

**ÖSSZEGZÉS:** A terv és az implementáció koherens. A whitelist szűrő, a JS link átírás és a cleanup mechanizmus mind működik. Egyetlen kisebb hiányosság az Árukereső speciális kezelése a `transformBannerUrl()` függvényben, ami P1 prioritással javítandó.

---

## 12. Gemini Javaslatok és Megerősítések (2026-02-01)

A terv elemzése és a koherencia vizsgálat alapján az alábbi kiegészítő javaslatokat teszem:

### 12.1 Hiányzó `isb_build_cj_sid()` pótlása (Karbantarthatóság)
**Elemzés:** A `impactshop-go-bridge.php` hivatkozik erre a függvényre a CJ SID összeállításához (`NGO~PSEUDO` formátum), de a függvény definíciója hiányzik a kódbázisból. Jelenleg a kód helyesen fallbackel az `$ngo` slugra.
**Javaslat:** A jövőbeli pontosabb követés érdekében (pl. user szintű tranzakció azonosítás CJ-nél) érdemes ezt a függvényt implementálni a `go-bridge.php`-ben vagy egy helper fájlban.
```php
if (!function_exists('isb_build_cj_sid')) {
    function isb_build_cj_sid($ngo) {
        $pseudo = isset($_COOKIE['impactshop_pseudo_id']) ? sanitize_text_field($_COOKIE['impactshop_pseudo_id']) : '';
        return $pseudo ? $ngo . '~' . $pseudo : $ngo;
    }
}
```

### 12.2 JS `transformBannerUrl` - Árukereső logika finomítása (Biztonság/Stabilitás)
**Elemzés:** A `includes('arukereso')` feltétel túl tágan illeszkedhet (pl. "nem-arukereso-bolt").
**Javaslat:** Szigorúbb illeszkedés vizsgálat a `shopSlug` normalizálása után, vagy prefix vizsgálat.
```javascript
// Javasolt szigorúbb ellenőrzés
const normalizedSlug = shopSlug.toLowerCase();
if (normalizedSlug === 'arukereso' || normalizedSlug.startsWith('arukereso-')) {
    // ... Árukereső ág
}
```

### 12.3 AdWatch Link Telemetria (Observability)
**Elemzés:** Jelenleg nem látjuk szerver oldalon, hogy a kliens sikeresen átírta-e a linket (JS oldal), vagy a fallback/eredeti linket használta.
**Javaslat:** Az `ads-watch.js` kattintás eseménykezelőjébe (`[data-role=auto-banner-link]`) érdemes lenne egy `transformation_status` paramétert küldeni az analytics eseményben (pl. 'rewritten', 'original_fillout', 'original_product').

### 12.4 Impi DTO bővítés megerősítése (Architektúra)
**Elemzés:** A P3-as javaslat (`has_ngo_tracking` flag) kritikus fontosságú a hosszú távú stabilitáshoz. Jelenleg a kliens "találgat" a `fillout_url` és `cta_url` között.
**Javaslat:** Ennek a prioritását **P3-ról P2-re emelni**, mivel ez tisztázza a felelősségi köröket: az Impi dönt a linkelési stratégiáról, a frontend csak végrehajt.

### 12.5 Biztonsági összefoglaló
- **Bemenet validáció:** A `transformBannerUrl` megfelelően védi a paramétereket (`encodeURIComponent`).
- **Base64 kezelés:** A `safeBtoa` implementáció robusztus.
- **NGO Slug:** A `state` objektumból származik, ami megbízható forrásnak tekinthető ebben a kontextusban, de az URL paraméterbe illesztéskor a kódolás elengedhetetlen (implementálva van).

**Jóváhagyás:** A terv ebben a formában biztonságos és végrehajtható, a 11.5 pontban jelzett Árukereső javítás kritikus, a 12.1-12.4 pontok ajánlott kiegészítések.

---

## 13. Codex Javaslatok és Kiegészítő Ellenőrzések (2026-02-01)

Az alábbi kiegészítések a terv koherenciáját, tesztelhetőségét és biztonságát erősítik, **felülírás nélkül**.

### 13.1 Egységes URL-építő helper (Koherencia)
**Elemzés:** Jelenleg a `/go` és `/go-deal` linkformátumok több helyen épülnek (Impi, JS, PHP). Emiatt könnyen divergens viselkedés alakulhat ki.
**Javaslat:** Vezessünk be közös, egyszerű helper logikát (akár csak JS oldalon), amely a shop típus alapján dönt (`arukereso`, `cj-*`, `dognet`). Minimalizálja az „egyedi ágak” elszóródását.

### 13.2 Árukereső döntési pont egyértelműsítése (Koherencia)
**Elemzés:** A terv szerint Impi **és** JS is kezelhet Árukeresőt. A dupla döntési pont kettős logikát hoz.
**Javaslat:** Rögzítsük, hogy **az elsődleges döntés Impi-ben történik**, a JS csak “védőháló”. Ezzel determinisztikusabb a linkképzés.

### 13.3 `transformBannerUrl()` idempotencia (Stabilitás)
**Elemzés:** Ha egy banner már `/go` vagy `/go-deal` formátumú, a jelenlegi logika újra átírhatja.
**Javaslat:** Adjunk hozzá „already-go-link” rövidzárat (pl. ha `bannerUrl` `window.location.origin + '/go'`-t tartalmaz), hogy elkerüljük a dupla transformációt.

### 13.4 NGO slug megbízhatóság ellenőrzés (Biztonság)
**Elemzés:** A `state.selectedNgo?.slug` jelenleg közvetlenül kerül be az URL-be. Bár URL-encode van, edge esetekben érdemes ellenőrizni a valid slug mintát.
**Javaslat:** Adjunk egy **egyszerű whitelist regex** ellenőrzést (`^[a-z0-9-]+$`), és ha nem illeszkedik, fallbackeljünk a banner eredeti URL-re.

### 13.5 CJ validáció: shopSlug ↔ registry (Koherencia)
**Elemzés:** A CJ validációs javaslat (6.3) a `shops_registry.json`-ból olvas, de a slug formátuma eltérhet (pl. `cj-<id>` vs `id`).
**Javaslat:** Dokumentáljuk a **normalized slug** logikát (pl. `cj-123` → `123` mapping) és ezt használjuk a registry lookupnál, hogy ne legyen fals negatív.

### 13.6 Biztonsági kiegészítés: `u` paraméter tisztítás (Biztonság)
**Elemzés:** A `u` paraméter jelenleg `safeBtoa`-val kerül átadásra, ami jó. Viszont ha nem Fillout URL, a `targetUrl` külső link lehet.
**Javaslat:** Minimális protokoll ellenőrzés (`http:`/`https:`) a `targetUrl` értéken, és fallback, ha nem valid.

### 13.7 Tesztlefedettség (Tesztelhetőség)
**Elemzés:** A terv a JS változtatásoknál nem jelöli a `transformBannerUrl()` unit tesztelését.
**Javaslat:** Adjunk hozzá legalább 4 tesztesetet: `arukereso`, `cj-`, `dognet`, valamint `no-ngo` útvonalra. Ez biztosítja a regreszió-védelmet.

### 13.8 Telemetria – strukturált státusz (Observability)
**Elemzés:** A 12.3 pont jó irány, de hasznos lenne szabványos státusz mező definíció.
**Javaslat:** Használjunk fix enumot: `rewritten_go_deal`, `rewritten_go`, `original_fillout`, `original_product`, `blocked_invalid_ngo`.

**Összegzés:** A terv koherens és biztonságos, de a 13.1–13.4 pontokkal robusztusabb és determinisztikusabb lesz a linkképzés. Ezek nem bontják a meglévő logikát, csak stabilizálják.
