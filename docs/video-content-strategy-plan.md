# Videó Content Stratégia - Részletes Implementációs Terv
## ImpactShop Ads Watch Rendszer Bővítése: Edukációs Videók + Auto-Banner + Click Tracking

**Készítette:** GPT 5.2  
**Dátum:** 2026. január 26.  
**Verzió:** 1.0  
**Kapcsolódik:** 
- `impactshop-ads-watch` v2.3.0 (meglévő videó rendszer)
- `docs/offerwall-integration-plan.md` (offerwall tab)
- Harvester rendszer (auto-banner forrás)

> **Összhang megjegyzés:** Ez a terv **nem módosítja** a meglévő `impactshop-ads-watch.php` fájlt, hanem **bővíti** a `/ads-watch/next` endpoint response-át és új frontend réteget ad hozzá. A szavazatok ugyanabba az `impactshop_ads_user_votes` táblába kerülnek, mint a videó és offerwall jutalmak.

---

## 📋 Executive Summary

Ez a dokumentum egy **kibővített videó lejátszási stratégiát** ír le, amely:

1. ✅ **Háromféle videótípust** kezel egyetlen playerben (reklám, szponzor, edukáció)
2. ✅ **YouTube edukációs videók** lejátszása 30 mp-es szakaszokra bontott jutalmazással
3. ✅ **Automatikus banner generálás** a Harvester affiliate ajánlataiból
4. ✅ **Videó utáni CTA linkek** típusonként eltérő céloldallal
5. ✅ **Kattintás tracking** pseudo_id szinten, profil építéshez

### Alapelvek:
- 🎯 **Egy ablak, egy player** – a user sosem hagyja el az oldalt
- 🎯 **Folyamatos engagement** – váltakozó tartalom, de konzisztens UX
- 🎯 **Fair jutalmazás** – csak a ténylegesen megnézett időért jár pont
- 🎯 **Mérhetőség** – minden interakció trackelhető

---

## 🎬 Videótípusok Definíciója

| Típus | Forrás | Hossz | Jutalom | CTA Link | Megszakítható |
|-------|--------|-------|---------|----------|---------------|
| **Reklám (ad)** | IMA SDK / VAST | 15-30 mp | 5 pont + 1 szavazat | Affiliate ajánlat | Nem (kötelező) |
| **Szponzor** | Saját CDN / MP4 | 30-60 mp | 10 pont + 2 szavazat | Szponzor által megadott | 5 mp után skip |
| **Edukáció** | YouTube embed | 2-10 perc | 5 pont + 5 szavazat / 30 mp | Opcionális weboldal | Bármikor |

---

## 🔄 Lejátszási Stratégia (Content Rotation)

### Algoritmus:

```
┌─────────────────────────────────────────────────────────────┐
│  Content Queue Logic                                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. User kattint "Videó megtekintése"                       │
│     ↓                                                        │
│  2. Backend dönt a következő tartalomról:                   │
│     │                                                        │
│     ├── 60% → Reklám (IMA VAST / Auto-banner)               │
│     ├── 25% → Szponzori videó (ha van aktív kampány)        │
│     └── 15% → Edukációs videó (YouTube)                     │
│     ↓                                                        │
│  3. Prioritás szabályok:                                     │
│     - Max 3 reklám egymás után                              │
│     - Edukáció után mindig reklám                           │
│     - Szponzor csak napi 1x per user                        │
│     - User preferencia tanulás (profil alapján)             │
│     ↓                                                        │
│  4. Lejátszás → CTA megjelenés → Kattintás tracking         │
│     ↓                                                        │
│  5. Következő tartalom (goto 2)                             │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Backend Endpoint Bővítés:

```php
// GET /wp-json/impact/v1/ads-watch/next
// Bővített response:

