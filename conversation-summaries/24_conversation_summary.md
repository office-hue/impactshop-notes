# 24. Beszélgetés összefoglaló: ImpactShop kártyaigénylő shortcode

## Áttekintés
A Fillout nélküli embed/share pass igényléshez készítettem egy új WordPress shortcode-ot, amely egy AJAX-os űrlapon kéri be a három kötelező adatot (kép, név, videónézős link) és e-mailben továbbítja az ImpactShop csapatnak.

## Főbb elemek
- Új MU plugin: `mu-plugins/impactshop-card-request.php`. Regisztrálja a `[impactshop_card_request]` shortcode-ot, eldönti mikor kell a JS, és az `wp_ajax_(nopriv)_impactshop_card_request` handlerrel feldolgozza a kéréseket (`wp_handle_upload`, alap admin/konfigurálható e-mail értesítés).
- Frontend script: `mu-plugins/impactshop-card-request.js` (jQuery). AJAX-szal küldi a formot, és inline státuszüzeneteket jelenít meg siker/hiba esetén.
- Alap űrlap mezők: kép feltöltés (PNG/JPG/WEBP), szervezet/projekt neve, videós URL + opcionális e-mail/üzenet. A shortcode attribútumokkal cím/leírás/gomb felirat testre szabható.
- A plugint `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh ...` paranccsal prod/staging környezetre is feltöltöttem, cache flush után `~/bin/impactall` lefutott (csak a baseline WARN maradt), így az űrlap azonnal használható.

## Következő lépések
- Helyezd el a `[impactshop_card_request]` shortcode-ot a kívánt oldal/szekcióban; szükség esetén add meg egyedi `title`, `description` vagy `button` attribútumot.
- Állítsd be az `impactshop_card_request_email` opciót (pl. WP-CLI: `wp option update impactshop_card_request_email ops@impactshop.hu`), hogy a beérkező igénylések dedikált postafiókba jussanak.
