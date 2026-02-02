# Codex Implementációs Feladat: Offerwall + Video Content Strategy + Badge System

**Feladat azonosító:** `IMPL-2026-01-26-OFFERWALL-VIDEO-BADGE`  
**Prioritás:** HIGH  
**Mód:** AUTONOMOUS – végigvinni megállás nélkül  
**Deploy:** Staging + Production (scan engedéllyel)

---

## 🎯 Célkitűzés

Implementáld a teljes **Offerwall**, **Video Content Strategy** és **Badge (Gamification)** rendszert a meglévő `impactshop-ads-watch` architektúrára építve. A három modul összhangban van – mindegyik ugyanazt a pont/szavazat rendszert és közös badge infrastruktúrát használja.

---

## 📋 Felhatalmazás és Szabályok

### ✅ MIT TEHETSZ:

1. **Technikai döntések meghozása** – library választás, pattern alkalmazás, kód struktúra
2. **Hibák önálló javítása** – ha valami nem működik, javítsd és folytasd
3. **Rendszerekhez igazítás** – ha inkonzisztencia van, igazítsd a meglévő mintákhoz
4. **Védett fájl felülírása** – DE CSAK a backup+rollback protokoll betartásával:
   ```bash
   # Kötelező lépések védett fájl módosítása ELŐTT:
   mkdir -p .codex/backups/{feature}-{timestamp}
   cp {eredeti_fájl} .codex/backups/{feature}-{timestamp}/
   echo "#!/bin/bash\ncp .codex/backups/{feature}-{timestamp}/{fájlnév} {eredeti_útvonal}" > .codex/backups/{feature}-{timestamp}/rollback.sh
   chmod +x .codex/backups/{feature}-{timestamp}/rollback.sh
   ```
5. **Deploy staging + production** – scan engedéllyel (`IMPACTSHOP_ALLOW_FULL_SCAN=1`)
6. **Cache flush** – deploy után mindkét környezeten

### ❌ MIT NE TEGYÉL:

1. **NE állj meg kérdéssel** – ha döntés kell, hozd meg és dokumentáld
2. **NE töröld a meglévő kódot** – csak bővíts (kivéve: bug fix)
3. **NE hagyj félkész állapotot** – minden fázis legyen teljes
4. **NE felejtsd el a notes.md frissítését** – minden jelentős változás kerüljön bele

---

## 📁 Forrás Dokumentumok

Olvasd be és kövesd ezeket a terveket:

| Dokumentum | Útvonal | Tartalom |
|------------|---------|----------|
| **Offerwall Plan** | `docs/offerwall-integration-plan.md` | Backend, postback, security, admin UI |
| **Video Strategy** | `docs/video-content-strategy-plan.md` | YouTube, auto-banner, CTA, click tracking |
| **Badge System** | `docs/impactshop-badge-system-plan.md` | Gamification, badge kategóriák, ID panel integráció |
| **Meglévő Ads Watch** | A tervekben dokumentálva | Referencia architektúra |

---

## 🔧 Implementációs Fázisok

### FÁZIS 0: Előkészítés (10 perc)

```
[ ] Olvasd be mindkét tervet teljes egészében
[ ] Ellenőrizd a meglévő impactshop-ads-watch.php struktúráját
[ ] Készíts snapshot-ot (TM: Snapshot Now task)
[ ] Hozd létre a backup könyvtárat: .codex/backups/offerwall-video-impl-{timestamp}/
```

### FÁZIS 1: Offerwall Backend (MU-plugin)

**Fájl:** `/wp-content/mu-plugins/impactshop-offerwall.php`

