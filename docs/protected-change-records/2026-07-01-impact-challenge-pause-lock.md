# Protected Change Record

- Date: 2026-07-01
- Scope: Impact Challenge atmeneti pause-lock + frozen tally hotfix
- Reason: A lezart Impact Challenge ujraaktivodott, ezert a publikus route-on atmeneti maintenance allapot kellett: a mai aktivitasi/szavazasi gombok befagyasztasa, a napi szavazatok nullazasa, valamint a 2026-06-30 23:59:59 allapot szerinti vegeredmeny publikus megjelenitese.
- Risk: Medium (protected Impact Challenge runtime + REST response override + public scoreboard freeze)
- Rollback: A commit teljes visszavonasa (`git revert <commit>`), vagy a megelőző repo-tracked `wp-content/mu-plugins/zzz-impactshop-ui-lock.php` allapot visszaallitasa. Live hotfix rollback artefaktok: `.codex/reports/hotfix-sync/rollback_20260701T051021Z.sh`, `.codex/reports/hotfix-sync/rollback_20260701T051354Z.sh`.

## Protected Files Touched

- `wp-content/mu-plugins/zzz-impactshop-ui-lock.php`

## Runtime Contract

- A `/impact-challenge/` route egy designos maintenance bannert mutat, kattinthato `https://factlens.eu/vb2026/` linkkel.
- Az oldalon vegezheto aktivitasi elemek (`watch`, `allocate`, NGO valtas, auto-vote, kapcsolodo CTA-k) vizualisan es interakciosan is fagyasztottak.
- A `status` REST valasz a napi aktivitasra `0` allapotot ad vissza.
- A `tally` REST valasz a `2026-06-30 23:59:59` cutoff szerinti publikus vegallast adja vissza, nem a frissen ujranyilt negyedevet.
- A donation pool truth a cutoff quarterhoz kotodik; a follow-up fix celja pont az volt, hogy ne az aktualis quarter key (`2026Q3`), hanem a cutoffbol levezetett quarter key (`2026Q1`) alapjan szamoljon.

## Validation Plan

- `php -l wp-content/mu-plugins/zzz-impactshop-ui-lock.php`
- `curl -I https://app.sharity.hu/impact-challenge/`
- `curl -s https://app.sharity.hu/impact-challenge/` es HTML ellenorzes a maintenance bannerre, a `factlens.eu/vb2026` linkre es a `Rangsor: 2026.06.30. 23:59:59` szovegre
- `curl -s 'https://app.sharity.hu/wp-json/impact/v1/ads-watch/tally?limit=5'`
- Remote parity: local/production/staging `sha256sum` egyezes a celzott MU-pluginra
