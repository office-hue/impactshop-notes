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
