# Partner audit log – eseménykatalógus

## Cél
Egységes eseménylista a partner folyamatok auditálásához.

---

## Események
- `partner_tx_received` – webhook beérkezett
- `partner_tx_duplicate` – idempotency dupe
- `partner_tx_rejected` – validáció/HMAC hiba
- `partner_tx_pending` – pending státusz
- `partner_tx_approved` – jóváhagyva
- `partner_tx_declined` – elutasítva
- `partner_tx_void` – technikai törlés
- `partner_reconcile_run` – egyeztetés futtatva
- `partner_reconcile_mismatch` – eltérés
- `partner_dispute_opened` – vita nyitás
- `partner_dispute_resolved` – vita lezárás
- `partner_config_update` – partner config módosítás
- `partner_key_rotated` – kulcs rotáció
- `partner_auth_failed` – auth fail

---

## Kötelező audit mezők
- `partner_code`
- `event_id` (ha releváns)
- `status`
- `actor` (system/ops/admin)
- `created_at`
