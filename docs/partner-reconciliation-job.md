# Reconciliation job – napi/heti egyeztetés

## Cél
A partner által küldött tranzakciók és az Impact Shop ledger között **rendszeres egyeztetés**, eltérések kezelése és dispute státusz kezelése.

---

## Ütemezés
- **Napi**: gyors egyeztetés (utolsó 24 óra)
- **Heti**: teljes körű egyeztetés (7 nap)

---

## Input források
- `wp_impact_partner_tx` (partner tranzakciók)
- `wp_impact_ledger` (jóváhagyott tételek)
- Partner riport (CSV/API) – opcionális

---

## Egyeztetési logika
1. **Match**: `(partner_code, event_id)` egyezés
2. **Összeg ellenőrzés**: `amount_gross` ±1% tolerancia
3. **Státusz ellenőrzés**:
   - partner `paid` → Impact `approved`
   - partner `refunded` → Impact `declined`

**Reconcile státuszok**
- `matched`: minden ok
- `mismatch`: eltérő összeg/státusz
- `missing`: partner riportban van, nálunk nincs
- `extra`: nálunk van, partnernél nincs
- `disputed`: manuális review szükséges

---

## Dispute flow
1. `reconcile_status=disputed`
2. `impact_audit_log` bejegyzés
3. Partner értesítés (email/webhook)
4. Döntés: `approved|declined` → ledger update

---

## Output / riport
- CSV export: `partner_code, event_id, status, reconcile_status, amount_gross`
- Dashboard widgetek: mismatch count, pending days, dispute count

---

## Javasolt job szerkezet
- `impactshop_partner_reconcile_daily` (cron, 24h)
- `impactshop_partner_reconcile_weekly` (cron, 7d)
- Manuális futtatás: `/impact/v1/admin/partner/reconcile`
