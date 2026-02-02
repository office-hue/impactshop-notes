# Non-affiliate partner integracio – vegleges terv (koherencia + biztonsag)

## Cel es scope
A cel egy auditálható, idempotens, HMAC-kal vedett partner API bevezetese (webhook + kedvezmeny + dispute), amely a non-affiliate tranzakciokat kezeli, es partner dashboard/monitoring is rendelkezesre all.

**In-scope (most):**
- `POST /impact/v1/partner/transaction`
- `POST /impact/v1/partner/discount/quote`
- `POST /impact/v1/partner/dispute`

**Out-of-scope (kesobb):**
- QR-OTP, NFC, hospitality token lifecycle (`/identity/qr-otp`, `/token/*`). Ezek kulon roadmapon maradnak, ameddig nincs OpenAPI + implementacios terv.

## Forrasok
- Fo terv: `docs/non-affiliate-integration-plan.md`
- OpenAPI: `docs/partner-api-openapi.yaml`
- Osszefoglalo: `docs/partner-summary.md`
- DB es migracio: `docs/partner-db-schema.md`, `docs/partner-db-migration-template.md`
- Config: `docs/partner-config-storage.md`, `docs/partner-config-validation.md`
- Auth/secrets: `docs/partner-auth-secrets.md`
- Reconcile/dispute: `docs/partner-reconciliation-job.md`, `docs/partner-dispute-policy.md`
- Monitoring: `docs/partner-monitoring-kpi.md`
- UI: `docs/partner-dashboard-wireframes.md`, `docs/partner-admin-ui-draft.md`, `docs/partner-admin-permissions.md`
- Biztonsag + retry: `docs/partner-webhook-security-checklist.md`, `docs/partner-webhook-retry-spec.md`

## API specifikacio (koherens, implementalando)
### 1) Partner transaction webhook
**Endpoint:** `POST /impact/v1/partner/transaction`

**Kotelezo headerek:**
- `Authorization: Bearer <partner_api_key>`
- `X-Impact-Signature: sha256=<hex>` (raw JSON body HMAC)
- `Idempotency-Key: <key>`
- `X-Impact-Timestamp: <epoch_ms>`

**Kotelezo mezok:**
`partner_code, event_id, event_type, pseudo_id, ngo_code, amount_gross, currency, timestamp, payment_status`

**Engedelyezett event_type:** `purchase | booking | retail`
**payment_status:** `paid | unpaid | refunded`

**Valasz:**
- `200` (accepted/duplicate)
- `409` (idempotency mismatch)
- `401` (invalid signature)
- `403` (unauthorized)
- `422` (invalid state)

**Megjegyzes:**
- Refund nem uj event_type: `event_type=purchase`, `payment_status=refunded`.
- Dupe eseten ugyanaz a valasz legyen (status + ledger_id).

### 2) Kedvezmeny kalkulacio
**Endpoint:** `POST /impact/v1/partner/discount/quote`

**Kotelezo headerek:**
- `Authorization`, `X-Impact-Signature`, `X-Impact-Timestamp`

**Input:** `partner_code, pseudo_id, amount_gross, currency`

**Output:** `tier, partner_max_discount, discount_rate, discount_amount, amount_net, explain`

### 3) Dispute nyitas
**Endpoint:** `POST /impact/v1/partner/dispute`

**Kotelezo headerek:**
- `Authorization`, `X-Impact-Signature`, `X-Impact-Timestamp`

**Input:** `ledger_id, event_id, partner_code, reason, details?`

**Output:** `status=opened, dispute_id, ledger_id`

## Koherencia ellenorzes – kritikus megallapitasok es dontesek
1) **Staging base URL elteres:** OpenAPI `https://staging.app.sharity.hu/wp-json`, a tenyleges staging host `https://app.sharity.hu/impactshop-staging/wp-json`.
   - **Döntes:** canonical base URL a `app.sharity.hu/impactshop-staging` legyen, OpenAPI-t ehhez igazitani kell.
2) **X-Impact-Timestamp:** OpenAPI + peldaiban szerepel, a fo tervben nincs.
   - **Döntes:** kotelezo header, replay vedelmi ablak ellenorzessel.
