# ImpactShop WordPress Project - AI Assistant Instructions

## Project Overview
Ez egy WordPress alapú ImpactShop platform, amely akciós kártyákat és banner rendszert kezel. A fő cél az, hogy a linkek megfelelően a termékekre vezessenek, ne csak a shop főoldalára.

## Key Files & Structure
- `wp-content/mu-plugins/` - Must-use plugins (automatikusan betöltődnek)
- `wp-content/plugins/` - Standard plugins
- `notes.md` - Projekt napló, döntések, teendők
- `snippets/` - Kódrészletek és példák

## Critical Patterns & Conventions

### URL/Deeplink Management
- **Prioritás**: `deeplink` először, csak utána `url`
- **Banner indexelés**: `shop_slug` alapján (fallback: `slug`)
- **Shallow URL detection**: Ha REST `url` csak `/` vagy egy szegmens → banner felülírás
- `has_u_param()` függvény: `/go-deal` és alternatív query-k felismerése

### Code Organization
- PHP fájlok neve leíró legyen (pl. `deals_shortcode_fixed.php`)
- Minden nagyobb változtatás kerüljön a `notes.md`-ba
- Snippet-ek külön mappában tárolva

### Development Workflow
1. Kód fejlesztés itt VS Code-ban
2. Tesztelés lokálisan ha lehetséges
3. Feltöltés WP adminba
4. HTML ellenőrzés: jobbklikk → link másolása
5. Eredmények dokumentálása `notes.md`-ban

## WordPress Specific Notes
- Must-use plugins automatikusan aktívak
- REST API integration a banner/deal rendszerhez
- GA4 event tracking: `data-*` attribútumok használata

## Current Priorities
- Deeplink vs URL priorizálás implementálása
- Banner indexelés optimalizálása
- URL shallow detection javítása

Mindig nézd meg a `notes.md` fájlt a legfrissebb döntésekért és teendőkért!