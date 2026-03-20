# ImpactShop Badge Rendszer – Tervezési Dokumentum

> **Verzió**: 2.0  
> **Dátum**: 2026-01-26  
> **Státusz**: TERVEZÉS  
> **Kapcsolódó**: [CODEX-IMPLEMENTATION-TASK.md](./CODEX-IMPLEMENTATION-TASK.md), [offerwall-integration-plan.md](./offerwall-integration-plan.md), [video-content-strategy-plan.md](./video-content-strategy-plan.md)

---

## 1. Célkitűzés

Egységes, forrás-agnosztikus badge (jelvény) rendszer, amely:
- **Perzisztált**: DB táblában tárolt, nem csak session-based
- **Többszintű**: Bronze → Silver → Gold → Platinum szintek
- **Kategorizált**: Aktivitás, Támogatás, Tanulás, Offerwall, Speciális
- **ID panelen megjelenő**: A felhasználó látja megszerzett badge-eit
- **Gamification-központú**: Motiválja a visszatérő aktivitást
- **Örökérvényű**: Megszerzett badge soha nem vész el (nem degradálódik)
- **HeroWall**: Örök dicsőségtábla a badge-ek alapján

---

## 1.1 Badge vs Level Rendszer – Különbségek

| Tulajdonság | Level (Sharity_Level_Manager) | Badge (Gamification) |
|-------------|-------------------------------|----------------------|
| **Megszerzés** | Automatikus, pont alapú | Tevékenység által kiérdemelt |
| **Változás** | Degradálódhat (percentilis) | **Örök** – egyszer megszerezve marad |
| **Előny** | Anyagi (multiplier, szavazat súly, kedvezmény) | **Elismerés** (nem anyagi) |
| **Verseny** | Nem igazi (sok azonos pont) | **Igazi** (badge érték + szám) |
| **Cél** | Hűség jutalmazása | Mérföldkövek elismerése |

**Fontos**: A badge rendszer **NEM ad anyagi előnyt** (szorzó, szavazat, kedvezmény) – ezek a Level System-ből jönnek. A badge tisztán **elismerés és verseny** célú.

---

## 2. Badge Kategóriák és Típusok

### 2.1 🏆 Aktivitás Kategória

| Badge Key | Név | Leírás | Szintek |
|-----------|-----|--------|---------|
| `streak_3` | Hármas Streak | 3 egymást követő nap | Bronze |
| `streak_7` | Heti Streak | 7 egymást követő nap | Silver |
| `streak_30` | Havi Streak | 30 egymást követő nap | Gold |
| `streak_100` | Száznapos | 100 egymást követő nap | Platinum |
| `views_10` | Video Marathon | 10 videó megtekintése | Bronze |
| `views_50` | Video Guru | 50 videó megtekintése | Silver |
| `views_100` | Video Master | 100 videó megtekintése | Gold |
| `daily_active` | Napi Aktív | 7 különböző napon visszatér | Bronze |

### 2.2 💚 Támogatás Kategória

| Badge Key | Név | Leírás | Szintek |
|-----------|-----|--------|---------|
| `first_vote` | Első Szavazat | Első szavazat leadása | Bronze |
| `votes_100` | Top Supporter | 100 szavazat | Silver |
| `votes_500` | Super Supporter | 500 szavazat | Gold |
| `votes_1000` | Legend Supporter | 1000 szavazat | Platinum |
| `ngo_loyal` | NGO Hűség | Ugyanazt az NGO-t 10x támogatta | Silver |
| `multi_ngo` | Sokszínű Támogató | 5 különböző NGO-t támogatott | Silver |

### 2.3 🎓 Tanulás Kategória (Video Content Strategy)

| Badge Key | Név | Leírás | Szintek |
|-----------|-----|--------|---------|
| `first_edu_video` | Első Tanulás | Első edukációs videó befejezése | Bronze |
| `edu_complete_5` | Tanuló | 5 edukációs videó befejezése | Silver |
| `edu_complete_20` | Tudáskereső | 20 edukációs videó befejezése | Gold |
| `quiz_master` | Kvíz Mester | 10 kvíz helyes megválaszolása | Gold |

### 2.4 🎁 Offerwall Kategória

