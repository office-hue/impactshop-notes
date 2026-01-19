# PIN SMS/QR runbook (staging)

## Cél
- PIN kiadás és SMS/QR kézbesítés gyors ellenőrzése stagingen.

## Gyors parancsok
Minta változók:
```bash
# Teszt pseudo-ID és telefonszám (staginghez)
PSEUDO_ID="ab12cd34ef56"
PHONE="+3611111111"
```

Staging smoke + log tail:
```bash
PSEUDO_ID="ab12cd34ef56"
PHONE="+3611111111"
curl -sS -X POST "https://app.sharity.hu/impactshop-staging/wp-json/impact/v1/identity/pin/issue" \
  -H "Content-Type: application/json" \
  -d "{\"pseudo_id\":\"${PSEUDO_ID}\",\"context\":\"impactshop\",\"delivery\":{\"channel\":\"sms\",\"target\":\"${PHONE}\"}}" && \
ssh -o IdentitiesOnly=yes sharityh@s59.tarhely.com \
  "tail -n 50 /home/sharityh/app-staging/wp-content/debug.log; \
   tail -n 50 /home/sharityh/app-staging/wp-content/uploads/impactshop-pin-delivery.log"
```

Prod smoke + log tail:
```bash
PSEUDO_ID="ab12cd34ef56"
PHONE="+3611111111"
curl -sS -X POST "https://app.sharity.hu/wp-json/impact/v1/identity/pin/issue" \
  -H "Content-Type: application/json" \
  -d "{\"pseudo_id\":\"${PSEUDO_ID}\",\"context\":\"impactshop\",\"delivery\":{\"channel\":\"sms\",\"target\":\"${PHONE}\"}}" && \
ssh -o IdentitiesOnly=yes sharityh@s59.tarhely.com \
  "tail -n 50 /home/sharityh/app/wp-content/debug.log; \
   tail -n 50 /home/sharityh/app/wp-content/uploads/impactshop-pin-delivery.log"
```

## Előfeltételek
- MU pluginok a stagingen:
  - `impactshop-identity-pin.php`
  - `impactshop-identity-pin-migration.php`
  - `impactshop-identity-pin-cron.php`
  - `impactshop-identity-pin-sms-vonage.php`
  - `impactshop-identity-pin-qr-quickchart.php`
- Vonage kulcsok a staging szerveren:
  - `/home/sharityh/.impact-secrets/env.d/sms.env`
  - `VONAGE_API_KEY`, `VONAGE_API_SECRET`, `VONAGE_FROM`

## Pre-smoke mini-checklist (staging)
- [ ] MU pluginek fent vannak a stagingen
- [ ] `sms.env` kulcsok beállítva
- [ ] Staging REST elérhető (`/wp-json/impact/v1/health`)

## Smoke teszt (SMS)
```bash
curl -sS -X POST "https://app.sharity.hu/impactshop-staging/wp-json/impact/v1/identity/pin/issue" \
  -H "Content-Type: application/json" \
  -d '{"pseudo_id":"ab12cd34ef56","context":"impactshop","delivery":{"channel":"sms","target":"+3611111111"}}'
```
Várt válasz: `delivery.status=sent`.

## Post-smoke ellenőrzés
- Debug log: `/home/sharityh/app-staging/wp-content/debug.log`
- Delivery log: `/home/sharityh/app-staging/wp-content/uploads/impactshop-pin-delivery.log`

Gyors tail:
```bash
ssh -o IdentitiesOnly=yes sharityh@s59.tarhely.com \
  "tail -n 50 /home/sharityh/app-staging/wp-content/debug.log; \
   tail -n 50 /home/sharityh/app-staging/wp-content/uploads/impactshop-pin-delivery.log"
```

## Egyparancsos staging smoke + log tail
```bash
curl -sS -X POST "https://app.sharity.hu/impactshop-staging/wp-json/impact/v1/identity/pin/issue" \
  -H "Content-Type: application/json" \
  -d '{"pseudo_id":"ab12cd34ef56","context":"impactshop","delivery":{"channel":"sms","target":"+3611111111"}}' && \
ssh -o IdentitiesOnly=yes sharityh@s59.tarhely.com \
  "tail -n 50 /home/sharityh/app-staging/wp-content/debug.log; \
   tail -n 50 /home/sharityh/app-staging/wp-content/uploads/impactshop-pin-delivery.log"
```

