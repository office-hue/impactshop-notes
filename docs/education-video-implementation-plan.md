# 📋 Edukációs Videó Rendszer - Implementációs Terv

**Dátum:** 2026. január 29.
**Státusz:** Tervezés
**Prioritás:** P0

---

## 🔍 Jelenlegi állapot (Koherencia analízis)

### Meglévő komponensek:

| Komponens | Státusz | Megjegyzés |
|-----------|---------|------------|
| Szponzor videó CPT | ✅ Működik | `impact_sponsor_video` post type + meta box |
| Edukációs videók | ⚠️ Filter-based | `impactshop_ads_watch_education_videos` filter, nincs admin UI |
| 30mp intervallumos jutalmazás | ✅ Kód van | `IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS = 30` |
| Session kezelés | ✅ Működik | Transient alapú, `impactshop_ads_edu_session_*` |
| Pontok/szavazatok | ✅ Kód van | `IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL = 5` |

### Hiányzó komponensek:

| Komponens | Státusz | Prioritás |
|-----------|---------|-----------|
| Edukációs videó CPT | ❌ Hiányzik | P0 |
| Admin felület beállításokkal | ❌ Hiányzik | P0 |
| Skip (átlépés) gomb | ❌ Hiányzik | P1 |
| "Itt vagy még?" jelenlét ellenőrzés | ❌ Hiányzik | P1 |
| Videó alatt tájékoztató szöveg | ❌ Hiányzik | P1 |
| Végén bónusz pont | ❌ Hiányzik | P2 |

### Codex javaslatok (Koherencia – quick notes)
- **Tartalomforrás-prioritás:** rögzítsük, hogy a CPT legyen az elsődleges forrás, a filteres lista pedig csak *merge* vagy *fallback* módban érkezik. Ezt itt érdemes explicit döntésként megjelölni.
- **Session TTL skálázás:** hosszú videóknál a `IMPACTSHOP_ADS_EDU_SESSION_TTL` (30p) kevés lehet; legyen dinamikus (pl. videó hossza + 30p) vagy időzített frissítés.
- **Pontvalidáció szerveroldalon:** a `watched_seconds` csak kliens oldali; szükséges szerver oldali ellenőrzés (elapsed time alapú plafon).

### Sonnet javaslatok (Architektúra & biztonság) 🔐
- **Rate limiting:** Az `/education` endpoint-hoz IP/user alapú rate limit szükséges (max 10 req/perc), hogy ne lehessen script-tel spammelni az interval küldéseket.
- **Session integrity:** A session token mellé hash-eljük a `pseudo_id + content_id + created_at` értéket, és minden POST-nál validáljuk – ez megakadályozza a token manipulation-t.
- **Videó validáció:** CPT-ben tárolt YouTube ID-t ellenőrizzük a YouTube API-val mentéskor (legalább oEmbed hívással), hogy létező videó-e, így elkerülhető a törött linkek admin-ba kerülése.

---

## 📐 Részletes Terv

### 1. Custom Post Type: `impact_edu_video` (20 karakter ✅)

```php
register_post_type('impact_edu_video', [
    'labels' => [
        'name' => 'Edukációs videók',
        'singular_name' => 'Edukációs videó',
        'add_new_item' => 'Új edukációs videó',
        'edit_item' => 'Edukációs videó szerkesztése',
    ],
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'menu_icon' => 'dashicons-welcome-learn-more',
    'supports' => ['title', 'editor'], // editor = leírás
]);
```

### 2. Admin Meta Box beállítások