| Badge Key | Név | Leírás | Szintek |
|-----------|-----|--------|---------|
| `first_offer` | Első Ajánlat | Első offerwall ajánlat teljesítése | Bronze |
| `offers_5` | Ajánlatvadász | 5 ajánlat teljesítése | Silver |
| `offers_20` | Offerwall Pro | 20 ajánlat teljesítése | Gold |
| `high_value_offer` | Nagyértékű | $5+ értékű ajánlat teljesítése | Gold |

### 2.5 ⭐ Speciális Kategória

| Badge Key | Név | Leírás | Szintek |
|-----------|-----|--------|---------|
| `early_adopter` | Korai Felhasználó | A rendszer első 1000 felhasználója | Platinum |
| `seasonal_xmas` | Karácsonyi | Karácsonyi kampányban részvétel | Gold |
| `referral_1` | Ajánló | Első sikeres meghívó | Silver |
| `anniversary_1` | Évfordulós | 1 éves regisztráció | Gold |

---

## 3. Szint (Tier) Rendszer

### 3.1 Szintek

| Tier | Szín | Ikon | Leírás | Badge Pont Súly |
|------|------|------|--------|-----------------|
| `bronze` | #CD7F32 | 🥉 | Kezdő szint | 1 |
| `silver` | #C0C0C0 | 🥈 | Haladó szint | 2 |
| `gold` | #FFD700 | 🥇 | Magas szint | 4 |
| `platinum` | #E5E4E2 | 💎 | Legmagasabb szint | 8 |

### 3.2 Badge Pont Számítás

A felhasználó **badge pontja** a megszerzett badge-ek súlyozott összege:

```php
function impact_calculate_badge_points(string $pseudo_id): int {
    $badges = impact_get_user_badges($pseudo_id);
    $tier_weights = [
        'bronze'   => 1,
        'silver'   => 2,
        'gold'     => 4,
        'platinum' => 8,
    ];
    
    $total = 0;
    foreach ($badges as $badge) {
        $total += $tier_weights[$badge['tier']] ?? 1;
    }
    return $total;
}
```

**Példa**: 5 bronze (5×1) + 3 silver (3×2) + 1 gold (1×4) = 5 + 6 + 4 = **15 badge pont**

---

## 3.3 HeroWall – Örök Dicsőségtábla

A HeroWall az ImpactShop **örök elismerési rendszere**, ahol a felhasználók badge pontjaik alapján kapnak helyet.

### HeroWall Szintek

| Tier | Badge Pont | Vizuális |
|------|------------|----------|
| 🏆 **Legend** | 100+ | Animált arany keret, csillagok |
| 💎 **Platinum** | 50-99 | Platina keret, fény effekt |
| 🥇 **Gold** | 25-49 | Arany keret, halvány ragyogás |
| 🥈 **Silver** | 10-24 | Ezüst keret |
| 🥉 **Bronze** | 1-9 | Bronz keret |

### HeroWall Szabályok

1. **Örökérvényű pozíció**: Ha elérsz egy szintet, ott maradsz (nem degradálódsz)
2. **Sorrend**: Badge pont (azonosnál: korábbi elérés győz)
3. **Megjelenítés**: Becenév vagy pseudo_id (anonim, de felismerhető)
4. **Legacy üzenet**: Platinum+ szinten opcionális 280 karakteres üzenet hagyható

### HeroWall UI Koncepció

```
┌─────────────────────────────────────────────────────────────┐
│                      🏆 HEROWALL 🏆                         │
│            Az ImpactShop Hőseinek Örök Emlékműve            │
├─────────────────────────────────────────────────────────────┤
│  🏆 LEGEND HEROES (100+ badge pont)                         │
│  ├─ ✨ "ImpactHero_7x3f" – 127 pont – 23 badge             │
│  │     📝 "Minden nap számít. Büszke vagyok rá!"           │
│  ├─ ✨ "Támogató42" – 118 pont – 21 badge                  │
│  └─ ...                                                     │
│                                                             │
│  💎 PLATINUM CHAMPIONS (50-99 badge pont)                   │
│  ├─ "Anon_9k2m" – 87 pont – 15 badge                       │
│  └─ ...                                                     │
│                                                             │
│  🥇 GOLD SUPPORTERS (25-49 badge pont)                     │
│  └─ ...                                                     │
│                                                             │
│  🥈 SILVER MEMBERS (10-24 badge pont)                      │
│  └─ ...                                                     │
│                                                             │
│  🥉 BRONZE STARTERS (1-9 badge pont)                       │
│  └─ (Csak top 50 megjelenítve)                             │
└─────────────────────────────────────────────────────────────┘
```

