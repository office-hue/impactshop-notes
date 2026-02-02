# Partner test runner (CLI)

## Cél
A `fixtures/partner/*.json` payloadokat sorban elküldi a staging webhook endpointnak.

## Használat
```bash
BASE_URL="https://staging.app.sharity.hu" \
PARTNER_API_KEY="test_xxx" \
HMAC_SECRET="test_hmac" \
node tools/partner-test-runner.cjs
```

## Dry run
```bash
node tools/partner-test-runner.cjs --dry-run
```

## Megjegyzés
- `tools/partner-test-runner.js` egy ESM shim, ami a `.cjs` runnerre továbbít.

## Megjegyzés
- Idempotency‑Key alapból a fájlnév (pl. `transaction-valid`).
- A HMAC a nyers JSON body alapján készül.
