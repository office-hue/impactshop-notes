# Partner release checklist (go‑live)

## 1) Staging zárás
- [ ] QA futtatva: `node tools/partner-qa.cjs`
- [ ] Postman smoke OK
- [ ] Runner smoke OK

## 2) Kulcsok és auth
- [ ] Prod kulcsok kiadva
- [ ] Staging kulcsok letiltva prodon
- [ ] HMAC aláírás valid

## 3) Konfiguráció
- [ ] Partner config (max discount, cap, min cart) rögzítve
- [ ] Webhook URL + event types valid

## 4) Monitoring
- [ ] KPI dashboard elérhető
- [ ] Alert küszöbök beállítva

## 5) Kommunikáció
- [ ] Go‑live visszaigazolás partnernek
- [ ] SLA elfogadva