### HeroWall Tábla (DB)

```sql
CREATE TABLE IF NOT EXISTS {$wpdb->prefix}impact_herowall (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pseudo_id VARCHAR(32) NOT NULL,
    nickname VARCHAR(32) NULL,
    badge_points INT NOT NULL DEFAULT 0,
    badge_count INT NOT NULL DEFAULT 0,
    herowall_tier ENUM('bronze', 'silver', 'gold', 'platinum', 'legend') NOT NULL,
    tier_achieved_at DATETIME NOT NULL,
    legacy_message VARCHAR(280) NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pseudo (pseudo_id),
    KEY idx_tier_points (herowall_tier, badge_points DESC),
    KEY idx_achieved (tier_achieved_at)
) {$charset};
```

### HeroWall Frissítés Logika

```php
/**
 * HeroWall pozíció frissítése badge megszerzés után.
 * A tier csak FELFELÉ változhat (örökérvényű).
 */
function impact_update_herowall(string $pseudo_id): void {
    global $wpdb;
    $table = $wpdb->prefix . 'impact_herowall';
    
    $badge_points = impact_calculate_badge_points($pseudo_id);
    $badge_count = count(impact_get_user_badges($pseudo_id));
    $new_tier = impact_get_herowall_tier($badge_points);
    
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT herowall_tier, tier_achieved_at FROM {$table} WHERE pseudo_id = %s",
        $pseudo_id
    ));
    
    $tier_order = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4, 'legend' => 5];
    
    if (!$existing) {
        // Új bejegyzés
        $wpdb->insert($table, [
            'pseudo_id' => $pseudo_id,
            'badge_points' => $badge_points,
            'badge_count' => $badge_count,
            'herowall_tier' => $new_tier,
            'tier_achieved_at' => current_time('mysql'),
        ]);
    } else {
        // Csak frissítés, tier csak felfelé
        $update_data = [
            'badge_points' => $badge_points,
            'badge_count' => $badge_count,
        ];
        
        if ($tier_order[$new_tier] > $tier_order[$existing->herowall_tier]) {
            $update_data['herowall_tier'] = $new_tier;
            $update_data['tier_achieved_at'] = current_time('mysql');
        }
        
        $wpdb->update($table, $update_data, ['pseudo_id' => $pseudo_id]);
    }
}

function impact_get_herowall_tier(int $badge_points): string {
    if ($badge_points >= 100) return 'legend';
    if ($badge_points >= 50) return 'platinum';
    if ($badge_points >= 25) return 'gold';
    if ($badge_points >= 10) return 'silver';
    return 'bronze';
}
```

---

## 4. Elismerés Típusok (Nem Anyagi Jutalmak)

A badge-ek **nem adnak anyagi előnyt**, de valódi, látható elismerést biztosítanak:

### 4.1 Vizuális Jutalmak

| Badge Pont Szint | ID Panel Vizuális |
|------------------|-------------------|
| 1-9 (Bronze) | Alapértelmezett háttér |
| 10-24 (Silver) | Halványkék háttér gradiens |
| 25-49 (Gold) | Arany háttér gradiens |
| 50-99 (Platinum) | Platina háttér + halvány ragyogás |
| 100+ (Legend) | Animált csillagok ✨ + prémium keret |

### 4.2 Badge-Specifikus Elismerések

| Badge | Elismerés |
|-------|-----------|
| `streak_30` (Gold) | 🔥 Speciális "Streak Master" címke |
| `streak_100` (Platinum) | 🔥🔥 "Száznapos Hős" címke + animált tűz ikon |
| `votes_1000` (Platinum) | ✓ "Verified Supporter" címke |
| `early_adopter` (Platinum) | ⭐ Arany csillag a pseudo_id mellett |
| `multi_ngo` (Silver) | 🌈 Szivárványszínű profilkeret |
| `edu_complete_20` (Gold) | 📚 "Tudáskereső" címke |

