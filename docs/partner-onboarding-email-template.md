# Partner onboarding email – template

## Tárgy
Sharity / Impact Shop – partner integráció indulás

## Szöveg
Szia {{partner_name}},

Köszi, hogy csatlakoztok a Sharity / Impact Shop partner programhoz. Az induláshoz küldjük a legfontosabb lépéseket és anyagokat:

1) Staging teszt
- Endpoint: https://staging.app.sharity.hu/wp-json/impact/v1/partner/transaction
- Kulcsok: {{test_api_key}} / {{test_hmac_secret}}
- Postman: `docs/partner-postman-collection.json`

2) Validáció és HMAC
- `Authorization: Bearer <partner_api_key>`
- `X-Impact-Signature: sha256=<hex>`
- Idempotency-Key kötelező

3) Dokumentációk
- Onboarding: `docs/partner-onboarding-checklist.md`
- API: `docs/partner-api-openapi.yaml`
- Runbook: `docs/partner-staging-runbook.md`

Ha megvagytok a staging tesztekkel, jelezzetek és küldjük a production kulcsokat.

Üdv,
{{sender_name}}
Sharity / Impact Shop
