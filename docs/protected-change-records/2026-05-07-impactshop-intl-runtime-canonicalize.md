# Protected Change Record: Impact Shop / Impact Challenge intl runtime canonicalize

## Scope

- Cél: a live-on validált EN shop és EN challenge runtime állapot kanonikus commitba rendezése.
- Határ: csak a jelenleg kint lévő és Playwright-tal ellenőrzött locale-, fordítás- és megjelenítési runtime változások kerülnek be.
- Kifejezetten kívül marad minden más dirty worktree módosítás.

## Protected Files Touched

- `wp-content/mu-plugins/impactshop-ads-watch.php`
- `wp-content/mu-plugins/impactshop-ads-watch.js`
- `wp-content/mu-plugins/impactshop-identity-panel.php`

## Additive Runtime Files

- `wp-content/mu-plugins/impactshop-ads-watch-intl-overlay.js`
- `wp-content/mu-plugins/impactshop-identity-panel-intl-overlay.js`

## Coherence / Impact

- Érintett route-ok: `/impact-challenge/`, `/impactshop/`, locale querys stringes shop nézetek.
- Érintett frontend belépési pontok: ads-watch runtime, challenge placeholder rendering, identity panel asset enqueue, shop intl overlay.
- Érintett user-facing funkciók: EN fordítások, locale-aware placeholder szövegek, USD megjelenítés, shop linkek kattinthatósága.
- Nem cél: admin, közösségi, CJ, leaderboard, NGO card vagy más párhuzamos worktree módosítások kanonizálása.

## Risk

- Közvetlen kockázat: challenge vagy profil közeli felületeken regresszió, ha az i18n payload vagy az identity panel enqueue rosszul töltődik.
- Közvetett kockázat: a locale overlay újrafutása felülírhatna már jó DOM-ot, ezért a scope szűk marad és a meglévő live viselkedést rögzíti.
- Rejtett regresszió: más protected runtime ágakkal való keveredés, ezért csak a validált öt runtime fájl és a két overlay asset kerül commitba.

## Rollback

- Rollback plan: revert this commit, majd szükség esetén a legutóbbi hotfix-sync rollback script futtatása a `.codex/reports/hotfix-sync/` alól.
- Rollback leírás: ha a merge utáni smoke driftet mutat, a commit azonnal revertálható, mert nem kever más dirty worktree fájlokat.

## Smoke

- Smoke scope: `route:impact-challenge`, `flow:video-start`, `flow:cta-click`, `flow:reward-accumulation`, `browser:webkit`, `browser:chrome`, `route:profil`, `flow:message-popup`, `flow:points-jump`, `flow:legacy-pool-visibility`.
- Manuális UI ellenőrzőlista:
  - `/impact-challenge/?lang=en` alatt a chance placeholder legyen angol.
  - `/impactshop/?lang=en&country=us` alatt az angol shop szövegek maradjanak angolok és a linkek kattinthatók maradjanak.
  - A locale-váltás után a panel assetek betöltődjenek cache drift nélkül.

## Validation Evidence

- PHP lint rendben: `impactshop-ads-watch.php`, `impactshop-identity-panel.php`.
- JS syntax check rendben: `impactshop-ads-watch.js`, `impactshop-ads-watch-intl-overlay.js`, `impactshop-identity-panel-intl-overlay.js`.
- Git health check rendben.
- Safe repo audit rendben.