# Hatás Körök hotfix — 2026-03-25

## Scope

Production route-helyreállítás a `https://app.sharity.hu/hatas-korok` oldalhoz.

## Javítások

- `impact-community.php`
  - szerveroldali nonce-check a módosító REST endpointokra
  - tagságellenőrzés a vote flow-ban
  - join/post/delete számláló- és DB-hibakezelés
  - explicit `200` státusz a route template redirectben
- `impact-community-app.php`
  - shortcode redeclare guard az NGO admin shortcode-okhoz
- `impactshop-ngo-guides.php`
  - route-ütközés megszüntetése a statikus `/hatas-korok` és a community route között
  - rewrite flush verziófrissítés

## Ellenőrzés

- read-only smoke: route, lista, detail, API-k rendben
- write smoke: `join`, `post`, `vote`, `delete post`, `leave` végigment
- production cleanup sikeres volt a teszt után