### 4.3 Legacy Üzenet (Platinum+ HeroWall)

Platinum vagy Legend szinten a felhasználó **opcionálisan hagyhat örök üzenetet**:

```php
function impact_set_legacy_message(string $pseudo_id, string $message): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'impact_herowall';
    
    // Ellenőrzés: csak platinum+ tier hagyhat üzenetet
    $tier = $wpdb->get_var($wpdb->prepare(
        "SELECT herowall_tier FROM {$table} WHERE pseudo_id = %s",
        $pseudo_id
    ));
    
    if (!in_array($tier, ['platinum', 'legend'])) {
        return false;
    }
    
    $message = sanitize_text_field(mb_substr($message, 0, 280));
    
    return $wpdb->update(
        $table,
        ['legacy_message' => $message],
        ['pseudo_id' => $pseudo_id]
    ) !== false;
}
```

### 4.4 Szezonális Versenyek (Opcionális, Jövőbeli)

```
┌────────────────────────────────────────────────────┐
│  🏁 JANUÁRI VERSENY – Ki szerzi a legtöbb badge-t? │
│                                                    │
│  1. 🥇 "Hero_3x9f" – 7 új badge                   │
│  2. 🥈 "Anon_k2m" – 5 új badge                    │
│  3. 🥉 "Támogató77" – 4 új badge                  │
│                                                    │
│  Top 10 bekerül a Hónap Hősei örök táblájára!     │
└────────────────────────────────────────────────────┘
```

---

## 5. Adatbázis Séma

### 5.1 Badge Definíciók Tábla

```sql
CREATE TABLE IF NOT EXISTS {$wpdb->prefix}impact_badge_definitions (
    badge_key VARCHAR(50) PRIMARY KEY,
    category ENUM('activity', 'support', 'learning', 'offerwall', 'special') NOT NULL,
    name_hu VARCHAR(100) NOT NULL,
    description_hu VARCHAR(255) NOT NULL,
    default_tier ENUM('bronze', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'bronze',
    icon_url VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) {$charset};
```

### 5.2 Felhasználói Badge-ek Tábla

```sql
CREATE TABLE IF NOT EXISTS {$wpdb->prefix}impact_user_badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pseudo_id VARCHAR(32) NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    tier ENUM('bronze', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'bronze',
    source VARCHAR(50) NOT NULL DEFAULT 'system',  -- 'ads-watch', 'offerwall', 'video', 'manual'
    awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    metadata JSON NULL,
    UNIQUE KEY uniq_user_badge (pseudo_id, badge_key),
    KEY idx_pseudo (pseudo_id),
    KEY idx_badge (badge_key),
    KEY idx_awarded (awarded_at),
    KEY idx_tier (tier),
    FOREIGN KEY (badge_key) REFERENCES {$wpdb->prefix}impact_badge_definitions(badge_key)
) {$charset};
```

### 5.3 Badge Progress Tábla

(Opcionális, haladás követéshez) (opcionális, haladás követéshez)

```sql
CREATE TABLE IF NOT EXISTS {$wpdb->prefix}impact_badge_progress (
    pseudo_id VARCHAR(32) NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    current_value INT NOT NULL DEFAULT 0,
    target_value INT NOT NULL,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (pseudo_id, badge_key),
    KEY idx_pseudo (pseudo_id)
) {$charset};
```

### 5.4 HeroWall Tábla

(Lásd: 3.3 szekció – HeroWall Tábla (DB))

---

## 6. PHP API

### 6.1 Alapvető Függvények

