## Összefoglaló
- szűk incidens-hotfix az Impact Challenge mobil-only freeze kivizsgálására és csillapítására
- célzottan a `handleWindowResize()` / `adsManager.resize(...)` ág terhelését csökkenti
- a javítás nem nyúl az observer ághoz, az külön lane marad

## Protected files touched
- `wp-content/mu-plugins/impactshop-ads-watch.js`

## Érintett változás
- új state mezők:
  - `lastImaResizeAt`
  - `lastImaResizeWidth`
  - `lastImaResizeHeight`
- a resize handler most:
  - rejtett dokumentum alatt nem hív IMA resize-t
  - kis vagy duplikált méretváltozást eldob
  - resize burst alatt throttlingot használ
  - csak valid konténerméretre hív `adsManager.resize(...)`

## Kockázat
- alacsony-közepes
- a változás egyetlen protected runtime fájlra szűkül
- fő regressziós felület: IMA overlay méretezés orientation vagy viewport váltás közben
- nem érinti közvetlenül az action bar, offerwall, AyeT vagy Impact Shop route logikát

## Rollback
- lokális backup:
  - `.codex/backups/20260419-113424-mobile-freeze-resize/impactshop-ads-watch.js.pre`
- lokális visszaállítás:
  - `.codex/backups/20260419-113424-mobile-freeze-resize/rollback.sh`
- production rollback terv:
  - a jelenlegi éles `impactshop-ads-watch.js` fájlról deploy előtt időbélyeges távoli backup készül
  - regresszió esetén azonnali visszamásolás a távoli backupból
  - utána `wp cache flush`
- tényleges távoli backupok:
  - `/home/sharityh/app/.codex-hotfix-backups/20260419-mobile-resize-hotfix/impactshop-ads-watch.js.20260419-133823.pre`
  - `/home/sharityh/app-staging/.codex-hotfix-backups/20260419-mobile-resize-hotfix/impactshop-ads-watch.js.20260419-133941.pre`

## Smoke scope
- `route:impact-challenge`
- `flow:video-start`
- `flow:cta-click`
- `flow:reward-accumulation`
- `browser:webkit`
- `browser:chrome`
- `route:impactshop`
- `flow:saved-offers-open`
- `flow:go-deal`

## Ellenőrzés
- mobil shell load az `https://app.sharity.hu/impact-challenge/` route-on
- videóindítás és IMA konténer stabilitás
- `video -> tasks -> video -> stats`
- külső CTA vagy sponsor visszatérés után nincs freeze
- Impact Shop alap route smoke és nincs új közös runtime hiba

## Megjegyzés
- a `Clear-Site-Data` drift kifejezetten out of scope ebben az incidens-hotfixben
- a `2.5.55` working snapshot csak baseline, nem automatikus rollback cél

## Tényleges deploy kimenet
- a szűk incidens-hotfix szerveroldalon sikeresen kiment mindkét érintett példányra:
  - `/home/sharityh/app/wp-content/mu-plugins/impactshop-ads-watch.js`
  - `/home/sharityh/app-staging/wp-content/mu-plugins/impactshop-ads-watch.js`
- a deploy vizsgálat közben kiderült, hogy a publikus `https://app.sharity.hu/wp-content/mu-plugins/impactshop-ads-watch.js?ver=2.5.65`
  régi hash-e nem az `app` path-szel, hanem az `app-staging` példánnyal egyezett
- ez deploy-path / docroot driftre utal a production env dokumentáció és a ténylegesen kiszolgált asset között
- a publikus URL a frissítés után is Cloudflare `HIT` cache-ből a régi assetet szolgálta (`afedb76c...`)
- ebből következően a szerveroldali hotfix kint van, de a kliensoldali látható hatás külön CDN purge vagy verzióbump nélkül nem garantálható azonnal

## Cache-bypass lezárás
- külön follow-up körben az `impactshop-ads-watch.php` asset verziója `2.5.65` → `2.5.66` értékre emelve
- cél: új `impactshop-ads-watch.js?ver=2.5.66` URL generálása, hogy a beragadt Cloudflare cache megkerülhető legyen purge nélkül is
- lokális PHP backup:
  - `.codex/backups/20260419-ads-watch-version-bump/impactshop-ads-watch.php.pre`
- távoli PHP backupok:
  - `/home/sharityh/app/.codex-hotfix-backups/20260419-ads-watch-version-bump/impactshop-ads-watch.php.20260419-160708.pre`
  - `/home/sharityh/app-staging/.codex-hotfix-backups/20260419-ads-watch-version-bump/impactshop-ads-watch.php.20260419-160708.pre`
- verifikáció:
  - publikus HTML már az új assetet hivatkozza: `impactshop-ads-watch.js?ver=2.5.66`
  - publikus header: `X-ImpactShop-AdsWatch-Version: 2.5.66`
  - publikus JS hash: `3cd313f32a253cff5226a8322a971d7f529bba999cf3b698af22a88da48a614b`
  - `impact-challenge-ui-smoke.sh` továbbra is OK