| Mező | Típus | Leírás | Default |
|------|-------|--------|---------|
| **Videó típus** | select | `youtube` / `mp4` | youtube |
| **YouTube URL** | url | YouTube link | - |
| **MP4 URL** | url | Direkt videó link | - |
| **Teljes hossz (mp)** | number | Videó időtartam másodpercben | 0 (auto) |
| **Intervallum (mp)** | number | Mennyi időnként jutalmazunk | 30 |
| **Pont/intervallum** | number | Hány pont jár intervallumonként | 5 |
| **Szavazat/intervallum** | number | Hány szavazat jár | 5 |
| **Bónusz pont videó végén** | number | Extra pont ha végignézi | 10 |
| **Bónusz szavazat videó végén** | number | Extra szavazat | 10 |
| **Jelenlét ellenőrzés (mp)** | number | Mennyi időnként kérdezünk rá | 60 |
| **Jelenlét timeout (mp)** | number | Ennyi idő után leáll ha nem válaszol | 30 |
| **Skip engedélyezve** | checkbox | Át lehet-e lépni | igen |
| **Max megtekintés / user** | number | 0 = korlátlan | 0 |
| **Cooldown (perc)** | number | Újranézés előtt várakozás | 0 |
| **Kezdete / Vége** | datetime | Időzítés | - |
| **CTA gomb felirat** | text | Pl. "Tudj meg többet" | - |
| **CTA gomb link** | url | - | - |

#### Codex javaslatok (Admin beállítások)
- **Globál vs. videó-szintű override:** jelöljük egyértelműen, hogy a videó szintű beállítások felülírják a globális `IMPACTSHOP_ADS_EDU_*` értékeket.
- **Validáció:** mp4 URL esetén kötelező `.mp4` validáció; YouTube ID automatikus kinyerése, hibajelzés invalid URL-re.
- **Disabled states:** ha `presence` kikapcsolt (0 mp), a kapcsolódó timeout mező rejtve/tiltva legyen a szerkesztőben.

#### Sonnet javaslatok (Admin UX & adatminőség) 📊
- **Preview funkció:** Admin meta box-ban legyen egy "Előnézet" gomb, ami egy modal-ban betölti a videót (YouTube iframe vagy HTML5), hogy azonnal ellenőrizhető legyen.
- **Automatikus duration lekérés:** YouTube videóknál az API-ból (oEmbed vagy Data API) automatikusan töltsük be a `duration` mezőt, hogy ne kelljen manuálisan beírni.
- **Bulk settings:** Adjunk lehetőséget "Beállítások másolása másik videóról" funkcióra, hogy ne kelljen minden mezőt újra kitölteni hasonló videóknál.
- **Konfliktusos beállítások validálása:** Ha `skip_enabled = false` ÉS `presence_interval = 0`, akkor a videó "elakadhat" – jeleztessük admin mentéskor.

### 3. Jelenlét ellenőrzés ("Itt vagy még?")

**Működés:**
1. Minden `presence_check_interval` másodpercenként (default: 60mp)
2. Videó **megáll** (pause)
3. Overlay jelenik meg: "Még itt vagy? Kattints a folytatáshoz!"
4. "Igen, folytatom" gomb
5. Ha `presence_timeout` másodpercen belül (default: 30mp) nem kattint:
   - Videó leáll
   - Notification: "A videó leállt inaktivitás miatt."
   - Csak az addig megnézett intervallumokért jár pont

**JS State bővítés:**
```javascript
state.presenceCheckInterval = 60,      // másodpercenként
state.presenceTimeout = 30,            // timeout
state.presenceCheckTimer = null,       // setInterval ID
state.presenceTimeoutTimer = null,     // setTimeout ID
state.presenceRequired = false,        // overlay megjelenjen-e
state.presenceLastCheck = 0            // utolsó ellenőrzés ideje
```

#### Codex javaslatok (Jelenlét ellenőrzés)
- **Overlay UX:** legyen egyértelmű, hogy a videó megállt, és a háttér legyen fél-átlátszó, hogy látszódjon hol tartott.
- **Fókusz jelzés:** ha a tab nem aktív, opcionális hang/vibrációs jelzés (browser engedélyezéssel).
- **Anti-abuse:** a jelenlét gomb spam kattintás ellen védett (debounce / minimum 1s).