{
  "content_type": "education",  // 'ad' | 'sponsor' | 'education'
  "content_id": "edu_12345",
  
  // Típusfüggő mezők:
  "source": "youtube",          // 'ima' | 'cdn' | 'youtube'
  "youtube_id": "dQw4w9WgXcQ",  // csak education esetén
  "video_url": null,            // cdn esetén
  "vast_tag": null,             // ima esetén
  
  // Közös mezők:
  "duration_seconds": 180,
  "title": "Fenntartható divat alapjai",
  "description": "Ismerj meg 5 tippet...",
  
  // Jutalmazás:
  "reward_rules": {
    "type": "interval",           // 'fixed' | 'interval'
    "interval_seconds": 30,
    "points_per_interval": 5,
    "votes_per_interval": 5,
    "min_watch_percent": 0        // edukációnál 0 (bármikor megszakítható)
  },
  
  // CTA:
  "cta": {
    "enabled": true,
    "text": "Tudj meg többet",
    "url": "https://example.org/sustainable-fashion",
    "tracking_id": "cta_edu_12345_abc123"
  },
  
  // Dedupe:
  "session_token": "sess_xyz789"
}
```

---

## 📺 Egységes Player Architektúra

### Egy ablak, három mód:

```html
<div id="impactshop-video-player" class="video-player-container">
    
    <!-- Állapot indikátor -->
    <div class="player-header">
        <span class="content-badge" data-type="education">📚 Edukáció</span>
        <span class="progress-indicator">
            <span class="earned-points">+15</span> pont eddig
        </span>
    </div>
    
    <!-- Univerzális player wrapper -->
    <div class="player-viewport">
        
        <!-- Mód 1: IMA SDK hirdetések -->
        <div id="ad-container" class="player-layer" style="display:none;">
            <video id="ad-video" playsinline></video>
            <div id="ad-ui-container"></div>
        </div>
        
        <!-- Mód 2: HTML5 videó (szponzor / auto-banner) -->
        <div id="content-container" class="player-layer" style="display:none;">
            <video id="content-video" playsinline></video>
        </div>
        
        <!-- Mód 3: YouTube embed -->
        <div id="youtube-container" class="player-layer" style="display:none;">
            <div id="youtube-player"></div>
        </div>
        
        <!-- Közös overlay: progress + skip -->
        <div class="player-overlay">
            <div class="interval-progress">
                <div class="interval-bar" style="width: 0%"></div>
                <span class="interval-label">Következő +5 pont: 12 mp</span>
            </div>
            
            <button class="btn-skip" style="display:none;">
                Kihagyás <span class="skip-countdown">5</span>
            </button>
            
            <button class="btn-stop-education" style="display:none;">
                Befejezem (eddig szerzett pontok jóváírása)
            </button>
        </div>
    </div>
    
    <!-- Videó utáni CTA -->
    <div id="post-video-cta" class="cta-container" style="display:none;">
        <div class="cta-content">
            <h3 class="cta-title">Tetszett a videó?</h3>
            <a id="cta-link" class="cta-button" href="#" target="_blank">
                <span class="cta-text">Tudj meg többet</span>
                <span class="cta-arrow">→</span>
            </a>
            <button class="btn-next-video">Következő videó</button>
        </div>
        
        <!-- Reklám esetén: termékkártya -->
        <div class="product-card" style="display:none;">
            <img class="product-image" src="" alt="">
            <div class="product-info">
                <h4 class="product-name"></h4>
                <div class="product-price">
                    <span class="price-old"></span>
                    <span class="price-new"></span>
                    <span class="discount-badge"></span>
                </div>
            </div>
        </div>
    </div>
    
