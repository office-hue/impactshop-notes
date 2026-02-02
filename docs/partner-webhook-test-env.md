# Webhook teszt környezet (staging)

## Cél
Biztonságos tesztelés a partner webhookokhoz, log‑gyűjtéssel és smoke ellenőrzéssel.

---

## Staging elvek
- **Staging kulcsok**: `pk_test_*`, `test_*`
- **Staging endpoint**: `https://staging.app.sharity.hu/impact/v1/partner/transaction`
- Prod kulcs stagingen **tiltott**

---

## Smoke teszt lépések
1. **HMAC aláírás valid** teszt
2. **Idempotency** teszt (duplikált event)
3. **Pending → approved** flow
4. **Refund → declined** flow

---

## Log gyűjtés
- `impact_audit_log` bejegyzés minden webhookhoz
- Staging log export: napi CSV
- Sablon log mezők:
  - `partner_code`, `event_id`, `status`, `signature_valid`, `latency_ms`

---

## Teszt adat
- Demo partner: `partner_demo`
- NGO: `bator-tabor`
- Amount: 19990 HUF
- Pseudo‑ID: `test_pseudo_01`

---

## Hibakezelés
- HMAC fail → `401` + audit log
- Missing idempotency → `400`
- Invalid payload → `422`

---

## Ajánlott eszközök
- `docs/partner-pilot-tests.md` curl minták
- Postman collection (opcionális)
