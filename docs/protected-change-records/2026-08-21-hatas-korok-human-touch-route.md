# Hatás Körök Human Touch route cutover — protected change record

Date: 2026-08-21

## Approval and scope

Az operátor a live domain-eltérés bizonyítása után kifejezetten kérte a hiba
javítását. A megoldás additív új MU-plugin: a legacy community runtime fájljaihoz
nem nyúl.

## Protected files touched

- `wp-content/mu-plugins/impactshop-hatas-korok-human-touch-route.php`
- `docs/impactshop-protected-files.json`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-guard-config.sha256`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `docs/bastion-guard-status.md`

## Coherence and affected functions

Közvetlenül érintett:

- `template_redirect`, priority 1;
- kizárólag `app.sharity.hu/hatas-korok[/]` GET/HEAD;
- read-only post-deploy smoke és kézi UI checklist.

Változatlan:

- `impact-community.php`, `impact-community-app.php` és community REST;
- `/hatas-korok-dev`, `/impactshop-staging/hatas-korok-dev`;
- profil, identity-return, pont, szavazat, reward, pénz, Offerwall és VB2026;
- admin, AJAX, callback, cron és watchdog.

## Security and risk

- A redirect cél konstans; query, cookie és azonosító nem kerül a Locationbe.
- Exact host/path/method allowlist és admin/AJAX/REST kizárás van.
- 302 + no-cache miatt a cutover visszafordítható.
- A fő kockázat a hibásan túl széles redirect; negatív dev/API tesztek és exact
  Location smoke zárja.

## Bastion and rollback

Az új modul bekerül a protected inventoryba, guard configba és SHA-256
manifestbe. Production release csak merged exact-mainból, CAS/backup/PHP-lint/
atomic apply/0444 relock útvonalon mehet.

Rollback a deploy által kiírt release-ID + deployed-SHA kötött exact rollback
parancs. Első installnál ez csak az új fájlt távolítja el; a legacy handler azonnal
újra 200-as HTML-t szolgál ki. Adat- vagy DB-rollback nincs.

## Mandatory verification

- PHP lint és focused route contract;
- exact 302 + query-free Location;
- target 200 + Human Touch marker;
- auth/circles API shape;
- dev 404, staging-dev 200;
- FactLens VB2026 200 és kézi profil-visszatérés;
- protected-touch, JSON/checksum parity, strict audit, docsync, diff-check.
