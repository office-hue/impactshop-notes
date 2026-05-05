# JVK Event Widget — Public Dashboard Fix (2026-05-05)

## Változások

### `impactshop-event-donation-widget.php` v1.6.2
- `public="yes"` shortcode attribútum: nem admin felhasználók is látják a dashboardot
- `data-public="1"` HTML attribútum: JS public módot jelez
- Új REST route: `GET /event-campaigns/<slug>/transactions/public` (nincs auth)
- `impactshop_event_donation_public_transactions()`: public callback, ugyanazok a mezők mint az admin endpoint-on
- Cert auto-send bekapcsolva `utalas_megerosites` confirm-nél: ha `is_company=1` és `request_certificate=1`, a cert automatikusan kiküldésre kerül
- Verziószám: 1.6.1 → 1.6.2 (JS cache-bust)

### `impactshop-event-admin-dashboard-widget.js`
- `isPublic` flag: `data-public` attribútum alapján
- Licit tab (`data-tab="auc"`) elrejtve public módban
- `donationTxUrl`: public módban `/transactions/public`, admin módban `/transactions`
- Külön `aucError` div: az auction hibák nem írják felül az adományok hibaüzenetét
- `loadAuctions()` kihagyva public módban (elkerüli az admin 403-t)
- `🏦 Utalás megerősítése` gomb: csak admin módban látható (`!isPublic`)

### WP Page 19133
- Shortcode frissítve: `[impact_event_admin_dashboard campaign="jovonkvize-2026" title="Jövőnk Vize - Nyilvános dashboard" public="yes"]`

## Deploy
- prod + staging: 2026-05-05T17:09Z–17:20Z