#### Sonnet javaslatok (Presence check robustness) ⚡
- **Visibilty API integráció:** Használjuk a `document.visibilityState` API-t – ha a tab háttérbe kerül, ne számoljon az idő, vagy jelezzük az overlay-en: "A videó szünetel, amíg a lap nem aktív."
- **Timeout progressbar:** A presence overlay timeout bar-ja ne csak lineárisan csökkenjen, hanem minden 5 másodpercben pulzáljon (figyelemfelkeltés).
- **Accessibility:** ARIA labels és focus trap a presence overlay-en, hogy képernyőolvasóval és billentyűzettel is használható legyen (`Tab` + `Enter` a gombra).
- **Multi-instance védelem:** Ha a user több tabban nyitja meg, a session token alapján csak egy helyen legyen aktív session – máshol hibaüzenet.

### 4. Skip (Átlépés) gomb

**Működés:**
1. "Videó kihagyása" gomb megjelenik 5mp után
2. Kattintásra:
   - Videó leáll
   - Kiszámolja: `Math.floor(watchedSeconds / intervalSeconds)` = earned intervals
   - POST `/education` endpoint-ra küldi az eddig megnézett intervallumokat
   - Notification: "X pont és Y szavazat jóváírva"
   - Következő content betöltése

**HTML elem:**
```html
<button type="button" class="btn-skip-education" id="btn-skip-education" style="display: none;">
    Videó kihagyása
</button>
```

#### Codex javaslatok (Skip)
- **Megerősítés:** első kattintáskor modal: „Biztosan kihagyod? A bónusz elvész."
- **Skip cool-down:** ne jelenjen meg 5 mp alatt; javasolt 10–15 mp, hogy ne legyen reflex skip.

#### Sonnet javaslatok (Skip button stratégia) 🎯
- **Feltételes megjelenés:** Ha a videó < 60 mp, ne jelenjen meg skip gomb (rövid videót érdemes végignézni).
- **Skip analytics:** Loggoljuk a skip eseményeket (melyik videónál, hány mp után) – így látható, mely videók unalmasak/túl hosszúak.
- **Partial reward messaging:** A modal ne csak figyelmeztessen, hanem mutassa: "Eddig kerestél: X pont. Kihagyással ezt kapod, végignézéssel +Y bónusz várna."

### 5. Tájékoztató szöveg a videó alatt

**Megjelenés:**
```
📚 Edukációs videó | 3:45 hosszú
💰 Minden 30 másodpercért: +5 pont, +5 szavazat
🎁 Végignézésért: +10 bónusz pont, +10 bónusz szavazat
⏱️ Eddig: 1:30 → 15 pont, 15 szavazat jóváírva
```

**HTML struktúra:**
```html
<div class="education-info-bar" id="education-info-bar" style="display: none;">
    <div class="edu-info-title">📚 <span id="edu-video-title"></span></div>
    <div class="edu-info-rewards">
        💰 Minden <span id="edu-interval-sec">30</span> mp-ért: 
        +<span id="edu-pts-interval">5</span> pont, 
        +<span id="edu-votes-interval">5</span> szavazat
    </div>
    <div class="edu-info-bonus">
        🎁 Végignézésért: +<span id="edu-bonus-pts">10</span> bónusz pont
    </div>
    <div class="edu-info-progress">
        ⏱️ Eddig: <span id="edu-watched-time">0:00</span> → 
        <span id="edu-earned-pts">0</span> pont jóváírva
    </div>
</div>
```

### 6. Végén bónusz pont

**Logika:**
```php
// Ha a videó véget ért (watched_seconds >= duration - 5)
if ($watched_seconds >= $duration_seconds - 5) {
    $bonus_points = (int) get_post_meta($video_id, 'impactshop_edu_bonus_points', true);
    $bonus_votes = (int) get_post_meta($video_id, 'impactshop_edu_bonus_votes', true);
    // Jóváírás
}
```

#### Codex javaslatok (Bónusz logika)
- **Progresszív bónusz (opcionális):** nagy videóknál jelöljünk checkpointokat (pl. 50% / 75%), hogy ne veszítsen el mindent hálózati hiba esetén.
- **Bónusz csak egyszer:** session-ben `bonus_awarded` flag, hogy ne legyen ismételhető.

