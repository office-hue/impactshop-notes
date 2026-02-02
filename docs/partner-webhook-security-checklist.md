# Webhook security checklist (partner)

## Cél
Gyors ellenőrzőlista a partner oldali webhook biztonságos beállításához.

---

## Kötelező
- [ ] HMAC SHA256 aláírás a **raw JSON body** alapján
- [ ] `X-Impact-Signature: sha256=<hex>` header küldése
- [ ] `Authorization: Bearer <partner_api_key>` header küldése
- [ ] `Idempotency-Key` header beállítása minden requesthez
- [ ] TLS/HTTPS endpoint használata

---

## Ajánlott
- [ ] Staging kulcsok elkülönítése (`test_*`)
- [ ] Retry backoff alkalmazása 5xx/timeout esetén
- [ ] Logolás request_id + event_id szinten
- [ ] Payload validáció a küldés előtt

---

## Tiltott
- [ ] Kulcsok mentése plaintext logba
- [ ] Prod kulcs használata stagingen