</div>
```

### CSS – Videótípus badge-ek:

```css
.content-badge {
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.content-badge[data-type="ad"] {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.content-badge[data-type="sponsor"] {
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: white;
}

.content-badge[data-type="education"] {
    background: linear-gradient(135deg, #4ECDC4, #44B09E);
    color: white;
}

/* Interval progress bar */
.interval-progress {
    position: absolute;
    bottom: 60px;
    left: 16px;
    right: 16px;
    height: 8px;
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
    overflow: hidden;
}

.interval-bar {
    height: 100%;
    background: linear-gradient(90deg, #FFE66D, #51CF66);
    transition: width 0.1s linear;
}

.interval-label {
    position: absolute;
    top: -24px;
    left: 0;
    font-size: 0.75rem;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.5);
}
```

---

## 📚 YouTube Edukációs Videók – Részletes Flow

### 1. YouTube IFrame API integráció:

```javascript
// YouTube API betöltése
function loadYouTubeAPI() {
    if (window.YT) return Promise.resolve();
    
    return new Promise((resolve) => {
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(tag);
        window.onYouTubeIframeAPIReady = resolve;
    });
}

// Player inicializálás
let ytPlayer = null;

async function initYouTubePlayer(videoId) {
    await loadYouTubeAPI();
    
    ytPlayer = new YT.Player('youtube-player', {
        videoId: videoId,
        playerVars: {
            autoplay: 1,
            controls: 1,           // User kontrolálhatja
            modestbranding: 1,     // Minimális YouTube branding
            rel: 0,                // Kapcsolódó videók kikapcsolva
            fs: 0,                 // Fullscreen tiltva (maradjon az oldalon)
            disablekb: 0,          // Billentyűzet engedélyezve
            playsinline: 1         // iOS inline lejátszás
        },
        events: {
            onReady: onYouTubeReady,
            onStateChange: onYouTubeStateChange
        }
    });
}
```

### 2. Intervallum-alapú jutalmazás:

```javascript
const educationState = {
    videoId: null,
    totalDuration: 0,
    watchedSeconds: 0,
    lastRewardedSecond: 0,
    intervalSeconds: 30,
    pointsPerInterval: 5,
    votesPerInterval: 5,
    earnedPoints: 0,
    earnedVotes: 0,
    trackingTimer: null
};

function startEducationTracking() {
    // 1 másodpercenként ellenőrzés
    educationState.trackingTimer = setInterval(() => {
        if (!ytPlayer || ytPlayer.getPlayerState() !== YT.PlayerState.PLAYING) {
            return;
        }
        
        const currentTime = Math.floor(ytPlayer.getCurrentTime());
        educationState.watchedSeconds = currentTime;
        
        // Intervallum progress frissítése
        const secondsInCurrentInterval = currentTime % educationState.intervalSeconds;
        const progress = (secondsInCurrentInterval / educationState.intervalSeconds) * 100;
        updateIntervalProgress(progress, educationState.intervalSeconds - secondsInCurrentInterval);
        
        // Ellenőrzés: elértünk-e új intervallumot?
        const completedIntervals = Math.floor(currentTime / educationState.intervalSeconds);
        const rewardedIntervals = Math.floor(educationState.lastRewardedSecond / educationState.intervalSeconds);
        
        if (completedIntervals > rewardedIntervals) {
            // Új intervallum elérve → jutalom!
            awardIntervalReward(completedIntervals);
        }
        
    }, 1000);
}

function awardIntervalReward(intervalCount) {
    const newPoints = educationState.pointsPerInterval;
    const newVotes = educationState.votesPerInterval;
    
    educationState.earnedPoints += newPoints;
    educationState.earnedVotes += newVotes;
    educationState.lastRewardedSecond = intervalCount * educationState.intervalSeconds;
    
    // Visual feedback
    showIntervalRewardToast(newPoints, newVotes);
    updateEarnedDisplay(educationState.earnedPoints);
    
    // Backend-nek küldés (debounced, nem minden intervallumnál)
    debouncedSendProgress();
}

function updateIntervalProgress(percent, secondsRemaining) {
    document.querySelector('.interval-bar').style.width = percent + '%';
    document.querySelector('.interval-label').textContent = 
        `Következő +${educationState.pointsPerInterval} pont: ${secondsRemaining} mp`;
}
```

### 3. Megszakítás kezelés:

```javascript
// User rákattint "Befejezem" gombra
function stopEducationVideo() {
    clearInterval(educationState.trackingTimer);
    
    if (ytPlayer) {
        ytPlayer.pauseVideo();
    }
    
    // Összesített jutalom elküldése
    finalizeEducationReward();
}

// YouTube státusz változás (pause, end, stb.)
function onYouTubeStateChange(event) {
    switch (event.data) {
        case YT.PlayerState.ENDED:
            // Videó vége → utolsó (részleges) intervallum is jár
            handleEducationComplete();
            break;
            
        case YT.PlayerState.PAUSED:
            // Pause → tracking szünetel (de nem vész el a progress)
            // Nem küldünk semmit, várjuk a folytatást vagy befejezést
            break;
    }
}

function handleEducationComplete() {
    clearInterval(educationState.trackingTimer);
    
    // Utolsó részleges intervallum is számít
    const remainingSeconds = educationState.watchedSeconds % educationState.intervalSeconds;
    if (remainingSeconds > 0) {
        // Arányos jutalom az utolsó részért
        const partialPoints = Math.floor(
            (remainingSeconds / educationState.intervalSeconds) * educationState.pointsPerInterval
        );
        const partialVotes = Math.floor(
            (remainingSeconds / educationState.intervalSeconds) * educationState.votesPerInterval
        );
        
        if (partialPoints > 0 || partialVotes > 0) {
            educationState.earnedPoints += partialPoints;
            educationState.earnedVotes += partialVotes;
        }
    }
    
    finalizeEducationReward();
}

async function finalizeEducationReward() {
    // Backend-nek küldés
    const response = await fetch('/wp-json/impact/v1/ads-watch/education-complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            pseudo_id: getPseudoId(),
            content_id: educationState.videoId,
            watched_seconds: educationState.watchedSeconds,
            total_duration: educationState.totalDuration,
            points_earned: educationState.earnedPoints,
            votes_earned: educationState.earnedVotes,
            session_token: currentSessionToken
        })
    });
    
    if (response.ok) {
        // CTA megjelenítése
        showPostVideoCTA();
    }
}
```

---

## 🏷️ Harvester Auto-Banner Generálás

### Adatfolyam:

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Harvester     │────►│  Banner Queue   │────►│  Video Player   │
│   (affiliate    │     │  (DB tábla)     │     │  (auto-banner   │
│   offers)       │     │                 │     │   lejátszás)    │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

### Új adatbázis tábla:

```sql
CREATE TABLE wp_impactshop_auto_banners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Forrás
    source_type VARCHAR(32) NOT NULL,           -- 'harvester', 'manual'
    source_offer_id VARCHAR(128),               -- Harvester offer ID
    shop_slug VARCHAR(64),                      -- Kapcsolódó shop
    
    -- Banner tartalom
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_url VARCHAR(500),                     -- Termék kép
    
    -- Árazás
    price_original DECIMAL(10,2),
    price_discounted DECIMAL(10,2),
    discount_percent INT,
    currency CHAR(3) DEFAULT 'HUF',
    
    -- CTA
    cta_url VARCHAR(500) NOT NULL,              -- Affiliate link
    cta_text VARCHAR(64) DEFAULT 'Megnézem',
    
    -- Státusz
    status VARCHAR(32) DEFAULT 'pending',       -- 'pending', 'active', 'expired', 'rejected'
    priority INT DEFAULT 50,                    -- 0-100, magasabb = gyakrabban jelenik meg
    
    -- Időzítés
    valid_from DATETIME,
    valid_until DATETIME,
    
    -- Tracking
    impressions INT UNSIGNED DEFAULT 0,
    clicks INT UNSIGNED DEFAULT 0,
    
    -- Meta
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    KEY status_priority (status, priority),
    KEY shop_slug (shop_slug),
    KEY valid_dates (valid_from, valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Harvester → Banner konverzió:

```php
/**
 * Harvester offer-ből auto-banner generálása
 */
function impactshop_create_auto_banner_from_offer(array $offer): int|false {
    global $wpdb;
    
    // Validáció
    if (empty($offer['title']) || empty($offer['url']) || empty($offer['image'])) {
        return false;
    }
    
    // Ár feldolgozás
    $price_original = floatval($offer['price_original'] ?? 0);
    $price_discounted = floatval($offer['price'] ?? $price_original);
    $discount_percent = 0;
    
    if ($price_original > 0 && $price_discounted < $price_original) {
        $discount_percent = round((1 - $price_discounted / $price_original) * 100);
    }
    
    // Banner létrehozás
    $result = $wpdb->insert(
        $wpdb->prefix . 'impactshop_auto_banners',
        [
            'source_type'       => 'harvester',
            'source_offer_id'   => $offer['id'] ?? null,
            'shop_slug'         => $offer['shop_slug'] ?? null,
            'title'             => sanitize_text_field($offer['title']),
            'description'       => sanitize_textarea_field($offer['description'] ?? ''),
            'image_url'         => esc_url_raw($offer['image']),
            'price_original'    => $price_original,
            'price_discounted'  => $price_discounted,
            'discount_percent'  => $discount_percent,
            'currency'          => 'HUF',
            'cta_url'           => esc_url_raw($offer['url']),
            'status'            => 'active',  // vagy 'pending' ha moderálás kell
            'priority'          => impactshop_calculate_banner_priority($offer),
            'valid_until'       => $offer['valid_until'] ?? null,
            'created_at'        => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
    );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Banner prioritás számítás (kedvezmény + frissesség + shop relevancia)
 */
function impactshop_calculate_banner_priority(array $offer): int {
    $priority = 50; // Alap
    
    // Kedvezmény bónusz (max +30)
    $discount = intval($offer['discount_percent'] ?? 0);
    $priority += min(30, $discount);
    
    // Frissesség bónusz (24 órán belüli = +10)
    if (!empty($offer['created_at'])) {
        $age_hours = (time() - strtotime($offer['created_at'])) / 3600;
        if ($age_hours < 24) {
            $priority += 10;
        }
    }
    
    // Shop relevancia (ha a user korábban kattintott erre a shopra)
    // Ez majd a profil alapján dinamikusan számolódik
    
    return min(100, max(0, $priority));
}
```

### Auto-banner megjelenítés (animált):

```javascript
function playAutoBanner(bannerData) {
    showPlayerLayer('content-container');
    
    // Banner HTML generálás
    const bannerHtml = `
        <div class="auto-banner animated">
            <div class="banner-background" style="background-image: url('${bannerData.image_url}')"></div>
            
            <div class="banner-content">
                <div class="shop-logo">
                    <img src="/wp-content/uploads/shops/${bannerData.shop_slug}-logo.png" alt="">
                </div>
                
                <h2 class="banner-title">${bannerData.title}</h2>
                
                <div class="price-block">
                    ${bannerData.price_original > bannerData.price_discounted ? 
                        `<span class="price-old">${formatPrice(bannerData.price_original)}</span>` : ''}
                    <span class="price-new">${formatPrice(bannerData.price_discounted)}</span>
                    ${bannerData.discount_percent > 0 ? 
                        `<span class="discount-badge">-${bannerData.discount_percent}%</span>` : ''}
                </div>
                
                <div class="banner-cta">
                    <span class="cta-text">${bannerData.cta_text || 'Megnézem'}</span>
                    <span class="cta-icon">→</span>
                </div>
            </div>
            
            <!-- Progress bar (15 mp) -->
            <div class="banner-progress">
                <div class="banner-progress-bar"></div>
            </div>
        </div>
    `;
    
    document.getElementById('content-container').innerHTML = bannerHtml;
    
    // Animáció indítása
    startBannerAnimation(bannerData);
}

function startBannerAnimation(bannerData) {
    const duration = 15000; // 15 másodperc
    const progressBar = document.querySelector('.banner-progress-bar');
    
    // CSS animáció a progress bar-hoz
    progressBar.style.animation = `bannerProgress ${duration}ms linear`;
    
    // Vége után CTA megjelenítés
    setTimeout(() => {
        showPostVideoCTA({
            type: 'ad',
            cta_url: bannerData.cta_url,
            product: bannerData
        });
    }, duration);
}
```

### Auto-banner CSS:

```css
.auto-banner {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.banner-background {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    filter: blur(20px) brightness(0.4);
    transform: scale(1.1);
}

.banner-content {
    position: relative;
    z-index: 1;
    text-align: center;
    color: white;
    padding: 2rem;
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.banner-title {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 1rem;
    text-shadow: 0 2px 20px rgba(0,0,0,0.5);
    animation: fadeInUp 0.6s ease-out 0.2s both;
}

.price-block {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    animation: fadeInUp 0.6s ease-out 0.4s both;
}

.price-old {
    font-size: 1.25rem;
    text-decoration: line-through;
    opacity: 0.7;
}

.price-new {
    font-size: 2.5rem;
    font-weight: 800;
    color: #51CF66;
}

.discount-badge {
    background: #FF6B6B;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-weight: 700;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.banner-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: rgba(255,255,255,0.2);
}

.banner-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #4ECDC4, #51CF66);
    width: 0%;
}

@keyframes bannerProgress {
    from { width: 0%; }
    to { width: 100%; }
}
```

---

## 🔗 CTA és Click Tracking

### CTA típusok videótípusonként:

| Videótípus | CTA szöveg | Cél URL | Tracking esemény |
|------------|------------|---------|------------------|
| **Reklám** | "Megnézem az ajánlatot" | Affiliate link | `ad_cta_click` |
| **Szponzor** | Szponzor által megadott | Szponzor link | `sponsor_cta_click` |
| **Edukáció** | "Tudj meg többet" (ha van) | Opcionális weboldal | `education_cta_click` |
| **Edukáció** | *(nincs CTA)* | - | - |

### Click Tracking implementáció:

```javascript
function handleCTAClick(event) {
    event.preventDefault();
    
    const ctaData = {
        pseudo_id: getPseudoId(),
        content_type: currentContent.type,          // 'ad', 'sponsor', 'education'
        content_id: currentContent.id,
        cta_url: currentContent.cta.url,
        tracking_id: currentContent.cta.tracking_id,
        timestamp: Date.now(),
        session_id: getSessionId(),
        
        // Profil építéshez:
        shop_slug: currentContent.shop_slug || null,
        category: currentContent.category || null,
        price_range: currentContent.price_range || null
    };
    
    // Beacon API (megbízható, oldal elhagyás előtt is elküldi)
    navigator.sendBeacon(
        '/wp-json/impact/v1/tracking/cta-click',
        JSON.stringify(ctaData)
    );
    
    // GA4 event
    gtag('event', 'cta_click', {
        content_type: ctaData.content_type,
        content_id: ctaData.content_id,
        shop_slug: ctaData.shop_slug
    });
    
    // Eredeti link megnyitása új tabban
    window.open(ctaData.cta_url, '_blank', 'noopener');
}
```

### Backend tracking endpoint:

```php
// POST /wp-json/impact/v1/tracking/cta-click
register_rest_route('impact/v1', '/tracking/cta-click', [
    'methods'  => 'POST',
    'callback' => 'impactshop_handle_cta_click',
    'permission_callback' => '__return_true'
]);

function impactshop_handle_cta_click(WP_REST_Request $request): WP_REST_Response {
    global $wpdb;
    
    $data = $request->get_json_params();
    
    // Validáció
    $pseudo_id = sanitize_text_field($data['pseudo_id'] ?? '');
    if (empty($pseudo_id)) {
        return new WP_REST_Response(['error' => 'missing_pseudo_id'], 400);
    }
    
    // Click rekord mentése
    $wpdb->insert(
        $wpdb->prefix . 'impactshop_click_tracking',
        [
            'pseudo_id'     => $pseudo_id,
            'content_type'  => sanitize_text_field($data['content_type'] ?? ''),
            'content_id'    => sanitize_text_field($data['content_id'] ?? ''),
            'cta_url'       => esc_url_raw($data['cta_url'] ?? ''),
            'tracking_id'   => sanitize_text_field($data['tracking_id'] ?? ''),
            'shop_slug'     => sanitize_text_field($data['shop_slug'] ?? ''),
            'category'      => sanitize_text_field($data['category'] ?? ''),
            'user_agent'    => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'ip_address'    => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'created_at'    => current_time('mysql')
        ]
    );
    
    // Banner impressions/clicks frissítése (ha auto-banner)
    if (!empty($data['content_type']) && $data['content_type'] === 'ad') {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}impactshop_auto_banners 
             SET clicks = clicks + 1 
             WHERE id = %d",
            intval($data['content_id'])
        ));
    }
    
    // User profil frissítése (shop preferencia)
    if (!empty($data['shop_slug'])) {
        impactshop_update_user_preference($pseudo_id, 'shop_click', $data['shop_slug']);
    }
    
    return new WP_REST_Response(['success' => true], 200);
}
```

### Click tracking tábla:

```sql
CREATE TABLE wp_impactshop_click_tracking (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pseudo_id VARCHAR(32) NOT NULL,
    content_type VARCHAR(32) NOT NULL,          -- 'ad', 'sponsor', 'education'
    content_id VARCHAR(128),
    cta_url VARCHAR(500),
    tracking_id VARCHAR(128),
    shop_slug VARCHAR(64),
    category VARCHAR(128),
    user_agent TEXT,
    ip_address VARCHAR(64),
    created_at DATETIME NOT NULL,
    
    KEY pseudo_id (pseudo_id),
    KEY content_type (content_type),
    KEY shop_slug (shop_slug),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 👤 User Profil Építés

### Profil adatok:

```php
/**
 * User preferencia frissítése kattintás/megtekintés alapján
 */
function impactshop_update_user_preference(
    string $pseudo_id, 
    string $action_type, 
    string $value
): void {
    $preferences = get_transient('user_prefs_' . $pseudo_id) ?: [
        'shop_clicks' => [],      // shop_slug => count
        'shop_views' => [],
        'categories' => [],       // category => count
        'price_ranges' => [],     // 'budget', 'mid', 'premium' => count
        'preferred_content' => [] // 'ad', 'sponsor', 'education' => engagement score
    ];
    
    switch ($action_type) {
        case 'shop_click':
            $preferences['shop_clicks'][$value] = ($preferences['shop_clicks'][$value] ?? 0) + 1;
            break;
        case 'shop_view':
            $preferences['shop_views'][$value] = ($preferences['shop_views'][$value] ?? 0) + 1;
            break;
        case 'category':
            $preferences['categories'][$value] = ($preferences['categories'][$value] ?? 0) + 1;
            break;
    }
    
    // 30 napos TTL
    set_transient('user_prefs_' . $pseudo_id, $preferences, 30 * DAY_IN_SECONDS);
    
    // Hosszú távú tárolás is (ha szükséges)
    impactshop_persist_user_preferences($pseudo_id, $preferences);
}

/**
 * Következő tartalom kiválasztása profil alapján
 */
function impactshop_get_personalized_content(string $pseudo_id): array {
    $preferences = get_transient('user_prefs_' . $pseudo_id) ?: [];
    
    // Leggyakrabban kattintott shopok
    $preferred_shops = array_keys(
        array_slice(
            arsort($preferences['shop_clicks'] ?? []) ?: [],
            0, 5
        )
    );
    
    // Prioritás boost a kedvenc shopok bannerjeinek
    // ...
    
    return $content;
}
```

---

## 📊 Adatbázis Séma Összefoglaló

### Új táblák:

1. **`wp_impactshop_auto_banners`** – Harvester-ből generált bannerek
2. **`wp_impactshop_click_tracking`** – CTA kattintások
3. **`wp_impactshop_education_views`** – Edukációs videó megtekintések (intervallumokkal)
4. **`wp_impactshop_content_queue`** – Lejátszási sor (user-enként)

### Education views tábla:

```sql
CREATE TABLE wp_impactshop_education_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pseudo_id VARCHAR(32) NOT NULL,
    youtube_id VARCHAR(16) NOT NULL,
    content_id VARCHAR(128),
    
    -- Időtartam
    total_duration_seconds INT UNSIGNED,
    watched_seconds INT UNSIGNED NOT NULL,
    completed_intervals INT UNSIGNED DEFAULT 0,
    
    -- Jutalom
    points_earned INT UNSIGNED DEFAULT 0,
    votes_earned INT UNSIGNED DEFAULT 0,
    
    -- Státusz
    completion_status VARCHAR(32) DEFAULT 'partial', -- 'partial', 'complete', 'skipped'
    
    -- Dedupe
    session_token VARCHAR(64) NOT NULL,
    dedupe_key VARCHAR(191) NOT NULL UNIQUE,
    
    created_at DATETIME NOT NULL,
    
    KEY pseudo_id (pseudo_id),
    KEY youtube_id (youtube_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 🚀 Implementációs Roadmap

### Phase 1: Unified Player (2-3 nap)
- [ ] Player HTML/CSS refaktor (3 mód)
- [ ] Layer switching logika
- [ ] Content badge UI
- [ ] Progress overlay

### Phase 2: YouTube Integration (2 nap)
- [ ] YouTube IFrame API integráció
- [ ] Intervallum tracking
- [ ] Részleges jutalom számítás
- [ ] "Befejezem" gomb + partial reward

### Phase 3: Auto-Banner System (2-3 nap)
- [ ] DB tábla + migration
- [ ] Harvester → Banner konverzió hook
- [ ] Banner lejátszó animáció
- [ ] Priority queue

### Phase 4: CTA & Click Tracking (1-2 nap)
- [ ] Post-video CTA UI
- [ ] Beacon-based tracking
- [ ] Click tracking endpoint
- [ ] Profil preferencia frissítés

### Phase 5: Content Rotation (1-2 nap)
- [ ] Backend queue logika
- [ ] Rotation algoritmus
- [ ] User preference boosting
- [ ] A/B test framework

### Phase 6: Admin & Analytics (2 nap)
- [ ] Edukációs videó kezelő UI
- [ ] Auto-banner moderáció
- [ ] Click analytics dashboard
- [ ] Profil insights

**Összesen: ~10-14 munkanap**

---

## ✅ Acceptance Criteria

### Funkcionális:
- [ ] Három videótípus zökkenőmentesen váltakozik
- [ ] YouTube videó 30 mp-enként jutalmaz
- [ ] Megszakítás esetén részleges jutalom jóváíródik
- [ ] Harvester ajánlatok automatikusan bannerré válnak
- [ ] Minden CTA kattintás trackelhető pseudo_id szinten
- [ ] User profil épül a kattintásokból

### UX:
- [ ] User sosem hagyja el az oldalt videó nézés közben
- [ ] Egyértelmű vizuális feedback (badge, progress, pont counter)
- [ ] CTA gomb minden videó után (típusfüggő)
- [ ] Mobile-first, touch-optimized

### Teljesítmény:
- [ ] YouTube API lazy load
- [ ] Click tracking beacon (nem blokkoló)
- [ ] Banner queue pre-fetch

---

**Következő lépés:** Jóváhagyás után a Phase 1 (Unified Player) implementációja kezdődhet!
