# 2026-06-23 - VB2026 NGO source callback hotfix

- Scope: `wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php`
- Change type: szuk prod-helyreallito hotfix

## Mi tortent

A VB2026 NGO source lane publikus katalogus oldala es a kapcsolodo REST route-ok nem alltak fel stabilan a Sharity source oldalon, mert a `template_redirect` hook rossz callback-nevre mutatott.

## Root cause

- Regisztralt hook:
  - `impactshop_vb2026_ngo_catalog_template_redirect`
- Tenylegesen letezo handler:
  - `impactshop_vb2026_catalog_template_redirect`

Ez azt jelentette, hogy a katalogus oldal template-lane-je nem a tenyleges handlerre kotodott.

## Javitas

- A `template_redirect` hook a tenyleges `impactshop_vb2026_catalog_template_redirect` callbackre lett visszaallitva.
- A javitas szuk scope-ban maradt; a guard-hash ujrageneralasi mellekhatasok nem reszei ennek a hotfixnek.

## Verifikacio

- `php -l wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php` PASS
- Prod source lane ujra zold:
  - `GET /wp-json/impact/v1/ngo-catalog`
  - `GET /wp-json/impact/v1/vb2026/featured-ngos?campaign=vb2026`
  - `GET /szervezetek/?campaign=vb2026`

## Rollback

- Git rollback: revert of commit `04cc9845`
- Runtime rollback csak akkor indokolt, ha a callback-fix utan regresszio igazolhato; egyebkent ez a commit a hibas hook-paritast allitja helyre.
