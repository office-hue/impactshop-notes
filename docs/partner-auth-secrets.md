# Auth & secrets kezelési terv

## Cél
Biztosítani a partner webhookok hitelesítését, a kulcsok rotálhatóságát, valamint a staging/prod elkülönítését.

---

## Auth modell (egyszerű, modern)
- **API kulcs**: `Authorization: Bearer <partner_api_key>`
- **HMAC aláírás**: `X-Impact-Signature: sha256=<hex>`
- **Idempotency**: `Idempotency-Key` header

**Opcionális kulcs‑ID**:
- `X-Impact-Key-Id: pk_live_123` – rotáció és párhuzamos kulcsok támogatására

---

## Secrets tárolás
### Javaslat
- **Production**: `IMPACT_PARTNER_SECRETS` env / secret store
- **Staging**: külön env kulcskészlet

### Minimál formátum (env JSON)
```json
{
  "partner_abc": {
    "key_id": "pk_live_001",
    "api_key": "live_xxx",
    "hmac_secret": "live_hmac_xxx",
    "valid_from": "2026-01-01",
    "valid_to": "2026-04-01"
  }
}
```

---

## Kulcs rotáció
1. **Új kulcs előkészítés** (staging → prod)
2. **Dual‑key ablak** (régi + új elfogadása, pl. 14 nap)
3. **Key‑id kötelező** új kulcsnál
4. **Régi kulcs lejáratás** → `valid_to` + audit log

---

## Környezet szétválasztás
- **Staging**: `pk_test_*`, `test_*` prefix
- **Prod**: `pk_live_*`, `live_*` prefix
- Kereszthívás tiltás: staging kulcs prodon **invalid**

---

## Audit és naplózás
- `impact_audit_log`: `action=partner_auth`, `status=ok|fail`, `key_id`, `partner_code`
- Hibás aláírás esetén `401` + audit bejegyzés

---

## Vészforgatókönyv
- Kulcs kompromittálódás: `status=disabled`, webhook leállítás, új kulcs
- Visszaélések jelzése: dispute workflow aktiválás
