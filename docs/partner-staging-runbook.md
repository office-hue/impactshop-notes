# Partner staging runbook (rövid)

## Cél
Gyors staging ellenőrzés a partner integrációhoz.

## Lépések
1. **QA check**
   ```bash
   node tools/partner-qa.cjs
   ```
2. **Postman smoke**
   - Import: `docs/partner-postman-collection.json`
   - HMAC secret beállítás + fixture body betöltés
3. **Runner (automata)**
   ```bash
   BASE_URL="https://staging.app.sharity.hu" \
   PARTNER_API_KEY="test_xxx" \
   HMAC_SECRET="test_hmac" \
   node tools/partner-test-runner.cjs
   ```

## Ellenőrzés
- Audit log bejegyzések
- `accepted/duplicate/rejected` státuszok

## Staging vs prod
- Stagingen **csak** `test_*` kulcsok és sandbox adatok használhatók.
- Prod kulcs/endpoint stagingen **tiltott**.
