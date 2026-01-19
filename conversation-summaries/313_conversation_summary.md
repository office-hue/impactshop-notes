# 313. beszélgetés összefoglaló

- Prod log ellenőrzés: `about-you` kattintás CJ hostra ment (www.anrdoezrs.net), de `is_cj=0`, mert nem CJ slug.
- `impactshop_shops` optionben 106 shop van, ebből 42 CJ slug, 33 rendelkezik `cj_click_url`-lel.
- Valószínű ok: a shoplisták/shortcode-ok CSV-alapúak, nem olvassák össze az `impactshop_shops` CJ listát, ezért a CJ shopok nem láthatók.