```
[ ] Hozd létre az új MU-plugin fájlt
[ ] DB migráció (muplugins_loaded hook alatt):
    - wp_impactshop_offerwall_completions tábla
    - Mezők a terv szerint + awarded_at, reversed_at, request_id
    - Indexek: (provider, transaction_id) UNIQUE, pseudo_id, status+created_at

[ ] Provider config rendszer:
    - wp_option: impactshop_offerwall_providers (JSON)
    - Getter/setter függvények
    - Default config struktúra

[ ] REST API endpoints:
    - POST /impact/v1/offerwall/callback/{provider} (postback)
    - GET /impact/v1/offerwall/config (frontend)
    - GET /impact/v1/offerwall/history (user history)
    - GET /impact/v1/offerwall/health (monitoring)

[ ] Postback kezelés:
    - Signature validáció (provider-specifikus)
    - IP allowlist ellenőrzés (opcionális)
    - Rate limiting (újrahasználd: impactshop_ads_watch_rate_limit_check)
    - Dedupe: (provider, transaction_id) - duplicate esetén 200 OK
    - request_id generálás (wp_generate_uuid4)

[ ] Reward kiosztás:
    - Pont: Sharity_Points_Manager::add_points_for_pseudo()
    - Szavazat: impactshop_ads_watch_add_votes() - UGYANAZ A TÁBLA!
    - Action hook: do_action('impactshop_offerwall_rewards_awarded', ...)

[ ] Fraud guard:
    - 50 completion/hour per user
    - 200 completion/hour per IP
    - Logging: impactshop_offerwall_log_fraud()

[ ] Health endpoint:
    - last_postback per provider
    - 24h completions count
    - rate_limit_status
```

**Döntések (meghozva):**
- Jutalom formula: `payout_usd * 100 = points`, `payout_usd * 10 = votes` (min 1-1)
- History: csak cookie-based pseudo_id-val (ownership)
- Admin menü: "Beállítások" alatt (konzisztens a többi ImpactShop beállítással)
- Első provider: AdGate (legjobban dokumentált)

### FÁZIS 2: Offerwall Admin UI

**Fájl:** `/wp-content/mu-plugins/impactshop-offerwall.php` (folytatás) vagy külön `/wp-content/mu-plugins/impactshop-offerwall-admin.php`

```
[ ] Admin menü regisztráció (add_submenu_page)
[ ] Settings page renderelés:
    - Provider kártyák (enable/disable toggle)
    - API Key + Secret Key mezők
    - IFrame URL mező
    - Points/Votes multiplier slider (0.5x - 2.0x)
    - Postback URL megjelenítés (copy gomb)

[ ] Statisztika panel:
    - Összes completion
    - Összes pont kiosztva
    - Összes szavazat kiosztva
    - Provider breakdown

[ ] Settings mentés (sanitize + validate)
```

### FÁZIS 3: Offerwall Frontend Widget

**Fájl:** `/wp-content/mu-plugins/impactshop-offerwall.js`

```
[ ] Shortcode: [impactshop_offerwall]
[ ] Tab integráció a meglévő ads-watch widget-be:
    - "🎬 Videó" | "🎁 Feladatok" tabok
    
[ ] Provider lista renderelés:
    - Dinamikus a /config endpoint-ból
    - Kártya UI (icon, név, jutalom preview)

[ ] IFrame modal:
    - sandbox attribútum (security)
    - referrerpolicy="no-referrer"
    - pseudo_id paraméter az URL-ben

[ ] Consent checkbox:
    - "Elfogadom az offerwall feltételeit"
    - LocalStorage-ban megjegyezve

[ ] History panel:
    - Utolsó 10 completion
    - "Még nincs teljesítés" empty state

[ ] Trust messaging:
    - "Néha pár órán belül fut be a jutalom"
    - "Hol a jutalmam?" FAQ link

[ ] CSS: match existing ads-watch design
```

### FÁZIS 4: Video Content Strategy - Unified Player

**Fájl:** `/wp-content/mu-plugins/impactshop-ads-watch.php` (bővítés - VÉDETT FÁJL!)

⚠️ **VÉDETT FÁJL PROTOKOLL:**
```bash
# KÖTELEZŐ backup ELŐTTE:
mkdir -p .codex/backups/video-strategy-impl-$(date +%Y%m%d-%H%M%S)
cp /wp-content/mu-plugins/impactshop-ads-watch.php .codex/backups/video-strategy-impl-*/
cp /wp-content/mu-plugins/impactshop-ads-watch.js .codex/backups/video-strategy-impl-*/
# rollback.sh generálás
```

