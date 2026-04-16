# Change Record: ads-watch iOS ytDur=0 fallback deadline (v2.5.64)

**Dátum:** 2026-04-10
**Verzió:** v2.5.64
**Érintett fájl:** wp-content/mu-plugins/impactshop-ads-watch.js

## Probléma
iOS WebKit a PLAYING eventet korábban tüzeli mint getDuration() érvényes értéket ad.
ytDur=0 esetén nem volt deadline → ENDED miss → végtelen freeze iPhone-on.

## Fix
Ha ytDur=0, fallback 180s deadline kerül beállításra.

## Rollback
git checkout 15ada6b8 -- wp-content/mu-plugins/impactshop-ads-watch.js