```php
/**
 * Badge odaítélése.
 *
 * @param string $pseudo_id Felhasználó azonosító
 * @param string $badge_key Badge kulcs (pl. 'streak_7')
 * @param string $tier Szint: bronze|silver|gold|platinum
 * @param string $source Forrás: ads-watch|offerwall|video|manual
 * @param array  $metadata Opcionális extra adatok
 * @return bool Sikeresség
 */
function impact_award_badge(
    string $pseudo_id, 
    string $badge_key, 
    string $tier = 'bronze',
    string $source = 'system',
    array $metadata = []
): bool;

/**
 * Badge upgrade magasabb szintre.
 *
 * @param string $pseudo_id
 * @param string $badge_key
 * @param string $new_tier
 * @return bool
 */
function impact_upgrade_badge(
    string $pseudo_id, 
    string $badge_key, 
    string $new_tier
): bool;

/**
 * Felhasználó összes badge-ének lekérése.
 *
 * @param string $pseudo_id
 * @return array Array of badge objects
 */
function impact_get_user_badges(string $pseudo_id): array;

/**
 * Ellenőrzés: van-e badge.
 *
 * @param string $pseudo_id
 * @param string $badge_key
 * @param string|null $min_tier Minimum szint (opcionális)
 * @return bool
 */
function impact_has_badge(
    string $pseudo_id, 
    string $badge_key, 
    ?string $min_tier = null
): bool;

/**
 * Badge progress frissítése és automatikus odaítélés.
 *
 * @param string $pseudo_id
 * @param string $badge_key
 * @param int    $increment
 * @param int    $target
 * @return array ['progress' => int, 'awarded' => bool]
 */
function impact_increment_badge_progress(
    string $pseudo_id,
    string $badge_key,
    int $increment = 1,
    int $target = 0
): array;
```

### 6.2 Trigger Hooks

```php
// Ads-watch integráció
add_action('impactshop_ads_view_recorded', function($pseudo_id, $view_data) {
    // Streak badge-ek
    $stats = impactshop_ads_watch_get_user_stats($pseudo_id);
    $streak = (int) ($stats['streak_days'] ?? 0);
    
    if ($streak >= 3 && !impact_has_badge($pseudo_id, 'streak_3')) {
        impact_award_badge($pseudo_id, 'streak_3', 'bronze', 'ads-watch');
    }
    if ($streak >= 7 && !impact_has_badge($pseudo_id, 'streak_7')) {
        impact_award_badge($pseudo_id, 'streak_7', 'silver', 'ads-watch');
    }
    // ... stb.
}, 10, 2);

// Offerwall integráció
add_action('impactshop_offerwall_conversion', function($pseudo_id, $conversion) {
    $count = impact_get_offerwall_completion_count($pseudo_id);
    
    if ($count === 1) {
        impact_award_badge($pseudo_id, 'first_offer', 'bronze', 'offerwall');
    }
    if ($count >= 5 && !impact_has_badge($pseudo_id, 'offers_5')) {
        impact_award_badge($pseudo_id, 'offers_5', 'silver', 'offerwall');
    }
    // ... stb.
}, 10, 2);

// Video content integráció
add_action('impactshop_edu_video_completed', function($pseudo_id, $video_data) {
    $count = impact_get_edu_video_count($pseudo_id);
    
    if ($count === 1) {
        impact_award_badge($pseudo_id, 'first_edu_video', 'bronze', 'video');
    }
    // ... stb.
}, 10, 2);
```

---

## 7. REST API Endpoints

### 7.1 Badge-ek lekérése

```
GET /wp-json/impact/v1/badges/user
```

**Response:**
```json
{
  "badges": [
    {
      "badge_key": "streak_7",
      "name": "Heti Streak",
      "description": "7 egymást követő nap",
      "category": "activity",
      "tier": "silver",
      "icon_url": "/assets/badges/streak-7.svg",
      "awarded_at": "2026-01-20T14:30:00Z"
    }
  ],
  "stats": {
    "total_badges": 5,
    "by_tier": {
      "bronze": 2,
      "silver": 2,
      "gold": 1,
      "platinum": 0
    },
    "by_category": {
      "activity": 2,
      "support": 2,
      "learning": 1,
      "offerwall": 0,
      "special": 0
    }
  }
}
```

### 7.2 Badge progress

```
GET /wp-json/impact/v1/badges/progress
```

**Response:**
```json
{
  "progress": [
    {
      "badge_key": "views_50",
      "name": "Video Guru",
      "current": 32,
      "target": 50,
      "percent": 64
    }
  ]
}
```

### 7.3 Elérhető badge-ek listája

```
GET /wp-json/impact/v1/badges/available
```

### 7.4 HeroWall lekérése

```
GET /wp-json/impact/v1/herowall
```

**Query parameters:**
- `tier` (optional): Szűrés tier-re (bronze|silver|gold|platinum|legend)
- `limit` (optional): Max eredmények (default: 50)