#### Sonnet javaslatok (Bónusz rendszer finomhangolás) 🎁
- **Tolerance sáv:** Ne szigorú `duration - 5s` legyen, hanem `duration * 0.95` (95%), hogy YouTube-nál a kis időzítési eltérések ne blokkoljanak.
- **Bonus multiplier:** Admin-ban beállítható "bónusz szorzó" (1x, 1.5x, 2x), hogy kiemelt videóknál nagyobb incentívát adhassunk végignézésre.
- **Animált notification:** A bónusz pont jóváírásakor ne csak toast notification, hanem rövid (1–2s) konfetti animáció a player felett, hogy ünnepélyesebb legyen.
- **Ellenőrzés backend-en:** `recordEducationIntervals()` ne csak a kliens `watched_seconds` alapján döntse el a bónuszt, hanem ellenőrizze: `session.intervals_awarded >= session.max_intervals - 1` (tehát majdnem végig volt).

---

## 🔄 Koherencia vizsgálat

### Kompatibilitás a meglévő kóddal:

| Meglévő elem | Változás szükséges? | Részletek |
|--------------|---------------------|-----------|
| `impactshop_ads_watch_get_education_videos()` | ✅ Módosítás | CPT-ből olvasson, ne filterből |
| `IMPACTSHOP_ADS_EDU_*` konstansok | ⚠️ Fallback | Videónkénti beállítás felülírja |
| `playEducationContent()` JS | ✅ Bővítés | Presence check + skip + info bar |
| `startEducationTimer()` JS | ✅ Bővítés | Presence check integráció |
| `maybeAwardEducationIntervals()` JS | Változatlan | Működik |
| `/education` REST endpoint | ✅ Bővítés | Bónusz pont támogatás |
| Session transient | ✅ Bővítés | Bónusz awarded flag |

### Backward compatibility:

- Filter (`impactshop_ads_watch_education_videos`) továbbra is működik fallbacként
- Ha nincs videónkénti beállítás, a globális konstansok érvényesek
- Régi session-ök működnek (bónusz nélkül)

### Codex javaslatok (Backward compatibility pontosítás)
- **Merge vs fallback:** ajánlott *merge* stratégia prioritással (CPT előbb), ezt a tervben rögzíteni.
- **Régi session migráció:** ha bevezetjük `bonus_awarded` flag-et, kezeljük a hiányzó értéket `false`-ként.

### Sonnet javaslatok (Backward compatibility implementáció) 🔄
**Konkrét merge stratégia:**
```php
function impactshop_ads_watch_get_education_videos(): array {
    // 1. CPT videók (prioritás)
    $cpt_videos = get_posts(['post_type' => 'impact_edu_video', 'post_status' => 'publish']);
    $videos = array_map('impactshop_normalize_edu_video_from_cpt', $cpt_videos);
    
    // 2. Filter videók (legacy támogatás)
    $filter_videos = apply_filters('impactshop_ads_watch_education_videos', []);
    
    // 3. Merge: filter videókat csak akkor adjuk hozzá, ha content_id még nincs CPT-ben
    $existing_ids = array_column($videos, 'id');
    foreach ($filter_videos as $fv) {
        if (!in_array($fv['id'], $existing_ids, true)) {
            $videos[] = $fv;
        }
    }
    
    return $videos;
}
```
- **Deprecation notice:** Ha filter videók vannak használatban, írjunk admin notice-t: "Legacy education videos detected. Migrate to CPT for full features."
- **Migration script:** Készítsünk WP-CLI parancsot (`wp impactshop migrate-edu-videos`), ami a filteres videókat CPT-vé konvertálja automatikusan.

---

## 📊 Adatbázis változások

### Új post meta kulcsok (`impact_edu_video`):

```
impactshop_edu_media_type        (youtube|mp4)
impactshop_edu_youtube_url       (string)
impactshop_edu_youtube_id        (string, kiszámolt)
impactshop_edu_video_url         (string)
impactshop_edu_duration          (int, seconds)
impactshop_edu_interval_seconds  (int, default 30)
impactshop_edu_points_per_interval (int, default 5)
impactshop_edu_votes_per_interval  (int, default 5)
impactshop_edu_bonus_points      (int, default 10)
impactshop_edu_bonus_votes       (int, default 10)
impactshop_edu_presence_interval (int, seconds, default 60)
impactshop_edu_presence_timeout  (int, seconds, default 30)
impactshop_edu_skip_enabled      (bool, default true)
impactshop_edu_user_limit        (int, default 0)
impactshop_edu_cooldown          (int, seconds)
impactshop_edu_start_at          (datetime-local)
impactshop_edu_end_at            (datetime-local)
impactshop_edu_cta_label         (string)
impactshop_edu_cta_url           (string)
```

