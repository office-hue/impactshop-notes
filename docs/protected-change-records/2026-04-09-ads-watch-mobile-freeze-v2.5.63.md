## Összefoglaló
- ads-watch v2.5.63: két mobil freeze bug javítása Android Chrome-on
- Bug #1: `_bannerDeadlineId` lokális closure var → `state.autoBannerDeadlineId` state property
  - `stopAutoBannerProgress()` mostantól képes törölni a deadline timeout-ot → nincs ghost AJAX resetPlayer után
- Bug #2: YouTube sponsor ENDED event nem garantált Android Chrome háttérben
  - `state.sponsorYoutubeDeadlineId` hard deadline a PLAYING event alapján (ytDur + 3s grace)
  - csak akkor tüzel ha `sponsorCompletionFired === false && isPlaying && mode === 'sponsor'`
  - ENDED handler is törli a deadline-t (desktop: no-op; mobil: duplázás megelőzése)
  - `resetEducationState()` törli a deadline-t

## Érintett fájlok
- `wp-content/mu-plugins/impactshop-ads-watch.js`
- `wp-content/mu-plugins/impactshop-ads-watch.php`
- `sw.js`

## Kockázat
- Alacsony: csak additive/defensive változások
- Desktop: ENDED event normálisan érkezik → `sponsorCompletionFired = true` → deadline no-op
- Mobil: ENDED nem érkezik → deadline tüzel → handleAdCompletion → gomb újra aktív
- resetPlayer → stopAutoBannerProgress → clearTimeout(autoBannerDeadlineId) → nincs ghost AJAX

## Rollback
- git revert HEAD a hotfix/mobile-freeze-v2.5.63 branchen
- `BASTION_OVERRIDE=1 bash scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-ads-watch.js wp-content/mu-plugins/impactshop-ads-watch.php sw.js`

## Ellenőrzés
- Android Chrome privát ablak → `app.sharity.hu/impact-challenge`
- Synlab IMA ad → auto banner ~5s → lejár → Watch YouTube gomb → sponsor video → gomb újra aktív
- Desktop: változatlan viselkedés ellenőrzése (Chrome, Firefox, Safari)

## Smoke tag
- ads-watch-v2-5-63-mobile-freeze-fix