```
[ ] GET /ads-watch/next endpoint bővítés:
    - Új response mezők: content_type, source, youtube_id, reward_rules, cta
    - Visszafelé kompatibilis (régi frontend továbbra is működik)

[ ] Content rotation logika:
    - 60% reklám, 25% szponzor, 15% edukáció
    - Max 3 reklám egymás után
    - Szponzor: napi 1x per user
    - Session tracking a rotációhoz

[ ] Új tábla: wp_impactshop_education_views
    - Mezők: pseudo_id, youtube_id, watched_seconds, completed_intervals, points_earned, votes_earned, session_token, dedupe_key
```

**Fájl:** `/wp-content/mu-plugins/impactshop-ads-watch.js` (bővítés - VÉDETT FÁJL!)

```
[ ] YouTube IFrame API integráció:
    - Lazy load
    - Player inicializálás
    - State change kezelés

[ ] Interval-based jutalmazás:
    - 30 mp = 5 pont + 5 szavazat
    - Progress bar UI
    - "Következő +5 pont: X mp" label

[ ] Megszakítás kezelés:
    - "Befejezem" gomb
    - Részleges jutalom számítás
    - finalizeEducationReward() → backend

[ ] Player layer switching:
    - ad-container (IMA)
    - content-container (CDN/auto-banner)
    - youtube-container (education)
    - Zökkenőmentes váltás

[ ] Content type badge UI:
    - 📺 Reklám (lila)
    - 🎬 Szponzor (pink)
    - 📚 Edukáció (türkiz)
```

### FÁZIS 5: Auto-Banner System (Harvester Integration)

**Fájl:** `/wp-content/mu-plugins/impactshop-auto-banner.php` (új)

```
[ ] Új tábla: wp_impactshop_auto_banners
    - Mezők a terv szerint

[ ] Harvester hook integráció:
    - add_action('impactshop_harvester_offer_saved', ...)
    - Offer → Banner konverzió
    - Priority számítás (kedvezmény + frissesség)

[ ] Banner lejátszó:
    - 15 mp animált megjelenítés
    - Kép + cím + ár (régi/új) + kedvezmény badge
    - Progress bar

[ ] Moderáció (opcionális):
    - status: pending → active
    - Admin approve flow
```

### FÁZIS 6: CTA és Click Tracking

**Fájl:** `/wp-content/mu-plugins/impactshop-click-tracking.php` (új)

```
[ ] Új tábla: wp_impactshop_click_tracking
    - Mezők: pseudo_id, content_type, content_id, cta_url, shop_slug, category, created_at

[ ] REST endpoint: POST /impact/v1/tracking/cta-click
    - Beacon-compatible (sendBeacon)
    - Profil frissítés (shop preferencia)

[ ] Post-video CTA UI:
    - Reklám: "Megnézem az ajánlatot" → affiliate link
    - Szponzor: custom text → szponzor link
    - Edukáció: "Tudj meg többet" → opcionális weboldal (vagy nincs CTA)

[ ] User profil preferenciák:
    - Transient: user_prefs_{pseudo_id}
    - shop_clicks, categories, price_ranges
```

### FÁZIS 7: Badge (Gamification) Rendszer

**Terv dokumentum:** `docs/impactshop-badge-system-plan.md`

**Fájl:** `/wp-content/mu-plugins/impact-gamification.php` (új)

