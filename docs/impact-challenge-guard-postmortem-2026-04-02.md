# Impact Challenge Guard Postmortem 2026-04-02

## Incident
- A production `https://app.sharity.hu/impact-challenge/` route elvesztette a 8 ikonos action bar shellt, és a legacy 4 tabos UI maradt látható.

## Confirmed Cause
- A production deploy lane a teljes `wp-content/mu-plugins` mappinget szinkronizálja.
- A kanonikus source repóban nem volt jelen két, az IC shellhez szükséges fájl:
  - `wp-content/mu-plugins/impactshop-action-bar.php`
  - `wp-content/mu-plugins/zzz-impactshop-ui-lock.php`
- Emiatt egy mapped deploy `rsync --delete` mellett el tudta távolítani őket a production célról.

## Broken Controls
- A guard config nem tartalmazta explicit protected fájlként ezt a két shell-fájlt.
- A `zzz-impactshop-ui-lock.php` csak additive státuszban szerepelt a protected modellben.
- A deploy lane nem ellenőrizte, hogy a mapped protected fájlok ténylegesen jelen vannak-e a source repóban.
- A non-interactive `--auto-approve` lane nem különböztette meg a guard control-plane változásokat az IC runtime/shell driftől.
- Az IC UI smoke scriptre már volt hivatkozás, de a script nem létezett a repóban.

## Controls Added
- A két IC shell-fájl explicit protected lett a guard configban.
- A deploy lane most hard-faillel blokkol, ha egy mapped protected source hiányzik.
- A `zzz-impactshop-ui-lock.php` átkerült valódi protected státuszba.
- A non-interactive `--auto-approve` most már csak guard control-plane fájlokra engedett; IC runtime/shell driftre tiltott.
- Elkészült a `scripts/impact-challenge-ui-smoke.sh` sentinel smoke, amely a 8 ikont és a legacy tabs rejtését ellenőrzi.

## Verification
- `php -l wp-content/mu-plugins/impactshop-action-bar.php`
- `php -l wp-content/mu-plugins/zzz-impactshop-ui-lock.php`
- `bash -n bin/deploy-wpcontent-map.sh`
- `bash -n bin/impactshop-guard-deploy.sh`
- `bash -n scripts/check-protected-file-touch.sh`
- Szimuláció: hiányzó source esetén a deploy block listázza a két shell-fájlt.
- Szimuláció: `impactshop-ads-watch.php`, `impactshop-action-bar.php`, `zzz-impactshop-ui-lock.php` auto-approve alatt `BLOCKED`.

## Operational Rule
- Guide vagy más unrelated deploy nem vihet ki IC runtime/shell változást auto-approve úton.
- Ha az IC shellhez kapcsolódó protected fájl changed, csak kézi jóváhagyás + rollback artifact + explicit smoke mellett mehet tovább.
