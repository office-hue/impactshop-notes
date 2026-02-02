# Partner API – sample responses

## Transaction webhook (success)
```json
{
  "status": "accepted",
  "ledger_id": "882001",
  "event_id": "order_1001",
  "partner_code": "partner_demo",
  "message": "Accepted"
}
```

## Transaction webhook (duplicate)
```json
{
  "status": "duplicate",
  "ledger_id": "882001",
  "event_id": "order_1001",
  "partner_code": "partner_demo",
  "message": "Duplicate event"
}
```

## Transaction webhook (error)
```json
{
  "code": "partner_payload_invalid",
  "message": "Invalid payload",
  "details": {
    "field": "pseudo_id"
  },
  "request_id": "req_01HZX8PDN1"
}
```

## Discount quote (success)
```json
{
  "tier": "gold",
  "partner_max_discount": 0.2,
  "discount_rate": 0.16,
  "discount_amount": 3198,
  "amount_net": 16792,
  "explain": "Gold szint → 80% a max kedvezményből"
}
```

## Dispute open (success)
```json
{
  "status": "opened",
  "dispute_id": "disp_10001",
  "ledger_id": "882001"
}
```
