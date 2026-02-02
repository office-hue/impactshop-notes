# Monitoring + KPI dashboard

## Cél
Átlátható metrikák a partner integráció egészségéről és üzleti hatásáról.

---

## Core KPI-k
- **Approved ratio** = approved / total
- **Pending átlagos idő** (órában)
- **Dispute arány** = disputed / total
- **Mismatch arány** = mismatch / total
- **Webhook success rate**

---

## KPI definíciók
- Approved ratio: $\frac{approved}{approved + pending + declined}$
- Pending idő: átlag `now - created_at` pending státuszban
- Dispute arány: $\frac{disputed}{total}$

---

## Dashboard blokkok
1. **Tranzakció státusz megoszlás** (pie)
2. **Pending idő trend** (line)
3. **Dispute trend** (line)
4. **Webhook success/fail** (bar)
5. **Top NGO / Top partner** (table)

---

## Alap adatforrások
- `wp_impact_partner_tx`
- `impact_audit_log`
- Ledger summary

---

## Riasztási küszöbök (javaslat)
- Approved ratio < 70% (24h)
- Pending avg > 3 nap
- Dispute rate > 5%
- Webhook fail rate > 2% (1h)

---

## Export / audit
- Napi KPI snapshot CSV
- Heti summary riport