---

## 🎨 UI/UX elemek

### Presence check overlay:

```css
.presence-check-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.95);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 20;
}

.presence-check-title {
    font-size: 24px;
    color: #fff;
    margin-bottom: 16px;
}

.btn-presence-confirm {
    padding: 16px 32px;
    background: #22c55e;
    color: #fff;
    border-radius: 999px;
    font-weight: 700;
    border: none;
    cursor: pointer;
}

.btn-presence-confirm:hover {
    background: #16a34a;
}

.presence-timeout-bar {
    width: 200px;
    height: 6px;
    background: rgba(255,255,255,0.3);
    margin-top: 16px;
    border-radius: 999px;
    overflow: hidden;
}

.presence-timeout-fill {
    height: 100%;
    background: #ef4444;
    transition: width 1s linear;
}
```

### Education info bar:

```css
.education-info-bar {
    background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
    border-radius: 12px;
    padding: 12px 16px;
    margin-top: 12px;
    color: #e2e8f0;
    font-size: 14px;
}

.edu-info-title {
    font-weight: 700;
    font-size: 16px;
    margin-bottom: 8px;
}

.edu-info-rewards,
.edu-info-bonus,
.edu-info-progress {
    margin: 4px 0;
}

.edu-info-progress {
    color: #22c55e;
    font-weight: 600;
}
```

### Skip button:

```css
.btn-skip-education {
    position: absolute;
    bottom: 60px;
    right: 16px;
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.9);
    color: #0f172a;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    z-index: 15;
    transition: opacity 0.3s;
}

.btn-skip-education:hover {
    background: #fff;
}
```

---

## 🔬 Sonnet Koherencia Vizsgálat Összefoglaló

### 1. Architektúra koherencia ✅
- **CPT név ütközés elkerülése:** `impact_edu_video` (20 char) nem ütközik `impact_sponsor_video`-val ✓
- **Meta kulcs prefix konzisztencia:** Mindkét CPT `impactshop_*` prefix-et használ, de edu és sponsor elkülönül ✓
- **REST endpoint névkonvenció:** `/impact/v1/education` illeszkedik a meglévő `/impact/v1/ads-watch/*` struktúrához ✓

### 2. State management koherencia ⚠️
**Potenciális ütközések:**
- A jelenlegi `state` objektum (JS) már tartalmaz education-specifikus mezőket (`educationSessionToken`, `educationPlaying`).
- **Kockázat:** Ha egyidejűleg fut IMA ad ÉS education videó (unified display váltáskor), a `state.isPlaying` és `state.adProgress` ütközhet.
- **Megoldás:** Education videóknál használjunk `state.educationProgress` külön mezőt, ne írjuk felül az `adProgress`-t.

### 3. Session kezelés inkonzisztencia 🔴
**Kritikus probléma:**
```php
// Jelenlegi session TTL: 30 perc
define('IMPACTSHOP_ADS_EDU_SESSION_TTL', 1800);

// Ha egy videó 25 perces:
// - 0:00 - session start
// - 25:00 - videó vége
// - 30:00 - session lejár
// ✅ OK

// Ha egy videó 40 perces:
// - 0:00 - session start
// - 30:00 - SESSION LEJÁR (közben nézi!)
// - 40:00 - videó vége → session token invalid → NEM kap bónuszt
// ❌ PROBLÉMA
```
**Javasolt fix:**
```php
function impactshop_ads_watch_create_education_session(string $pseudo_id, array $content): array {
    $token = wp_generate_uuid4();
    $max_intervals = (int) ($content['max_intervals'] ?? 0);
    $duration = (int) ($content['duration_seconds'] ?? 0);
    
    // Dinamikus TTL: videó hossza + 30 perc buffer
    $ttl = max(1800, $duration + 1800);
    
    $payload = [
        'pseudo_id' => $pseudo_id,
        'content_id' => (string) ($content['id'] ?? ''),
        'max_intervals' => $max_intervals,
        'intervals_awarded' => 0,
        'bonus_awarded' => false, // ÚJ
        'created_at' => time(),
    ];

    set_transient('impactshop_ads_edu_session_' . $token, $payload, $ttl);
    return ['token' => $token, 'max_intervals' => $max_intervals];
}
```

