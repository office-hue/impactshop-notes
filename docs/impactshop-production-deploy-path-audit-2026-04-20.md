# Impact Shop Production Deploy-Path Audit

## Kontextus

2026-04-19-én az `impactshop-ads-watch.js` mobil-freeze incidens hotfix deploy közben környezeti drift derült ki a dokumentált production deploy path és a ténylegesen publikus asset között.

## Megfigyelések

- A dokumentált production env jelenleg ezt tekinti source of truth-nak:
  - `SSH_HOST="sharityh@s59.tarhely.com"`
  - `REMOTE_WP_PATH="/home/sharityh/app"`
- A publikus `https://app.sharity.hu/wp-content/mu-plugins/impactshop-ads-watch.js?ver=2.5.65` kezdeti hash-e nem az `/home/sharityh/app/.../impactshop-ads-watch.js` példánnyal, hanem az `/home/sharityh/app-staging/.../impactshop-ads-watch.js` fájllal egyezett.
- A hotfixet ezért rollback-first módon mindkét pathra ki kellett vinni:
  - `/home/sharityh/app`
  - `/home/sharityh/app-staging`
- A szerveroldali JS csere után a publikus asset továbbra is Cloudflare `HIT` cache-ből a régi tartalmat adta.
- A végső cache-bypass lezárást az asset verzió `2.5.65 -> 2.5.66` bumpja adta.

## Bizonyítékok

- publikus HTML végül már ezt hivatkozta:
  - `impactshop-ads-watch.js?ver=2.5.66`
- publikus header:
  - `X-ImpactShop-AdsWatch-Version: 2.5.66`
- publikus JS hash:
  - `3cd313f32a253cff5226a8322a971d7f529bba999cf3b698af22a88da48a614b`
- az ismert szerveroldali példányokban a `2.5.66` konstans már bent van:
  - `/home/sharityh/app/wp-content/mu-plugins/impactshop-ads-watch.php`
  - `/home/sharityh/app-staging/wp-content/mu-plugins/impactshop-ads-watch.php`

## Következtetés

Az audit alapján a kanonikus production WordPress runtime gyökér **`/home/sharityh/app`**.

- A publikus belépési pont **`/home/sharityh/public_html/index.php`**, de ez csak wrapper:
  - `require __DIR__ . '/../app/wp-blog-header.php';`
- Ez azt jelenti, hogy a deploy/runbook truth helyesen az `.deploy.production.env` szerinti `/home/sharityh/app`.
- Az incidens közbeni `app-staging` asset-egyezés nem source-of-truth, hanem környezeti drift/cache parity jel volt.
- A guard deploy irányt tehát nem átírni kell `app-staging`-re, hanem expliciten rögzíteni, hogy productionön a `public_html` csak entry wrapper, a valódi origin pedig az `app`.

## Nyitott kérdések

1. Mi okozta pontosan az `app-staging` asset-egyezést az incidens időablakában?
2. Cache parity, kézi restore vagy történeti fájlszinkron állt mögötte?
3. Van-e olyan vhost/rewrite vagy operátori útvonal, amely production incidensnél még mindig két pathot érinthet?

## Döntés

- A production source of truth kimondva: `/home/sharityh/app`
- A `public_html` szerepe: entry wrapper, nem deploy target
- A guard deploy infrastruktúrát ehhez kell igazítani és ezzel kell ellenőrizni
- Az `app-staging` production-egyezést külön drift-megfigyelésként kell kezelni, nem kanonikus célpathként
