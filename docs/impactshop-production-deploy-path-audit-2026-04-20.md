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

A production deploy út jelenleg nincs elég szorosan összezárva a ténylegesen kiszolgált origin/docroot valósággal. A guard deploy runbook és a valós live asset útvonal között legalább egy történeti vagy vhost szintű drift létezik.

## Nyitott kérdések

1. Az `app.sharity.hu` tényleges document rootja mi?
2. Az `/home/sharityh/app-staging` miért tudott egyezni a publikus assettel productionön?
3. Van-e olyan vhost/symlink/release mapping, amit a `.deploy.production.env` jelenleg nem ír le?
4. A JS és a PHP miért ugyanabba a drift-mintába futott bele?

## Döntési javaslat

- A kanonikus production source of truth-ot külön audit eredménnyel ki kell mondani.
- Addig a mostani incidens-hotfixet érvényes, de nem teljesen kanonikus restore-nak kell tekinteni.
- A guard deploy infrastruktúrát csak az audit lezárása után szabad átállítani.
