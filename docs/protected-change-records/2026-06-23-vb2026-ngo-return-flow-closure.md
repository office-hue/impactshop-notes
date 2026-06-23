# 2026-06-23 - VB2026 NGO return-flow closure

## Scope
- A VB2026 NGO-választási journey source oldali lezárása.
- A Sharity NGO-katalógus és profilpanel közötti selection-intent visszatérési lánc befejezése.
- A `return_to=vb-prod` kanonikus cél végigvezetése a source truth payloadokban.

## Protected Files Touched
- `wp-content/mu-plugins/impactshop-identity-panel.js`

## Related Non-Protected Files
- `wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php`
- `docs/VB2026-SHARITY-NGO-CATALOG-AND-SELECTION-PLAN-2026-06-23.md`
- `notes.md`
- `system-status-snapshot.md`

## Change Summary
- A profilpanel felismeri a `selection_intent` query tokent, meghívja a `POST /wp-json/impact/v1/vb2026/selection-intent/complete` lane-t, és siker esetén a source oldali `redirect_url` szerint továbblép.
- A NGO-katalógus `select-ngo` és `my-ngo-selection` lane-jei explicit `return_to` paramétert kapnak, így a source payload nem vak katalógus-linket, hanem célzott visszatérési útvonalat ad vissza.
- `return_to=vb-prod` esetén a sikeres NGO-választás után a user visszanavigálható a `https://factlens.eu/factlens/vb-prod/?view=sharity` shellre.

## Risk
- A protected profilpanel JS a Sharity fiókoldal egyik központi runtime pereme, ezért hibás branch logika vagy rossz redirect törheti a profilból induló bridge-flow-t.
- Ha a `selection_intent` completion hibásan fut, a user bent ragadhat a profiloldalon vagy a szervezetlistán.

## Rollback
- Git rollback: `git revert <this-commit>`
- Runtime rollback: az előző stabil `impactshop-identity-panel.js` és `impactshop-vb2026-ngo-catalog.php` állapot visszaállítása a legutóbbi prod backupból vagy a merge előtti HEAD-ből.
- Operatív rollback cél: a selection-intent completion ág kikapcsolása, és visszaállás a korábbi source NGO-katalógus flow-ra, ahol nincs automatikus `vb-prod` visszatérés.

## Smoke Scope
- `route:impact-challenge`
- `route:profil`
- `flow:message-popup`
- `flow:points-jump`
- `flow:legacy-pool-visibility`

## Smoke Checklist
- Bejelentkezett userrel a `https://app.sharity.hu/szervezetek/?campaign=vb2026&return_to=vb-prod` megnyílik, és NGO-választás után a user visszakerül a `vb-prod` shellre.
- Ha a user selection-intent tokennel érkezik a profiloldalra, a profilpanel befejezi a választást és visszaléptet.
- A sima profiloldali betöltés selection-intent nélkül nem sérül: a pont/szint/panel továbbra is működik.