### 4. Időmérés pontossága (JavaScript) 🕐
**Probléma:** `setInterval(fn, 1000)` background tab-ban throttle-olt (~1 perc).
```javascript
// Jelenlegi
function startEducationTimer() {
    state.educationTimer = setInterval(function () {
        state.educationWatchedSeconds += 1; // ❌ pontatlan
        ...
    }, 1000);
}

// Javasolt
function startEducationTimer() {
    state.educationStartTime = Date.now();
    state.educationTimer = setInterval(function () {
        const elapsed = Math.floor((Date.now() - state.educationStartTime) / 1000);
        state.educationWatchedSeconds = elapsed; // ✅ pontos
        ...
    }, 1000);
}
```

### 5. REST API response konzisztencia ✅
- Sponsor videók: `{points, votes, total_views}`
- Education endpoint: `{points, votes, new_total, available_votes, total_intervals}`
- **Konzisztens:** mindkét endpoint `points` és `votes` property-t ad vissza ✓

### 6. CSS namespace ütközések ellenőrzése ✅
- Új class-ek: `.education-info-bar`, `.presence-check-overlay`, `.btn-skip-education`
- Meglévő class-ek: `.ads-watch-*` prefix
- **Nincs ütközés**, de javasolt `.edu-*` prefix konzisztensen használni a CSS-ben is.

### 7. Accessibility audit 🦾
**Hiányzó elemek:**
- [ ] ARIA label-ek presence overlay-en
- [ ] Keyboard navigation (Tab, Enter, Escape)
- [ ] Screen reader announcement videó szüneteléskor
- [ ] Focus trap presence overlay-en
- [ ] Skip link a videó elé (SR felhasználóknak)

---

## 📝 Érintett fájlok

| Fájl | Változás típusa | Részletek |
|------|-----------------|-----------|
| `impactshop-ads-watch.php` | Bővítés | CPT regisztráció, meta box, REST bővítés |
| `impactshop-ads-watch.js` | Bővítés | Presence check, skip, info bar, bónusz |
| `impactshop-ads-watch.css` | Bővítés | Presence overlay, info bar, skip button stílusok |

---

## 🚀 Implementációs lépések

### Fázis 1: CPT és Admin (P0)
1. [ ] `impact_edu_video` CPT regisztráció
2. [ ] Meta box létrehozása összes beállítással
3. [ ] `impactshop_ads_watch_get_education_videos()` módosítása CPT-ből olvasásra
4. [ ] Backward compatibility filter-rel

### Fázis 2: Frontend alapok (P1)
5. [ ] Education info bar HTML + CSS
6. [ ] Skip gomb HTML + CSS + JS
7. [ ] Info bar dinamikus frissítése lejátszás közben

### Fázis 3: Jelenlét ellenőrzés (P1)
8. [ ] Presence check overlay HTML + CSS
9. [ ] JS logika: timer, pause, overlay megjelenítés
10. [ ] Timeout kezelés, videó leállítás

### Fázis 4: Bónusz rendszer (P2)
11. [ ] REST endpoint bővítés bónusz támogatással
12. [ ] Session bővítés `bonus_awarded` flag-gel
13. [ ] Frontend notification bónuszról

### Fázis 5: Tesztelés és deploy
14. [ ] Staging teszt
15. [ ] Production deploy

---

## ✅ Elfogadási kritériumok