```
[ ] DB séma létrehozása (init hook):
    - wp_impact_badge_definitions (badge_key PRIMARY, category, name_hu, description_hu, default_tier, icon_url, is_active, sort_order)
    - wp_impact_user_badges (id, pseudo_id, badge_key, tier, source, awarded_at, metadata JSON)
    - wp_impact_badge_progress (pseudo_id, badge_key, current_value, target_value, last_updated)
    - wp_impact_herowall (pseudo_id, nickname, badge_points, badge_count, herowall_tier, tier_achieved_at, legacy_message, is_visible)
    - Schema version management

[ ] Badge definíciók seed data:
    - Aktivitás: streak_3, streak_7, streak_30, streak_100, views_10, views_50, views_100
    - Támogatás: first_vote, votes_100, votes_500, votes_1000, ngo_loyal, multi_ngo
    - Tanulás: first_edu_video, edu_complete_5, edu_complete_20, quiz_master
    - Offerwall: first_offer, offers_5, offers_20, high_value_offer
    - Speciális: early_adopter, seasonal_xmas, referral_1, anniversary_1

[ ] Core PHP API függvények:
    - impact_award_badge($pseudo_id, $badge_key, $tier, $source, $metadata): bool
    - impact_upgrade_badge($pseudo_id, $badge_key, $new_tier): bool
    - impact_get_user_badges($pseudo_id): array
    - impact_has_badge($pseudo_id, $badge_key, $min_tier): bool
    - impact_increment_badge_progress($pseudo_id, $badge_key, $increment, $target): array
    - impact_calculate_badge_points($pseudo_id): int
    - impact_update_herowall($pseudo_id): void
    - impact_get_herowall_tier($badge_points): string
    - impact_set_legacy_message($pseudo_id, $message): bool

[ ] Badge pont súlyozás:
    - bronze = 1, silver = 2, gold = 4, platinum = 8

[ ] HeroWall tier küszöbök:
    - legend = 100+, platinum = 50-99, gold = 25-49, silver = 10-24, bronze = 1-9

[ ] Dedupe: (pseudo_id, badge_key) UNIQUE – upgrade via impact_upgrade_badge()

[ ] Örökérvényűség: HeroWall tier SOHA nem csökken (csak felfelé frissül)
```

**Trigger integráció (hook-ok):**

```
[ ] Ads-watch integráció:
    add_action('impactshop_ads_view_recorded', function($pseudo_id, $view_data) {
        // Streak és view-based badge-ek automatikus kiosztása
        // HeroWall frissítés: impact_update_herowall($pseudo_id)
    });

[ ] Offerwall integráció:
    add_action('impactshop_offerwall_conversion', function($pseudo_id, $conversion) {
        // Offer completion badge-ek
        // HeroWall frissítés
    });

[ ] Video content integráció:
    add_action('impactshop_edu_video_completed', function($pseudo_id, $video_data) {
        // Edukációs badge-ek
        // HeroWall frissítés
    });

[ ] Badge award hook (központi HeroWall frissítés):
    add_action('impact_badge_awarded', function($pseudo_id, $badge_key, $tier) {
        impact_update_herowall($pseudo_id);
        impact_track_badge_award($pseudo_id, $badge_key, $tier);
    });
```

**REST API endpoints:**

```
[ ] GET /impact/v1/badges/user
    - Response: badges array + stats (total, by_tier, by_category) + badge_points

[ ] GET /impact/v1/badges/progress
    - Response: progress array (badge_key, current, target, percent)

[ ] GET /impact/v1/badges/available
    - Response: összes aktív badge definíció

[ ] GET /impact/v1/herowall
    - Query: tier (optional), limit (default 50)
    - Response: herowall entries + tiers count + user_position

[ ] POST /impact/v1/herowall/legacy
    - Body: { message: string }
    - Csak platinum+ szint hagyhat üzenetet
    - Response: success/error
```

**ID Panel integráció:**

**Fájl:** `/wp-content/mu-plugins/impactshop-identity-panel.php` (bővítés - VÉDETT FÁJL!)

⚠️ **VÉDETT FÁJL PROTOKOLL:**
```bash
mkdir -p .codex/backups/badge-impl-$(date +%Y%m%d-%H%M%S)
cp /wp-content/mu-plugins/impactshop-identity-panel.php .codex/backups/badge-impl-*/
# rollback.sh generálás
```