3) **Pseudo ID validacio:** OpenAPI regex `^[a-z0-9]{10,12}$`, de a fixture-ekben `test_pseudo_01` szerepel.
   - **Döntes:** fixture-eket realis mintara kell frissiteni a tesztekhez.
4) **Event scope:** Hospitality/QR-OTP flow nincs az OpenAPI-ban.
   - **Döntes:** ez out-of-scope, csak a partner endpoints a scope.

## Biztonsagi kovetelmenyek
- **HMAC SHA256 a raw JSON body** alapjan, `X-Impact-Signature: sha256=<hex>`.
- **X-Impact-Timestamp** ellenorzese (javasolt ablak: +-5 perc).
- **Idempotency-Key** kotelezo, TTL 24 ora.
- **Kulcs rotacio**: key_id tamogatasa (dual-key ablak).
- **Rate limit**: partnerenkent (pl. 60 rpm), kulon 429 es audit log.
- **Audit log**: minden auth + payload validacio, `request_id`, `partner_code`, `event_id` mezokkel.

## Adatmodell (ajánlott)
**Uj tabla:** `wp_impact_partner_tx` (ld. `docs/partner-db-schema.md`)
- `partner_code + event_id` unique
- `idempotency_key` kulon mezoben
- `pseudo_id_hash` tarolas (raw pseudo_id csak payload_json-ban, retention szerint)
- `status`: `pending | approved | declined | void`
- `ledger_id` link csak approved eseten

**Retention:**
- `payload_json`: 180 nap
- `proof_hash` + fo mezok: 3 ev

## Feldolgozasi flow (transaction)
1) **Auth + HMAC + timestamp** ellenorzes
2) **Idempotencia**: ha letezik kulcs, azonos valasz vissza
3) **Payload validacio** (regex + required + amount/currency)
4) **Partner config ellenorzes** (allowed_event_types, cap, min cart)
5) **Mentés** `wp_impact_partner_tx` (status = pending)
6) **Status mapping (alapdontes)**:
   - `payment_status=paid` -> `approved` + ledger bejegyzes azonnal
   - `payment_status=unpaid` -> `pending` (ledger nelkul)
   - `payment_status=refunded` -> `declined` + ledger visszaforditas, ha mar letrejott
7) **Ledger update** csak `approved` eseten

## Kedvezmeny logika
- Szintek a pontrendszer spec szerint (basic->legend).
- `partner_max_discount` a forras, a tier szorzoival kalkulalunk.
- Kerekites: 0.5% lepcso.
- `discount_cap_amount` + `discount_min_cart` betartasa.
- `discount_stackable` figyelembe vevo logika.

## Dispute + Reconcile
- Dispute endpoint auditalt, SLA: 2 munkanap visszajelzes, 5 nap dontes.
- Napi/heti reconcile job, `(partner_code, event_id)` egyezessel.
- Reconcile status: `matched | mismatch | missing | extra | disputed`.

## Monitoring es dashboard
- KPI-k: approved ratio, pending ido, dispute arany, webhook success rate.
- Dashboard blokkok: status megoszlas, trendek, top NGO/partner.
- Alert threshold: approved ratio < 70%, fail rate > 2% (1h).

## UI es jogosultsagok
- Partner dashboard (read + export + dispute status).
- Admin UI: `partner_admin / partner_ops / partner_readonly / impact_admin` cap matrix.
- Kulcs rotacio csak `partner_admin` vagy `impact_admin`.

## Teszt es QA
- Fixtures: `fixtures/partner/*.json` (valid pseudo_id mintakra igazitani).
- Postman: `docs/partner-postman-collection.json` + HMAC pre-request.
- Runner: `tools/partner-test-runner.cjs`.
- OpenAPI check: `tools/openapi-check.cjs`.

## Rollout terv (rovid)
1) Staging kulcsok + config felvitel
2) API smoke (Postman + runner)
3) Reconcile job dry-run
4) Monitoring dashboard bekotes
5) Production kulcsok + partner onboarding

## Nyitott kerdesek
- `approved` automatikus vagy manual review alapu legyen default?
- `refunded` eseten ledger visszaforditas vagy csak status?
- Discount quote currency konverzio (HUF/EUR/USD) forras?
