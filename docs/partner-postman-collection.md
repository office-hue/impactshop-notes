# Postman collection – staging smoke

## Fájl
- `docs/partner-postman-collection.json`

## Cél
Gyors staging smoke teszt a partner webhookhoz (valid HMAC, idempotency, invalid signature).

## Használat
1. Importáld a collectiont Postmanbe
2. Állítsd be a változókat:
   - `base_url`
   - `partner_api_key`
   - `signature` (HMAC)
   - `idempotency_key`
3. Futtasd sorban a 3 requestet

## Fixture payloadok
- Minta JSON-ok: `fixtures/partner/*.json`
- Másold be a `raw` body mezőbe a Postmanben, vagy töltsd be fájlból.

## HMAC helper (gyors generálás)

### Node.js példa
```bash
node -e "const crypto=require('crypto');const secret='test_hmac';const payload=JSON.stringify({partner_code:'partner_demo',event_id:'order_1001',event_type:'purchase',pseudo_id:'test_pseudo_01',amount_gross:19990,currency:'HUF',ngo_code:'bator-tabor',timestamp:'2026-01-23T10:30:00Z',payment_status:'paid'});const sig=crypto.createHmac('sha256',secret).update(payload).digest('hex');console.log(sig);"
```

### PHP példa
```bash
php -r '$secret="test_hmac";$payload=json_encode(["partner_code"=>"partner_demo","event_id"=>"order_1001","event_type"=>"purchase","pseudo_id"=>"test_pseudo_01","amount_gross"=>19990,"currency"=>"HUF","ngo_code"=>"bator-tabor","timestamp"=>"2026-01-23T10:30:00Z","payment_status"=>"paid"]);echo hash_hmac("sha256",$payload,$secret),"\n";'
```
