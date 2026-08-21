# Hatás Körök post-deploy checklist

## Cél

Gyors, read-only ellenőrzés deploy után, hogy a legacy app belépőpont az új
Sharity Human Touch felületre vezet, miközben az alap community API-k és a dev
route-ok változatlanok.

## Automata smoke

Futtatás a repo gyökeréből:

```bash
./scripts/hatas-korok-post-deploy-smoke.sh
```

Egyedi hosttal:

```bash
./scripts/hatas-korok-post-deploy-smoke.sh https://staging.sharity.hu
```

## Mit ellenőriz a script

- `GET /hatas-korok/?hk_route_probe=1` → `HTTP 302`
- `Location` pontosan `https://sharity.hu/hatas-korok`, query nélkül
- a Human Touch céloldal → `HTTP 200`, és tartalmazza:
  - `Hatás Körök — Közösségek, nem követők`
  - `safe-area-inset-bottom`
- névtelen `/hatas-korok-dev` → `HTTP 404`
- `/impactshop-staging/hatas-korok-dev` → `HTTP 200`
- `GET /wp-json/impact/v1/auth/status`
  - van `authenticated`
  - van `nonce`
- `GET /wp-json/impact/v1/circles?page=1`
  - van nem üres `circles[]`
  - van `total`
  - az első körnek van `name`

## Kézi utóellenőrzés

- Nyisd meg mobilon és desktopon az `https://app.sharity.hu/hatas-korok/` címet.
- A böngésző címe váltson `https://sharity.hu/hatas-korok` értékre.
- A Human Touch fejlécet, közösségkártyákat, szűrőket és lebegő mobil sávot kell látnod.
- Bejelentkezve a „Köreim” panelt; kijelentkezve a biztonságos belépési CTA-t ellenőrizd.
- Nyisd meg külön a FactLens VB2026 oldalt, és ellenőrizd, hogy a profil-visszatérési út változatlanul működik.
- Regressziójel: legacy lila Impact Community shell, redirect loop, továbbított teszt/query paraméter vagy eltűnő mobil alsó sáv.

## Mikor kell több, mint read-only smoke

Csak akkor futtass write smoke-ot (`join`, `post`, `vote`, `delete`, `leave`), ha:

- deploy közben backend logika is változott, nem csak markup vagy route
- van takarítási terv a tesztadatokra
- tudatosan production smoke-ról van szó

## Megjegyzés

Ez a script direkt read-only alapértelmezésű. A célja, hogy deploy után gyorsan
jelezze a route-cutover, Human Touch target, dev-route vagy alap API regressziót
anélkül, hogy production állapotot módosítana.