**Response:**
```json
{
  "herowall": [
    {
      "rank": 1,
      "pseudo_id": "ImpactHero_7x3f",
      "nickname": "ImpactHero",
      "badge_points": 127,
      "badge_count": 23,
      "herowall_tier": "legend",
      "tier_achieved_at": "2026-01-15T10:30:00Z",
      "legacy_message": "Minden nap számít. Büszke vagyok rá!",
      "visual_class": "herowall-legend"
    }
  ],
  "tiers": {
    "legend": 3,
    "platinum": 12,
    "gold": 45,
    "silver": 120,
    "bronze": 890
  },
  "user_position": {
    "rank": 156,
    "badge_points": 18,
    "herowall_tier": "silver"
  }
}
```

### 7.5 Legacy üzenet beállítása (Platinum+)

```
POST /wp-json/impact/v1/herowall/legacy
```

**Request body:**
```json
{
  "message": "Minden nap számít. Büszke vagyok rá!"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Legacy üzenet mentve."
}
```

**Hibák:**
- 403: "Csak platinum vagy legend szint hagyhat üzenetet."
- 400: "Üzenet túl hosszú (max 280 karakter)."

---

## 8. ID Panel Integráció

### 8.1 Badge Grid Komponens

Az `impactshop-identity-panel.php` shortcode-ba integrált badge megjelenítés:

```php
function impactshop_identity_panel_badges_html(string $pseudo_id): string
{
    $badges = impact_get_user_badges($pseudo_id);
    
    if (empty($badges)) {
        return '<div class="impactshop-badges-empty">
            <p>Még nincsenek jelvényeid. Kezdj el videókat nézni!</p>
        </div>';
    }
    
    $html = '<div class="impactshop-badges-grid">';
    foreach ($badges as $badge) {
        $tier_class = 'badge-tier-' . esc_attr($badge['tier']);
        $html .= sprintf(
            '<div class="impactshop-badge %s" title="%s">
                <img src="%s" alt="%s" class="badge-icon" />
                <span class="badge-name">%s</span>
            </div>',
            $tier_class,
            esc_attr($badge['description']),
            esc_url($badge['icon_url'] ?: '/assets/badges/default.svg'),
            esc_attr($badge['name']),
            esc_html($badge['name'])
        );
    }
    $html .= '</div>';
    
    return $html;
}
```

### 8.2 Badge CSS

```css
.impactshop-badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 12px;
    margin-top: 16px;
    padding: 16px;
    background: rgba(255,255,255,0.5);
    border-radius: 16px;
}

.impactshop-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 8px;
    border-radius: 12px;
    background: rgba(255,255,255,0.8);
    border: 2px solid transparent;
    transition: transform 0.2s, box-shadow 0.2s;
}

.impactshop-badge:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.badge-tier-bronze { border-color: #CD7F32; }
.badge-tier-silver { border-color: #C0C0C0; }
.badge-tier-gold { border-color: #FFD700; box-shadow: 0 0 12px rgba(255,215,0,0.3); }
.badge-tier-platinum { border-color: #E5E4E2; box-shadow: 0 0 16px rgba(229,228,226,0.5); }

.badge-icon {
    width: 48px;
    height: 48px;
    margin-bottom: 8px;
}

.badge-name {
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    color: #1e293b;
}

.impactshop-badges-empty {
    padding: 20px;
    text-align: center;
    color: #64748b;
    font-style: italic;
}
```

---

## 9. GA4 Tracking

```php
function impact_track_badge_award(string $pseudo_id, string $badge_key, string $tier): void
{
    impactshop_ads_watch_track_ga4('badge_awarded', [
        'badge_key' => $badge_key,
        'badge_tier' => $tier,
        'pseudo_id' => substr($pseudo_id, 0, 8),
    ]);
}
```

---

## 10. Admin UI (WP Admin)

### 10.1 Badge Management Oldal

- Badge definíciók listája
- Új badge hozzáadása
- Badge aktiválás/deaktiválás
- Manual badge odaítélés user-nek

### 10.2 User Badge Viewer

- Keresés pseudo_id alapján
- Badge-ek listázása
- Manual badge visszavonás

---

## 11. Implementációs Fázisok

