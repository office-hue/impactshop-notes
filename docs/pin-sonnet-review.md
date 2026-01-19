# PIN token rendszer – Sonnet vélemény (összegzés)

## Áttekintett modulok
- `wp-content/mu-plugins/impactshop-identity-pin.php`
- `wp-content/mu-plugins/impactshop-identity-pin-migration.php`
- `wp-content/mu-plugins/impactshop-identity-pin-sms-vonage.php`
- `wp-content/mu-plugins/impactshop-identity-pin-qr-quickchart.php`
- `wp-content/mu-plugins/impactshop-identity-pin-cron.php`

## Fő megállapítások
**Erősségek**
- Prepared statementek minden SQL művelethez.
- Többszintű rate limit (IP + pseudo + regen).
- Audit log JSON formátumban.
- Moduláris hookok SMS/QR integrációra.

**Kockázatok**
- PIN verify időzítéses megfigyelhetősége (timing attack).
- Kombinált IP+pseudo rate limit hiánya.
- Logfájlok rotációjának hiánya.
- Konstansok hardcode‑olt értékei (env‑enként nehéz hangolás).

## Ajánlott javítások (prioritás szerint)
**P0 (kritikus)**
1. Kombinált IP+pseudo limit (botnet ellen).
2. Log rotáció az audit + delivery logokra.
3. Konstansok env‑fájlba (staging/prod hangoláshoz).

**P1 (fontos)**
4. PIN verify timing védelem (konstans késleltetés).
5. Composite index a `wp_impact_pin_tokens` táblán.
6. Vonage retry logic (transient hálózati hibák).
7. Health endpoint kiegészítése PIN státusszal.

**P2–P3 (backlog)**
8. QR payload validáció.
9. Structured logging egységes JSON sémával.
10. Metrics endpoint (Prometheus).
11. PHPUnit skeleton + teszt mód flag.
12. Hibakód dokumentáció + sequence diagram.

## Ajánlott megvalósítási sorrend
Kezdés: P0 → P1 → backlog (P2–P3), minden lépés után staging smoke.

## Status (2026-01-18)
- P0 megvalósítva: kombinált IP+pseudo limit, log rotáció, `PIN_*` env konfiguráció.
- P1 megvalósítva: timing védelem, composite index, Vonage retry, health bővítés.

## Új javaslatok (P2–P3, még nem implementált)

**P2 (javasolt)**
1. Használt PIN újrakiadás audit trail (`impactshop_identity_pin_reissue_after_use`).
2. QR payload validáció + invalid esemény hook.
3. Test mode flag (`PIN_TEST_MODE`) staginghez.
4. Hibakód dokumentáció (frontend UX).
5. Sequence diagram a PIN flow-hoz.

**P3 (backlog)**
6. IP spoofing védelem `X-Forwarded-For` trusted proxy listával.
7. Objektum cache figyelmeztetés adminban.
8. Batch cleanup a nagy tábla törléséhez.
9. Prometheus metrics endpoint.
10. Structured logging (audit + delivery egységes JSON).
11. PHPUnit teszt alapok.
12. Migration history tábla.
13. PHPDoc minden publikus függvényhez.

## Status (2026-01-18) – P2 kiegészítések
- Megvalósítva: audit trail reissue hook, QR payload validáció, `PIN_TEST_MODE`.
- Dokumentáció: `docs/pin-error-codes.md`, `docs/pin-sequence-diagram.md`.

## Status (2026-01-18) – P3 kiegészítések
- Megvalósítva: IP spoofing védelem, object cache notice, batch cleanup,
  metrics endpoint, structured logging, migration history tábla.
- Megvalósítva: PHPUnit skeleton + PHPDoc minden publikus függvényhez.
