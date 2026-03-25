# Hatás Körök post-deploy checklist

## Cél

Gyors, read-only ellenőrzés deploy után, hogy a `/hatas-korok` route és az alap community API-k életben vannak.

## Automata smoke

Futtatás a repo gyökeréből:

```bash
./scripts/hatas-korok-post-deploy-smoke.sh
```

Egyedi hosttal:

```bash
./scripts/hatas-korok-post-deploy-smoke.sh https://staging.sharity.hu
```

## Memory/context betöltés munkához

Ha fejleszteni vagy hibát keresni mész a Hatás Körök flow-ban, előtte töltsd be a célzott contextet:

```bash
bash ./scripts/hatas-korok-load-memory.sh
```

Ez frissíti a dev-memory briefet, generál egy dedikált context packot és kiír egy kurált memo fájlt is.

## Mit ellenőriz a script

- `GET /hatas-korok` → `HTTP 200`
- a HTML shell tartalmazza a bootstrap markereket:
  - `Hatás Körök — Impact Community`
  - `id="ic-content"`
  - `window.ImpactCommunity`
- `GET /wp-json/impact/v1/auth/status`
  - van `authenticated`
  - van `nonce`
- `GET /wp-json/impact/v1/circles?page=1`
  - van nem üres `circles[]`
  - van `total`
  - az első körnek van `name`

## Kézi utóellenőrzés

- Nyisd meg böngészőben a `/hatas-korok` oldalt.
- Kattints be legalább egy kör detail nézetébe.
- Nézd meg a `wp-content/debug.log` végét, nincs-e új `impact-community` fatál vagy warning.

## Mikor kell több, mint read-only smoke

Csak akkor futtass write smoke-ot (`join`, `post`, `vote`, `delete`, `leave`), ha:

- deploy közben backend logika is változott, nem csak markup vagy route
- van takarítási terv a tesztadatokra
- tudatosan production smoke-ról van szó

## Megjegyzés

Ez a script direkt read-only alapértelmezésű. A célja, hogy deploy után gyorsan jelezze a route-, bootstrap- vagy alap API regressziót anélkül, hogy production állapotot módosítana.