```
[ ] Badge grid komponens hozzáadása a shortcode-hoz:
    - impactshop_identity_panel_badges_html($pseudo_id) helper
    - Grid layout: auto-fill, minmax(80px, 1fr)
    - Tier-specifikus border szín és glow effekt

[ ] Vizuális jutalmak (badge pont alapján):
    - 1-9: Alapértelmezett háttér
    - 10-24: Halványkék háttér gradiens
    - 25-49: Arany háttér gradiens
    - 50-99: Platina háttér + halvány ragyogás
    - 100+: Animált csillagok + prémium keret

[ ] HeroWall pozíció megjelenítés:
    - "Te a HeroWall Silver szintjén vagy (18 badge pont)"
    - Link a teljes HeroWall oldalra

[ ] CSS kiegészítés:
    .impactshop-badges-grid { ... }
    .impactshop-badge { ... }
    .badge-tier-bronze / silver / gold / platinum { ... }
    .impactshop-badges-empty { ... }
    .impactshop-herowall-position { ... }

[ ] JavaScript: badge tooltip / hover info
```

**HeroWall Frontend:**

**Fájl:** `/wp-content/mu-plugins/impact-gamification.php` (folytatás)

```
[ ] [impactshop_herowall] shortcode:
    - Tier szekciók renderelése (Legend → Bronze)
    - Legacy üzenetek megjelenítése (Platinum+)
    - User pozíció highlight (is-current-user class)
    - Animációk: legend-glow

[ ] HeroWall CSS:
    - .herowall-tier-section (tier-specifikus háttér)
    - .herowall-entry (kártya stílus)
    - .herowall-legacy (üzenet megjelenítés)
    - @keyframes legend-glow

[ ] JavaScript:
    - REST API fetch (/herowall)
    - User pozíció scroll-to
```

**Achievement migráció (egyszeri):**

```
[ ] Meglévő impactshop_ads_watch_get_achievements() badge-ekre migrálása:
    - first_view → first_view (bronze)
    - first_vote → first_vote (bronze)
    - video_marathon → views_10 (bronze)
    - top_supporter → votes_100 (silver)
    - streak_7 → streak_7 (silver)

[ ] Migrációs script: impact_migrate_achievements_to_badges()
    - Felhasználók bejárása az ads_user_stats táblából
    - Meglévő statisztikák alapján badge-ek kiosztása
    - HeroWall bejegyzések létrehozása
    - Egyszeri futtatás (WP-CLI vagy admin trigger)
```

**GA4 tracking:**

```
[ ] Badge award event:
    impactshop_ads_watch_track_ga4('badge_awarded', [
        'badge_key' => $badge_key,
        'badge_tier' => $tier,
        'pseudo_id' => substr($pseudo_id, 0, 8),
    ]);
```

### FÁZIS 8: Tesztelés

```
[ ] Unit tesztek:
    - Signature validáció (AdGate, Tapjoy formátum)
    - Reward kalkuláció
    - Dedupe logika
    - Badge award/upgrade logika
    - Tier validáció

[ ] Integrációs teszt:
    - Teljes postback flow
    - YouTube interval tracking
    - Auto-banner generálás
    - Badge trigger → award → ID panel megjelenés

[ ] Manuális teszt:
    - Admin UI minden funkció
    - Frontend widget minden state
    - Badge grid megjelenés ID panelen
    - Mobile responsiveness
```

### FÁZIS 9: Deploy

```
[ ] Staging deploy:
    IMPACTSHOP_ALLOW_FULL_SCAN=1 ./bin/deploy.sh staging
    
[ ] Staging verification:
    - /impact/v1/offerwall/health endpoint
    - Admin UI betöltődik
    - Frontend widget renderel

[ ] Production deploy:
    IMPACTSHOP_ALLOW_FULL_SCAN=1 ./bin/deploy.sh production

[ ] Post-deploy:
    - wp cache flush
    - wp rewrite flush
    - Health check
    - notes.md frissítés
```

---

## 📊 Adatbázis Séma Összefoglaló

### Új táblák (8 db):

```sql
-- 1. Offerwall completions
CREATE TABLE wp_impactshop_offerwall_completions ( ... );

-- 2. Education views
CREATE TABLE wp_impactshop_education_views ( ... );

-- 3. Auto banners
CREATE TABLE wp_impactshop_auto_banners ( ... );

-- 4. Click tracking
CREATE TABLE wp_impactshop_click_tracking ( ... );

-- 5. Badge definitions
CREATE TABLE wp_impact_badge_definitions ( ... );

-- 6. User badges
CREATE TABLE wp_impact_user_badges ( ... );

-- 7. Badge progress
CREATE TABLE wp_impact_badge_progress ( ... );

-- 8. HeroWall (örök dicsőségtábla)
CREATE TABLE wp_impact_herowall ( ... );
```