- [ ] Admin felületen létrehozható edukációs videó
- [ ] Videónként beállítható intervallum és pontok
- [ ] "Itt vagy még?" overlay megjelenik és működik
- [ ] Skip gomb működik, részleges pont jóváírás OK
- [ ] Tájékoztató szöveg megjelenik és frissül
- [ ] Bónusz pont jár videó végén
- [ ] Backward compatible a régi filter-alapú rendszerrel

---

## 🚨 Sonnet Priorizált Akciólista (Kritikus javítások)

### P0 - Implementáció előtt KÖTELEZŐ
1. **Session TTL dinamizálás** – hosszú videók ne vesszenek el
2. **Időmérés pontosítás** – `Date.now()` alapú delta számítás
3. **Backend pontvalidáció** – ne lehessen többet kérni, mint fizikailag lehetséges
4. **Merge stratégia rögzítése** – CPT + filter együttes működése

### P1 - MVP részei, de javítandók
5. **Skip confirmation modal** – bónusz elvesztés figyelmeztető
6. **Presence overlay accessibility** – ARIA + keyboard support
7. **Rate limiting** – `/education` endpoint védelem
8. **Admin preview** – videó előnézet a meta box-ban

### P2 - Nice-to-have, post-MVP
9. **Analytics logging** – skip események, presence fail-ek
10. **WP-CLI migration tool** – filter → CPT automatikus migráció
11. **Konfetti animáció** – bónusz pont jóváíráskor
12. **Progresszív bónusz** – 50%/75% checkpointok

---

## 📞 Kapcsolódó dokumentumok

- [Szponzor videó implementáció](../wp-content/mu-plugins/impactshop-ads-watch.php)
- [notes.md](../notes.md)

---

### Gemeni Javítási Javaslatok (UX & Biztonság) 💡
1. **Biztonsági validáció:**
   - A POST endpointnak ellenőriznie kellene, hogy a `watched_seconds` összhangban van-e a szerveroldali időbélyegekkel (session `created_at` vs. `current_time`), hogy ne lehessen script-tel felgyorsítani a pontszerzést.
   - Javaslat: `max_possible_points = (elapsed_time / interval) * points_per_interval` ellenőrzés a REST API-ban.

2. **UX optimalizálás:**
   - A "Még itt vagy?" overlay ne takarja ki az egész videót, csak az alsó sávot, vagy legyen áttetszőbb, hogy a felhasználó lássa, hol tartott.
   - Hangjelzés (finom pittyegés) a `presence check` megjelenésekor, ha a tab épp nincs fókuszban.

3. **Technikai stabilitás:**
   - Ha a böngésző tab inaktív lesz (background throttling), a `setInterval` pontatlan lehet.
   - Javaslat: `requestAnimationFrame` vagy `Date.now()` alapú delta időszámítás használata a `startEducationTimer`-ben a pontos időméréshez.
   
### Gemeni Kiegészítés (Koherencia) 🛠️
**Inkonzisztencia kockázat:**
- Jelenleg a `videos` filter (`impactshop_ads_watch_education_videos`) és a CPT videók keveredhetnek.
- **Megoldás:** A CPT videók legyenek az elsődlegesek, a filteres videókat csak akkor töltsük be, ha a CPT lekérdezés üres, VAGY fűzzük hozzá őket a listához (merge). A tervben a *Backward compatibility* szakaszt pontosítani kell, hogy "merge" vagy "fallback" stratégiát követünk-e. (Ajánlott: Merge, prioritással).

### Gemeni Kiegészítés (Bónusz logika) 🎲
- **User-élmény kockázat:** Ha a felhasználó egy 10 perces videónál az utolsó 10 másodpercben kap egy hálózati hibát vagy véletlenül bezárja, elveszíti a nagy bónuszt.
- **Javaslat:** A bónusz pontokat ne csak a "legvégén" (`duration - 5s`) adjuk, hanem legyen egy "progresszív bónusz" vagy checkpoint mentés (pl. 50%, 75%-nál is bejegyezzük, hogy meddig jutott), hogy újratöltésnél onnan folytathassa. Ez bonyolultabb, de biztonságosabb. Az MVP verzióban a jelenlegi "vége" megoldás elfogadható, de a UX kockázatot jelezni kell.
