# Partner integráció – master checklist

## 1) Adatmodell és ledger
- [ ] Döntés: ledger bővítés vs új `wp_impact_partner_tx`
- [ ] DDL véglegesítése + indexek
- [ ] Migrációs terv + rollback
- [ ] Ledger linkelés `ledger_id`‑val

## 2) Partner konfiguráció
- [ ] `wp_impact_partner_config` tábla létrehozás
- [ ] Max kedvezmény, cap, min kosár, stacking flag
- [ ] Konfig cache (transient) + invalidálás
- [ ] Audit log konfiguráció módosításokra

## 3) Auth & secrets
- [ ] API kulcs + HMAC séma véglegesítése
- [ ] Key‑id támogatás (rotáció)
- [ ] Staging/prod kulcsok elkülönítése
- [ ] Audit log sikertelen auth‑ra

## 4) Webhook feldolgozás
- [ ] Idempotency kezelése
- [ ] HMAC ellenőrzés + `proof_hash`
- [ ] Pending → approved/declined flow
- [ ] Visszajelzés: `ledger_id`, státusz

## 5) Reconciliation + dispute
- [ ] Napi/heti reconcile job
- [ ] Reconcile státuszok bevezetése
- [ ] Dispute endpoint + audit
- [ ] CSV export

## 6) Dashboard + UI
- [ ] Partner dashboard (KPI + webhooks)
- [ ] Vásárlói dashboard (szint + kedvezmény)
- [ ] Dispute státusz megjelenítés

## 7) Monitoring + KPI
- [ ] Approved ratio, pending idő, dispute arány
- [ ] Webhook success/fail trend
- [ ] Riasztási küszöbök

## 8) Staging tesztkörnyezet
- [ ] Staging kulcsok (test prefix)
- [ ] Smoke teszt (HMAC + idempotency)
- [ ] Log export

## Kapcsolódó dokumentumok
- `docs/non-affiliate-integration-plan.md`
- `docs/partner-db-schema.md`
- `docs/partner-config-storage.md`
- `docs/partner-auth-secrets.md`
- `docs/partner-reconciliation-job.md`
- `docs/partner-dashboard-wireframes.md`
- `docs/partner-webhook-test-env.md`
- `docs/partner-monitoring-kpi.md`