### Fázis 1: Core Infrastructure
- [ ] `impact-gamification.php` MU-plugin létrehozása
- [ ] DB séma (4 tábla: definitions, user_badges, progress, herowall) létrehozása
- [ ] Alapvető PHP API függvények
- [ ] Schema version management

### Fázis 2: Badge Definíciók
- [ ] Seed data: összes badge definíció beszúrása
- [ ] Icon assets előkészítése (SVG)

### Fázis 3: Trigger Integration
- [ ] Ads-watch hook integráció
- [ ] Meglévő achievement logika migrálása
- [ ] HeroWall frissítés hook (badge award után)

### Fázis 4: HeroWall
- [ ] HeroWall tábla és logika
- [ ] `impact_update_herowall()` függvény
- [ ] Örökérvényű tier logika (csak felfelé)
- [ ] Legacy üzenet kezelés

### Fázis 5: REST API
- [ ] `/badges/user` endpoint
- [ ] `/badges/progress` endpoint
- [ ] `/badges/available` endpoint
- [ ] `/herowall` endpoint
- [ ] `/herowall/legacy` endpoint (POST)

### Fázis 6: ID Panel UI
- [ ] Badge grid komponens
- [ ] Vizuális jutalmak (háttér, keret)
- [ ] HeroWall pozíció megjelenítés
- [ ] CSS styling
- [ ] JavaScript interakció

### Fázis 7: HeroWall Frontend
- [ ] `[impactshop_herowall]` shortcode
- [ ] Tier szekciók renderelése
- [ ] User pozíció highlight
- [ ] Legacy üzenet megjelenítés
- [ ] Animációk (legend, platinum)

### Fázis 8: Future Extensions
- [ ] Offerwall trigger integráció
- [ ] Video content trigger integráció
- [ ] Szezonális versenyek
- [ ] Admin UI

---

## 11.1 HeroWall Shortcode

```php
/**
 * [impactshop_herowall] shortcode – HeroWall megjelenítés.
 *
 * @param array $atts Shortcode attribútumok
 * @return string HTML output
 */
function impactshop_herowall_shortcode(array $atts = []): string
{
    $atts = shortcode_atts([
        'tier' => '',      // Szűrés tier-re (opcionális)
        'limit' => 50,     // Max megjelenített
    ], $atts);
    
    impactshop_herowall_enqueue_assets();
    
    $rest_base = esc_url_raw(rest_url('impact/v1'));
    $panel_id = 'impactshop-herowall-' . wp_generate_password(6, false, false);
    
    $html = '<div class="impactshop-herowall" id="' . esc_attr($panel_id) . '" ';
    $html .= 'data-rest-base="' . esc_attr($rest_base) . '" ';
    $html .= 'data-tier="' . esc_attr($atts['tier']) . '" ';
    $html .= 'data-limit="' . esc_attr((int)$atts['limit']) . '">';
    
    $html .= '<div class="herowall-header">';
    $html .= '<h2>🏆 HEROWALL 🏆</h2>';
    $html .= '<p class="herowall-subtitle">Az ImpactShop Hőseinek Örök Emlékműve</p>';
    $html .= '</div>';
    
    $html .= '<div class="herowall-content" data-role="herowall-list">';
    $html .= '<p class="herowall-loading">Betöltés...</p>';
    $html .= '</div>';
    
    $html .= '<div class="herowall-user-position" data-role="user-position"></div>';
    
    $html .= '</div>';
    
    return $html;
}
add_shortcode('impactshop_herowall', 'impactshop_herowall_shortcode');
```

### HeroWall CSS