### Meglévő táblák (használat, NEM módosítás):

```sql
-- Szavazat egyenleg (közös minden forrásra)
wp_impactshop_ads_user_votes

-- Pont rendszer
Sharity tables (points_manager)
```

---

## 🔗 Integrációs Pontok

| Komponens | Kapcsolódik | Hogyan |
|-----------|-------------|--------|
| Offerwall rewards | Ads Watch szavazat | `impactshop_ads_watch_add_votes()` |
| Education rewards | Ads Watch szavazat | `impactshop_ads_watch_add_votes()` |
| Minden pont | Sharity | `Sharity_Points_Manager` |
| Auto-banner | Harvester | `impactshop_harvester_offer_saved` hook |
| Click tracking | User profil | Transient-based preferences |
| Badge awards | Ads Watch, Offerwall, Video | Hook-alapú trigger-ek |
| Badge megjelenítés | Identity Panel | `impactshop_identity_panel_badges_html()` |

---

## ✅ Befejezési Kritériumok

Az implementáció AKKOR tekinthető késznek, ha:

1. ✅ Minden új tábla létrejött és indexelve van (8 tábla)
2. ✅ Offerwall postback működik (AdGate teszt)
3. ✅ Admin UI-n be lehet állítani a providereket
4. ✅ Frontend widget renderel és iframe nyílik
5. ✅ YouTube edukációs videó lejátszható és jutalmaz
6. ✅ Auto-banner generálódik harvester ajánlatból
7. ✅ CTA kattintások trackingje működik
8. ✅ Badge rendszer működik: award, upgrade, get
9. ✅ Badge-ek megjelennek az ID panelen
10. ✅ Badge trigger hook-ok aktívak (ads-watch, offerwall, video)
11. ✅ HeroWall működik: örökérvényű pozíció, badge pont számítás
12. ✅ HeroWall shortcode renderel tier szekciókkal
13. ✅ Legacy üzenet beállítható (platinum+)
14. ✅ /health endpoint valid JSON-t ad vissza
15. ✅ Staging + Production deploy sikeres
16. ✅ notes.md frissítve a változásokkal

---

## 📝 Dokumentáció Frissítés (végén)

Frissítsd a `notes.md` fájlt az alábbi bejegyzéssel:

```markdown
### 2026-01-XX – Offerwall + Video Content Strategy + Badge System + HeroWall implementáció
- 🚀 Új MU-plugin: `impactshop-offerwall.php` (postback, admin, frontend)
- 🚀 Új MU-plugin: `impactshop-auto-banner.php` (harvester integráció)
- 🚀 Új MU-plugin: `impactshop-click-tracking.php` (CTA tracking)
- 🚀 Új MU-plugin: `impact-gamification.php` (badge rendszer, HeroWall, tier-ek, progress)
- 📺 `impactshop-ads-watch.{php,js}` bővítve: YouTube edukáció, content rotation, unified player
- 🎖️ `impactshop-identity-panel.php` bővítve: badge grid + HeroWall pozíció megjelenítés
- 🏆 HeroWall: örök dicsőségtábla badge pontok alapján, legacy üzenet (platinum+)
- 🗄️ Új táblák: offerwall_completions, education_views, auto_banners, click_tracking, badge_definitions, user_badges, badge_progress, herowall
- ✅ Deploy: staging + production (scan engedéllyel)
- 🧷 Backup: `.codex/backups/offerwall-video-badge-herowall-impl-{timestamp}/`
```

---

## 🚀 INDÍTÁS

**Codex, kezdd el az implementációt a FÁZIS 0-val és haladj végig minden fázison (0-9) megállás nélkül. Ha bármilyen döntést kell hoznod, hozd meg, dokumentáld a kódban kommentben, és folytasd. Ha hibát találsz, javítsd és menj tovább. A végén deploy-olj staging-re és production-re.**

**Hajrá!**