## Smoke teszt (QR)
```bash
curl -sS -X POST "https://app.sharity.hu/impactshop-staging/wp-json/impact/v1/identity/pin/issue" \
  -H "Content-Type: application/json" \
  -d '{"pseudo_id":"ab12cd34ef56","context":"impactshop","delivery":{"channel":"qr","target":"n/a"}}'
```
Várt válasz: `delivery.status=ready` + `qr_payload` URL.

## Rate limit reset (ha 429)
```bash
ssh -o IdentitiesOnly=yes sharityh@s59.tarhely.com \
  "/usr/local/bin/wp --path=/home/sharityh/app-staging transient delete --all"
```

## Debug tippek
- Debug log: `/home/sharityh/app-staging/wp-content/debug.log`
- Delivery log: `/home/sharityh/app-staging/wp-content/uploads/impactshop-pin-delivery.log`
- SMS hiba esetén ellenőrizd a Vonage kulcsokat és a sender ID-t.

## Production runbook (óvatosan)
- Előfeltétel: staging smoke `delivery.status=sent` sikeres.
- Guard: friss `impactall` futás + naplózás.
- Secrets: `/home/sharityh/.impact-secrets/env.d/sms.env` legyen beállítva.

### Top-level checklist kivonat
- [ ] `impactall` zöld
- [ ] Staging PIN smoke `delivery.status=sent`
- [ ] Prod PIN smoke `delivery.status=sent`

### Go/No-go checklist (prod)
- ✅ Go: staging smoke `delivery.status=sent`, Vonage kulcsok élesítve, `impactall` zöld.
- ✅ Go: prod smoke `delivery.status=sent`, debug log tiszta, nincs 5xx spike.
- ❌ No-go: Vonage auth hiba, rate limit hiba, vagy PIN endpoint 404/500.
- ❌ No-go: `impactshop-pin-delivery.log` üres vagy ismételt `error` státuszok.

### Deploy (prod)
```bash
rsync -a --checksum -e "ssh -o IdentitiesOnly=yes" \
  wp-content/mu-plugins/impactshop-identity-pin*.php \
  sharityh@s59.tarhely.com:/home/sharityh/app/wp-content/mu-plugins/

rsync -a --checksum -e "ssh -o IdentitiesOnly=yes" \
  wp-content/mu-plugins/impactshop-identity-pin-sms-vonage.php \
  sharityh@s59.tarhely.com:/home/sharityh/app/wp-content/mu-plugins/

rsync -a --checksum -e "ssh -o IdentitiesOnly=yes" \
  wp-content/mu-plugins/impactshop-identity-pin-qr-quickchart.php \
  sharityh@s59.tarhely.com:/home/sharityh/app/wp-content/mu-plugins/
```

### Rewrite flush (prod)
```bash
ssh -o IdentitiesOnly=yes sharityh@s59.tarhely.com \
  "/usr/local/bin/wp --path=/home/sharityh/app rewrite flush --hard"
```

### Smoke teszt (prod)
```bash
curl -sS -X POST "https://app.sharity.hu/wp-json/impact/v1/identity/pin/issue" \
  -H "Content-Type: application/json" \
  -d '{"pseudo_id":"ab12cd34ef56","context":"impactshop","delivery":{"channel":"sms","target":"+3611111111"}}'
```
Várt válasz: `delivery.status=sent`.

### Post-smoke ellenőrzés (prod)
- Debug log: `/home/sharityh/app/wp-content/debug.log`
- Delivery log: `/home/sharityh/app/wp-content/uploads/impactshop-pin-delivery.log`

Gyors tail:
```bash
ssh -o IdentitiesOnly=yes sharityh@s59.tarhely.com \
  "tail -n 50 /home/sharityh/app/wp-content/debug.log; \
   tail -n 50 /home/sharityh/app/wp-content/uploads/impactshop-pin-delivery.log"
```

### Egyparancsos prod smoke + log tail
```bash
curl -sS -X POST "https://app.sharity.hu/wp-json/impact/v1/identity/pin/issue" \
  -H "Content-Type: application/json" \
  -d '{"pseudo_id":"ab12cd34ef56","context":"impactshop","delivery":{"channel":"sms","target":"+3611111111"}}' && \
ssh -o IdentitiesOnly=yes sharityh@s59.tarhely.com \
  "tail -n 50 /home/sharityh/app/wp-content/debug.log; \
   tail -n 50 /home/sharityh/app/wp-content/uploads/impactshop-pin-delivery.log"
```

### Rollback
- Töröld a PIN MU pluginokat vagy nevezd `.off`-ra, majd `wp cache flush`.
