# Jövőnk Vize – Adománygyűjtő widget beágyazás (2026-03-03)

## 1) Beillesztendő HTML kód (`jovonkvize.hu`)

```html
<div id="jovonkvize-adomany-widget"></div>

<script
  src="https://app.sharity.hu/wp-content/mu-plugins/impactshop-event-donation-widget-jovonkvize.js"
  data-impact-campaign-widget
  data-campaign="jovonkvize-2026"
  data-target="#jovonkvize-adomany-widget"
  data-api-base="https://app.sharity.hu/wp-json/impact/v1/event-campaigns"
  data-poll-ms="30000"
  defer
></script>
```

## 2) Mit tud a widget

- Stripe checkout a Sharity Adományszervező Alapítvány részére
- Külön flow, **nem** Impact Challenge szavazatvásárlás
- Valós idejű statok:
  - összes adomány
  - támogatók száma
  - átlagos adomány / támogató
- Céges adomány + adományigazolás kérés (e-mailes kiküldés queue-ból)
- Megosztás gomb:
  - Web Share API (mobil)
  - fallback: link másolás, Facebook, LinkedIn, e-mail

## 3) Stripe webhook (erősen ajánlott)

Állíts be új webhook endpointot az alábbi URL-re:

```text
https://app.sharity.hu/wp-json/impact/v1/event-campaigns/webhook
```

Kötelező események:

- `checkout.session.completed`
- `checkout.session.expired`

Megjegyzés: webhook nélkül is van `success` fallback feldolgozás visszatéréskor, de a webhook ad teljesen megbízható realtime könyvelést.

## 4) Fontos route-ok

- Publikus kampány + kezdő stat:  
  `GET /wp-json/impact/v1/event-campaigns/jovonkvize-2026/public`
- Realtime stat:  
  `GET /wp-json/impact/v1/event-campaigns/jovonkvize-2026/stats`
- Checkout indítás:  
  `POST /wp-json/impact/v1/event-campaigns/jovonkvize-2026/checkout`

## 5) Kampány adatok forrása

MU-plugin fájl:

- `wp-content/mu-plugins/impactshop-event-donation-widget.php`

A `impactshop_event_donation_campaigns()` tömbben módosítható:

- kampány szöveg
- minimum/maximum adomány
- preset összegek
- célösszeg
- engedélyezett origin domain-ek
- színvilág

## Changelog

### 2026-05-28 — Standalone jegyvásárlás + tranzakció értesítő

- JS dev: `TICKET_UNIT_PRICE=150000`, `STANDALONE_TICKET_MAX=10` — solo jegy dropdown (1–10 jegy).
- Solo dropdown automatikusan kitölti az adományösszeget: N × 150 000 Ft.
- PHP: minden befejezett tranzakcióra email értesítő (`bujdoso.arnold@` + `koncz.veronika@mielemed.hu`).
- PHP: adományigazolás emailekbe BCC `bujdoso.arnold@bujdosoiroda.com`.
- Status: dev-only, production deploy külön jóváhagyás után.
### 2026-05-28b — Version bump 1.1.0 + cache-bust embed kód
- `IMPACTSHOP_EVENT_DONATION_VERSION` 1.0.0 → 1.1.0, production deploy megtörtént.
- JS URL-ben `?v=1.1.0` → böngésző/CDN cache bust.

### 2026-05-28c — jovonkvize.js production deploy
- Solo jegyvásárlás (1-10 db × 150 000 Ft) élesben a `jovonkvize.hu` embedben.

### 2026-03-24 — version 1.2.0
- IMPACTSHOP_EVENT_DONATION_VERSION: 1.2.0 — cache-bust új embed kódhoz.

### 2026-03-24 — version 1.2.0
- IMPACTSHOP_EVENT_DONATION_VERSION: 1.2.0 — cache-bust új embed kódhoz.

### 2026-03-24 — solo ticket CSS fix
- Fehér háttér / világos szám probléma javítva: hiányzó CSS szabályok pótolva.

### 2026-03-25 — v1.3.0
- solo-select CSS !important: fehér bg override javítva.

### 2026-03-25 — STYLE_ID collision fix (jvk suffix)
- Gyökérok: dev.js és jovonkvize.js ugyanazt a STYLE_ID-t használta → dev.js (először tölt be) injektálta a CSS-t, jovonkvize.js kihagyta → régi stílusok maradtak.
- Fix: `STYLE_ID = "impact-event-donation-widget-style-jvk"` egyedi azonosítóra cserélve jovonkvize.js-ben.
- Prod: `sharityh@s59.tarhely.com` → deploy OK, chmod 444 OK.

### 2026-03-25 - v1.4.0 solo ticket number input
- Gyokérok: Safari select ignoralja a CSS background-color-t.
- Fix: input type=number (80px, sotet hatter, vilagos szoveg).
- JS: innerHTML populacio helyett .max attribute beallitas.

### 2026-03-25 - v1.5.0 email rendszer deploy
- Vasarlo visszaigazolo email jegy sorszamokkal
- Admin notification kibovitve ticket infoval
- Ticket serial generator: JVK-2026-XXXXX