```css
.impactshop-herowall {
    max-width: 800px;
    margin: 24px auto;
    font-family: inherit;
}

.herowall-header {
    text-align: center;
    padding: 24px;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-radius: 20px 20px 0 0;
    border: 2px solid #f59e0b;
}

.herowall-header h2 {
    margin: 0;
    font-size: 28px;
    color: #92400e;
}

.herowall-subtitle {
    margin: 8px 0 0;
    color: #b45309;
    font-style: italic;
}

.herowall-tier-section {
    padding: 16px;
    border-left: 2px solid #e5e7eb;
    border-right: 2px solid #e5e7eb;
}

.herowall-tier-section.tier-legend {
    background: linear-gradient(135deg, #fef3c7, #fff7ed);
    border-color: #f59e0b;
}

.herowall-tier-section.tier-platinum {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-color: #94a3b8;
}

.herowall-tier-section.tier-gold {
    background: linear-gradient(135deg, #fef9c3, #fef3c7);
    border-color: #eab308;
}

.herowall-tier-section.tier-silver {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-color: #cbd5e1;
}

.herowall-tier-section.tier-bronze {
    background: linear-gradient(135deg, #fef3e7, #fed7aa);
    border-color: #ea580c;
}

.herowall-entry {
    display: flex;
    align-items: center;
    padding: 12px;
    margin: 8px 0;
    background: rgba(255,255,255,0.8);
    border-radius: 12px;
    gap: 12px;
}

.herowall-entry.is-current-user {
    border: 2px solid #3b82f6;
    box-shadow: 0 0 12px rgba(59, 130, 246, 0.3);
}

.herowall-rank {
    font-size: 18px;
    font-weight: 700;
    min-width: 40px;
    text-align: center;
}

.herowall-user {
    flex: 1;
}

.herowall-nickname {
    font-weight: 600;
    color: #1e293b;
}

.herowall-stats {
    font-size: 13px;
    color: #64748b;
}

.herowall-legacy {
    margin-top: 6px;
    padding: 8px;
    background: rgba(245, 158, 11, 0.1);
    border-radius: 8px;
    font-style: italic;
    color: #92400e;
}

.herowall-legacy::before {
    content: "📝 ";
}

.herowall-user-position {
    padding: 16px;
    text-align: center;
    background: #eff6ff;
    border: 2px solid #3b82f6;
    border-radius: 0 0 20px 20px;
}

@keyframes legend-glow {
    0%, 100% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.4); }
    50% { box-shadow: 0 0 30px rgba(245, 158, 11, 0.7); }
}

.herowall-entry.tier-legend {
    animation: legend-glow 2s ease-in-out infinite;
}
```

---

## 12. Meglévő Achievement Migráció

Az `impactshop-ads-watch.php` `impactshop_ads_watch_get_achievements()` függvény badge-ekre migrálása:

| Régi Achievement | Új Badge Key | Tier |
|------------------|--------------|------|
| `first_view` | `first_view` | bronze |
| `first_vote` | `first_vote` | bronze |
| `video_marathon` | `views_10` | bronze |
| `top_supporter` | `votes_100` | silver |
| `streak_7` | `streak_7` | silver |

A migrációs script egyszeri futtatásra:
```php
function impact_migrate_achievements_to_badges(): void
{
    global $wpdb;
    $stats_table = $wpdb->prefix . 'impactshop_ads_user_stats';
    
    $users = $wpdb->get_results("SELECT pseudo_id, total_views, total_votes, streak_days FROM {$stats_table}");
    
    foreach ($users as $user) {
        $pseudo_id = $user->pseudo_id;
        $views = (int) $user->total_views;
        $votes = (int) $user->total_votes;
        $streak = (int) $user->streak_days;
        
        if ($views >= 1) impact_award_badge($pseudo_id, 'first_view', 'bronze', 'migration');
        if ($votes >= 1) impact_award_badge($pseudo_id, 'first_vote', 'bronze', 'migration');
        if ($views >= 10) impact_award_badge($pseudo_id, 'views_10', 'bronze', 'migration');
        if ($votes >= 100) impact_award_badge($pseudo_id, 'votes_100', 'silver', 'migration');
        if ($streak >= 7) impact_award_badge($pseudo_id, 'streak_7', 'silver', 'migration');
    }
}
```

---

## 13. Tesztelési Terv

### Unit Tests
- Badge award/upgrade logika
- Duplikáció védelem
- Tier validáció

### Integration Tests
- Ads-watch trigger → badge award
- REST API response format
- ID panel rendering

### E2E Tests
- Teljes flow: videó nézés → badge megjelenik ID panelen

---

## Függelék: Badge Ikonok

Javasolt ikon stílus: 
- Formátum: SVG
- Méret: 96x96 viewport
- Stílus: Flat design, kerekített sarkok
- Szín: Tier-nek megfelelő árnyalat

Ikon készlet mappa: `/wp-content/uploads/impact-badges/`

---

*Dokumentum vége*
