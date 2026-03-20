# Partner onboarding checklist (non‑affiliate)

## 1) Szerződés + adatkezelés
- [ ] Partner elfogadta a webhook SLA-t (`docs/partner-webhook-sla.md`)
- [ ] Adatkezelési / GDPR tájékoztató egyeztetve
- [ ] Kedvezmény szabályok rögzítve (max %, min kosár, cap)

## 2) Technikai kulcsok
- [ ] `partner_code` létrehozva
- [ ] `partner_api_key` kiadva
- [ ] `partner_hmac_secret` kiadva
- [ ] Whitelistelt IP (ha van)

## 3) Webhook beállítás
- [ ] Endpoint: `POST /impact/v1/partner/transaction`
- [ ] HMAC aláírás működik
- [ ] Idempotency-Key küldése működik
- [ ] Time skew < 5 perc

## 4) Kedvezmény kalkuláció
- [ ] `POST /impact/v1/partner/discount/quote` elérhető
- [ ] Szintek helyesen jelennek meg (Legend→Basic)
- [ ] Max kedvezmény helyes

## 5) Dispute flow
- [ ] `POST /impact/v1/partner/dispute` működik
- [ ] Audit log események keletkeznek

## 6) Pilot teszt
- [ ] 1 teszt tranzakció `accepted`
- [ ] 1 duplikált tranzakció `duplicate`
- [ ] 1 refund → `declined`/`void`

## 7) Dashboard hozzáférés
- [ ] Partner logins elkészítve
- [ ] Exportok működnek (CSV/JSON)
- [ ] KPI-k megjelennek

## 8) Élesítés
- [ ] SLA elfogadva
- [ ] Monitoring beállítva
- [ ] On-call kontakt rögzítve
