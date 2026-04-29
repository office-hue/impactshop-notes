# Jovonk Vize aukcio widget scaffold

Datum: 2026-04-29

## Scope

Additiv scaffold keszult a Jovonk Vize x Miele aukcios widgethez ugy, hogy a meglevo protected donation widget fajlokhoz nem nyultunk hozza.

Erintett uj modulok:

- `wp-content/mu-plugins/impactshop-event-auction-widget.php`
- `wp-content/mu-plugins/impactshop-event-auction-widget-jovonkvize-1.0.0.js`

## Mit tud most a scaffold

- kulon `event-auctions` read REST surface,
- read-only query fallback csak `public`, `stats`, `lot` hivasokra,
- JVK-szinu frontend gallery + detail drawer + bid form skeleton,
- a 7 lot minimalis scaffold metadataja bent van,
- a stats payload mar kulon kezeli a `leading`, `closed` es `paid` szemantikakat,
- session token alapu bidder-regisztracio lane,
- bidder token + rate limit + idempotency key + tranzakcios licit submit lane,
- admin close lane WP admin + `wp_rest` nonce vedelmmel,
- Stripe winner-payment request lane, webhook es success/cancel reconcile endpointek.

## Tudatosan nincs meg benne meg

- biddernek kuldott automatikus winner-payment e-mail lane,
- admin UI a close/payment triggerhez,
- SMS disclosure + release-ready compliance lane.

## Kockazati megjegyzes

Ez a scaffold tovabbra sem teljesen deploy-kesz. A publikus bidder/bid lane, az admin close es a Stripe winner-payment backend mar aktiv, de kulon admin UI, kommunikacios lane es release checklist meg szukseges.

## 2026-04-29 Public embed copy cleanup

- A publikus embed write-enabled alapallapotban mar nem jelenit meg technikai scaffold status-szoveget.
- Statusuzenet csak hiba vagy write-disabled helyzetben marad lathato.
- A cache-busting query string ehhez a korhez `0.2.4`-re emelve.

## 2026-04-29 Detail drawer copy cleanup

- A detail drawer publikus szovegei mar nem tartalmaznak technikai scaffold/lane/admin UI kifejezeseket.
- A licitform note, gombfelirat, kezdo instrukcio es betoltesi hiba copy felhasznaloi nyelvre lett irva.
- A cache-busting query string ehhez a korhez `0.2.5`-re emelve.

## Staging smoke allapot

- A ket runtime fajl stagingre ki lett deployolva az `app-staging` peldanyra.
- A public read lane mukodik, a payload session tokent ad vissza.
- A public `register-bidder` smoke csak a kanonikus `https://app.sharity.hu/impactshop-staging` hoston mukodik megbizhatoan.
- A `https://www.sharity.hu/impactshop-staging` staging URL POST esetben 302-vel atiranyit az `app.sharity.hu` hostra; a korabbi `invalid_session_token` hiba ebbol a host-canonicalization eltolasbol jott, nem a session transient hibajabol.
- Direkt backend smoke alapjan a `bid`, `admin close` es `winner payment request` backend logika stagingen mukodik.
- A route-szintu admin REST smoke stagingen jelenleg kornyezeti okbol blokkolt, mert a lekert staging usereknel nem latszik `manage_options` kepesseg.
- A success redirect teljesites most mar csak Stripe session-status ellenorzessel fut le, igy a callback onmagaban nem irhat tevesen `paid` allapotot.
- A staging runtime most blokkolja az elo Stripe checkout session letrehozasat; tenyleges payment completion smoke csak teszt kulcsokra valtott staging konfiggal javasolt.
## 2026-04-29 — v1.0.6 JS + v0.2.6 PHP deploy

### Változások
- **JS v1.0.6**: scroll visszaállítva (1.0.3 PR-merged bázison), HU ékezetek, scaffold szövegek eltávolítva
- **PHP v0.2.6**: wp_mail értesítő minden licitnél + nyertes lezárásnál → office@sharity.hu + koncz.veronika@mielemed.hu
