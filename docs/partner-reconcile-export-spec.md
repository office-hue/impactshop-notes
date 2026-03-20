# Reconcile export – CSV schema + példa

## CSV schema
```
partner_code,event_id,status,reconcile_status,amount_gross,currency,ledger_id,created_at
```

## Példa
```csv
partner_demo,order_1001,approved,matched,19990,HUF,882001,2026-01-23T10:31:00Z
partner_demo,order_1002,pending,missing,19990,HUF,,2026-01-23T10:35:00Z
partner_demo,order_1003,declined,disputed,19990,HUF,882009,2026-01-23T10:40:00Z
```

## Megjegyzés
- `ledger_id` üres lehet (pending/void esetén)
- `created_at` ISO8601
