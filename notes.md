# Projekt napló

## 0. Rövid összefoglaló
- Platform: WordPress (ImpactShop)
- Fő téma: akciós kártyák linkjei → ne a shop főoldalra, hanem termékoldalra vigyenek.

## 1. Döntésnapló
- 2025-09-30 — Prioritás: `deeplink` először, `url` csak ha nincs deeplink.
- 2025-09-30 — Banner indexelés: `shop_slug` alapján (fallback: `slug`).
- 2025-09-30 — `has_u_param()` bővítés: `/go-deal` és alternatív query-k felismerése.

## 2. Kódrészletek
- Lásd: `snippets/deals_shortcode_fixed.php`

## 3. Teendők
- [ ] Ellenőrizd a renderelt HTML-ben a `href`-eket (jobbklikk → link másolása).
- [ ] Ha valamelyik feed shop-főoldalt ad, nézd meg van-e banner deeplink.
- [ ] GA4 event (ha kell): kattintáskor `data-*` attr. alapján loggolni.

## 4. Megfigyelések / hibák
- Ha a REST `url` sekély (`/` vagy egy szegmens), akkor nagy eséllyel főoldal → bannerrel felülírni.
