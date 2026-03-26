# Hatás Körök NGO admin route fix — 2026-03-26

## Probléma

A Hatás Körök teszt mód a `ngo_admin_url` mezőben és a reset password linkben a nem létező `/impact-challenge/ngo-admin/` útvonalat adta vissza, miközben a live WordPress oldal valójában az `/impact-shop_ngo/`.

## Javítás

- `impact-community.php`:
  - `auth/status.ngo_admin_url` -> `/impact-shop_ngo/`
  - Hatás Körök template bootstrap `NGO_ADMIN_URL` -> `/impact-shop_ngo/`
  - NGO reset password email link -> `/impact-shop_ngo/?reset=...`

## Validáció

- `php -l wp-content/mu-plugins/impact-community.php`
- production deploy lefutott
- `GET /impact-shop_ngo/?ic_test_mode=1&impact_pseudo_id=TESTUSER01&impact_ngo_slug=bator-tabor-alapitvany` -> `HTTP 200`
- pseudo-alapú `POST /wp-json/impact/v1/ngo/login` működik erről az oldalról
