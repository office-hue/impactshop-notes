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

## Staging smoke allapot

- A ket runtime fajl stagingre ki lett deployolva az `app-staging` peldanyra.
- A public read lane mukodik, a payload session tokent ad vissza.
- A public `register-bidder` smoke csak a kanonikus `https://app.sharity.hu/impactshop-staging` hoston mukodik megbizhatoan.
- A `https://www.sharity.hu/impactshop-staging` staging URL POST esetben 302-vel atiranyit az `app.sharity.hu` hostra; a korabbi `invalid_session_token` hiba ebbol a host-canonicalization eltolasbol jott, nem a session transient hibajabol.
- Direkt backend smoke alapjan a `bid`, `admin close` es `winner payment request` backend logika stagingen mukodik.
- A route-szintu admin REST smoke stagingen jelenleg kornyezeti okbol blokkolt, mert a lekert staging usereknel nem latszik `manage_options` kepesseg.
- A success redirect teljesites most mar csak Stripe session-status ellenorzessel fut le, igy a callback onmagaban nem irhat tevesen `paid` allapotot.
- A staging runtime most blokkolja az elo Stripe checkout session letrehozasat; tenyleges payment completion smoke csak teszt kulcsokra valtott staging konfiggal javasolt.

## 2026-04-29 UI polish follow-up

- A frontend magyar feliratai ékezetesítve lettek.
- A galéria vízszintes, görgethető kártyasáv lett, hogy több tételnél se nőjön túl magasra az embed.
- A jelenlegi 404-es kép URL-ek miatt a widget fallback vizuális placeholdert mutat a gallery és detail nézetben, ahelyett hogy üres vagy törött képkonténert rajzolna.
- A lot árak és részletnézeti értékek kontrasztja javítva lett.
- Teszt körhöz minden scaffold lot kezdőár és minimum licitlépcső `500 Ft`.

## 2026-04-29 Képfallback + auto-scroll follow-up

- A scaffold lotok most már nem üres képkonténert kapnak, ha a végleges JPEG asset nincs feltöltve: a backend lot-specifikus, szerveroldalon generált SVG képet ad vissza működő `image_url`-ként.
- A galéria automatikus vízszintes scrollt kapott, ami addig fut, amíg a felhasználó hozzá nem nyúl pointerrel, touch-csal, görgővel vagy billentyűvel.
- Ha a valódi `uploads/jovonkvize-auction/2026/...` fájlok később felkerülnek, a backend automatikusan azokat fogja preferálni a generált képek helyett, kódmódosítás nélkül.

## 2026-04-29 Végleges képfeltöltési checklist + scroll finomhangolás

- Az auto-scroll most lassabb, indulás előtt rövid késleltetést kap, és körbeérésnél egy rövid megállással indul újra; az első user interakciónál továbbra is végleg leáll.
- A detail drawer megnyitása is user interakciónak számít, ezért onnan már nem indul vissza automatikusan a galéria.

### Végleges képfeltöltési checklist

- Célmappa a production/staging uploadban: `wp-content/uploads/jovonkvize-auction/2026/`
- Elvárt fájlnevek:
	- `toth-marta.jpg`
	- `kek-sugarzas.jpg`
	- `part-iii.jpg`
	- `elindulok-a-csillagokhoz.jpg`
	- `szabo-anna-cseresznye.jpg`
	- `szabo-anna-no-turkizben.jpg`
	- `sirocco-elmenyvitorlazas.jpg`
- Feltöltés után ellenőrzés:
	- `curl -I https://app.sharity.hu/wp-content/uploads/jovonkvize-auction/2026/<fajlnev>` minden képre `200`-at adjon
	- a public payload első lotján az `image_url` már ne `data:image/svg+xml` prefixszel kezdődjön
	- az embed oldalon hard refresh után a generált poster helyett a végleges kép látszódjon
- Ha a fájlnevek eltérnek, nem kell kódot módosítani, csak az aukciós lotok `image_path` mezőit kell a kanonikus repo-forrásban átírni, majd újra deployolni.

## 2026-04-29 Embed cache-busting paritás

- A PHP runtime verzió `0.2.3`-ra emelve, hogy az embed HTML a finomhangolt JS buildhez illeszkedő `?v=0.2.3` query stringet szolgálja.
- Ez a kör nem módosította újra a galéria logikát; csak a verziózott script URL és a production cache-busting lett konzisztens.