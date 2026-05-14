# Protected Change Record — 2026-05-14 NGO Guides Redirect Fix

## Protected files touched

- `wp-content/mu-plugins/impactshop-ngo-guides.php`

## Root cause

A server-side hotfix (2026-05-05) a redirect logikát szerverre írta, de nem commitolta git repóba. A következő teljes deploy (`deploy-wpcontent-map.sh`) az rsync `--delete` flag miatt felülírta a fájlt a redirect-nélküli verzióval.

**Fix strategy**: A redirect logika stabil PHP code-ba kerül az eredeti `template_redirect()` hook-ba, amit a git source of truth semmilyen deploy-delete-vel nem fog módosítani.

## Rollback

Kimeneti útvonal: `git revert <commit>` ezen a branchen, vagy szerveren direkt restore:

**Git rollback:**
```bash
git revert 15de3677
```

**Server-side restore (ha szükséges):**
```bash
cp ~/impactshop-ngo-guides.php.bak-20260514 ~/app/wp-content/mu-plugins/impactshop-ngo-guides.php
php -l ~/app/wp-content/mu-plugins/impactshop-ngo-guides.php
wp cache flush --all
```

Érintett fájl: `wp-content/mu-plugins/impactshop-ngo-guides.php`

## Smoke scope

- `route:jysk-riport` — jysk szlug alapú riport render
- `route:ngo-guides` — ngo guides általános routing
- `flow:guide-route-render` — template render logika
- `flow:guide-print-mode` — print mód (template_redirect nem módosít)
- `flow:guide-data-json` — JSON export (template_redirect nem módosít)
- `deploy:guard-preflight` — deploy pre-flight checklist
- `deploy:checksum-verify` — fájl checksum verifikáció post-deploy

## Verification

- **Local test**: PHP syntax OK, vsCode error scanner OK
- **Production test (2026-05-14 09:30 UTC)**:
  - `curl -L https://app.sharity.hu/adomany-automata-portal-1/` → 301 redirect → `https://app.sharity.hu/?impact_event_auction_embed=1&slug=jovonkvize-2026` → 200
  - `curl -L https://app.sharity.hu/adomany-automata-portal-2/` → 301 redirect → `https://app.sharity.hu/?impact_event_auction_embed=1&slug=jovonkvize-2026` → 200
- **Server backup**: `/home/sharityh/impactshop-ngo-guides.php.bak-20260514` (pre-redirect version, 459 lines)
- **Production file**: now 467 lines with redirect block

## Additional notes

- Commit hash for reference: `15de3677`
- No schema changes, no transient cache impacts, no API changes
- Redirect is early `template_redirect` hook (priority 0), executes before page template logic
- 301 permanent redirect ensures browser caching and SEO best practices
