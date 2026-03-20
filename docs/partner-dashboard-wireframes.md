# Partner + vásárló dashboard – UI drótváz

## Cél
Áttekinthető UI a partner és a vásárló számára: státusz, kedvezmény, egyeztetés, viták.

---

## Partner dashboard (admin)

```
┌───────────────────────────────────────────────┐
│ Partner Dashboard                             │
├───────────────────────────────────────────────┤
│ KPIs:                                         │
│  Approved ratio  | Pending avg (days)         │
│  Dispute rate    | Total volume (HUF)         │
├───────────────────────────────────────────────┤
│ Recent Transactions (table)                   │
│  event_id | amount | status | discount | ngo  │
│  ...                                          │
├───────────────────────────────────────────────┤
│ Reconciliation                                │
│  mismatches | missing | extra | disputed      │
│  [Export CSV] [Open disputes]                 │
├───────────────────────────────────────────────┤
│ Webhook Health                                │
│  last 24h | success rate | last error         │
└───────────────────────────────────────────────┘
```

### Partner dashboard fő elemek
- KPI kártyák (approved ratio, pending time, dispute rate)
- Tranzakció lista + szűrők (status, date, ngo)
- Egyeztetés szekció (mismatch, dispute)
- Webhook health panel

---

## Vásárlói dashboard (user)

```
┌───────────────────────────────────────────────┐
│ Vásárlásaim / Kedvezményeim                   │
├───────────────────────────────────────────────┤
│ Szint: Gold  | Kedvezmény: 16% (max 20%)      │
├───────────────────────────────────────────────┤
│ Tranzakciók                                   │
│  dátum | partner | összeg | status | kedv.    │
│  ...                                          │
├───────────────────────────────────────────────┤
│ Dispute státuszok (ha van)                    │
│  event_id | status | decision                 │
└───────────────────────────────────────────────┘
```

### Vásárlói dashboard fő elemek
- Szint + kedvezmény magyarázat
- Tranzakció lista (pending/approved/declined)
- Dispute státusz blokk

---

## UI note
- Minimalista, auditálhatóság fókusz
- Minden státusz mellett rövid magyarázat
