# JVK Aukció Analytics és Publikus Dashboard Update

## Scope
- Repo: `impactshop-notes`
- Campaign: `jovonkvize-2026`
- Érintett runtime fájlok:
  - `wp-content/mu-plugins/impactshop-event-auction-widget.php`
  - `wp-content/mu-plugins/impactshop-event-auction-widget-jovonkvize-1.0.0.js`
  - `wp-content/mu-plugins/impactshop-event-admin-dashboard-widget.js`
  - `wp-content/mu-plugins/impactshop-event-donation-widget.php`

## Változások
- Az aukciós embed publikus runtime helyes éles kikiáltási árakat és a valós aukciózárási időt használja.
- Az embed kliensoldali analytics eseményeket küld a WordPress backend felé, külön új szolgáltatás bevezetése nélkül.
- A publikus dashboard külön megjeleníti:
  - jegyek és adományok
  - aukciós tételek aktuális állapottal
  - aukciós realtime statisztikák
- A dashboard asset query verzió cache-bustot kapott, hogy a publikus oldal biztosan a friss JS-t töltse.

## REST felületek
- Publikus analytics summary:
  - `/wp-json/impact/v1/event-auctions/{slug}/analytics/public`
- Publikus analytics event ingest:
  - `/wp-json/impact/v1/event-auctions/{slug}/analytics/event`

## Követett események
- `page_view`
- `lot_open`
- `deep_link_open`
- `preset_click`
- `share_click`
- `bid_submit`
- `engagement`

## Verifikáció
- `php -l wp-content/mu-plugins/impactshop-event-auction-widget.php`
- `php -l wp-content/mu-plugins/impactshop-event-donation-widget.php`
- `node --check wp-content/mu-plugins/impactshop-event-auction-widget-jovonkvize-1.0.0.js`
- `node --check wp-content/mu-plugins/impactshop-event-admin-dashboard-widget.js`
- Prod smoke: publikus API visszaadta mind a 9 lotot helyes árakkal és a `2026-05-17T20:00:00Z` zárással.
- Prod smoke: browser-szerű `Referer` mellett az analytics event POST sikeres volt.
- Publikus dashboard HTML végállapot: `impactshop-event-admin-dashboard-widget.js?ver=1.6.4`

## Kockázat és rollback
- Fő kockázat: kliens- vagy edge-cache régi JS assetet tart életben. Ezt a `1.6.4` query verzió és az explicit purge kezeli.
- Rollback: az érintett négy mu-plugin/runtime fájl visszaállítása az előző commitra, majd újra deploy és cache purge.

## Kézi UI checklist
- A publikus aukciós embedben látszik a megosztás gomb a tétel panelen.
- A deeplinkes `?lot=` URL megnyitja a megfelelő tételt.
- A publikus dashboard három külön füllel jelenik meg.
- Az `Aukció tételek` fülön a lot státuszok és összegek látszanak.
- Az `Aukció statok` fülön megjelennek a látogatói és forrás statisztikák.